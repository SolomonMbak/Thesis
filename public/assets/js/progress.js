/*
 * Live progress page: polls api/progress.php every 1.5 seconds, updates the
 * figures, the progress bar and the Chart.js chart (best penalty on a log
 * scale, hard violations on a second axis). Stops polling when the run ends.
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
    var pending = { labels: [], penalty: [], hard: [] };

    function field(name) {
      return root.querySelector('[data-field="' + name + '"]');
    }

    function formatElapsed(ms) {
      var s = Math.round(ms / 1000);
      if (s < 60) {
        return s + 's';
      }
      return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
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
            { label: 'Best penalty', data: [], yAxisID: 'y', borderColor: '#1d5fa8', pointRadius: 0, borderWidth: 2, spanGaps: true },
            { label: 'Hard violations (V_H)', data: [], yAxisID: 'y1', borderColor: '#b42318', pointRadius: 0, borderWidth: 2, stepped: true }
          ]
        },
        options: {
          animation: false,
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          scales: {
            x: { title: { display: true, text: 'Generation' } },
            y: { type: 'logarithmic', position: 'left', title: { display: true, text: 'Best penalty (log scale)' } },
            y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false },
              title: { display: true, text: 'V_H' }, ticks: { precision: 0 } }
          }
        }
      });
    }

    function addPoints(points) {
      points.forEach(function (pt) {
        pending.labels.push(pt.g);
        pending.penalty.push(pt.p > 0 ? pt.p : null);   // a log scale cannot show 0
        pending.hard.push(pt.h);
      });
      makeChart();
      if (!chart) {
        return;   // Chart.js not loaded yet (or blocked); keep the data for later
      }
      Array.prototype.push.apply(chart.data.labels, pending.labels);
      Array.prototype.push.apply(chart.data.datasets[0].data, pending.penalty);
      Array.prototype.push.apply(chart.data.datasets[1].data, pending.hard);
      pending = { labels: [], penalty: [], hard: [] };
      chart.update('none');
    }

    function show(data) {
      field('status').textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
      field('generation').textContent = data.generation;
      field('elapsed').textContent = formatElapsed(data.elapsed_ms);
      field('best_penalty').textContent = data.best_penalty === null ? '-' : data.best_penalty;
      field('hard').textContent = data.hard === null ? '-' : data.hard;
      field('soft').textContent = data.soft === null ? '-' : data.soft;

      var bar = root.querySelector('.progress-bar');
      var percent = Math.min(100, Math.round(100 * data.generation / maxGen));
      if (FINAL.indexOf(data.status) !== -1 && data.status !== 'failed') {
        percent = 100;
      }
      bar.querySelector('span').style.width = percent + '%';
      bar.setAttribute('aria-valuenow', data.generation);

      document.getElementById('queued-hint').hidden = data.status !== 'queued';
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
