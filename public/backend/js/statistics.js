/**
 * Admin → Statistika: satış dinamikası (dövriyyə — sütun, sifariş sayı — xətt, iki ox)
 * və ödəniş üsulları (doughnut). Chart.js 2.8; rənglər Acorn Globals-dan, tema dəyişəndə yenidən qurulur.
 */
(function () {
  'use strict';

  const charts = [];
  const money = (v) => Number(v).toLocaleString('ru-RU', {maximumFractionDigits: 0}).replace(/ /g, ' ');

  function salesChart() {
    const canvas = document.getElementById('statSalesChart');
    if (!canvas) {
      return;
    }
    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const many = labels.length > 10;
    const grid = {color: Globals.separatorLight, drawBorder: false, zeroLineColor: Globals.separatorLight};
    const ticks = {fontColor: Globals.alternate, fontFamily: Globals.font, fontSize: 12, padding: 8};

    charts.push(new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            type: 'line',
            label: 'Sifariş',
            yAxisID: 'orders',
            data: JSON.parse(canvas.dataset.orders || '[]'),
            borderColor: Globals.tertiary,
            backgroundColor: 'transparent',
            pointBackgroundColor: Globals.foreground,
            pointBorderColor: Globals.tertiary,
            pointHoverBackgroundColor: Globals.tertiary,
            borderWidth: 2,
            pointRadius: many ? 2 : 4,
            pointBorderWidth: 2,
            pointHoverRadius: 6,
            lineTension: 0.35,
            fill: false,
          },
          {
            label: 'Dövriyyə',
            yAxisID: 'revenue',
            data: JSON.parse(canvas.dataset.revenue || '[]'),
            backgroundColor: 'rgba(' + Globals.primaryrgb + ',0.18)',
            hoverBackgroundColor: 'rgba(' + Globals.primaryrgb + ',0.45)',
            borderColor: Globals.primary,
            borderWidth: {top: 2, right: 0, bottom: 0, left: 0},
            barPercentage: many ? 0.8 : 0.55,
            categoryPercentage: 0.9,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {display: false},
        plugins: {crosshair: false, datalabels: {display: false}},
        hover: {mode: 'index', intersect: false},
        tooltips: Object.assign(ChartsExtend.ChartTooltip(), {
          mode: 'index',
          intersect: false,
          displayColors: true,
          callbacks: {
            label: (item, data) => {
              const ds = data.datasets[item.datasetIndex];
              return ' ' + ds.label + ': ' + (ds.yAxisID === 'revenue' ? money(item.yLabel) + ' ₼' : item.yLabel);
            },
          },
        }),
        scales: {
          xAxes: [{gridLines: {display: false}, ticks: Object.assign({}, ticks, {maxRotation: 0, autoSkip: true, autoSkipPadding: 12})}],
          yAxes: [
            {
              id: 'revenue',
              position: 'left',
              gridLines: grid,
              ticks: Object.assign({}, ticks, {beginAtZero: true, maxTicksLimit: 5, callback: (v) => money(v)}),
            },
            {
              id: 'orders',
              position: 'right',
              gridLines: {display: false, drawBorder: false},
              ticks: Object.assign({}, ticks, {beginAtZero: true, maxTicksLimit: 5, precision: 0}),
            },
          ],
        },
      },
    }));
  }

  function paymentsChart() {
    const canvas = document.getElementById('statPaymentsChart');
    if (!canvas) {
      return;
    }
    const colors = JSON.parse(canvas.dataset.colors || '[]');
    charts.push(new Chart(canvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: JSON.parse(canvas.dataset.labels || '[]'),
        datasets: [{
          data: JSON.parse(canvas.dataset.values || '[]'),
          backgroundColor: colors.map((c) => Globals[c]),
          hoverBackgroundColor: colors.map((c) => Globals[c]),
          borderColor: Globals.foreground,
          borderWidth: 3,
        }],
      },
      options: {
        cutoutPercentage: 74,
        responsive: true,
        maintainAspectRatio: false,
        legend: {display: false},
        plugins: {crosshair: false, datalabels: {display: false}},
        tooltips: Object.assign(ChartsExtend.ChartTooltip(), {
          callbacks: {label: (item, data) => ' ' + data.labels[item.index] + ': ' + money(data.datasets[0].data[item.index]) + ' ₼'},
        }),
      },
    }));
  }

  function build() {
    charts.splice(0).forEach((chart) => chart.destroy());
    salesChart();
    paymentsChart();
  }

  if (typeof Chart === 'undefined' || typeof ChartsExtend === 'undefined') {
    return;
  }
  // Globals rəngləri scripts.js-də DOMContentLoaded-da dolur — ondan sonra qururuq
  window.addEventListener('load', () => {
    build();
    document.documentElement.addEventListener(Globals.colorAttributeChange, build);
  });
})();
