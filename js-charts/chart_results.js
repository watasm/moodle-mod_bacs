window._bacsUserColors = window._bacsUserColors || {};
window._bacsNextColorIdx = window._bacsNextColorIdx || 0;

window.BacsUtils = window.BacsUtils || {};
window.BacsUtils.toDateTimeLocal = window.BacsUtils.toDateTimeLocal || function(ms) {
  const d = new Date(ms);
  const pad = (n) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

window.renderResultsGraph = () => {
  if (window.resultsChartInstance) {
    return;
  }
  const canvasId = 'results-graph-chart';
  const prefix = 'results-graph';
  const resetZoomBtn = document.getElementById('results-graph-reset-zoom');
  const hideUpsolvingCheckbox = document.getElementById("results-graph-hide-upsolving");

  const zoomStartInput = document.getElementById('results-zoom-start');
  const zoomEndInput = document.getElementById('results-zoom-end');
  const zoomApplyBtn = document.getElementById('results-zoom-apply');

  const currentLocale = document.documentElement.lang || 'en-US';
  const loc = (key, fallback) => (window.BACS_LOCALIZED_STRINGS && window.BACS_LOCALIZED_STRINGS[key]) ? window.BACS_LOCALIZED_STRINGS[key] : fallback;

  const layout = BacsUtils.createChartLayout(
    canvasId, prefix, 'bi-graph-up', loc('results_empty_title', 'Results graph is empty'),
    loc('results_empty_desc', 'Individual score dynamics will appear here. Submit a solution to start the graph!')
  );
  if (!layout) {
 return;
}

  const rawSubmissions = window.BACS_PAGE_DATA.submissions || [];
  const students = window.BACS_PAGE_DATA.students || [];
  const contestData = window.BACS_PAGE_DATA.contest;
  const durationMs = (contestData.endtime - contestData.starttime) * 1000;

  const studentStarts = {};
  students.forEach(s => studentStarts[s.id] = s.starttime || contestData.starttime);

  const canvasEl = document.getElementById(canvasId);
  let dragTooltip = document.getElementById(`${prefix}-drag-tooltip`);
  if (!dragTooltip) {
    dragTooltip = document.createElement('div');
    dragTooltip.id = `${prefix}-drag-tooltip`;
    dragTooltip.innerHTML = `<i class="bi bi-zoom-in" style="color: #60a5fa; margin-right: 5px;"></i>
      <span id="' + prefix + '-drag-text"></span>`;
    dragTooltip.style.cssText = `position: absolute; top: 40px; left: 50%; transform: translateX(-50%); 
      background: rgba(17, 24, 39, 0.9); color: #fff; padding: 6px 12px; border-radius: 6px; 
      font-size: 13px; font-family: 'Inter', sans-serif; pointer-events: none; opacity: 0; 
      transition: opacity 0.15s; z-index: 100; white-space: nowrap; font-weight: 500; 
      box-shadow: 0 4px 6px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);`;
    canvasEl.parentElement.appendChild(dragTooltip);
    canvasEl.parentElement.style.position = 'relative';
  }

  let isDragging = false;
  let startX = 0;

  const formatDragTime = (val) => {
    if (val < 0) {
 val = 0;
}
    const d = new Date(contestData.starttime * 1000 + val);
    return `${d.toLocaleDateString(currentLocale, {day: 'numeric', month: 'short'})} ${d.toLocaleTimeString(currentLocale, {hour: '2-digit', minute: '2-digit'})}`;
  };

  canvasEl.removeEventListener('mousedown', canvasEl._bacsMouseDown);
  canvasEl.removeEventListener('mousemove', canvasEl._bacsMouseMove);
  window.removeEventListener('mouseup', canvasEl._bacsMouseUp);

  canvasEl._bacsMouseDown = (e) => {
 isDragging = true; startX = e.offsetX;
};
  canvasEl._bacsMouseMove = (e) => {
    if (!isDragging) {
 return;
}
    const currentX = e.offsetX;
    if (Math.abs(currentX - startX) > 20) {
      const chart = window.resultsChartInstance;
      if (!chart) {
 return;
}
      const xAxis = chart.scales.x;
      const val1 = xAxis.getValueForPixel(startX);
      const val2 = xAxis.getValueForPixel(currentX);

      const str1 = formatDragTime(Math.min(val1, val2));
      const str2 = formatDragTime(Math.max(val1, val2));

      document.getElementById(`${prefix}-drag-text`).innerText = `${str1}  →  ${str2}`;
      dragTooltip.style.left = ((startX + currentX) / 2) + 'px';
      dragTooltip.style.opacity = '1';
    } else {
      dragTooltip.style.opacity = '0';
    }
  };
  canvasEl._bacsMouseUp = () => {
 isDragging = false; dragTooltip.style.opacity = '0';
};

  canvasEl.addEventListener('mousedown', canvasEl._bacsMouseDown);
  canvasEl.addEventListener('mousemove', canvasEl._bacsMouseMove);
  window.addEventListener('mouseup', canvasEl._bacsMouseUp);

  const updateManualZoomInputs = ({chart}) => {
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
      submissions = submissions.filter(s => s.submit_time <= contestData.endtime);
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
        data: [{x: 0, y: 0, taskName: 'Start', taskShort: '', delta: 0, realTime: 0}],
        totalScore: 0,
        taskScores: {},
      };
    });

    let maxContestScore = 0;
    let maxRealSubmitTimeMs = 0;

    const tempUserScores = {};
    sortedSubmissions.forEach((sub) => {
      const points = parseInt(sub.points, 10) || 0;
      if (!tempUserScores[sub.user_id]) {
 tempUserScores[sub.user_id] = {};
}
      if (points > (tempUserScores[sub.user_id][sub.task_id] || 0)) {
        tempUserScores[sub.user_id][sub.task_id] = points;
      }
    });
    Object.values(tempUserScores).forEach((tasks) => {
      const total = Object.values(tasks).reduce((a, b) => a + b, 0);
      if (total > maxContestScore) {
 maxContestScore = total;
}
    });

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
          isDummy: true
        });
      }
    });

    const curveOffset = Math.max(60000, chartMaxXVisual * 0.005);
    Object.values(userData).forEach((user) => {
      let smoothData = [];
      for (let i = 0; i < user.data.length; i++) {
        let pt = user.data[i];
        if (i > 0 && pt.x - user.data[i - 1].x > curveOffset * 1.5 && user.data[i - 1].y !== pt.y) {
          smoothData.push({
            x: pt.x - curveOffset,
            y: user.data[i - 1].y,
            taskName: null,
            delta: 0,
            isDummy: true,
            realTime: pt.x - curveOffset,
          });
        }
        smoothData.push(pt);
      }
      for (let i = smoothData.length - 1; i >= 0; i--) {
        if (!smoothData[i].isDummy) {
          smoothData[i].isLast = true;
          break;
        }
      }
      user.data = smoothData;
    });

    const datasets = [];
    const sortedUsers = Object.values(userData).sort((a, b) => b.totalScore - a.totalScore);
    layout.legendContainer.innerHTML = `<h6 class="text-muted mb-3" style="font-size:0.85rem; 
      font-weight:600; text-transform:uppercase;">${loc('participants', 'Participants')}</h6>`;

    const clickHandler = (chart, dsIndex) => {
      BacsUtils.toggleDatasetFocus(chart, dsIndex, `custom-${prefix}-legend`, (p) => !p.isDummy && (p.delta > 0 || p.isLast) ? 5 : 0);
    };

    sortedUsers.forEach((user) => {
      if (user.totalScore > 0) {
        if (!window._bacsUserColors[user.id]) {
          window._bacsUserColors[user.id] = BacsUtils.COLORS[window._bacsNextColorIdx % BacsUtils.COLORS.length];
          window._bacsNextColorIdx++;
        }
        const baseHex = window._bacsUserColors[user.id];
        const colorNormal = baseHex + 'E6';
        const dsIndex = datasets.length;

        datasets.push({
          label: user.name,
          userId: user.id,
          data: user.data,
          baseColor: baseHex,
          borderColor: colorNormal,
          backgroundColor: colorNormal,
          fill: false,
          stepped: false,
          tension: 0.4,
          cubicInterpolationMode: 'monotone',
          borderWidth: 2,
          pointRadius: user.data.map((p) => (p.isLast ? 4 : 0)),
          pointBackgroundColor: user.data.map(() => '#FFFFFF'),
          pointBorderColor: user.data.map(() => colorNormal),
          pointBorderWidth: user.data.map(() => 2),
          pointHoverRadius: user.data.map((p) => p.isDummy ? 0 : 6),
          pointHoverBackgroundColor: colorNormal,
          pointHoverBorderColor: '#FFFFFF',
          pointHoverBorderWidth: 2,
        });

        BacsUtils.createLegendItem(layout.legendContainer, baseHex, user.name, user.totalScore, () => clickHandler(window.resultsChartInstance, dsIndex));
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
      title: (c) => c && c[0] && c[0].dataset && c[0].raw ? c[0].raw.taskName ? `${c[0].dataset.label} - ${c[0].raw.taskName}` : c[0].dataset.label : '',
      label: (c) => {
        if (!c || !c.raw || c.raw.isDummy) {
 return '';
}
        let label = `${loc('score', 'Score:')} ${c.parsed.y}`;
        if (c.raw.delta > 0) {
 label += ` (+${c.raw.delta})`;
}

        let timeStr = BacsUtils.formatTime(c.raw.realTime / 1000);
        let dateStr = "";
        if (c.raw.realTime !== undefined) {
          const uId = c.dataset.userId;
          const uStart = studentStarts[uId] || contestData.starttime;
          const isVirtual = uStart > contestData.starttime;
          
          if (isVirtual) {
            const d = new Date(uStart * 1000 + c.raw.realTime);
            dateStr = ` (${d.toLocaleDateString(currentLocale, {day: 'numeric', month: 'short'})} ${d.toLocaleTimeString(currentLocale, {hour: '2-digit', minute: '2-digit'})}) [${loc('virtual', 'Virtual')}]`;
          } else {
            const d = new Date(contestData.starttime * 1000 + c.raw.realTime);
            dateStr = ` (${d.toLocaleDateString(currentLocale, {day: 'numeric', month: 'short'})} ${d.toLocaleTimeString(currentLocale, {hour: '2-digit', minute: '2-digit'})})`;
          }
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
      data: {datasets: datasets},
      plugins: [activeLabelsPlugin, BacsUtils.getLineClickPlugin(clickHandler), BacsUtils.getTimelinePlugin(durationMs, loc('start', 'Start'), loc('end', 'End'))],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        clip: false,
        layout: {padding: {top: 30, right: 20, left: 10, bottom: 20}},
        scales: {
          x: {
            type: 'linear',
            title: {display: false},
            border: {display: false},
            ticks: {
              color: TEXT_COLOR,
              maxRotation: 0,
              autoSkipPadding: 20,
              callback: (value) => {
                if (value >= 0) {
                  const elapsed = BacsUtils.formatTime(value / 1000);
                  const d = new Date(contestData.starttime * 1000 + value);
                  const dateStr = `${d.toLocaleDateString(currentLocale, {day: 'numeric', month: 'short', year: 'numeric'})}, ${d.toLocaleTimeString(currentLocale, {hour: '2-digit', minute: '2-digit'})}`;
                  return [`+ ${elapsed}`, dateStr];
                }
                return "";
              }
            },
            grid: {color: 'rgba(0,0,0,0)', drawBorder: false},
            min: 0,
            max: chartMaxXVisual,
          },
          y: {
            beginAtZero: true,
            grace: '5%',
            border: {display: false},
            title: {display: true, text: contestData.localizedStrings.points || 'Score', color: TEXT_COLOR, font: {weight: '500'}},
            ticks: {color: TEXT_COLOR, padding: 10},
            grid: {color: GRID_COLOR, drawBorder: false},
          },
        },
        animation: {duration: 1000, easing: 'easeOutQuart'},
        transitions: {zoom: {animation: {duration: 0}}},
        plugins: {
          legend: {display: false},
          tooltip: tooltipConfig,
          zoom: {
            limits: {
              x: {min: 'original', max: 'original', minRange: 60000}
            },
            pan: {
              enabled: true, mode: 'x',
              onPanComplete: updateManualZoomInputs
            },
            zoom: {
              wheel: {enabled: true, speed: 0.15},
              pinch: {enabled: true},
              drag: {enabled: true, backgroundColor: 'rgba(54, 162, 235, 0.2)', threshold: 20},
              mode: 'x',
              onZoomComplete: updateManualZoomInputs
            },
          },
        },
      },
    });

    if (resetZoomBtn) {
 resetZoomBtn.classList.add('d-none');
}
  };

  if (zoomApplyBtn && zoomStartInput && zoomEndInput) {
    zoomApplyBtn.addEventListener('click', () => {
      const chart = window.resultsChartInstance;
      if (!chart) {
 return;
}
      const t1 = new Date(zoomStartInput.value).getTime();
      const t2 = new Date(zoomEndInput.value).getTime();
      if (isNaN(t1) || isNaN(t2)) {
 return;
}

      const startMs = Math.min(t1, t2) - contestData.starttime * 1000;
      const endMs = Math.max(t1, t2) - contestData.starttime * 1000;

      chart.zoomScale('x', {min: Math.max(0, startMs), max: endMs}, 'default');
      if (resetZoomBtn) {
 resetZoomBtn.classList.remove('d-none');
}
    });
  }

  if (hideUpsolvingCheckbox) {
    hideUpsolvingCheckbox.addEventListener("change", drawChart);
  }

  if (resetZoomBtn) {
    resetZoomBtn.addEventListener('click', function() {
      if (window.resultsChartInstance) {
 window.resultsChartInstance.resetZoom();
}
      this.classList.add('d-none');
      if (zoomStartInput && zoomEndInput) {
        zoomStartInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000);
        zoomEndInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000 + window.resultsChartInstance.scales.x.max);
      }
    });
  }

  drawChart();
};