/* global BacsUtils, Chart */
window._bacsUserColors = window._bacsUserColors || {};
window._bacsNextColorIdx = window._bacsNextColorIdx || 0;

window.BacsUtils = window.BacsUtils || {};
window.BacsUtils.toDateTimeLocal = window.BacsUtils.toDateTimeLocal || function(ms) {
  const d = new Date(ms);
  const pad = (n) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

window.initializeLeaderDynamicsChart = () => {
  const canvasId = "leader-dynamics-chart";
  const prefix = "leader-dynamics";
  const controlsContainer = document.getElementById(`${prefix}-controls`);
  const stepContainer = document.getElementById("leader-dynamics-step-container");
  const stepSelect = document.getElementById("leader-dynamics-step");
  const resetZoomBtn = document.getElementById("leader-dynamics-reset-zoom");
  const hideUpsolvingCheckbox = document.getElementById("leader-dynamics-hide-upsolving");
  const hideUpsolvingContainer = document.getElementById("leader-dynamics-hide-upsolving-container");

  const manualZoomContainer = document.getElementById("leader-dynamics-manual-zoom");
  const zoomStartInput = document.getElementById('leader-zoom-start');
  const zoomEndInput = document.getElementById('leader-zoom-end');
  const zoomApplyBtn = document.getElementById('leader-zoom-apply');

  const currentLocale = document.documentElement.lang || 'en-US';
  const loc = (key, fallback) => (window.BACS_LOCALIZED_STRINGS && window.BACS_LOCALIZED_STRINGS[key]) ? window.BACS_LOCALIZED_STRINGS[key] : fallback;

  const layout = BacsUtils.createChartLayout(
    canvasId, prefix, "bi-rocket-takeoff", loc('race_empty_title', 'The race hasn\'t started yet'),
    loc('race_empty_desc', 'Leader dynamics chart will appear here automatically as soon as participants start submitting solutions.')
  );
  if (!layout) {
 return;
}

  const rawSubmissions = window.BACS_PAGE_DATA.submissions || [];
  const students = window.BACS_PAGE_DATA.students || [];
  const tasks = window.BACS_PAGE_DATA.tasks || [];
  const contestData = window.BACS_PAGE_DATA.contest;
  const durationMs = (contestData.endtime - contestData.starttime) * 1000;

  let currentMode = "realtime";

  const canvasEl = document.getElementById(canvasId);
  let dragTooltip = document.getElementById(`${prefix}-drag-tooltip`);
  if (!dragTooltip) {
    dragTooltip = document.createElement('div');
    dragTooltip.id = `${prefix}-drag-tooltip`;
    dragTooltip.innerHTML = '<i class="bi bi-zoom-in" style="color: #60a5fa; margin-right: 5px;"></i> <span id="' + prefix + '-drag-text"></span>';
    dragTooltip.style.cssText = `position: absolute; top: 40px; left: 50%; transform: translateX(-50%); background: rgba(17, 24, 39, 0.9); color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif; pointer-events: none; opacity: 0; transition: opacity 0.15s; z-index: 100; white-space: nowrap; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);`;
    canvasEl.parentElement.appendChild(dragTooltip);
    canvasEl.parentElement.style.position = 'relative';
  }

  let isDragging = false;
  let startX = 0;

  const formatDragValue = (val) => {
    if (currentMode === 'normalized') {
 return `${Math.max(0, Math.round(val))}%`;
}
    if (currentMode === 'events') {
 return `${loc('event', 'Event')} #${Math.max(0, Math.round(val))}`;
}
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
      const chart = window.leaderDynamicsChartInstance;
      if (!chart) {
 return;
}
      const xAxis = chart.scales.x;
      const val1 = xAxis.getValueForPixel(startX);
      const val2 = xAxis.getValueForPixel(currentX);

      const str1 = formatDragValue(Math.min(val1, val2));
      const str2 = formatDragValue(Math.max(val1, val2));

      document.getElementById(`${prefix}-drag-text`).innerText = `${str1}  →  ${str2}`;

      const midX = (startX + currentX) / 2;
      dragTooltip.style.left = midX + 'px';
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
    if (currentMode !== "realtime" || !zoomStartInput || !zoomEndInput) {
      if (resetZoomBtn) {
 resetZoomBtn.classList.remove('d-none');
}
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

  let precomputedData = null;
  const MAX_PARTICIPANTS_TO_SHOW = 120;
  const raceComparator = (a, b) => (b.points !== a.points) ? (b.points - a.points) : (a.lastImprovement - b.lastImprovement);

  const precomputeAllData = (submissions) => {
    let allUserStates = {};
    students.forEach((student) => {
      allUserStates[student.id] = {id: student.id, name: `${student.firstname} ${student.lastname}`, points: 0, lastImprovement: 0};
    });

    const allEvents = [];
    const eventsByUser = {};
    students.forEach((s) => (eventsByUser[s.id] = []));

    const userTaskScores = {};
    const sortedSubmissions = submissions.slice().sort((a, b) => a.submit_time - b.submit_time);

    sortedSubmissions.forEach((sub) => {
      if (!allUserStates[sub.user_id]) {
 return;
}
      const newPoints = parseInt(sub.points, 10) || 0;
      const userScores = (userTaskScores[sub.user_id] = userTaskScores[sub.user_id] || {});
      const oldTaskScore = userScores[sub.task_id] || 0;

      if (newPoints > oldTaskScore) {
        userScores[sub.task_id] = newPoints;
        const timeElapsedMs = Math.max(0, (sub.submit_time - contestData.starttime) * 1000);
        const event = {time: timeElapsedMs, userId: sub.user_id, delta: newPoints - oldTaskScore};
        allEvents.push(event);
        eventsByUser[sub.user_id].push(event);
      }
    });

    let maxContestScore = 0,
globalMaxSubmitTime = 0;
    let simulationStates = JSON.parse(JSON.stringify(allUserStates));
    const rankSnapshots = [{time: 0, ranks: {}}];

    let rankedUsers = Object.values(simulationStates).sort(raceComparator);
    rankedUsers.forEach((user, index) => {
      if (user) {
 rankSnapshots[0].ranks[user.id] = index + 1;
}
    });

    for (const event of allEvents) {
      if (simulationStates[event.userId]) {
        simulationStates[event.userId].points += event.delta;
        simulationStates[event.userId].lastImprovement = event.time;
        maxContestScore = Math.max(maxContestScore, simulationStates[event.userId].points);
        globalMaxSubmitTime = Math.max(globalMaxSubmitTime, event.time);
      }
      rankedUsers = Object.values(simulationStates).sort(raceComparator);
      const snapshot = {time: event.time, ranks: {}};
      rankedUsers.forEach((user, index) => {
        if (user) {
 snapshot.ranks[user.id] = index + 1;
}
      });
      rankSnapshots.push(snapshot);
    }
    const finalRankedUsers = Object.values(simulationStates).sort(raceComparator);
    return {finalRankedUsers, rankSnapshots, eventsByUser, maxContestScore, globalMaxSubmitTime};
  };

  const clickHandler = (chart, dsIndex) => {
    BacsUtils.toggleDatasetFocus(chart, dsIndex, `custom-${prefix}-legend`, p => p.isDummy ? 0 : (p.isLast ? 6 : 4));
  };

  const generateChartConfig = (mode) => {
    const validTaskIds = new Set(tasks.map(t => String(t.task_id)));
    let submissions = rawSubmissions.filter(s => validTaskIds.has(String(s.task_id)));

    if (mode === "realtime" && hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked) {
      submissions = submissions.filter(s => s.submit_time <= contestData.endtime);
    }

    const hasPoints = submissions.some(s => parseInt(s.points, 10) > 0);
    if (!hasPoints) {
      layout.flexContainer.style.display = 'none';
      layout.emptyStateContainer.style.display = 'flex';
      return null;
    } else {
      layout.flexContainer.style.display = 'flex';
      layout.emptyStateContainer.style.display = 'none';
    }

    if (!precomputedData) {
 precomputedData = precomputeAllData(submissions);
}
    const {finalRankedUsers, rankSnapshots, eventsByUser, maxContestScore, globalMaxSubmitTime} = precomputedData;
    const topUsers = finalRankedUsers.slice(0, MAX_PARTICIPANTS_TO_SHOW);
    const NORMALIZED_AGGREGATION_STEP = stepSelect ? parseInt(stepSelect.value, 10) : 5;

    const trueFinalRanks = {};
    finalRankedUsers.forEach((user, index) => {
 trueFinalRanks[user.id] = index + 1;
});

    let chartMaxXVisual, xAxisTitle, curveOffset, minZoomRange;

    if (mode === "normalized") {
      chartMaxXVisual = 100;
      xAxisTitle = loc('progress_to_max', 'Progress to Contest Max Score (%)');
      curveOffset = 1.5;
      minZoomRange = 5;
    } else if (mode === "events") {
      chartMaxXVisual = rankSnapshots.length - 1;
      xAxisTitle = loc('successful_submissions_seq', 'Successful Submissions (Sequence)');
      curveOffset = 0.4;
      minZoomRange = 5;
    } else {
      const finalRealTimeMs = globalMaxSubmitTime > 0 ? globalMaxSubmitTime : (Date.now() - contestData.starttime * 1000);
      if (hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked) {
        chartMaxXVisual = durationMs;
      } else {
        chartMaxXVisual = Math.max(durationMs, finalRealTimeMs) * 1.05;
      }
      xAxisTitle = loc('timefromstart', 'Time from start');
      curveOffset = Math.max(60000, chartMaxXVisual * 0.005);
      minZoomRange = 60000;
    }

    if (mode === "realtime" && zoomStartInput && zoomEndInput) {
      zoomStartInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000);
      zoomEndInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000 + chartMaxXVisual);
    }

    const datasets = [];
    const datasetsInfo = [];
    layout.legendContainer.innerHTML = `<h6 class="text-muted mb-3" style="font-size:0.85rem; font-weight:600; text-transform:uppercase;">${loc('topparticipants', 'Top Participants')}</h6>`;

    if (mode === "normalized") {
      const normalizedSnapshots = [];
      for (let p = 0; p <= 100; p += NORMALIZED_AGGREGATION_STEP) {
        const targetPoints = (p / 100) * maxContestScore;
        const progressTimestamps = topUsers.map((user) => {
          let timeAtProgress = Infinity;
          let cumulativePoints = 0;
          for (const event of eventsByUser[user.id]) {
            cumulativePoints += event.delta;
            if (cumulativePoints >= targetPoints) {
 timeAtProgress = event.time; break;
}
          }
          return {userId: user.id, time: timeAtProgress};
        });
        progressTimestamps.sort((a, b) => a.time - b.time);
        const snapshot = {progress: p, ranks: {}, times: {}};
        progressTimestamps.forEach((data, index) => {
          snapshot.ranks[data.userId] = index + 1; snapshot.times[data.userId] = data.time;
        });
        normalizedSnapshots.push(snapshot);
      }

      topUsers.forEach((user) => {
        const rawData = [];
        const maxUserProgress = maxContestScore > 0 ? (user.points / maxContestScore) * 100 : 0;
        normalizedSnapshots.forEach(snap => {
          if (snap.progress <= maxUserProgress) {
            rawData.push({x: snap.progress, y: snap.ranks[user.id], realTime: snap.times[user.id]});
          }
        });

        let smoothData = [];
        for (let i = 0; i < rawData.length; i++) {
          let pt = rawData[i];
          if (i > 0 && (pt.x - rawData[i - 1].x) > curveOffset * 1.2 && rawData[i - 1].y !== pt.y) {
            smoothData.push({x: pt.x - curveOffset, y: rawData[i - 1].y, realTime: pt.realTime, isDummy: true});
          }
          smoothData.push({...pt});
        }
        for (let i = smoothData.length - 1; i >= 0; i--) {
          if (!smoothData[i].isDummy) {
 smoothData[i].isLast = true; break;
}
        }
        if (smoothData.length > 0) {
 datasetsInfo.push({user, smoothData, trueRank: trueFinalRanks[user.id]});
}
      });
    } else if (mode === "events") {
      const maxEventIndex = rankSnapshots.length - 1;
      topUsers.forEach((user) => {
        let smoothData = [];
        let lastRank = rankSnapshots[0].ranks[user.id] || (students.length + 1);

        smoothData.push({x: 0, y: lastRank, realTime: rankSnapshots[0].time});

        for (let eventIndex = 1; eventIndex <= maxEventIndex; eventIndex++) {
          const snap = rankSnapshots[eventIndex];
          const currentRank = snap.ranks[user.id] || (students.length + 1);

          if (currentRank !== lastRank) {
            smoothData.push({x: eventIndex - curveOffset, y: lastRank, realTime: snap.time, isDummy: true});
            smoothData.push({x: eventIndex, y: currentRank, realTime: snap.time});
            lastRank = currentRank;
          }
        }

        const lastPointAdded = smoothData[smoothData.length - 1];
        if (lastPointAdded.x < maxEventIndex) {
          smoothData.push({x: maxEventIndex, y: lastRank, realTime: rankSnapshots[maxEventIndex].time, isDummy: true});
        }

        for (let i = smoothData.length - 1; i >= 0; i--) {
          if (!smoothData[i].isDummy) {
 smoothData[i].isLast = true; break;
}
        }

        if (smoothData.length > 0) {
          datasetsInfo.push({user, smoothData, trueRank: trueFinalRanks[user.id]});
        }
      });
    } else {
      topUsers.forEach((user) => {
        let filteredData = rankSnapshots.map((snap) => ({
          x: snap.time, y: snap.ranks[user.id] || (students.length + 1), realTime: snap.time
        }));

        if (filteredData.length > 0) {
          filteredData.push({x: chartMaxXVisual, y: filteredData[filteredData.length - 1].y, realTime: chartMaxXVisual, isDummy: true});
        }

        let smoothData = [];
        if (filteredData.length > 0) {
          smoothData.push({...filteredData[0]});
          let currentY = filteredData[0].y;
          for (let i = 1; i < filteredData.length; i++) {
            let pt = filteredData[i];
            if (pt.y !== currentY) {
              if ((pt.x - smoothData[smoothData.length - 1].x) > curveOffset * 1.2) {
                smoothData.push({x: pt.x - curveOffset, y: currentY, realTime: pt.x - curveOffset, isDummy: true});
              }
              smoothData.push({...pt});
              currentY = pt.y;
            } else if (i === filteredData.length - 1) {
              smoothData.push({...pt});
            }
          }
        }

        for (let i = smoothData.length - 1; i >= 0; i--) {
          if (!smoothData[i].isDummy) {
 smoothData[i].isLast = true; break;
}
        }

        if (smoothData.length > 0) {
          datasetsInfo.push({user, smoothData, trueRank: trueFinalRanks[user.id]});
        }
      });
    }

    datasetsInfo.sort((a, b) => a.trueRank - b.trueRank);
    datasetsInfo.forEach((info) => {
      if (!window._bacsUserColors[info.user.id]) {
        window._bacsUserColors[info.user.id] = BacsUtils.COLORS[window._bacsNextColorIdx % BacsUtils.COLORS.length];
        window._bacsNextColorIdx++;
      }

      const baseHex = window._bacsUserColors[info.user.id];
      const colorNormal = baseHex + "E6";
      const dsIndex = datasets.length;

      datasets.push({
        label: info.user.name,
        data: info.smoothData,
        baseColor: baseHex,
        borderColor: colorNormal,
        backgroundColor: colorNormal,
        fill: false,
        stepped: false,
        tension: mode === "events" ? 0 : 0.4,
        cubicInterpolationMode: 'monotone',
        borderWidth: 2,
        clip: false,
        pointRadius: info.smoothData.map(p => p.isLast ? 4 : 0),
        pointBackgroundColor: info.smoothData.map(() => "#FFFFFF"),
        pointBorderColor: info.smoothData.map(() => colorNormal),
        pointBorderWidth: info.smoothData.map(() => 2),
        pointHoverRadius: info.smoothData.map(p => p.isDummy ? 0 : 6),
        pointHoverBackgroundColor: colorNormal,
        pointHoverBorderColor: "#FFFFFF",
        pointHoverBorderWidth: 2,
      });

      const rankText = info.trueRank > students.length ? "-" : info.trueRank;
      BacsUtils.createLegendItem(layout.legendContainer, baseHex, info.user.name, `#${rankText}`,
        () => clickHandler(window.leaderDynamicsChartInstance, dsIndex));
    });

    const TEXT_COLOR = "#6b7280";

    const tooltipConfig = BacsUtils.getTooltipBaseConfig({
      title: (c) => (c && c[0] && c[0].dataset) ? c[0].dataset.label : "",
      label: (c) => {
        if (!c || !c.raw || c.raw.isDummy) {
 return "";
}
        let timeStr = (c.raw.realTime !== undefined) ? BacsUtils.formatTime(c.raw.realTime / 1000) : "N/A";

        let dateStr = "";
        if (c.raw.realTime !== undefined) {
          const d = new Date(contestData.starttime * 1000 + c.raw.realTime);
          dateStr = ` (${d.toLocaleDateString(currentLocale, {day: 'numeric', month: 'short'})} ${d.toLocaleTimeString(currentLocale, {hour: '2-digit', minute: '2-digit'})})`;
        }

        if (mode === "normalized") {
 return `${loc('rank', 'Rank:')} #${c.parsed.y} at ${c.parsed.x.toFixed(0)}% (Time: +${timeStr}${dateStr})`;
}
        if (mode === "events") {
 return `${loc('rank', 'Rank:')} #${c.parsed.y} (${loc('event', 'Event')} #${Math.round(c.parsed.x)}) • +${timeStr}${dateStr}`;
}
        return `${loc('rank', 'Rank:')} #${c.parsed.y}  •  +${timeStr}${dateStr}`;
      }
    });

    return {
      type: "line",
      data: {datasets},
      plugins: [BacsUtils.getLineClickPlugin(clickHandler), BacsUtils.getTimelinePlugin(durationMs, loc('start', 'Start'), loc('end', 'End'))],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        clip: false,
        layout: {padding: {top: 25, right: 30, left: 20, bottom: 20}},
        scales: {
          x: {
            type: "linear",
            title: {display: false},
            border: {display: false},
            ticks: {
              color: TEXT_COLOR,
              maxRotation: 0,
              autoSkipPadding: 20,
              callback: (value) => {
                if (mode === "normalized") {
 return (value % NORMALIZED_AGGREGATION_STEP === 0 && value >= 0 && value <= 100 ? `${value}%` : "");
}
                if (mode === "events") {
 return (Number.isInteger(value) && value >= 0 ? `#${value}` : "");
}

                if (value >= 0) {
                  const elapsed = BacsUtils.formatTime(value / 1000);
                  const d = new Date(contestData.starttime * 1000 + value);
                  const dateStr = `${d.toLocaleDateString(currentLocale, {day: 'numeric', month: 'short', year: 'numeric'})}, ${d.toLocaleTimeString(currentLocale, {hour: '2-digit', minute: '2-digit'})}`;
                  return [`+ ${elapsed}`, dateStr];
                }
                return "";
              }
            },
            grid: {color: "rgba(0,0,0,0)", drawBorder: false},
            min: 0,
            max: chartMaxXVisual,
          },
          y: {
            title: {display: true, text: contestData.localizedStrings.rank || "Rank", color: TEXT_COLOR, font: {weight: '500'}},
            reverse: true,
            min: 1,
            max: topUsers.length > 0 ? topUsers.length + 1 : MAX_PARTICIPANTS_TO_SHOW + 1,
            border: {display: false},
            ticks: {color: TEXT_COLOR, stepSize: 1, precision: 0, padding: 10},
            grid: {color: "rgba(0, 0, 0, 0.04)", drawBorder: false},
          },
        },
        animation: {duration: 1000, easing: "easeOutQuart"},
        transitions: {zoom: {animation: {duration: 0}}},
        plugins: {
          legend: {display: false},
          tooltip: tooltipConfig,
          zoom: {
            limits: {
              x: {min: 'original', max: 'original', minRange: minZoomRange}
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
            }
          }
        },
      },
    };
  };

  const drawChart = () => {
    precomputedData = null;
    const config = generateChartConfig(currentMode);
    if (!config) {
 return;
}
    if (window.leaderDynamicsChartInstance) {
      window.leaderDynamicsChartInstance.destroy();
    }
    window.leaderDynamicsChartInstance = new Chart(document.getElementById(canvasId).getContext('2d'), config);
    if (resetZoomBtn) {
 resetZoomBtn.classList.add('d-none');
}
  };

  if (zoomApplyBtn && zoomStartInput && zoomEndInput) {
    zoomApplyBtn.addEventListener('click', () => {
      if (currentMode !== "realtime") {
 return;
}
      const chart = window.leaderDynamicsChartInstance;
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

  if (stepSelect) {
    stepSelect.addEventListener("change", () => {
      if (currentMode === "normalized") {
 drawChart();
}
    });
  }

  if (resetZoomBtn) {
    resetZoomBtn.addEventListener('click', function() {
      if (window.leaderDynamicsChartInstance) {
 window.leaderDynamicsChartInstance.resetZoom();
}
      this.classList.add('d-none');
      if (currentMode === "realtime" && zoomStartInput && zoomEndInput) {
        zoomStartInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000);
        zoomEndInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000 + window.leaderDynamicsChartInstance.scales.x.max);
      }
    });
  }

  if (controlsContainer) {
    controlsContainer.addEventListener("click", (e) => {
      const button = e.target.closest("button");
      if (!button || button.classList.contains("active")) {
 return;
}
      const newMode = button.dataset.mode;
      if (newMode && newMode !== currentMode) {
        currentMode = newMode;
        controlsContainer.querySelectorAll("button").forEach((btn) => {
          const isActive = btn.dataset.mode === newMode;
          btn.classList.toggle("active", isActive);
          btn.classList.toggle("btn-primary", isActive);
          btn.classList.toggle("btn-outline-secondary", !isActive);
        });

        if (stepContainer) {
          if (newMode === "normalized") {
            stepContainer.classList.remove("d-none");
            stepContainer.classList.add("d-flex");
          } else {
            stepContainer.classList.remove("d-flex");
            stepContainer.classList.add("d-none");
          }
        }

        if (hideUpsolvingContainer) {
          if (newMode === "realtime") {
            hideUpsolvingContainer.classList.remove("d-none");
            hideUpsolvingContainer.classList.add("d-flex");
          } else {
            hideUpsolvingContainer.classList.remove("d-flex");
            hideUpsolvingContainer.classList.add("d-none");
          }
        }

        if (manualZoomContainer) {
          if (newMode === "realtime") {
            manualZoomContainer.classList.remove("d-none");
            manualZoomContainer.classList.add("d-flex");
          } else {
            manualZoomContainer.classList.remove("d-flex");
            manualZoomContainer.classList.add("d-none");
          }
        }

        drawChart();
      }
    });
  }

  drawChart();
};