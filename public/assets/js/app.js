/*
 * Page behaviours. The Content-Security-Policy allows no inline scripts
 * (except the tiny theme script in <head>), so everything is wired up here
 * through data attributes. Every feature also works, in a simpler way,
 * without JavaScript.
 */
(function () {
  'use strict';

  /* ---------- Small helpers ---------- */
  function meta(name) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : '';
  }
  function store(key, value) {
    try {
      if (value === null) { localStorage.removeItem(key); } else { localStorage.setItem(key, value); }
    } catch (e) { /* private mode: the choice lasts for this page only */ }
  }
  function read(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  /** CSRF token from the meta tag, for fetch() POST requests. */
  window.csrfToken = function () { return meta('csrf-token'); };

  /** POST form data with the CSRF header attached. */
  window.postWithCsrf = function (url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': window.csrfToken() },
      body: body
    });
  };

  function postItem(url, name, value) {
    if (!url) { return Promise.resolve(); }
    var body = new FormData();
    body.append(name, value);
    return window.postWithCsrf(url, body).catch(function () {});
  }

  /* ---------- Theme: System, Light, Dark (BUILD_SPEC 10.5) ---------- */
  var THEMES = ['system', 'light', 'dark'];
  var THEME_NAMES = { system: 'System', light: 'Light', dark: 'Dark' };

  function currentChoice() {
    return read('tt-theme') || document.documentElement.getAttribute('data-theme-pref') || 'system';
  }
  function resolve(choice) {
    if (choice === 'light' || choice === 'dark') { return choice; }
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function applyTheme(choice) {
    document.documentElement.setAttribute('data-theme', resolve(choice));
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
      button.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
        icon.hidden = icon.getAttribute('data-theme-icon') !== choice;
      });
      var label = button.querySelector('[data-theme-label]');
      if (label) { label.textContent = THEME_NAMES[choice]; }
      button.setAttribute('aria-label', 'Theme: ' + THEME_NAMES[choice] + '. Change theme');
    });
    document.dispatchEvent(new CustomEvent('tt-themechange'));
  }
  function setUpTheme() {
    applyTheme(currentChoice());
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
      button.addEventListener('click', function () {
        var next = THEMES[(THEMES.indexOf(currentChoice()) + 1) % THEMES.length];
        store('tt-theme', next);
        document.documentElement.setAttribute('data-theme-pref', next);
        applyTheme(next);
        // Signed-in users: also save on the account (public visitors: this browser only).
        postItem(meta('theme-save-url'), 'theme', next);
      });
    });
    if (window.matchMedia) {
      var media = window.matchMedia('(prefers-color-scheme: dark)');
      var onChange = function () { if (currentChoice() === 'system') { applyTheme('system'); } };
      if (media.addEventListener) { media.addEventListener('change', onChange); } else if (media.addListener) { media.addListener(onChange); }
    }
  }

  /* ---------- Sidebar: groups, rail, mobile drawer ---------- */
  function setUpSidebar() {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) { return; }
    var opened = [];
    try { opened = JSON.parse(read('tt-nav-open') || '[]'); } catch (e) { opened = []; }
    sidebar.querySelectorAll('details.nav-group').forEach(function (group) {
      var id = group.getAttribute('data-group');
      if (opened.indexOf(id) !== -1) { group.open = true; }
      group.addEventListener('toggle', function () {
        if (group.hasAttribute('data-current')) { return; }   // the current page's group always opens
        var list = opened.filter(function (x) { return x !== id; });
        if (group.open) { list.push(id); }
        opened = list;
        store('tt-nav-open', JSON.stringify(list));
      });
    });

    var rail = sidebar.querySelector('[data-rail-toggle]');
    if (rail) {
      var sync = function () {
        var on = document.documentElement.classList.contains('rail');
        rail.setAttribute('aria-pressed', on ? 'true' : 'false');
        var label = rail.querySelector('.rail-toggle-label');
        if (label) { label.textContent = on ? 'Expand menu' : 'Collapse menu'; }
        rail.setAttribute('title', on ? 'Expand menu' : 'Collapse menu');
      };
      sync();
      rail.addEventListener('click', function () {
        var on = document.documentElement.classList.toggle('rail');
        store('tt-rail', on ? '1' : null);
        sync();
      });
    }

    var toggle = document.querySelector('.menu-toggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var open = sidebar.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (toggle) { toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
      }
    });
  }

  /* Close <details> dropdowns when clicking elsewhere or pressing Escape */
  function setUpDropdowns() {
    document.addEventListener('click', function (event) {
      document.querySelectorAll('details.dropdown[open]').forEach(function (d) {
        if (!d.contains(event.target)) { d.open = false; }
      });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') { return; }
      document.querySelectorAll('details.dropdown[open]').forEach(function (d) {
        d.open = false;
        var summary = d.querySelector('summary');
        if (summary) { summary.focus(); }
      });
    });
  }

  /* ---------- Help panel ---------- */
  function setUpHelpPanel() {
    var panel = document.getElementById('help-panel');
    var buttons = document.querySelectorAll('[data-help-toggle]');
    if (!panel) { return; }
    function show(open) {
      panel.hidden = !open;
      buttons.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
      if (open) { panel.focus(); } else if (buttons[0]) { buttons[0].focus(); }
    }
    buttons.forEach(function (b) { b.addEventListener('click', function () { show(panel.hidden); }); });
    panel.querySelectorAll('[data-help-close]').forEach(function (b) { b.addEventListener('click', function () { show(false); }); });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) { show(false); }
    });
  }

  /* ---------- First-visit tips ---------- */
  function setUpTips() {
    document.querySelectorAll('[data-tip]').forEach(function (card) {
      var button = card.querySelector('[data-tip-dismiss]');
      if (!button) { return; }
      button.addEventListener('click', function () {
        postItem(meta('help-save-url'), 'item', 'tip_' + card.getAttribute('data-tip'));
        card.remove();
      });
    });
  }

  /* ---------- Product tour: 5 stops after sign-up (BUILD_SPEC 10.3 item 5) ---------- */
  var TOUR = [
    { target: 'sidebar', title: 'The menu', text: 'Everything is in four groups: Get started, School data, Timetables and Manage. Click a group name to open or close it.' },
    { target: 'guide', title: 'The Setup Guide', text: 'Follow these 9 steps from an empty school to a published timetable. Each step turns green by itself when it is done.' },
    { target: 'help', title: 'Help on every page', text: 'Press Help at any time for what the page is for, how to use it and what each field means.' },
    { target: 'theme', title: 'Light or dark', text: 'Switch between your device setting, light and dark. Your choice is saved on your account.' },
    { target: 'switcher', title: 'Your schools', text: 'Switch between schools you belong to, or create another school, from here.' }
  ];

  function startTour() {
    var steps = TOUR.filter(function (s) { return document.querySelector('[data-tour="' + s.target + '"]'); });
    if (!steps.length) { return; }
    var index = 0;
    var backdrop = document.createElement('div');
    backdrop.className = 'tour-backdrop';
    var pop = document.createElement('div');
    pop.className = 'tour-pop';
    pop.setAttribute('role', 'dialog');
    pop.setAttribute('aria-modal', 'true');
    pop.setAttribute('aria-labelledby', 'tour-title');
    document.body.appendChild(backdrop);
    document.body.appendChild(pop);
    var sidebar = document.getElementById('sidebar');
    var current = null;

    function finish() {
      if (current) { current.classList.remove('tour-highlight'); }
      if (sidebar) { sidebar.classList.remove('open'); }
      backdrop.remove();
      pop.remove();
      document.removeEventListener('keydown', onKey);
      postItem(meta('help-save-url'), 'item', 'tour_done');
    }
    function onKey(event) {
      if (event.key === 'Escape') { finish(); }
    }
    function place(target) {
      var r = target.getBoundingClientRect();
      var width = pop.offsetWidth;
      var height = pop.offsetHeight;
      var top = r.bottom + 10;
      if (top + height > window.innerHeight - 8) { top = Math.max(8, r.top - height - 10); }
      if (r.right + width + 16 < window.innerWidth && r.height > window.innerHeight / 2) {
        top = Math.min(Math.max(8, r.top + 40), window.innerHeight - height - 8);
        pop.style.left = (r.right + 12) + 'px';
      } else {
        pop.style.left = Math.min(Math.max(8, r.left), window.innerWidth - width - 8) + 'px';
      }
      pop.style.top = top + 'px';
    }
    function show() {
      var step = steps[index];
      var target = document.querySelector('[data-tour="' + step.target + '"]');
      if (current) { current.classList.remove('tour-highlight'); }
      var inSidebar = sidebar && sidebar.contains(target);
      if (sidebar) { sidebar.classList.toggle('open', inSidebar && window.innerWidth <= 860); }
      current = target;
      target.classList.add('tour-highlight');
      pop.innerHTML = '';
      var counter = document.createElement('p');
      counter.className = 'tour-step';
      counter.textContent = 'Step ' + (index + 1) + ' of ' + steps.length;
      var h = document.createElement('h2');
      h.id = 'tour-title';
      h.textContent = step.title;
      var p = document.createElement('p');
      p.textContent = step.text;
      var actions = document.createElement('div');
      actions.className = 'tour-actions';
      var skip = document.createElement('button');
      skip.type = 'button';
      skip.className = 'btn-link';
      skip.textContent = 'Skip the tour';
      skip.addEventListener('click', finish);
      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'btn btn-primary btn-small';
      next.textContent = index === steps.length - 1 ? 'Finish' : 'Next';
      next.addEventListener('click', function () {
        if (index === steps.length - 1) { finish(); } else { index++; show(); }
      });
      if (index > 0) {
        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'btn btn-small';
        back.textContent = 'Back';
        back.addEventListener('click', function () { index--; show(); });
        actions.appendChild(back);
      }
      actions.insertBefore(skip, actions.firstChild);
      actions.appendChild(next);
      pop.appendChild(counter);
      pop.appendChild(h);
      pop.appendChild(p);
      pop.appendChild(actions);
      window.requestAnimationFrame(function () { place(target); next.focus(); });
    }
    document.addEventListener('keydown', onKey);
    backdrop.addEventListener('click', finish);
    window.addEventListener('resize', function () { if (current && document.body.contains(pop)) { place(current); } });
    show();
  }

  /* ---------- Small behaviours ---------- */
  /** Forms or buttons with data-confirm="Message" ask before submitting. */
  function setUpConfirmations() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      var submitter = event.submitter;
      var message = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  }

  /** Selects with data-autosubmit submit their form when changed. */
  function setUpAutoSubmit() {
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
      select.addEventListener('change', function () {
        if (select.form.requestSubmit) { select.form.requestSubmit(); } else { select.form.submit(); }
      });
    });
  }

  /** Buttons with data-print open the browser's print dialog. */
  function setUpPrintButtons() {
    document.querySelectorAll('[data-print]').forEach(function (button) {
      button.addEventListener('click', function () { window.print(); });
    });
  }

  /** Buttons with data-copy="input-id" copy that field (the share link). */
  function setUpCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        var field = document.getElementById(button.getAttribute('data-copy'));
        if (!field) { return; }
        var done = function () {
          var text = button.textContent;
          button.textContent = 'Copied';
          window.setTimeout(function () { button.textContent = text; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(field.value).then(done, function () { field.select(); });
        } else {
          field.select();
          try { document.execCommand('copy'); done(); } catch (e) { /* the text stays selected for Ctrl+C */ }
        }
      });
    });
  }

  /** Help Centre: filter sections and items as the user types. */
  function setUpHelpSearch() {
    var input = document.querySelector('[data-help-search]');
    if (!input) { return; }
    var sections = document.querySelectorAll('.help-section');
    var none = document.querySelector('[data-help-none]');
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var shown = 0;
      sections.forEach(function (section) {
        var items = section.querySelectorAll('[data-search-item]');
        var sectionHit = section.textContent.toLowerCase().indexOf(q) !== -1;
        var itemHits = 0;
        items.forEach(function (item) {
          var hit = q === '' || item.textContent.toLowerCase().indexOf(q) !== -1;
          item.hidden = !hit;
          itemHits += hit ? 1 : 0;
        });
        var visible = q === '' || (items.length ? itemHits > 0 : sectionHit);
        section.hidden = !visible;
        shown += visible ? 1 : 0;
      });
      if (none) { none.hidden = shown > 0; }
    });
  }

  /* Semester dates: show the "last day" or the "number of weeks" field to match the choice. */
  function setUpLengthChoice() {
    document.querySelectorAll('[data-length-choice]').forEach(function (choice) {
      var form = choice.closest('form');
      var sync = function () {
        var picked = choice.querySelector('input:checked');
        form.querySelectorAll('[data-length-for]').forEach(function (box) {
          box.hidden = !picked || box.getAttribute('data-length-for') !== picked.value;
        });
      };
      choice.addEventListener('change', sync);
      sync();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    setUpTheme();
    setUpSidebar();
    setUpDropdowns();
    setUpHelpPanel();
    setUpTips();
    setUpConfirmations();
    setUpAutoSubmit();
    setUpPrintButtons();
    setUpCopyButtons();
    setUpHelpSearch();
    setUpLengthChoice();
    if (document.querySelector('[data-tour-autostart]')) {
      window.setTimeout(startTour, 300);
    }
  });
})();

