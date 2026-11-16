/*
 * Live progress page: polls api/progress.php every 1.5 seconds, updates the
 * figures, the progress bar and the Chart.js chart (best penalty on a log
 * scale, hard violations on a second axis). Stops polling when the run ends.
 * Chart colours come from the theme variables and are redrawn when the
 * theme changes (BUILD_SPEC 10.5).
 */
(function () {
  'use strict';

  var POLL_MS = 1500;
  var FINAL = ['finished', 'failed', 'stopped'];

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('progress');
    if (!root) {
      return;
    }
    var api = root.getAttribute('data-api');
    var jobId = root.getAttribute('data-job');
    var maxGen = parseInt(root.getAttribute('data-max'), 10) || 1;
    var after = -1;
    var chart = null;
    var population = parseInt(root.getAttribute('data-population'), 10) || 0;
    var pending = { labels: [], penalty: [], hard: [], avg: [] };

    function field(name) {
      return root.querySelector('[data-field="' + name + '"]');
    }

    function formatElapsed(ms) {
      if (ms < 10000) {
        return (ms / 1000).toFixed(1) + 's';   // short runs: one decimal, so 0.5 s does not show as 0s
      }
      var s = Math.round(ms / 1000);
      if (s < 60) {
        return s + 's';
      }
      return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
    }

    /** Colour of a CSS variable for the current theme. */
    function themeColor(name) {
      return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function applyColors() {
      if (!chart) {
        return;
      }
      var text = themeColor('--muted');
      var grid = themeColor('--chart-grid');
      chart.data.datasets[0].borderColor = themeColor('--chart-penalty');
      chart.data.datasets[1].borderColor = themeColor('--chart-hard');
      chart.data.datasets[2].borderColor = themeColor('--muted');
      chart.options.plugins.legend.labels.color = themeColor('--text');
      ['x', 'y', 'y1'].forEach(function (axis) {
        chart.options.scales[axis].ticks.color = text;
        chart.options.scales[axis].title.color = text;
        chart.options.scales[axis].grid.color = grid;
      });
      chart.update('none');
    }

    function makeChart() {
      if (chart || typeof window.Chart === 'undefined') {
        return;
      }
      var canvas = document.getElementById('progress-chart');
      chart = new window.Chart(canvas, {
        type: 'line',
        data: {
          labels: [],
          datasets: [
            { label: 'Best penalty', data: [], yAxisID: 'y', borderColor: themeColor('--chart-penalty'), pointRadius: 0, borderWidth: 2, spanGaps: true },
            { label: 'Clashes left', data: [], yAxisID: 'y1', borderColor: themeColor('--chart-hard'), pointRadius: 0, borderWidth: 2, stepped: true },
            { label: 'Average of the population', data: [], yAxisID: 'y', borderColor: themeColor('--muted'), borderDash: [5, 4], pointRadius: 0, borderWidth: 1.5, spanGaps: true }
          ]
        },
        options: {
          animation: false,
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { labels: {} } },
          scales: {
            x: { title: { display: true, text: 'Generation' }, ticks: {}, grid: {} },
            y: { type: 'logarithmic', position: 'left', title: { display: true, text: 'Best penalty (log scale)' }, ticks: {}, grid: {} },
            y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false },
              title: { display: true, text: 'Clashes' }, ticks: { precision: 0 } }
          }
        }
      });
      applyColors();
    }

    document.addEventListener('tt-themechange', applyColors);

    function addPoints(points) {
      points.forEach(function (pt) {
        pending.labels.push(pt.g);
        pending.penalty.push(pt.p > 0 ? pt.p : null);   // a log scale cannot show 0
        pending.avg.push(pt.a > 0 ? pt.a : null);
        pending.hard.push(pt.h);
      });
      makeChart();
      if (!chart) {
        return;   // Chart.js not loaded yet (or blocked); keep the data for later
      }
      Array.prototype.push.apply(chart.data.labels, pending.labels);
      Array.prototype.push.apply(chart.data.datasets[0].data, pending.penalty);
      Array.prototype.push.apply(chart.data.datasets[1].data, pending.hard);
      Array.prototype.push.apply(chart.data.datasets[2].data, pending.avg);
      pending = { labels: [], penalty: [], hard: [], avg: [] };
      chart.update('none');
    }

    function show(data) {
      field('status').textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
      field('generation').textContent = data.generation;
      field('elapsed').textContent = formatElapsed(data.elapsed_ms);
      field('best_penalty').textContent = data.best_penalty === null ? '-' : data.best_penalty;
      if (field('best_penalty_copy')) {
        field('best_penalty_copy').textContent = field('best_penalty').textContent;
      }
      field('hard').textContent = data.hard === null ? '-' : data.hard;
      field('soft').textContent = data.soft === null ? '-' : data.soft;
      // What the genetic algorithm has done so far: one generation scores the whole population.
      var evaluated = field('evaluated');
      if (evaluated && population) {
        evaluated.textContent = (population * (data.generation + 1)).toLocaleString();
      }
      if (data.points.length && field('avg')) {
        field('avg').textContent = Math.round(data.points[data.points.length - 1].a).toLocaleString();
      }

      var bar = root.querySelector('.progress-bar');
      var percent = Math.min(100, Math.round(100 * data.generation / maxGen));
      if (FINAL.indexOf(data.status) !== -1 && data.status !== 'failed') {
        percent = 100;
      }
      bar.querySelector('span').style.width = percent + '%';
      bar.setAttribute('aria-valuenow', data.generation);

      var queued = document.getElementById('queued-hint');
      queued.hidden = data.status !== 'queued';
      var position = document.getElementById('queue-position');
      if (position) {
        position.textContent = data.queue_position ? 'Your run is number ' + data.queue_position + ' in the queue.' : '';
      }
      var notes = document.getElementById('notes');
      notes.hidden = !data.notes;
      notes.textContent = data.notes || '';
      if (data.points.length) {
        after = data.points[data.points.length - 1].g;
        addPoints(data.points);
      }
    }

    function finish(status) {
      var stopForm = document.getElementById('stop-form');
      if (stopForm) {
        stopForm.hidden = true;
      }
      if (status !== 'failed') {
        document.getElementById('results-link').classList.remove('hidden');
      }
    }

    function poll() {
      fetch(api + '?job=' + encodeURIComponent(jobId) + '&after=' + after, { credentials: 'same-origin' })
        .then(function (response) {
          if (response.status === 401) {
            throw new Error('signed-out');
          }
          return response.json();
        })
        .then(function (data) {
          show(data);
          if (FINAL.indexOf(data.status) !== -1) {
            finish(data.status);
          } else {
            window.setTimeout(poll, POLL_MS);
          }
        })
        .catch(function (error) {
          if (error.message === 'signed-out') {
            field('status').textContent = 'Signed out';
            return;
          }
          window.setTimeout(poll, POLL_MS * 4);   // network hiccup: try again more slowly
        });
    }

    // Chart.js loads from the CDN with defer; draw any data already received once it arrives.
    window.addEventListener('load', function () {
      addPoints([]);
    });
    poll();
  });
})();
