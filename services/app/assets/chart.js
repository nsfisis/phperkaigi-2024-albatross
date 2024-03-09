import {
  Chart,
  Colors,
  LineController,
  LineElement,
  LinearScale,
  PointElement,
  TimeScale,
  Tooltip,
} from 'chart.js'
import 'chartjs-adapter-date-fns';

Chart.register(
  Colors,
  LineController,
  LineElement,
  LinearScale,
  PointElement,
  TimeScale,
  Tooltip,
);

document.addEventListener('DOMContentLoaded', async () => {
  const chartCanvas = document.getElementById('chart');
  const quizId = chartCanvas.dataset.quizId;

  const apiUrl = `${process.env.ALBATROSS_BASE_PATH}/api/quizzes/${quizId}/chart`;
  const apiResult = await fetch(apiUrl).then(res => res.json());
  if (apiResult.error) {
    return;
  }
  const stats = apiResult.stats;

  // Filter best scores.
  for (const s of stats) {
    const bestScores = [];
    for (const score of s.scores) {
      if (bestScores.length === 0 || bestScores[bestScores.length - 1].code_size > score.code_size) {
        bestScores.push(score);
      }
    }
    s.scores = bestScores;
  }

  new Chart(
    chartCanvas,
    {
      type: 'line',
      data: {
        datasets: stats.map(s => ({
          label: `${s.user.name}${s.user.is_admin ? ' (staff)' : ''}`,
          data: s.scores.map(row => ({ x: row.submitted_at * 1000, y: row.code_size })),
        }))
      },
      options: {
        scales: {
          x: {
            type: 'time',
            time: {
              parsing: false,
              display: false,
              unit: 'day',
              tooltipFormat: 'yyyy-MM-dd HH:mm:ss',
              displayFormats: {
                day: 'yyyy-MM-dd',
              },
            },
            title: {
              display: true,
              text: '提出日時',
            },
          },
          y: {
            title: {
              display: true,
              text: 'コードサイズ (byte)',
            },
          },
        },
      },
    },
  );
});
