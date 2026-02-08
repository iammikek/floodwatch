import { Chart } from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
  const dataEl = document.getElementById('chart-data');
  const ctx = document.getElementById('llm-usage-chart');
  if (!dataEl || !ctx) return;

  const chartData = JSON.parse(dataEl.textContent);
  if (!chartData.length) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: chartData.map((d) => d.date),
      datasets: [
        {
          label: 'Requests',
          data: chartData.map((d) => d.requests),
          backgroundColor: 'rgba(59, 130, 246, 0.5)',
          borderColor: 'rgb(59, 130, 246)',
          borderWidth: 1,
          yAxisID: 'y',
        },
        {
          label: 'Est. cost ($)',
          data: chartData.map((d) => d.cost),
          backgroundColor: 'rgba(34, 197, 94, 0.5)',
          borderColor: 'rgb(34, 197, 94)',
          borderWidth: 1,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top' },
      },
      scales: {
        y: {
          type: 'linear',
          display: true,
          position: 'left',
          title: { display: true, text: 'Requests' },
        },
        y1: {
          type: 'linear',
          display: true,
          position: 'right',
          title: { display: true, text: 'Cost ($)' },
          grid: { drawOnChartArea: false },
        },
      },
    },
  });
});
