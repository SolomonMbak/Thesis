/*
 * Small page behaviours. No inline scripts are allowed by the
 * Content-Security-Policy, so everything is wired up here.
 */
(function () {
  'use strict';

  /** CSRF token from the meta tag, for fetch() POST requests. */
  window.csrfToken = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  };

  /** POST JSON or form data with the CSRF header attached. */
  window.postWithCsrf = function (url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': window.csrfToken() },
      body: body
    });
  };

  function setUpMenuToggle() {
    var button = document.querySelector('.menu-toggle');
    var sidebar = document.getElementById('sidebar');
    if (!button || !sidebar) {
      return;
    }
    button.addEventListener('click', function () {
      var open = sidebar.classList.toggle('open');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        button.setAttribute('aria-expanded', 'false');
        button.focus();
      }
    });
  }

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
        select.form.requestSubmit ? select.form.requestSubmit() : select.form.submit();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    setUpMenuToggle();
    setUpConfirmations();
    setUpAutoSubmit();
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
