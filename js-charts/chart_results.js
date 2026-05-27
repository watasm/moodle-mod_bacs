/* eslint-disable no-nested-ternary */
/* eslint-disable max-len */
/* global BacsUtils, Chart */
window._bacsUserColors = window._bacsUserColors || {};
window._bacsNextColorIdx = window._bacsNextColorIdx || 0;

window.renderResultsGraph = () => {
  if (window.resultsChartInstance) {
    return;
  }
  const canvasId = 'results-graph-chart';
  const prefix = 'results-graph';
  const resetZoomBtn = document.getElementById('results-graph-reset-zoom');
  const hideUpsolvingCheckbox = document.getElementById('results-graph-hide-upsolving');

  const zoomStartInput = document.getElementById('results-zoom-start');
  const zoomEndInput = document.getElementById('results-zoom-end');

  const currentLocale = BacsUtils.currentLocale();
  const loc = BacsUtils.loc;

  const layout = BacsUtils.createChartLayout(
    canvasId,
    prefix,
    'bi-graph-up',
    loc('results_empty_title', 'Results graph is empty'),
    loc('results_empty_desc', 'Individual score dynamics will appear here. Submit a solution to start the graph!'),
  );
  if (!layout) {
    return;
  }

  const rawSubmissions = window.BACS_PAGE_DATA.submissions || [];
  const students = window.BACS_PAGE_DATA.students || [];
  const contestData = window.BACS_PAGE_DATA.contest;
  const durationMs = (contestData.endtime - contestData.starttime) * 1000;

  const studentStarts = BacsUtils.getStudentStartsMap(students, contestData.starttime);

  const canvasEl = document.getElementById(canvasId);

  if (canvasEl._bacsAbortController) {
    canvasEl._bacsAbortController.abort();
  }
  canvasEl._bacsAbortController = new AbortController();
  const signal = canvasEl._bacsAbortController.signal;

  BacsUtils.initDragZoomTooltip(
    canvasId,
    prefix,
    (val) => BacsUtils.formatShortDate(contestData.starttime * 1000 + val, currentLocale),
    () => window.resultsChartInstance,
    signal,
  );

  const updateManualZoomInputs = ({ chart }) => {
    if (!zoomStartInput || !zoomEndInput) {
      return;
    }
    const minMs = contestData.starttime * 1000 + chart.scales.x.min;
    const maxMs = contestData.starttime * 1000 + chart.scales.x.max;
    zoomStartInput.value = BacsUtils.toDateTimeLocal(minMs);
    zoomEndInput.value = BacsUtils.toDateTimeLocal(maxMs);
    if (resetZoomBtn) {
      resetZoomBtn.classList.remove('d-none');
    }
  };

  const drawChart = () => {
    let submissions = rawSubmissions.slice();
    if (hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked) {
      submissions = submissions.filter((s) => s.submit_time <= contestData.endtime);
    }

    const hasPoints = submissions.some((s) => parseInt(s.points, 10) > 0);
    if (!hasPoints) {
      layout.flexContainer.style.display = 'none';
      layout.emptyStateContainer.style.display = 'flex';
      return;
    } else {
      layout.flexContainer.style.display = 'flex';
      layout.emptyStateContainer.style.display = 'none';
    }

    const taskMap = {},
      taskFullMap = {};
    (window.BACS_PAGE_DATA.tasks || []).forEach((t) => {
      taskMap[t.task_id] = t.task_order;
      taskFullMap[t.task_id] = t.task_order + '. ' + t.name;
    });

    const sortedSubmissions = submissions.slice().sort((a, b) => a.submit_time - b.submit_time);

    const userData = {};
    students.forEach((student) => {
      userData[student.id] = {
        id: student.id,
        name: `${student.firstname} ${student.lastname}`,
        data: [{ x: 0, y: 0, taskName: 'Start', taskShort: '', delta: 0, realTime: 0 }],
        totalScore: 0,
        taskScores: {},
      };
    });

    let maxRealSubmitTimeMs = 0;

    sortedSubmissions.forEach((sub) => {
      const userId = sub.user_id;
      if (!userData[userId]) {
        return;
      }
      const points = sub.points === null ? 0 : parseInt(sub.points, 10);
      const user = userData[userId];
      const oldTaskScore = user.taskScores[sub.task_id] || 0;

      if (points > oldTaskScore) {
        const delta = points - oldTaskScore;
        user.taskScores[sub.task_id] = points;
        user.totalScore += delta;

        const uStart = studentStarts[userId] || contestData.starttime;
        const realTimeElapsed = Math.max(0, (sub.submit_time - uStart) * 1000);
        if (realTimeElapsed > maxRealSubmitTimeMs) {
          maxRealSubmitTimeMs = realTimeElapsed;
        }

        user.data.push({
          x: realTimeElapsed,
          y: user.totalScore,
          taskName: taskFullMap[sub.task_id] || `Task ${sub.task_id}`,
          taskShort: taskMap[sub.task_id] || '?',
          delta: delta,
          realTime: realTimeElapsed,
        });
      }
    });

    const finalRealTimeMs = maxRealSubmitTimeMs > 0 ? maxRealSubmitTimeMs : Date.now() - contestData.starttime * 1000;
    let chartMaxXVisual;

    if (hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked) {
      chartMaxXVisual = durationMs;
    } else {
      chartMaxXVisual = Math.max(durationMs, finalRealTimeMs) * 1.05;
    }

    Object.values(userData).forEach((user) => {
      const lastPoint = user.data[user.data.length - 1];
      if (lastPoint.x < chartMaxXVisual) {
        user.data.push({
          x: chartMaxXVisual,
          y: lastPoint.y,
          taskName: null,
          delta: 0,
          realTime: chartMaxXVisual,
          isDummy: true,
        });
      }
    });

    const curveOffset = Math.max(60000, chartMaxXVisual * 0.005);
    Object.values(userData).forEach((user) => {
      user.data = BacsUtils.smoothStepData(user.data, curveOffset);
    });

    const datasets = [];
    const sortedUsers = Object.values(userData).sort((a, b) => b.totalScore - a.totalScore);
    layout.legendContainer.innerHTML = `<h6 class="text-muted mb-3" style="font-size:0.85rem; 
      font-weight:600; text-transform:uppercase;">${loc('participants', 'Participants')}</h6>`;

    const clickHandler = (chart, dsIndex) => {
      BacsUtils.toggleDatasetFocus(chart, dsIndex, `custom-${prefix}-legend`, (p) =>
        !p.isDummy && (p.delta > 0 || p.isLast) ? 5 : 0,
      );
    };

    sortedUsers.forEach((user) => {
      if (user.totalScore > 0) {
        const dataset = BacsUtils.createLineDataset(user, user.data);
        const dsIndex = datasets.length;
        datasets.push(dataset);

        BacsUtils.createLegendItem(layout.legendContainer, dataset.baseColor, user.name, user.totalScore, () =>
          clickHandler(window.resultsChartInstance, dsIndex),
        );
      }
    });

    const TEXT_COLOR = '#6b7280';
    const GRID_COLOR = 'rgba(0, 0, 0, 0.04)';

    const activeLabelsPlugin = {
      id: 'activeLabels',
      afterDatasetsDraw(chart) {
        const ctx = chart.ctx;
        chart.data.datasets.forEach((dataset, i) => {
          const meta = chart.getDatasetMeta(i);
          if (dataset.borderWidth === 4 && !meta.hidden) {
            ctx.font = "600 11px 'Inter', sans-serif";
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            meta.data.forEach((element, index) => {
              const dataPoint = dataset.data[index];
              if (dataPoint && !dataPoint.isDummy && dataPoint.delta > 0 && dataPoint.taskName) {
                const text = dataPoint.taskName;
                const x = element.x + 10,
                  y = element.y - 12;
                const textWidth = ctx.measureText(text).width;
                ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                ctx.beginPath();
                ctx.roundRect(x - 4, y - 8, textWidth + 8, 16, 4);
                ctx.fill();
                ctx.fillStyle = dataset.baseColor;
                ctx.fillText(text, x, y);
              }
            });
          }
        });
      },
    };

    const tooltipConfig = BacsUtils.getTooltipBaseConfig({
      title: (c) =>
        c && c[0] && c[0].dataset && c[0].raw
          ? c[0].raw.taskName
            ? `${c[0].dataset.label} - ${c[0].raw.taskName}`
            : c[0].dataset.label
          : '',
      label: (c) => {
        if (!c || !c.raw || c.raw.isDummy) {
          return '';
        }
        let label = `${loc('score', 'Score:')} ${c.parsed.y}`;
        if (c.raw.delta > 0) {
          label += ` (+${c.raw.delta})`;
        }

        let timeStr = BacsUtils.formatTime(c.raw.realTime / 1000);
        let dateStr = '';
        if (c.raw.realTime !== undefined) {
          const uId = c.dataset.userId;
          const uStart = studentStarts[uId] || contestData.starttime;
          dateStr = BacsUtils.formatTooltipDate(uStart, c.raw.realTime, contestData.starttime, currentLocale);
        }

        label += `  •  +${timeStr}${dateStr}`;
        return label;
      },
    });

    if (window.resultsChartInstance) {
      window.resultsChartInstance.destroy();
    }

    if (zoomStartInput && zoomEndInput) {
      zoomStartInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000);
      zoomEndInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000 + chartMaxXVisual);
    }

    window.resultsChartInstance = new Chart(document.getElementById(canvasId).getContext('2d'), {
      type: 'line',
      data: { datasets: datasets },
      plugins: [
        activeLabelsPlugin,
        BacsUtils.getLineClickPlugin(clickHandler),
        BacsUtils.getTimelinePlugin(durationMs, loc('start', 'Start'), loc('end', 'End')),
      ],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        clip: false,
        layout: { padding: { top: 30, right: 20, left: 10, bottom: 20 } },
        scales: {
          x: {
            type: 'linear',
            title: { display: false },
            border: { display: false },
            ticks: {
              color: TEXT_COLOR,
              maxRotation: 0,
              autoSkipPadding: 20,
              callback: (value) => {
                if (value >= 0) {
                  const elapsed = BacsUtils.formatTime(value / 1000);
                  const d = new Date(contestData.starttime * 1000 + value);
                  const dateStr = `${d.toLocaleDateString(currentLocale, { day: 'numeric', month: 'short', year: 'numeric' })}, ${d.toLocaleTimeString(currentLocale, { hour: '2-digit', minute: '2-digit' })}`;
                  return [`+ ${elapsed}`, dateStr];
                }
                return '';
              },
            },
            grid: { color: 'rgba(0,0,0,0)', drawBorder: false },
            min: 0,
            max: chartMaxXVisual,
          },
          y: {
            beginAtZero: true,
            grace: '5%',
            border: { display: false },
            title: {
              display: true,
              text: contestData.localizedStrings.points || 'Score',
              color: TEXT_COLOR,
              font: { weight: '500' },
            },
            ticks: { color: TEXT_COLOR, padding: 10 },
            grid: { color: GRID_COLOR, drawBorder: false },
          },
        },
        animation: { duration: 1000, easing: 'easeOutQuart' },
        transitions: { zoom: { animation: { duration: 0 } } },
        plugins: {
          legend: { display: false },
          tooltip: tooltipConfig,
          zoom: {
            limits: {
              x: { min: 'original', max: 'original', minRange: 60000 },
            },
            pan: {
              enabled: true,
              mode: 'x',
              onPanComplete: updateManualZoomInputs,
            },
            zoom: {
              wheel: { enabled: true, speed: 0.15 },
              pinch: { enabled: true },
              drag: { enabled: true, backgroundColor: 'rgba(54, 162, 235, 0.2)', threshold: 20 },
              mode: 'x',
              onZoomComplete: updateManualZoomInputs,
            },
          },
        },
      },
    });

    if (resetZoomBtn) {
      resetZoomBtn.classList.add('d-none');
    }
  };

  if (hideUpsolvingCheckbox) {
    hideUpsolvingCheckbox.addEventListener('change', drawChart);
  }

  BacsUtils.bindTimeZoomControls(
    () => window.resultsChartInstance,
    'results-zoom-start',
    'results-zoom-end',
    'results-zoom-apply',
    'results-graph-reset-zoom',
    contestData.starttime,
    signal,
  );

  drawChart();
};