/*
 * Availability grid: save a click without reloading the page.
 * Falls back to a normal form post if anything goes wrong.
 */
(function () {
  'use strict';
  var LABELS = { available: 'Available', preferred: 'Preferred', unavailable: 'Unavailable' };

  document.addEventListener('DOMContentLoaded', function () {
    var grid = document.getElementById('availability-grid');
    if (!grid) {
      return;
    }
    var api = grid.getAttribute('data-api');
    grid.addEventListener('submit', function (event) {
      var form = event.target.closest('form.availability-cell');
      if (!form) {
        return;
      }
      event.preventDefault();
      var button = form.querySelector('button');
      button.disabled = true;
      window.postWithCsrf(api, new FormData(form))
        .then(function (response) {
          return response.json().then(function (body) {
            if (!response.ok) {
              throw new Error(body.error || 'Save failed');
            }
            return body;
          });
        })
        .then(function (body) {
          var cell = form.parentElement;
          cell.className = 'status-' + body.status;
          var label = button.getAttribute('aria-label').replace(/: \w+\. Click to change\.$/, '');
          button.textContent = LABELS[body.status];
          button.setAttribute('aria-label', label + ': ' + LABELS[body.status] + '. Click to change.');
        })
        .catch(function (error) {
          window.alert(error.message + '. The page will reload.');
          form.submit();
        })
        .finally(function () {
          button.disabled = false;
          button.focus();
        });
    });
  });
})();
