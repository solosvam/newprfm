/**
 * Admin əsas səhifəsi: satış qrafikləri (Acorn ChartsExtend.LargeLineChart).
 * Məlumat canvas-ın data-* atributlarından gəlir; tema rəngi dəyişəndə qrafiklər yenidən qurulur.
 */
(function () {
  'use strict';

  const charts = [];

  function build() {
    charts.splice(0).forEach((chart) => chart.destroy());

    document.querySelectorAll('canvas.dashboard-line-chart').forEach((canvas) => {
      const values = JSON.parse(canvas.dataset.values || '[]');
      const labels = JSON.parse(canvas.dataset.labels || '[]');
      const color = Globals[canvas.dataset.color] || Globals.primary;
      // əvvəlki günə nisbətən ox: artım / azalma
      const icons = values.map((value, i) => (i > 0 && value < values[i - 1] ? 'arrow-bottom' : 'arrow-top'));

      const chart = ChartsExtend.LargeLineChart(canvas.id, {
        labels: labels,
        datasets: [
          {
            label: canvas.dataset.label,
            data: values,
            icons: icons,
            borderColor: color,
            pointBackgroundColor: color,
            pointBorderColor: color,
            pointHoverBackgroundColor: Globals.foreground,
            pointHoverBorderColor: color,
            borderWidth: 2,
            pointRadius: 2,
            pointBorderWidth: 2,
            pointHoverBorderWidth: 2,
            pointHoverRadius: 5,
            fill: false,
            datalabels: {align: 'end', anchor: 'end'},
          },
        ],
      });
      if (chart) {
        charts.push(chart);
      }
    });
    buildPayments();
  }

  // Ödəniş üsulları: Acorn "custom legend doughnut" üslubu (legend serverdə çəkilir)
  function buildPayments() {
    const canvas = document.getElementById('dashPaymentsChart');
    if (!canvas) {
      return;
    }
    const colors = JSON.parse(canvas.dataset.colors || '[]');
    const chart = new Chart(canvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: JSON.parse(canvas.dataset.labels || '[]'),
        datasets: [
          {
            data: JSON.parse(canvas.dataset.values || '[]'),
            backgroundColor: colors.map((c) => 'rgba(' + Globals[c + 'rgb'] + ',0.1)'),
            borderColor: colors.map((c) => Globals[c]),
          },
        ],
      },
      options: {
        cutoutPercentage: 72,
        responsive: true,
        maintainAspectRatio: false,
        legend: false,
        plugins: {crosshair: false, datalabels: {display: false}},
        tooltips: ChartsExtend.ChartTooltip(),
      },
    });
    charts.push(chart);
  }

  if (typeof Chart === 'undefined' || typeof ChartsExtend === 'undefined') {
    return;
  }
  // Globals rəngləri (Variables) scripts.js-də DOMContentLoaded-da dolur — ondan sonra qururuq
  window.addEventListener('load', () => {
    build();
    document.documentElement.addEventListener(Globals.colorAttributeChange, build);
  });
})();
