/* eslint-disable complexity */
/* eslint-disable object-curly-spacing */
/* eslint-disable no-console */
/* eslint-disable curly */
/* eslint-disable max-len */
/* global BacsUtils, Chart, StandingsScoringRules */
window._bacsUserColors = window._bacsUserColors || {};
window._bacsNextColorIdx = window._bacsNextColorIdx || 0;

window.initializeLeaderDynamicsChart = () => {
  const canvasId = 'leader-dynamics-chart';
  const prefix = 'leader-dynamics';
  const controlsContainer = document.getElementById(`${prefix}-controls`);
  const stepContainer = document.getElementById('leader-dynamics-step-container');
  const stepSelect = document.getElementById('leader-dynamics-step');
  const resetZoomBtn = document.getElementById('leader-dynamics-reset-zoom');
  const hideUpsolvingCheckbox = document.getElementById('leader-dynamics-hide-upsolving');
  const hideUpsolvingContainer = document.getElementById('leader-dynamics-hide-upsolving-container');

  const manualZoomContainer = document.getElementById('leader-dynamics-manual-zoom');
  const zoomStartInput = document.getElementById('leader-zoom-start');
  const zoomEndInput = document.getElementById('leader-zoom-end');

  const currentLocale = BacsUtils.currentLocale();
  const loc = BacsUtils.loc;

  const layout = BacsUtils.createChartLayout(
    canvasId,
    prefix,
    'bi-rocket-takeoff',
    loc('race_empty_title', "The race hasn't started yet"),
    loc(
      'race_empty_desc',
      'Leader dynamics chart will appear here automatically as soon as participants start submitting solutions.',
    ),
  );
  if (!layout) {
    return;
  }

  const rawSubmissions = window.BACS_PAGE_DATA.submissions || [];
  const students = window.BACS_PAGE_DATA.students || [];
  const tasks = window.BACS_PAGE_DATA.tasks || [];
  const contestData = window.BACS_PAGE_DATA.contest;
  const durationMs = (contestData.endtime - contestData.starttime) * 1000;

  const studentStarts = BacsUtils.getStudentStartsMap(students, contestData.starttime);

  let currentMode = 'realtime';

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
    if (currentMode !== 'realtime' || !zoomStartInput || !zoomEndInput) {
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

  const precomputeAllData = (submissions, mode) => {
    const STANDINGS_MODE = {
      IOI: 0,
      ICPC: 1,
      GENERAL: 2,
    };

    let allUserStates = {};
    students.forEach((student) => {
      allUserStates[student.id] = {
        id: student.id,
        name: `${student.firstname} ${student.lastname}`,
        points: 0,
        solved: 0,
        penalty: 0,
        lastImprovement: 0,
      };
    });

    const userTaskStates = {};

    const getTaskState = (userId, taskId) => {
      if (!userTaskStates[userId]) userTaskStates[userId] = {};
      if (!userTaskStates[userId][taskId]) {
        userTaskStates[userId][taskId] = {
          bestPoints: 0,
          attempts: 0,
          accepted: false,
          penaltyTime: 0,
        };
      }
      return userTaskStates[userId][taskId];
    };

    const allEvents = [];
    const eventsByUser = {};
    students.forEach((s) => (eventsByUser[s.id] = []));

    const sortedSubmissions = submissions.slice().sort((a, b) => a.submit_time - b.submit_time);

    sortedSubmissions.forEach((sub) => {
      const user = allUserStates[sub.user_id];
      if (!user) return;

      const newPoints = parseInt(sub.points, 10) || 0;
      const isAccepted = sub.result_id == 13;
      const uStart = studentStarts[sub.user_id] || contestData.starttime;
      const timeElapsedMs = Math.max(0, (sub.submit_time - uStart) * 1000);
      const timeMin = Math.floor(timeElapsedMs / 60000);

      const taskState = getTaskState(sub.user_id, sub.task_id);
      taskState.attempts++;

      const evalResult = StandingsScoringRules.evaluateSubmission(mode, isAccepted, newPoints, taskState, timeMin);

      if (!evalResult.isImprovement) return;

      const wasAccepted = taskState.accepted;

      taskState.bestPoints = evalResult.newPoints;
      taskState.accepted = taskState.accepted || isAccepted;
      taskState.penaltyTime = evalResult.newPenaltyTime;

      const event = {
        time: timeElapsedMs,
        userId: sub.user_id,
        pointsDelta: evalResult.pointsDelta,
        penaltyDelta: evalResult.penaltyDelta,
        newlySolved: isAccepted && !wasAccepted ? 1 : 0,
      };

      allEvents.push(event);
      eventsByUser[sub.user_id].push(event);
    });

    allEvents.sort((a, b) => a.time - b.time);

    const raceComparator = (a, b) => {
      if (mode === STANDINGS_MODE.IOI) {
        if (b.points !== a.points) return b.points - a.points;
        return a.lastImprovement - b.lastImprovement;
      }
      if (mode === STANDINGS_MODE.ICPC) {
        if (b.solved !== a.solved) return b.solved - a.solved;
        if (a.penalty !== b.penalty) return a.penalty - b.penalty;
        if (a.lastImprovement !== b.lastImprovement) return a.lastImprovement - b.lastImprovement;
        return 0;
      }
      if (b.points !== a.points) return b.points - a.points;
      if (b.solved !== a.solved) return b.solved - a.solved;
      if (a.penalty !== b.penalty) return a.penalty - b.penalty;
      if (a.lastImprovement !== b.lastImprovement) return a.lastImprovement - b.lastImprovement;
      return 0;
    };

    let maxContestScore = 0,
      globalMaxSubmitTime = 0;
    let simulationStates = JSON.parse(JSON.stringify(allUserStates));
    const rankSnapshots = [{ time: 0, ranks: {} }];

    let rankedUsers = Object.values(simulationStates).sort(raceComparator);
    rankedUsers.forEach((user, index) => {
      if (user) rankSnapshots[0].ranks[user.id] = index + 1;
    });

    for (const event of allEvents) {
      const state = simulationStates[event.userId];
      if (!state) continue;

      state.points += event.pointsDelta;
      state.solved += event.newlySolved;
      state.penalty += event.penaltyDelta;
      state.lastImprovement = event.time;

      maxContestScore = Math.max(maxContestScore, state.points);
      globalMaxSubmitTime = Math.max(globalMaxSubmitTime, event.time);

      rankedUsers = Object.values(simulationStates).sort(raceComparator);
      const snapshot = { time: event.time, ranks: {} };
      rankedUsers.forEach((user, index) => {
        if (user) snapshot.ranks[user.id] = index + 1;
      });
      rankSnapshots.push(snapshot);
    }

    const finalRankedUsers = Object.values(simulationStates).sort(raceComparator);
    return { finalRankedUsers, rankSnapshots, eventsByUser, maxContestScore, globalMaxSubmitTime };
  };

  const clickHandler = (chart, dsIndex) => {
    BacsUtils.toggleDatasetFocus(chart, dsIndex, `custom-${prefix}-legend`, (p) => (p.isDummy ? 0 : p.isLast ? 6 : 4));
  };

  const generateChartConfig = (mode) => {
    const validTaskIds = new Set(tasks.map((t) => String(t.task_id)));
    let submissions = rawSubmissions.filter((s) => validTaskIds.has(String(s.task_id)));

    if (mode === 'realtime' && hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked) {
      submissions = submissions.filter((s) => s.submit_time <= contestData.endtime);
    }

    const hasPoints = submissions.some((s) => parseInt(s.points, 10) > 0);
    if (!hasPoints) {
      layout.flexContainer.style.display = 'none';
      layout.emptyStateContainer.style.display = 'flex';
      return null;
    } else {
      layout.flexContainer.style.display = 'flex';
      layout.emptyStateContainer.style.display = 'none';
    }

    const currentStandingsMode = window.BACS_PAGE_DATA.contest.mode ?? 1;
    if (!precomputedData) precomputedData = precomputeAllData(submissions, currentStandingsMode);

    const { finalRankedUsers, rankSnapshots, eventsByUser, maxContestScore, globalMaxSubmitTime } = precomputedData;
    const topUsers = finalRankedUsers.slice(0, MAX_PARTICIPANTS_TO_SHOW);
    const NORMALIZED_AGGREGATION_STEP = stepSelect ? parseInt(stepSelect.value, 10) : 5;

    const trueFinalRanks = {};
    finalRankedUsers.forEach((user, index) => {
      trueFinalRanks[user.id] = index + 1;
    });

    let chartMaxXVisual, curveOffset, minZoomRange;

    if (mode === 'normalized') {
      chartMaxXVisual = 100;
      curveOffset = 1.5;
      minZoomRange = 5;
    } else if (mode === 'events') {
      chartMaxXVisual = rankSnapshots.length - 1;
      curveOffset = 0.4;
      minZoomRange = 5;
    } else {
      const finalRealTimeMs = globalMaxSubmitTime > 0 ? globalMaxSubmitTime : Date.now() - contestData.starttime * 1000;
      if (hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked) {
        chartMaxXVisual = durationMs;
      } else {
        chartMaxXVisual = Math.max(durationMs, finalRealTimeMs) * 1.05;
      }
      curveOffset = Math.max(60000, chartMaxXVisual * 0.005);
      minZoomRange = 60000;
    }

    if (mode === 'realtime' && zoomStartInput && zoomEndInput) {
      zoomStartInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000);
      zoomEndInput.value = BacsUtils.toDateTimeLocal(contestData.starttime * 1000 + chartMaxXVisual);
    }

    const datasets = [];
    const datasetsInfo = [];
    layout.legendContainer.innerHTML = `<h6 class="text-muted mb-3" style="font-size:0.85rem; font-weight:600; text-transform:uppercase;">${loc('topparticipants', 'Top Participants')}</h6>`;

    if (mode === 'normalized') {
      const normalizedSnapshots = [];
      for (let p = 0; p <= 100; p += NORMALIZED_AGGREGATION_STEP) {
        const targetPoints = (p / 100) * maxContestScore;
        const progressTimestamps = topUsers.map((user) => {
          let timeAtProgress = Infinity;
          let cumulativePoints = 0;
          for (const event of eventsByUser[user.id]) {
            cumulativePoints += event.pointsDelta;
            if (cumulativePoints >= targetPoints) {
              timeAtProgress = event.time;
              break;
            }
          }
          return { userId: user.id, time: timeAtProgress };
        });
        progressTimestamps.sort((a, b) => a.time - b.time);
        const snapshot = { progress: p, ranks: {}, times: {} };
        progressTimestamps.forEach((data, index) => {
          snapshot.ranks[data.userId] = index + 1;
          snapshot.times[data.userId] = data.time;
        });
        normalizedSnapshots.push(snapshot);
      }

      topUsers.forEach((user) => {
        const rawData = [];
        const maxUserProgress = maxContestScore > 0 ? (user.points / maxContestScore) * 100 : 0;
        normalizedSnapshots.forEach((snap) => {
          if (snap.progress <= maxUserProgress) {
            rawData.push({ x: snap.progress, y: snap.ranks[user.id], realTime: snap.times[user.id] });
          }
        });

        const smoothData = BacsUtils.smoothStepData(rawData, curveOffset);
        if (smoothData.length > 0) {
          datasetsInfo.push({ user, smoothData, trueRank: trueFinalRanks[user.id] });
        }
      });
    } else if (mode === 'events') {
      const maxEventIndex = rankSnapshots.length - 1;
      topUsers.forEach((user) => {
        let smoothData = [];
        let lastRank = rankSnapshots[0].ranks[user.id] || students.length + 1;

        smoothData.push({ x: 0, y: lastRank, realTime: rankSnapshots[0].time });

        for (let eventIndex = 1; eventIndex <= maxEventIndex; eventIndex++) {
          const snap = rankSnapshots[eventIndex];
          const currentRank = snap.ranks[user.id] || students.length + 1;

          if (currentRank !== lastRank) {
            smoothData.push({
              x: eventIndex - curveOffset,
              y: lastRank,
              realTime: snap.time,
              isDummy: true,
            });
            smoothData.push({ x: eventIndex, y: currentRank, realTime: snap.time });
            lastRank = currentRank;
          }
        }

        for (let i = smoothData.length - 1; i >= 0; i--) {
          if (!smoothData[i].isDummy) {
            smoothData[i].isLast = true;
            break;
          }
        }

        if (smoothData.length > 0) {
          datasetsInfo.push({ user, smoothData, trueRank: trueFinalRanks[user.id] });
        }
      });
    } else {
      topUsers.forEach((user) => {
        let filteredData = rankSnapshots.map((snap) => ({
          x: snap.time,
          y: snap.ranks[user.id] || students.length + 1,
          realTime: snap.time,
        }));

        let smoothData = [];
        if (filteredData.length > 0) {
          smoothData.push({ ...filteredData[0] });
          let currentY = filteredData[0].y;

          for (let i = 1; i < filteredData.length; i++) {
            let pt = filteredData[i];

            if (pt.y !== currentY) {
              if (pt.x - smoothData[smoothData.length - 1].x > curveOffset * 1.2) {
                smoothData.push({
                  x: pt.x - curveOffset,
                  y: currentY,
                  realTime: pt.x - curveOffset,
                  isDummy: true,
                });
              }
              smoothData.push({ ...pt });
              currentY = pt.y;
            }
          }
        }

        for (let i = smoothData.length - 1; i >= 0; i--) {
          if (!smoothData[i].isDummy) {
            smoothData[i].isLast = true;
            break;
          }
        }

        if (smoothData.length > 0) {
          datasetsInfo.push({ user, smoothData, trueRank: trueFinalRanks[user.id] });
        }
      });
    }

    datasetsInfo.sort((a, b) => a.trueRank - b.trueRank);
    datasetsInfo.forEach((info) => {
      const tension = mode === 'events' ? 0 : 0.4;
      const dataset = BacsUtils.createLineDataset(info.user, info.smoothData, tension);
      const dsIndex = datasets.length;
      datasets.push(dataset);

      const rankText = info.trueRank > students.length ? '-' : info.trueRank;
      BacsUtils.createLegendItem(layout.legendContainer, dataset.baseColor, info.user.name, `#${rankText}`, () =>
        clickHandler(window.leaderDynamicsChartInstance, dsIndex),
      );
    });

    const TEXT_COLOR = '#6b7280';

    const tooltipConfig = BacsUtils.getTooltipBaseConfig({
      title: (c) => (c && c[0] && c[0].dataset ? c[0].dataset.label : ''),
      label: (c) => {
        if (!c || !c.raw || c.raw.isDummy) {
          return '';
        }
        let timeStr = c.raw.realTime !== undefined ? BacsUtils.formatTime(c.raw.realTime / 1000) : 'N/A';

        let dateStr = '';
        if (c.raw.realTime !== undefined) {
          const uId = c.dataset.userId;
          const uStart = studentStarts[uId] || contestData.starttime;
          dateStr = BacsUtils.formatTooltipDate(uStart, c.raw.realTime, contestData.starttime, currentLocale);
        }

        if (mode === 'normalized') {
          return `${loc('rank', 'Rank:')} #${c.parsed.y} at ${c.parsed.x.toFixed(0)}% (Time: +${timeStr}${dateStr})`;
        }
        if (mode === 'events') {
          return `${loc('rank', 'Rank:')} #${c.parsed.y} (${loc('event', 'Event')} #${Math.round(c.parsed.x)}) • +${timeStr}${dateStr}`;
        }
        return `${loc('rank', 'Rank:')} #${c.parsed.y}  •  +${timeStr}${dateStr}`;
      },
    });

    return {
      type: 'line',
      data: { datasets },
      plugins: [
        BacsUtils.getLineClickPlugin(clickHandler),
        BacsUtils.getTimelinePlugin(durationMs, loc('start', 'Start'), loc('end', 'End')),
      ],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        clip: false,
        layout: { padding: { top: 25, right: 30, left: 20, bottom: 20 } },
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
                if (mode === 'normalized') {
                  return value % NORMALIZED_AGGREGATION_STEP === 0 && value >= 0 && value <= 100 ? `${value}%` : '';
                }
                if (mode === 'events') {
                  return Number.isInteger(value) && value >= 0 ? `#${value}` : '';
                }

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
            title: {
              display: true,
              text: contestData.localizedStrings.rank || 'Rank',
              color: TEXT_COLOR,
              font: { weight: '500' },
            },
            reverse: true,
            min: 1,
            max: topUsers.length > 0 ? topUsers.length + 1 : MAX_PARTICIPANTS_TO_SHOW + 1,
            border: { display: false },
            ticks: { color: TEXT_COLOR, stepSize: 1, precision: 0, padding: 10 },
            grid: { color: 'rgba(0, 0, 0, 0.04)', drawBorder: false },
          },
        },
        animation: { duration: 1000, easing: 'easeOutQuart' },
        transitions: { zoom: { animation: { duration: 0 } } },
        plugins: {
          legend: { display: false },
          tooltip: tooltipConfig,
          zoom: {
            limits: {
              x: { min: 'original', max: 'original', minRange: minZoomRange },
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

  if (stepSelect) {
    stepSelect.addEventListener('change', () => {
      if (currentMode === 'normalized') {
        drawChart();
      }
    });
  }

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

  const togglePanel = (el, showCondition) => {
    if (!el) return;
    if (showCondition) {
      el.classList.remove('d-none');
      el.classList.add('d-flex');
    } else {
      el.classList.remove('d-flex');
      el.classList.add('d-none');
    }
  };

  if (controlsContainer) {
    controlsContainer.addEventListener('click', (e) => {
      const button = e.target.closest('button');
      if (!button || button.classList.contains('active')) {
        return;
      }
      const newMode = button.dataset.mode;
      if (newMode && newMode !== currentMode) {
        currentMode = newMode;
        controlsContainer.querySelectorAll('button').forEach((btn) => {
          const isActive = btn.dataset.mode === newMode;
          btn.classList.toggle('active', isActive);
          btn.classList.toggle('btn-primary', isActive);
          btn.classList.toggle('btn-outline-secondary', !isActive);
        });

        togglePanel(stepContainer, newMode === 'normalized');
        togglePanel(hideUpsolvingContainer, newMode === 'realtime');
        togglePanel(manualZoomContainer, newMode === 'realtime');

        drawChart();
      }
    });
  }

  drawChart();
};
