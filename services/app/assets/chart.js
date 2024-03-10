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

  const scoresInChronologicalOrder = stats
    .flatMap(s => s.scores.map(score => ({ ...score, user: s.user })))
    .toSorted((a, b) => a.submitted_at - b.submitted_at);

  const scoresAndRanksAtEachTime = (() => {
    const result = [];
    const currentScoresForUser = new Map();
    for (const { user, submitted_at, code_size } of scoresInChronologicalOrder) {
      currentScoresForUser.set(user.name, { user, submitted_at, code_size });
      const ranking = currentScoresForUser
        .values()
        .toArray()
        .toSorted(
          (a, b) => a.code_size === b.code_size ? a.submitted_at - b.submitted_at : a.code_size - b.code_size,
        );
      const scores = new Map();
      for (const [i, { user, code_size }] of ranking.entries()) {
        scores.set(user.name, {
          user,
          code_size,
          rank: i + 1,
        });
      }
      result.push({ submitted_at: submitted_at, scores });
    }
    return result;
  })();

  const rankingHistory = (() => {
    const result = new Map();
    for (const { submitted_at, scores } of scoresAndRanksAtEachTime) {
      for (const [username, { user, code_size, rank }] of scores.entries()) {
        if (!result.has(username)) {
          result.set(username, []);
        }
        const scores = result.get(username);
        scores.push({ user, code_size, rank, submitted_at });
      }
    }
    return result
      .values()
      .toArray()
      .toSorted((a, b) => {
        const finalRankA = a[a.length - 1].rank;
        const finalRankB = b[b.length - 1].rank;
        return finalRankA - finalRankB;
      });
  })();

  new Chart(
    chartCanvas,
    {
      type: 'line',
      data: {
        datasets: rankingHistory.map(s => ({
          label: `${s[0].user.name}${s[0].user.is_admin ? ' (staff)' : ''}`,
          data: s.map(row => ({ x: row.submitted_at * 1000, y: row.rank, code_size: row.code_size })),
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
              text: '日時',
            },
          },
          y: {
            title: {
              display: true,
              text: '順位',
            },
            reverse: true,
            min: 1,
            offset: true,
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: (context) => {
                const label = context.dataset.label;
                const code_size = context.raw.code_size;
                return `${label} (${code_size} byte)`;
              },
            },
          },
        },
      },
    },
  );
});
