/* global BacsUtils, Chart */
/* eslint-disable complexity */
/* eslint-disable object-curly-spacing */
/* eslint-disable curly */
/* eslint-disable max-len */
window.renderTaskDynamicsGraph = () => {
  const select = document.getElementById('task-dynamics-select');
  const studentSelect = document.getElementById('student-dynamics-select');
  const intervalSelect = document.getElementById('task-dynamics-step-select');
  const canvas = document.getElementById('task-dynamics-chart');
  const placeholder = document.getElementById('task-dynamics-placeholder');
  const statsContainer = document.getElementById('task-stats-info');
  const detailsContainer = document.getElementById('task-click-details');
  const detailsTableBody = document.querySelector('#task-details-table tbody');
  const detailsTitle = document.getElementById('task-details-title');
  const resetZoomBtn = document.getElementById('task-dynamics-reset-zoom');
  const hideUpsolvingCheckbox = document.getElementById('task-dynamics-hide-upsolving');
  const disableCompressionCheckbox = document.getElementById('task-dynamics-disable-compression');
  const zoomStartInput = document.getElementById('task-zoom-start');
  const zoomEndInput = document.getElementById('task-zoom-end');
  const zoomApplyBtn = document.getElementById('task-zoom-apply');

  if (!select || !studentSelect || !canvas || !placeholder) return;

  if (canvas.parentElement) {
    canvas.parentElement.style.minHeight = '600px';
    canvas.parentElement.style.position = 'relative';
  }

  const contestData = window.BACS_PAGE_DATA.contest;
  const students = window.BACS_PAGE_DATA.students || [];
  const tasks = window.BACS_PAGE_DATA.tasks || [];
  const currentLocale = BacsUtils.currentLocale();
  const loc = BacsUtils.loc;
  const VERDICT_ACCEPTED = 13;

  const fmtTime = (sec) => {
    const d = new Date(sec * 1000);
    const hh = d.getHours().toString().padStart(2, '0');
    const mm = d.getMinutes().toString().padStart(2, '0');
    return {
      d,
      hh,
      mm,
      dateStr: `${d.getDate()} ${d.toLocaleDateString(currentLocale, { month: 'short' }).replace('.', '')}`,
    };
  };

  const fmtDateTimeFull = (sec) => {
    const d = new Date(sec * 1000);
    return `${d.toLocaleDateString(currentLocale, { month: 'short', day: 'numeric' })} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
  };

  const pointInRect = (x, y, rect) =>
    rect && x >= rect.x && x <= rect.x + rect.w && y >= rect.y && y <= rect.y + rect.h;

  const withClip = (ctx, { left, top, right, bottom }, extraTop = 0, fn) => {
    ctx.save();
    ctx.beginPath();
    ctx.rect(left, top - extraTop, right - left, bottom - top + extraTop);
    ctx.clip();
    fn();
    ctx.restore();
  };

  const getSessionColorScheme = (rate) => {
    if (rate >= 0.7)
      return {
        bg: 'rgba(16,185,129,0.12)',
        bgSolid: '#ecfdf5',
        text: '#059669',
        border: '#a7f3d0',
        rateColor: '#059669',
      };
    if (rate < 0.4)
      return {
        bg: 'rgba(239,68,68,0.12)',
        bgSolid: '#fef2f2',
        text: '#dc2626',
        border: '#fecaca',
        rateColor: '#dc2626',
      };
    return {
      bg: 'rgba(252,211,77,0.15)',
      bgSolid: '#fffbeb',
      text: '#b45309',
      border: '#fcd34d',
      rateColor: '#d97706',
    };
  };

  const populateSelect = (sel, items, getValue, getText) => {
    if (sel.options.length <= 1 && items.length > 0) {
      items.forEach((item) => {
        const opt = document.createElement('option');
        opt.value = getValue(item);
        opt.textContent = getText(item);
        sel.appendChild(opt);
      });
    }
  };

  const studentStarts = BacsUtils.getStudentStartsMap(students, contestData.starttime);

  const submissions = (window.BACS_PAGE_DATA.submissions || []).map((sub) => {
    const uStart = studentStarts[sub.user_id] || contestData.starttime;
    const isVirtual = uStart > contestData.starttime;
    return {
      ...sub,
      real_submit_time: sub.submit_time,
      submit_time: contestData.starttime + Math.max(0, sub.submit_time - uStart),
      isVirtual,
    };
  });

  populateSelect(
    select,
    tasks,
    (t) => t.task_id,
    (t) => `${t.task_order}. ${t.name}`,
  );

  const sortedStudents = [...students].sort((a, b) =>
    `${a.firstname} ${a.lastname}`.localeCompare(`${b.firstname} ${b.lastname}`),
  );
  populateSelect(
    studentSelect,
    sortedStudents,
    (s) => s.id,
    (s) => `${s.firstname} ${s.lastname}`,
  );

  const studentMap = Object.fromEntries(students.map((s) => [s.id, `${s.firstname} ${s.lastname}`]));
  const taskMap = Object.fromEntries(tasks.map((t) => [t.task_id, `${t.task_order}. ${t.name}`]));

  if (window.taskDynamicsChartInstance) {
    window.taskDynamicsChartInstance.destroy();
    window.taskDynamicsChartInstance = null;
  }
  if (detailsContainer) detailsContainer.style.display = 'none';
  if (statsContainer) statsContainer.innerHTML = '';

  const globalFirstSolvedTasks = new Set();
  submissions
    .slice()
    .sort((a, b) => a.submit_time - b.submit_time)
    .forEach((sub) => {
      if (sub.result_id == VERDICT_ACCEPTED && !globalFirstSolvedTasks.has(sub.task_id)) {
        globalFirstSolvedTasks.add(sub.task_id);
        sub.isFirstSolvedTask = true;
      }
    });

  const taskId = parseInt(select.value, 10);
  const studentId = parseInt(studentSelect.value, 10);

  let relevantSubmissions = submissions.slice();
  if (hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked)
    relevantSubmissions = relevantSubmissions.filter((s) => s.submit_time <= contestData.endtime);
  if (taskId !== -1 && !isNaN(taskId))
    relevantSubmissions = relevantSubmissions.filter((s) => String(s.task_id) === String(taskId));
  if (studentId !== -1 && !isNaN(studentId))
    relevantSubmissions = relevantSubmissions.filter((s) => String(s.user_id) === String(studentId));

  const existingEmpty = document.getElementById('task-dynamics-empty-state');
  if (existingEmpty) existingEmpty.style.display = 'none';

  if (relevantSubmissions.length === 0) {
    canvas.classList.add('d-none');
    placeholder.classList.add('d-none');

    const isAllTasks = taskId === -1;
    const isAllStudents = studentId === -1;

    const emptyStates = {
      '1_1': ['notasksyet', 'notasksyetdesc', {}],
      '0_1': ['tasknotsolvedyet', 'tasknotsolvedyetdesc', { '{task}': `<b>«${taskMap[taskId] || taskId}»</b>` }],
      '1_0': ['usernotsubmittedyet', 'usernotsubmittedyetdesc', { '{user}': `<b>${studentMap[studentId] || ''}</b>` }],
      '0_0': [
        'nosubmits',
        'nosubmitsdesc',
        { '{user}': `<b>${studentMap[studentId] || ''}</b>`, '{task}': `<b>«${taskMap[taskId] || ''}»</b>` },
      ],
    };

    const key = `${isAllTasks ? 1 : 0}_${isAllStudents ? 1 : 0}`;
    const [titleKey, descKey, replacements] = emptyStates[key];
    const title = loc(titleKey, titleKey);
    let desc = loc(descKey, descKey);
    Object.entries(replacements).forEach(([token, val]) => {
      desc = desc.replace(token, val);
    });

    BacsUtils.createEmptyState(
      'task-dynamics-empty-state',
      placeholder.parentNode,
      placeholder.nextSibling,
      'bi-inbox',
      title,
      desc,
    ).style.display = 'flex';
    return;
  }

  canvas.classList.remove('d-none');
  placeholder.classList.add('d-none');

  const totalSubmits = relevantSubmissions.length;
  const acceptedSubmits = relevantSubmissions.filter((s) => s.result_id == VERDICT_ACCEPTED).length;
  const successRate = totalSubmits > 0 ? ((acceptedSubmits / totalSubmits) * 100).toFixed(1) : 0;

  statsContainer.innerHTML = `
    <div class="d-inline-flex flex-wrap justify-content-center gap-2" style="font-family: 'Inter', sans-serif;">
      <div class="border rounded px-3 py-1 bg-light text-dark fw-bold shadow-sm" style="font-size: 0.9rem;">
        ${loc('statstotal', 'TOTAL')}: <span class="text-dark">${totalSubmits}</span>
      </div>
      <div class="border rounded px-3 py-1 fw-bold shadow-sm" style="background: #ecfdf5; border-color: #a7f3d0 !important; font-size: 0.9rem; color: #065f46;">
        ${loc('statsok', 'OK')}: <span style="color: #047857;">${acceptedSubmits}</span>
      </div>
      <div class="border rounded px-3 py-1 fw-bold shadow-sm" style="background: #eff6ff; border-color: #bfdbfe !important; font-size: 0.9rem; color: #1d4ed8;">
        ${loc('statssuccess', 'SUCCESS RATE')}: <span style="color: #2563eb;">${successRate}%</span>
      </div>
    </div>
  `;

  relevantSubmissions.sort((a, b) => a.submit_time - b.submit_time);
  const minTimeSec = relevantSubmissions[0].submit_time;
  const maxTimeSec = relevantSubmissions[relevantSubmissions.length - 1].submit_time;
  const timelineEndSec = Math.max(maxTimeSec, contestData.endtime);
  const span = timelineEndSec - minTimeSec;

  const forcedStep = intervalSelect ? parseInt(intervalSelect.value, 10) : 0;
  let stepSeconds;
  if (forcedStep > 0) {
    stepSeconds = forcedStep;
  } else {
    const steps = [
      [4 * 3600, 15 * 60],
      [24 * 3600, 3600],
      [3 * 86400, 4 * 3600],
      [7 * 86400, 12 * 3600],
      [30 * 86400, 86400],
      [90 * 86400, 3 * 86400],
    ];
    stepSeconds = span === 0 ? 3600 : (steps.find(([limit]) => span <= limit) || [0, 7 * 86400])[1];
  }

  const useCompression = !(disableCompressionCheckbox && disableCompressionCheckbox.checked);

  const subsByInterval = new Map();
  relevantSubmissions.forEach((sub) => {
    const idx = Math.floor(sub.submit_time / stepSeconds);
    if (!subsByInterval.has(idx)) subsByInterval.set(idx, []);
    subsByInterval.get(idx).push(sub);
  });

  const sortedIndices = Array.from(subsByInterval.keys()).sort((a, b) => a - b);
  const finalBuckets = [];

  const makeGapBuckets = (gapStart, gapEnd) => {
    const skipDur = gapEnd - gapStart;
    const gapId = 'gap_' + gapStart;
    const slots = skipDur < 86400 ? 1 : skipDur < 3 * 86400 ? 2 : skipDur < 7 * 86400 ? 3 : 4;
    return Array.from({ length: slots }, () => ({
      isGap: true,
      gapId,
      startSec: gapStart,
      endSec: gapEnd,
      skipDur,
      subs: [],
    }));
  };

  for (let i = 0; i < sortedIndices.length; i++) {
    const currIdx = sortedIndices[i];
    finalBuckets.push({
      isGap: false,
      startSec: currIdx * stepSeconds,
      endSec: (currIdx + 1) * stepSeconds,
      subs: subsByInterval.get(currIdx),
    });

    if (i < sortedIndices.length - 1) {
      const nextIdx = sortedIndices[i + 1];
      const diff = nextIdx - currIdx - 1;
      if (diff > 0) {
        if (useCompression) {
          if (diff < 2) {
            for (let j = 1; j <= diff; j++)
              finalBuckets.push({
                isGap: false,
                startSec: (currIdx + j) * stepSeconds,
                endSec: (currIdx + j + 1) * stepSeconds,
                subs: [],
              });
          } else {
            finalBuckets.push(...makeGapBuckets((currIdx + 1) * stepSeconds, nextIdx * stepSeconds));
          }
        } else {
          const safeDiff = Math.min(diff, 5000);
          for (let j = 1; j <= safeDiff; j++)
            finalBuckets.push({
              isGap: false,
              startSec: (currIdx + j) * stepSeconds,
              endSec: (currIdx + j + 1) * stepSeconds,
              subs: [],
            });
        }
      }
    }
  }

  const lastBucketEnd = finalBuckets.length > 0 ? finalBuckets[finalBuckets.length - 1].endSec : 0;
  const showUpsolving = !hideUpsolvingCheckbox || !hideUpsolvingCheckbox.checked;
  if (lastBucketEnd < contestData.endtime && showUpsolving) {
    if (useCompression) {
      finalBuckets.push(...makeGapBuckets(lastBucketEnd, contestData.endtime));
    } else {
      const safeTrail = Math.min(Math.floor((contestData.endtime - lastBucketEnd) / stepSeconds), 5000);
      for (let j = 0; j <= safeTrail; j++)
        finalBuckets.push({
          isGap: false,
          startSec: lastBucketEnd + j * stepSeconds,
          endSec: lastBucketEnd + (j + 1) * stepSeconds,
          subs: [],
        });
    }
  }

  finalBuckets.forEach((b) => {
    b.isEmpty = b.subs.length === 0;
    if (!b.isEmpty && !b.isGap) {
      const firstSolvedTasks = b.subs.filter((s) => s.isFirstSolvedTask);
      if (firstSolvedTasks.length > 0) b.firstSolvedTasks = firstSolvedTasks;
    }
  });

  // Session

  const MIN_SUBS_FOR_SESSION = 8;
  const MIN_USERS_FOR_SESSION = 2;

  const activeSessions = [];
  let currentSession = null;
  for (let i = 0; i < finalBuckets.length; i++) {
    const b = finalBuckets[i];
    if (!b.isEmpty && !b.isGap) {
      if (!currentSession) currentSession = { startIdx: i, endIdx: i };
      currentSession.endIdx = i;
    } else {
      if (currentSession && i + 1 < finalBuckets.length && !finalBuckets[i + 1].isEmpty && !b.isGap) {
        currentSession.endIdx = i;
      } else {
        if (currentSession) {
          activeSessions.push(currentSession);
          currentSession = null;
        }
      }
    }
  }
  if (currentSession) activeSessions.push(currentSession);

  const validSessions = activeSessions.reduce((acc, session) => {
    let okCount = 0;
    const users = new Set();
    const taskCounts = {};
    const allSubs = [];
    const failCountsByUser = {};
    let maxFailsByUser = 0;

    for (let i = session.startIdx; i <= session.endIdx; i++) {
      const b = finalBuckets[i];
      if (b.isGap) continue;
      b.subs.forEach((s) => {
        allSubs.push(s);
        users.add(s.user_id);
        if (s.result_id == VERDICT_ACCEPTED) {
          okCount++;
        } else {
          failCountsByUser[s.user_id] = (failCountsByUser[s.user_id] || 0) + 1;
          if (failCountsByUser[s.user_id] > maxFailsByUser) maxFailsByUser = failCountsByUser[s.user_id];
        }
        taskCounts[s.task_id] = (taskCounts[s.task_id] || 0) + 1;
      });
    }

    session.totalSubs = allSubs.length;
    session.okCount = okCount;
    session.successRate = session.totalSubs > 0 ? okCount / session.totalSubs : 0;
    session.uniqueUsers = users.size;
    session.allSubs = allSubs;
    session.hasSpammer =
      session.totalSubs - okCount > 0 && maxFailsByUser / (session.totalSubs - okCount) > 0.5 && maxFailsByUser >= 5;
    session.topTasks = Object.entries(taskCounts)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 3);

    if (session.totalSubs >= MIN_SUBS_FOR_SESSION && session.uniqueUsers >= MIN_USERS_FOR_SESSION) acc.push(session);
    return acc;
  }, []);

  const labels = [];
  const okData = [];
  const notOkData = [];
  const bucketColors = [];

  finalBuckets.forEach((b) => {
    let okCount = 0,
      failCount = 0;
    b.subs.forEach((s) => {
      if (s.result_id == VERDICT_ACCEPTED) okCount++;
      else failCount++;
    });
    okData.push(okCount);
    notOkData.push(failCount);

    if (b.isGap) {
      labels.push('');
      bucketColors.push('transparent');
    } else {
      const { dateStr, hh, mm } = fmtTime(b.startSec);
      labels.push(stepSeconds < 86400 ? `${dateStr}, ${hh}:${mm}` : dateStr);
      bucketColors.push(BacsUtils.getContestProgress(b.startSec, contestData.starttime, contestData.endtime).color);
    }
  });

  if (canvas._bacsAbortController) canvas._bacsAbortController.abort();
  canvas._bacsAbortController = new AbortController();
  const signal = canvas._bacsAbortController.signal;

  BacsUtils.initDragZoomTooltip(
    'task-dynamics-chart',
    'task-dynamics',
    (val) => {
      const idx = Math.max(0, Math.min(Math.round(val), finalBuckets.length - 1));
      return BacsUtils.formatShortDate(finalBuckets[idx].startSec * 1000, currentLocale);
    },
    () => window.taskDynamicsChartInstance,
    signal,
  );

  let sessionTooltip = document.getElementById('task-session-tooltip');
  if (!sessionTooltip) {
    sessionTooltip = document.createElement('div');
    sessionTooltip.id = 'task-session-tooltip';
    sessionTooltip.style.cssText = `position: absolute; display: none; background: rgba(255,255,255,0.98); border: 1px solid #e5e7eb; padding: 14px; border-radius: 8px; font-size: 13px; font-family: 'Inter', sans-serif; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 105; pointer-events: none; width: 240px; transition: opacity 0.1s;`;
    canvas.parentElement.appendChild(sessionTooltip);
  }

  const getSessionAtEvent = (e, chartInstance) => {
    if (!chartInstance) return null;
    const { top, bottom } = chartInstance.chartArea;
    const x = e.x !== undefined ? e.x : e.offsetX;
    const y = e.y !== undefined ? e.y : e.offsetY;
    return (
      validSessions.find(
        (s) =>
          pointInRect(x, y, { x: s._hitbox?.x, y: top, w: s._hitbox?.w, h: bottom - top }) ||
          pointInRect(x, y, s._labelBox),
      ) || null
    );
  };

  canvas.addEventListener(
    'mousemove',
    (e) => {
      if (e.buttons === 1) {
        sessionTooltip.style.display = 'none';
        return;
      }
      const chart = window.taskDynamicsChartInstance;
      if (!chart) return;

      const activeElements = chart.getElementsAtEventForMode(e, 'index', { intersect: true }, false);
      if (
        activeElements.length > 0 &&
        finalBuckets[activeElements[0].index] &&
        !finalBuckets[activeElements[0].index].isGap
      ) {
        sessionTooltip.style.display = 'none';
        canvas.style.cursor = 'pointer';
        return;
      }

      const hoveredSession = getSessionAtEvent(e, chart);
      if (hoveredSession) {
        canvas.style.cursor = 'pointer';
        const { rateColor } = getSessionColorScheme(hoveredSession.successRate);
        const tStart = fmtDateTimeFull(finalBuckets[hoveredSession.startIdx].startSec);
        const tEnd = fmtDateTimeFull(finalBuckets[hoveredSession.endIdx].endSec);
        const okPercent = (hoveredSession.successRate * 100).toFixed(0);

        let html = `<div style="font-weight:700;margin-bottom:6px;color:#111827;font-size:14px;"><i class="bi bi-people-fill text-primary me-1"></i> ${loc('classsession', 'Class Session')}</div>`;
        html += `<div style="color:#6b7280;font-size:11px;margin-bottom:10px;font-family:monospace;">${tStart} — ${tEnd}</div>`;
        html += `<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>${loc('usersactive', 'Users active:')}</span><span style="font-weight:600;">${hoveredSession.uniqueUsers}</span></div>`;
        html += `<div style="display:flex;justify-content:space-between;margin-bottom:10px;"><span>${loc('totalsubmits', 'Total Submits:')}</span><div><span style="font-weight:600;">${hoveredSession.totalSubs}</span><span style="color:${rateColor};font-size:11px;margin-left:4px;">(${okPercent}% OK)</span></div></div>`;

        if (hoveredSession.hasSpammer)
          html += `<div style="color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;border-radius:4px;padding:4px 8px;font-size:11px;margin-bottom:10px;text-align:center;"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${loc('spammeranomaly', 'Spammer anomaly detected')}</div>`;

        html += `<div style="font-size:12px;border-top:1px solid #f3f4f6;padding-top:8px;margin-top:8px;"><div style="font-weight:600;margin-bottom:6px;color:#4b5563;font-size:11px;text-transform:uppercase;">${loc('toptasks', 'Top Tasks')}</div>`;
        hoveredSession.topTasks.forEach(([tid, count]) => {
          let taskName = taskMap[tid] || `Task ${tid}`;
          if (taskName.length > 22) taskName = taskName.substring(0, 20) + '...';
          html += `<div style="display:flex;justify-content:space-between;margin-bottom:3px;color:#374151;"><span title="${taskMap[tid]}">${taskName}</span><span style="font-weight:600;background:#f3f4f6;padding:0 4px;border-radius:3px;">${count}</span></div>`;
        });
        html += `</div><div style="margin-top:10px;font-size:11px;color:#3b82f6;text-align:center;font-weight:500;">${loc('clicktoviewsubs', 'Click to view all submissions')}</div>`;

        sessionTooltip.innerHTML = html;
        let ttX = e.clientX - canvas.getBoundingClientRect().left + 15;
        const ttY = e.clientY - canvas.getBoundingClientRect().top + 15;
        if (ttX + 240 > canvas.getBoundingClientRect().width) ttX = ttX - 255;
        sessionTooltip.style.left = ttX + 'px';
        sessionTooltip.style.top = ttY + 'px';
        sessionTooltip.style.display = 'block';
      } else {
        canvas.style.cursor = 'default';
        sessionTooltip.style.display = 'none';
      }
    },
    { signal },
  );

  canvas.addEventListener(
    'mouseout',
    () => {
      sessionTooltip.style.display = 'none';
    },
    { signal },
  );

  const updateManualZoomInputs = ({ chart }) => {
    if (!zoomStartInput || !zoomEndInput || finalBuckets.length === 0) return;
    const minIdx = Math.max(0, Math.round(chart.scales.x.min));
    const maxIdx = Math.min(finalBuckets.length - 1, Math.round(chart.scales.x.max));
    if (finalBuckets[minIdx])
      zoomStartInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[minIdx].startSec * 1000);
    if (finalBuckets[maxIdx]) zoomEndInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[maxIdx].endSec * 1000);
    if (resetZoomBtn) resetZoomBtn.classList.remove('d-none');
  };

  if (zoomStartInput && zoomEndInput && finalBuckets.length > 0) {
    zoomStartInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[0].startSec * 1000);
    zoomEndInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[finalBuckets.length - 1].endSec * 1000);
  }

  const TEXT_COLOR = '#6b7280';

  const tooltipConfig = BacsUtils.getTooltipBaseConfig({
    title: function (context) {
      if (!context[0]) return '';
      const b = finalBuckets[context[0].dataIndex];
      const opts = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
      const minStr = new Date(b.startSec * 1000).toLocaleString(currentLocale, opts);
      const maxStr = new Date(b.endSec * 1000).toLocaleString(currentLocale, opts);
      const rangeStr = b.startSec === b.endSec ? minStr : `${minStr} —\n${maxStr}`;
      return [
        `📅 ${loc('period', 'Period:')}`,
        rangeStr,
        ``,
        `⏱️ ${loc('elapsedfromstart', 'Elapsed from start: +')} ${BacsUtils.formatTime(b.startSec - contestData.starttime)}`,
      ];
    },
    label: (context) => `${context.dataset.label}: ${context.raw}`,
    afterBody: (context) => {
      const b = finalBuckets[context[0].dataIndex];
      if (!b || b.isGap || !b.firstSolvedTasks?.length) return [];
      return [
        `\n🏆 ${loc('firstaccepted', 'First Accepted:')}`,
        ...b.firstSolvedTasks.map((sub) => `  • ${taskMap[sub.task_id] || loc('task', 'Task') + ' ' + sub.task_id}`),
      ];
    },
  });

  tooltipConfig.mode = 'index';
  tooltipConfig.intersect = true;
  tooltipConfig.filter = (item) => !finalBuckets[item.dataIndex].isGap;

  // Plugins

  const backgroundZonesPlugin = {
    id: 'backgroundZones',
    beforeDraw(chart) {
      const ctx = chart.ctx;
      const { top, bottom, left, right } = chart.chartArea;

      let boundaryIdx = finalBuckets.findIndex((b) => b.startSec >= contestData.endtime);
      if (boundaryIdx === -1) boundaryIdx = finalBuckets.findIndex((b) => b.endSec >= contestData.endtime);
      if (boundaryIdx === -1) return;

      const meta = chart.getDatasetMeta(0);
      if (!meta?.data[boundaryIdx]) return;

      const upsolvingPixel = meta.data[boundaryIdx].x - meta.data[boundaryIdx].width / 2;

      ctx.save();
      ctx.fillStyle = 'rgba(248,250,252,0.8)';
      ctx.fillRect(upsolvingPixel, top, right - upsolvingPixel, bottom - top);

      ctx.beginPath();
      ctx.strokeStyle = 'rgba(239,68,68,0.5)';
      ctx.lineWidth = 2;
      ctx.setLineDash([5, 5]);
      ctx.moveTo(upsolvingPixel, top);
      ctx.lineTo(upsolvingPixel, bottom);
      ctx.stroke();

      ctx.fillStyle = 'rgba(239,68,68,0.8)';
      ctx.font = "600 10px 'Inter', sans-serif";
      ctx.textAlign = 'left';
      ctx.textBaseline = 'bottom';
      ctx.fillText(loc('endofcontest', 'End of Contest'), upsolvingPixel + 4, top + 12);
      ctx.restore();
    },
  };

  const cleanGapPlugin = {
    id: 'cleanGap',
    afterDatasetsDraw(chart) {
      const ctx = chart.ctx;
      const yAxis = chart.scales.y;

      const gapGroups = {};
      chart.data.labels.forEach((_, i) => {
        if (finalBuckets[i]?.isGap) {
          const gid = finalBuckets[i].gapId;
          if (!gapGroups[gid]) gapGroups[gid] = [];
          gapGroups[gid].push(i);
        }
      });

      const meta = chart.getDatasetMeta(0);

      Object.values(gapGroups).forEach((indices) => {
        const firstBar = meta.data[indices[0]];
        const lastBar = meta.data[indices[indices.length - 1]];
        if (!firstBar || !lastBar) return;

        const left = firstBar.x - firstBar.width / 2;
        const right = lastBar.x + lastBar.width / 2;
        const width = right - left;
        const top = yAxis.top;
        const height = yAxis.bottom - yAxis.top;

        const b = finalBuckets[indices[0]];
        const { skipDur } = b;

        withClip(ctx, { left, top, right: left + width, bottom: yAxis.bottom }, 0, () => {
          ctx.strokeStyle = 'rgba(209,213,219,0.8)';
          ctx.lineWidth = 1;
          ctx.beginPath();
          ctx.moveTo(left, top);
          ctx.lineTo(left, top + height);
          ctx.moveTo(right, top);
          ctx.lineTo(right, top + height);
          ctx.stroke();

          const d = Math.floor(skipDur / 86400);
          const h = Math.floor((skipDur % 86400) / 3600);
          const durStr =
            `${d > 0 ? d + ' ' + loc('days_short', 'd') + ' ' : ''}${h > 0 || d === 0 ? h + ' ' + loc('hours_short', 'h') : ''}`.trim();
          const fmt = (sec) => {
            const { dateStr } = fmtTime(sec);
            return dateStr;
          };
          const textL1 = `${fmt(b.startSec)} — ${fmt(b.endSec)}`;
          const textL2 = `(${durStr})`;
          const textFull = `${textL1} ${textL2}`;

          ctx.translate(left + width / 2, top + height / 2);
          ctx.font = "500 11px 'Inter', sans-serif";

          const wFull = ctx.measureText(textFull).width;
          const maxW2 = Math.max(ctx.measureText(textL1).width, ctx.measureText(textL2).width);

          const configs = [
            [wFull + 20, 0, [textFull], wFull + 16, 20],
            [maxW2 + 16, 0, [textL1, textL2], maxW2 + 16, 34],
            [40, -Math.PI / 4, [textL1, textL2], maxW2 + 16, 34],
          ];
          const cfg = configs.find(([minW]) => width >= minW);
          if (!cfg || height <= cfg[4] + 10) return;

          const [, angle, lines, boxW, boxH] = cfg;
          ctx.rotate(angle);
          ctx.beginPath();
          ctx.roundRect(-boxW / 2, -boxH / 2, boxW, boxH, 4);
          ctx.fillStyle = 'rgba(255,255,255,0.7)';
          ctx.fill();
          ctx.fillStyle = '#9ca3af';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          if (lines.length === 1) {
            ctx.fillText(lines[0], 0, 1);
          } else {
            ctx.fillText(lines[0], 0, -6);
            ctx.fillText(lines[1], 0, 8);
          }
        });
      });
    },
  };

  const sessionAnalyticsPlugin = {
    id: 'sessionAnalytics',
    beforeDatasetsDraw(chart) {
      const ctx = chart.ctx;
      const meta = chart.getDatasetMeta(0);
      const area = chart.chartArea;

      withClip(ctx, area, 0, () => {
        validSessions.forEach((s) => {
          const barStart = meta.data[s.startIdx];
          const barEnd = meta.data[s.endIdx];
          if (!barStart || !barEnd) return;

          const startX = barStart.x - barStart.width / 2;
          const endX = barEnd.x + barEnd.width / 2;
          const width = endX - startX;

          ctx.fillStyle = getSessionColorScheme(s.successRate).bg;
          ctx.beginPath();
          ctx.roundRect(startX, area.top, width, area.bottom - area.top, 4);
          ctx.fill();
          s._hitbox = { x: startX, w: width };
        });
      });
    },

    afterDatasetsDraw(chart) {
      const ctx = chart.ctx;
      const meta0 = chart.getDatasetMeta(0);
      const meta1 = chart.getDatasetMeta(1);
      const { top, left, right, bottom } = chart.chartArea;

      withClip(ctx, { left, top, right, bottom }, 25, () => {
        finalBuckets.forEach((b, i) => {
          if (!b.firstSolvedTasks?.length) return;
          const bar0 = meta0.data[i];
          const bar1 = meta1.data[i];
          if (!bar0 && !bar1) return;

          const activeBar = bar0 || bar1;
          const x = activeBar.x;
          const barWidth = activeBar.width;

          if (x < left || x > right) return;

          let y = Math.min(bar0 ? bar0.y : Infinity, bar1 ? bar1.y : Infinity);
          if (y === Infinity) y = bottom;

          const starSize = Math.max(11, Math.min(32, barWidth * 0.6));
          const textSize = Math.max(9, Math.min(16, barWidth * 0.3));

          ctx.fillStyle = '#eab308';
          ctx.font = `${starSize}px Arial`;
          ctx.textAlign = 'center';
          ctx.textBaseline = 'bottom';
          ctx.fillText('★', x, y - 2);

          if (b.firstSolvedTasks.length > 1) {
            ctx.font = `bold ${textSize}px 'Inter', sans-serif`;
            ctx.textAlign = 'left';
            ctx.fillText('+' + (b.firstSolvedTasks.length - 1), x + starSize / 2 + 2, y - 2);
          }
        });

        validSessions.forEach((s) => {
          const barStart = meta0.data[s.startIdx];
          const barEnd = meta0.data[s.endIdx];
          if (!barStart || !barEnd) return;

          const startX = barStart.x - barStart.width / 2;
          const endX = barEnd.x + barEnd.width / 2;
          const width = endX - startX;
          const midX = startX + width / 2;

          let localPeakY = bottom;
          for (let i = s.startIdx; i <= s.endIdx; i++) {
            const b0 = meta0.data[i],
              b1 = meta1.data[i];
            if (b0 && b0.y < localPeakY) localPeakY = b0.y;
            if (b1 && b1.y < localPeakY) localPeakY = b1.y;
          }

          const text = `${s.totalSubs} ${loc('subs', 'subs')} / ${s.uniqueUsers} ${loc('usr', 'usr')}`;
          ctx.font = "600 10px 'Inter', sans-serif";
          let boxW = ctx.measureText(text).width + 16 + (s.hasSpammer ? 16 : 0);
          const boxH = 20;

          let labelY = Math.max(localPeakY - 14, top + 12);
          let labelX = Math.min(Math.max(midX, left + boxW / 2 + 2), right - boxW / 2 - 2);

          const { bgSolid, text: textColor, border } = getSessionColorScheme(s.successRate);

          ctx.fillStyle = bgSolid;
          ctx.beginPath();
          ctx.roundRect(labelX - boxW / 2, labelY - boxH / 2, boxW, boxH, 4);
          ctx.fill();
          ctx.lineWidth = 1;
          ctx.strokeStyle = border;
          ctx.stroke();

          ctx.fillStyle = s.hasSpammer ? '#dc2626' : textColor;
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText((s.hasSpammer ? '⚠️ ' : '') + text, labelX, labelY + 1);

          s._labelBox = { x: labelX - boxW / 2, y: labelY - boxH / 2, w: boxW, h: boxH };
        });
      });
    },
  };

  const chartCtx = canvas.getContext('2d');
  window.taskDynamicsChartInstance = new Chart(chartCtx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: loc('verdict_ok', 'Accepted'),
          data: okData,
          backgroundColor: '#10b981',
          borderColor: '#059669',
          borderWidth: 1,
          borderRadius: 4,
          categoryPercentage: 0.85,
          barPercentage: 1.0,
        },
        {
          label: loc('verdict_not_ok', 'Failed'),
          data: notOkData,
          backgroundColor: '#f43f5e',
          borderColor: '#e11d48',
          borderWidth: 1,
          borderRadius: 4,
          categoryPercentage: 0.85,
          barPercentage: 1.0,
        },
      ],
    },
    plugins: [backgroundZonesPlugin, cleanGapPlugin, sessionAnalyticsPlugin],
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { top: 20, right: 20, left: 10, bottom: 10 } },
      scales: {
        x: {
          stacked: true,
          border: { display: false },
          grid: {
            color: (context) => {
              const chart = context.chart;
              if (!chart) return 'transparent';
              return chart.scales.x.max - chart.scales.x.min < 50 ? 'rgba(0,0,0,0.03)' : 'transparent';
            },
            drawBorder: false,
          },
          ticks: {
            maxRotation: 0,
            autoSkipPadding: 35,
            font: { size: 11, family: "'Inter', monospace", weight: '500' },
            color: (c) => [TEXT_COLOR, bucketColors[c.index] || TEXT_COLOR],
          },
        },
        y: {
          stacked: true,
          beginAtZero: true,
          border: { display: false },
          title: { display: true, text: loc('submits', 'Submissions'), color: TEXT_COLOR, font: { weight: '500' } },
          ticks: { color: TEXT_COLOR, precision: 0, padding: 10 },
          grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
        },
      },
      animation: { duration: 1000, easing: 'easeOutQuart' },
      transitions: { zoom: { animation: { duration: 0 } } },
      plugins: {
        legend: { position: 'top', labels: { color: '#374151', boxWidth: 12, usePointStyle: true } },
        tooltip: tooltipConfig,
        zoom: {
          limits: { x: { min: 'original', max: 'original', minRange: 2 } },
          pan: { enabled: true, mode: 'x', onPanComplete: updateManualZoomInputs },
          zoom: {
            wheel: { enabled: true, speed: 0.15 },
            pinch: { enabled: true },
            drag: { enabled: true, backgroundColor: 'rgba(54,162,235,0.2)', threshold: 20 },
            mode: 'x',
            onZoomComplete: updateManualZoomInputs,
          },
        },
      },
      onClick: (e) => {
        const chart = window.taskDynamicsChartInstance;
        if (!chart) return;

        const populateTable = (subsList, titleHtml) => {
          subsList.sort((a, b) => b.submit_time - a.submit_time);
          if (detailsTitle) detailsTitle.innerHTML = titleHtml;
          if (detailsTableBody) {
            const makeVBadge = (sub) =>
              sub.isVirtual
                ? ` <span class="badge bg-secondary ms-1" style="font-size:0.65rem;">${loc('virtual', 'Virtual')}</span>`
                : '';
            const makeStarIcon = (sub) =>
              sub.isFirstSolvedTask
                ? `<i class="bi bi-star-fill text-warning me-1" title="${loc('firstaccepted', 'First Accepted in contest!')}"></i>`
                : '';
            const makeStatusBadge = (isOk) =>
              isOk
                ? `<span style="background:#d1fae5;color:#065f46;padding:2px 6px;border-radius:4px;font-size:0.8rem;font-weight:bold;">${loc('verdict_ok', 'OK')}</span>`
                : `<span style="background:#ffe4e6;color:#e11d48;padding:2px 6px;border-radius:4px;font-size:0.8rem;font-weight:bold;">${loc('fail', 'FAIL')}</span>`;
            const makeProgressBar = (sub) => {
              const prog = BacsUtils.getContestProgress(sub.submit_time, contestData.starttime, contestData.endtime);
              return prog.isUpsolving
                ? `<div style="font-size:0.75rem;color:#6b7280;margin-top:4px;">[${loc('upsolving_label', 'Upsolving')}]</div>`
                : `<div style="width:100%;height:4px;background:#e5e7eb;border-radius:2px;margin-top:6px;overflow:hidden;"><div style="width:${prog.percent}%;height:100%;background:${prog.color};"></div></div><div style="font-size:0.7rem;color:${prog.color};text-align:right;line-height:1;">${prog.percent.toFixed(0)}%</div>`;
            };

            detailsTableBody.innerHTML = subsList
              .map((sub) => {
                const isOk = sub.result_id == VERDICT_ACCEPTED;
                const dt = new Date(sub.real_submit_time * 1000);
                const realDateStr = `${dt.toLocaleDateString(currentLocale, { month: 'short', day: 'numeric' })}, ${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}`;
                return `<tr>
                <td class="align-middle" style="width:180px;">
                  <div class="fw-bold" style="font-size:0.85rem;color:#111827;">${realDateStr}${makeVBadge(sub)}</div>
                  <div style="font-family:monospace;font-size:0.8rem;color:#6b7280;margin-top:2px;">+ ${BacsUtils.formatTime(sub.submit_time - contestData.starttime)}</div>
                  ${makeProgressBar(sub)}
                </td>
                <td class="align-middle fw-500">${studentMap[sub.user_id] || `User ${sub.user_id}`}</td>
                <td class="align-middle">${makeStarIcon(sub)}${taskMap[sub.task_id] || sub.task_id}</td>
                <td class="align-middle">${makeStatusBadge(isOk)}</td>
                <td class="align-middle fw-bold">${sub.points !== null ? sub.points : '-'}</td>
              </tr>`;
              })
              .join('');
          }
          if (detailsContainer) {
            detailsContainer.style.display = 'block';
            detailsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        };

        const activeElements = chart.getElementsAtEventForMode(e, 'index', { intersect: true }, false);
        if (activeElements.length > 0) {
          const b = finalBuckets[activeElements[0].index];
          if (b && !b.isGap && b.subs.length > 0) {
            populateTable(
              b.subs,
              `<i class="bi bi-list-check me-2 text-primary"></i> ${loc('submissionsdetails', 'Submissions details')} <span class="badge bg-secondary ms-2">${b.subs.length} ${loc('total', 'total')}</span>`,
            );
            return;
          }
        }

        const clickedSession = getSessionAtEvent(e, chart);
        if (clickedSession) {
          populateTable(
            clickedSession.allSubs,
            `<i class="bi bi-people-fill me-2 text-primary"></i> ${loc('classsessiondetails', 'Class Session Details')} <span class="badge bg-secondary ms-2">${clickedSession.totalSubs} ${loc('total', 'total')}</span>`,
          );
        } else {
          if (detailsContainer) detailsContainer.style.display = 'none';
        }
      },
    },
  });

  if (zoomApplyBtn) {
    zoomApplyBtn.addEventListener(
      'click',
      () => {
        const chart = window.taskDynamicsChartInstance;
        if (!chart) return;
        const t1 = new Date(zoomStartInput.value).getTime() / 1000;
        const t2 = new Date(zoomEndInput.value).getTime() / 1000;
        if (isNaN(t1) || isNaN(t2)) return;

        const startSec = Math.min(t1, t2);
        const endSec = Math.max(t1, t2);
        let idx1 = 0,
          idx2 = finalBuckets.length - 1;
        for (let i = 0; i < finalBuckets.length; i++)
          if (finalBuckets[i].endSec >= startSec) {
            idx1 = i;
            break;
          }
        for (let i = finalBuckets.length - 1; i >= 0; i--)
          if (finalBuckets[i].startSec <= endSec) {
            idx2 = i;
            break;
          }

        chart.zoomScale('x', { min: idx1, max: idx2 }, 'default');
        if (resetZoomBtn) resetZoomBtn.classList.remove('d-none');
      },
      { signal },
    );
  }

  if (resetZoomBtn) {
    resetZoomBtn.addEventListener(
      'click',
      function () {
        window.taskDynamicsChartInstance?.resetZoom();
        this.classList.add('d-none');
      },
      { signal },
    );
  }

  [disableCompressionCheckbox, hideUpsolvingCheckbox, intervalSelect].forEach((el) => {
    if (el) el.addEventListener('change', () => window.renderTaskDynamicsGraph?.(), { signal });
  });
};
