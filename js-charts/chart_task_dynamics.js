/* global BacsUtils, Chart */

window.BacsUtils = window.BacsUtils || {};
window.BacsUtils.toDateTimeLocal = window.BacsUtils.toDateTimeLocal || function(ms) {
  const d = new Date(ms);
  const pad = (n) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

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

  const zoomStartInput = document.getElementById('task-zoom-start');
  const zoomEndInput = document.getElementById('task-zoom-end');
  const zoomApplyBtn = document.getElementById('task-zoom-apply');

  if (!select || !studentSelect || !canvas || !placeholder) {
    return;
  }

  if (canvas.parentElement) {
    canvas.parentElement.style.minHeight = '600px';
    canvas.parentElement.style.position = 'relative';
  }

  const submissions = window.BACS_PAGE_DATA.submissions || [];
  const contestData = window.BACS_PAGE_DATA.contest;
  const students = window.BACS_PAGE_DATA.students || [];
  const tasks = window.BACS_PAGE_DATA.tasks || [];

  const currentLocale = document.documentElement.lang || 'en-US';

  const loc = (key, fallback) => {
    if (window.BACS_LOCALIZED_STRINGS && window.BACS_LOCALIZED_STRINGS[key]) {
        return window.BACS_LOCALIZED_STRINGS[key];
    }
    return fallback;
  };

  if (select.options.length <= 1 && tasks.length > 0) {
    tasks.forEach((t) => {
      const option = document.createElement('option');
      option.value = t.task_id;
      option.textContent = `${t.task_order}. ${t.name}`;
      select.appendChild(option);
    });
  }

  if (studentSelect.options.length <= 1 && students.length > 0) {
    const sortedStudents = [...students].sort((a, b) =>
      `${a.firstname} ${a.lastname}`.localeCompare(`${b.firstname} ${b.lastname}`)
    );
    sortedStudents.forEach((s) => {
      const option = document.createElement('option');
      option.value = s.id;
      option.textContent = `${s.firstname} ${s.lastname}`;
      studentSelect.appendChild(option);
    });
  }

  const studentMap = {};
  students.forEach((s) => (studentMap[s.id] = `${s.firstname} ${s.lastname}`));
  const taskMap = {};
  tasks.forEach((t) => (taskMap[t.task_id] = t.task_order + '. ' + t.name));

  if (window.taskDynamicsChartInstance) {
    window.taskDynamicsChartInstance.destroy();
    window.taskDynamicsChartInstance = null;
  }

  if (detailsContainer) {
    detailsContainer.style.display = 'none';
  }
  if (statsContainer) {
    statsContainer.innerHTML = '';
  }

  const VERDICT_ACCEPTED = 13;

  const globalFirstBloods = new Set();
  const allSubsSorted = submissions.slice().sort((a, b) => a.submit_time - b.submit_time);
  allSubsSorted.forEach(sub => {
      if (sub.result_id == VERDICT_ACCEPTED && !globalFirstBloods.has(sub.task_id)) {
          globalFirstBloods.add(sub.task_id);
          sub.isFirstBlood = true;
      }
  });

  const taskId = parseInt(select.value, 10);
  const studentId = parseInt(studentSelect.value, 10);

  let relevantSubmissions = submissions.slice();

  if (hideUpsolvingCheckbox && hideUpsolvingCheckbox.checked) {
    relevantSubmissions = relevantSubmissions.filter((sub) => sub.submit_time <= contestData.endtime);
  }

  if (taskId !== -1 && !isNaN(taskId)) {
    relevantSubmissions = relevantSubmissions.filter((sub) => String(sub.task_id) === String(taskId));
  }
  if (studentId !== -1 && !isNaN(studentId)) {
    relevantSubmissions = relevantSubmissions.filter((sub) => String(sub.user_id) === String(studentId));
  }

  const existingEmpty = document.getElementById('task-dynamics-empty-state');
  if (existingEmpty) {
    existingEmpty.style.display = 'none';
  }

  if (relevantSubmissions.length === 0) {
    canvas.classList.add('d-none');
    placeholder.classList.add('d-none');

    const isAllTasks = taskId === -1;
    const isAllStudents = studentId === -1;
    let title = loc('nodata', 'No data');
    let desc = loc('nodatadesc', 'No submissions found for the selected filters.');

    if (isAllTasks && isAllStudents) {
      title = loc('notasksyet', 'No submissions in the contest yet');
      desc = loc('notasksyetdesc', 'The chart will appear as soon as participants start submitting solutions.');
    } else if (!isAllTasks && isAllStudents) {
      title = loc('tasknotsolvedyet', 'Task not solved yet');
      desc = loc('tasknotsolvedyetdesc', 'Nobody has submitted solutions for task {task} yet.').replace('{task}', `<b>«${taskMap[taskId] || taskId}»</b>`);
    } else if (isAllTasks && !isAllStudents) {
      title = loc('usernotsubmittedyet', 'Participant hasn\'t submitted yet');
      desc = loc('usernotsubmittedyetdesc', 'Participant {user} hasn\'t submitted anything yet.').replace('{user}', `<b>${studentMap[studentId] || ''}</b>`);
    } else {
      title = loc('nosubmits', 'No submissions');
      desc = loc('nosubmitsdesc', 'Participant {user} hasn\'t submitted solutions for task {task}.').replace('{user}', `<b>${studentMap[studentId] || ''}</b>`).replace('{task}', `<b>«${taskMap[taskId] || ''}»</b>`);
    }

    BacsUtils.createEmptyState('task-dynamics-empty-state', placeholder.parentNode, placeholder.nextSibling, 'bi-inbox', title, desc).style.display = 'flex';
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
    if (span === 0) stepSeconds = 3600;
    else if (span <= 4 * 3600) stepSeconds = 15 * 60;
    else if (span <= 24 * 3600) stepSeconds = 3600;
    else if (span <= 3 * 86400) stepSeconds = 4 * 3600;
    else if (span <= 7 * 86400) stepSeconds = 12 * 3600;
    else if (span <= 30 * 86400) stepSeconds = 86400;
    else if (span <= 90 * 86400) stepSeconds = 3 * 86400;
    else stepSeconds = 7 * 86400;
  }

  const subsByInterval = new Map();
  relevantSubmissions.forEach((sub) => {
    const idx = Math.floor(sub.submit_time / stepSeconds);
    if (!subsByInterval.has(idx)) subsByInterval.set(idx, []);
    subsByInterval.get(idx).push(sub);
  });

  const sortedIndices = Array.from(subsByInterval.keys()).sort((a, b) => a - b);
  const finalBuckets = [];

  for (let i = 0; i < sortedIndices.length; i++) {
    const currIdx = sortedIndices[i];
    const currSubs = subsByInterval.get(currIdx);

    finalBuckets.push({
      isGap: false,
      startSec: currIdx * stepSeconds,
      endSec: (currIdx + 1) * stepSeconds,
      subs: currSubs
    });

    if (i < sortedIndices.length - 1) {
      const nextIdx = sortedIndices[i + 1];
      const diff = nextIdx - currIdx - 1;

      if (diff > 0) {
        if (diff < 2) {
          for (let j = 1; j <= diff; j++) {
            finalBuckets.push({
              isGap: false,
              startSec: (currIdx + j) * stepSeconds,
              endSec: (currIdx + j + 1) * stepSeconds,
              subs: []
            });
          }
        } else {
          const gapStart = (currIdx + 1) * stepSeconds;
          const gapEnd = nextIdx * stepSeconds;
          const skipDur = gapEnd - gapStart;

          let slots = 1;
          if (skipDur < 86400) slots = 1;
          else if (skipDur < 3 * 86400) slots = 2;
          else if (skipDur < 7 * 86400) slots = 3;
          else slots = 4;

          const gapId = 'gap_' + gapStart;
          for (let s = 0; s < slots; s++) {
            finalBuckets.push({
              isGap: true,
              gapId: gapId,
              startSec: gapStart,
              endSec: gapEnd,
              skipDur: skipDur,
              subs: []
            });
          }
        }
      }
    }
  }

  const lastBucketEnd = finalBuckets.length > 0 ? finalBuckets[finalBuckets.length - 1].endSec : 0;
  if (lastBucketEnd < contestData.endtime && (!hideUpsolvingCheckbox || !hideUpsolvingCheckbox.checked)) {
      finalBuckets.push({
          isGap: true,
          gapId: 'gap_trailing',
          startSec: lastBucketEnd,
          endSec: contestData.endtime,
          skipDur: contestData.endtime - lastBucketEnd,
          subs: []
      });
  }

  finalBuckets.forEach(b => {
      b.isEmpty = b.subs.length === 0;
      if (!b.isEmpty && !b.isGap) {
          const firstBloods = b.subs.filter(s => s.isFirstBlood);
          if (firstBloods.length > 0) b.firstBloods = firstBloods;
      }
  });

  const activeSessions = [];
  let currentSession = null;

  for (let i = 0; i < finalBuckets.length; i++) {
      const b = finalBuckets[i];
      if (!b.isEmpty && !b.isGap) {
          if (!currentSession) currentSession = {startIdx: i, endIdx: i};
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

  const validSessions = [];
  const MIN_SUBS_FOR_SESSION = 8;
  const MIN_USERS_FOR_SESSION = 2;

  activeSessions.forEach(session => {
      let okCount = 0;
      let users = new Set();
      let taskCounts = {};
      let allSubs = [];
      let maxFailsByUser = 0;
      let failCountsByUser = {};

      for (let i = session.startIdx; i <= session.endIdx; i++) {
          const b = finalBuckets[i];
          if (b.isGap) continue;
          
          b.subs.forEach(s => {
              allSubs.push(s);
              users.add(s.user_id);
              if (s.result_id == VERDICT_ACCEPTED) {
                  okCount++;
              } else {
                  failCountsByUser[s.user_id] = (failCountsByUser[s.user_id] || 0) + 1;
                  if (failCountsByUser[s.user_id] > maxFailsByUser) {
                      maxFailsByUser = failCountsByUser[s.user_id];
                  }
              }
              taskCounts[s.task_id] = (taskCounts[s.task_id] || 0) + 1;
          });
      }

      session.totalSubs = allSubs.length;
      session.okCount = okCount;
      session.successRate = session.totalSubs > 0 ? (okCount / session.totalSubs) : 0;
      session.uniqueUsers = users.size;
      session.allSubs = allSubs;

      const totalFails = session.totalSubs - session.okCount;
      session.hasSpammer = (totalFails > 0 && (maxFailsByUser / totalFails) > 0.5 && maxFailsByUser >= 5);

      let sortedTasks = Object.entries(taskCounts).sort((a, b) => b[1] - a[1]);
      session.topTasks = sortedTasks.slice(0, 3);

      if (session.totalSubs >= MIN_SUBS_FOR_SESSION && session.uniqueUsers >= MIN_USERS_FOR_SESSION) {
          validSessions.push(session);
      }
  });

  const labels = [];
  const okData = [];
  const notOkData = [];
  const bucketColors = [];

  finalBuckets.forEach((b) => {
    let okCount = 0, failCount = 0;
    b.subs.forEach((s) => {
      if (s.result_id == VERDICT_ACCEPTED) okCount++;
      else failCount++;
    });

    okData.push(okCount);
    notOkData.push(failCount);

    if (b.isGap) {
      labels.push("");
      bucketColors.push('transparent');
    } else {
      const dStart = new Date(b.startSec * 1000);
      // ИСПОЛЬЗУЕМ currentLocale
      let dateStr = `${dStart.getDate()} ${dStart.toLocaleDateString(currentLocale, {month: 'short'}).replace('.', '')}`;

      if (stepSeconds < 86400) {
         dateStr += `, ${dStart.getHours().toString().padStart(2, '0')}:${dStart.getMinutes().toString().padStart(2, '0')}`;
      }

      labels.push(dateStr);
      bucketColors.push(BacsUtils.getContestProgress(b.startSec, contestData.starttime, contestData.endtime).color);
    }
  });

  let dragTooltip = document.getElementById('task-dynamics-drag-tooltip');
  if (!dragTooltip) {
    dragTooltip = document.createElement('div');
    dragTooltip.id = 'task-dynamics-drag-tooltip';
    dragTooltip.innerHTML = '<i class="bi bi-zoom-in" style="color: #60a5fa; margin-right: 5px;"></i> <span id="task-dynamics-drag-text"></span>';
    dragTooltip.style.cssText = `position: absolute; top: 40px; left: 50%; transform: translateX(-50%); background: rgba(17, 24, 39, 0.9); color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif; pointer-events: none; opacity: 0; transition: opacity 0.15s; z-index: 100; white-space: nowrap; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);`;
    canvas.parentElement.appendChild(dragTooltip);
  }

  let sessionTooltip = document.getElementById('task-session-tooltip');
  if (!sessionTooltip) {
      sessionTooltip = document.createElement('div');
      sessionTooltip.id = 'task-session-tooltip';
      sessionTooltip.style.cssText = `position: absolute; display: none; background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; padding: 14px; border-radius: 8px; font-size: 13px; font-family: 'Inter', sans-serif; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 105; pointer-events: none; width: 240px; transition: opacity 0.1s;`;
      canvas.parentElement.appendChild(sessionTooltip);
  }

  let isDragging = false;
  let startX = 0;

  const formatDragTime = (ms) => {
    const d = new Date(ms);
    return `${d.toLocaleDateString(currentLocale, {day: 'numeric', month: 'short'})} ${d.toLocaleTimeString(currentLocale, {hour: '2-digit', minute: '2-digit'})}`;
  };

  if (canvas._bacsMouseDown) {
      canvas.removeEventListener('mousedown', canvas._bacsMouseDown);
      canvas.removeEventListener('mousemove', canvas._bacsMouseMove);
      canvas.removeEventListener('mouseout', canvas._bacsMouseOut);
      window.removeEventListener('mouseup', window._bacsTaskDynMouseUp);
  }

  canvas._bacsMouseDown = (e) => { isDragging = true; startX = e.offsetX; };

  canvas._bacsMouseMove = (e) => {
    const chart = window.taskDynamicsChartInstance;
    if (!chart) return;

    if (isDragging) {
        const currentX = e.offsetX;
        if (Math.abs(currentX - startX) > 20) {
          const xAxis = chart.scales.x;
          const val1 = Math.round(xAxis.getValueForPixel(startX));
          const val2 = Math.round(xAxis.getValueForPixel(currentX));

          const idx1 = Math.max(0, Math.min(val1, finalBuckets.length - 1));
          const idx2 = Math.max(0, Math.min(val2, finalBuckets.length - 1));

          const ms1 = finalBuckets[idx1].startSec * 1000;
          const ms2 = finalBuckets[idx2].endSec * 1000;

          document.getElementById('task-dynamics-drag-text').innerText = `${formatDragTime(Math.min(ms1, ms2))}  →  ${formatDragTime(Math.max(ms1, ms2))}`;
          dragTooltip.style.left = ((startX + currentX) / 2) + 'px';
          dragTooltip.style.opacity = '1';
        } else {
          dragTooltip.style.opacity = '0';
        }
        sessionTooltip.style.display = 'none';
        return;
    }

    const activeElements = chart.getElementsAtEventForMode(e, 'index', {intersect: true}, false);
    let hoveringDataBar = false;

    if (activeElements.length > 0) {
        const index = activeElements[0].index;
        const b = finalBuckets[index];
        if (b && !b.isGap) {
            hoveringDataBar = true;
        }
    }

    if (hoveringDataBar) {
        sessionTooltip.style.display = 'none';
        canvas.style.cursor = 'pointer';
        return;
    }

    const x = e.offsetX;
    const y = e.offsetY;
    const {top, bottom} = chart.chartArea;
    let hoveredSession = null;

    for (let s of validSessions) {
        const inBg = s._hitbox && (x >= s._hitbox.x && x <= s._hitbox.x + s._hitbox.w && y >= top && y <= bottom);
        const inLabel = s._labelBox && (x >= s._labelBox.x && x <= s._labelBox.x + s._labelBox.w && y >= s._labelBox.y && y <= s._labelBox.y + s._labelBox.h);
        if (inBg || inLabel) {
            hoveredSession = s;
            break;
        }
    }

    if (hoveredSession) {
        canvas.style.cursor = 'pointer';

        const fmt = (ms) => {
            const d = new Date(ms);
            return `${d.toLocaleDateString(currentLocale, {month: 'short', day: 'numeric'})} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
        };
        const tStart = fmt(finalBuckets[hoveredSession.startIdx].startSec * 1000);
        const tEnd = fmt(finalBuckets[hoveredSession.endIdx].endSec * 1000);

        let html = `<div style="font-weight: 700; margin-bottom: 6px; color: #111827; font-size: 14px;"><i class="bi bi-people-fill text-primary me-1"></i> ${loc('classsession', 'Class Session')}</div>`;
        html += `<div style="color: #6b7280; font-size: 11px; margin-bottom: 10px; font-family: monospace;">${tStart} — ${tEnd}</div>`;

        let okPercent = (hoveredSession.successRate * 100).toFixed(0);
        let rateColor = '#d97706';
        if (hoveredSession.successRate >= 0.7) rateColor = '#059669';
        else if (hoveredSession.successRate < 0.4) rateColor = '#dc2626';

        html += `<div style="display: flex; justify-content: space-between; margin-bottom: 4px;"><span>${loc('usersactive', 'Users active:')}</span> <span style="font-weight:600;">${hoveredSession.uniqueUsers}</span></div>`;
        html += `<div style="display: flex; justify-content: space-between; margin-bottom: 10px;"><span>${loc('totalsubmits', 'Total Submits:')}</span> <div><span style="font-weight:600;">${hoveredSession.totalSubs}</span> <span style="color: ${rateColor}; font-size: 11px; margin-left:4px;">(${okPercent}% OK)</span></div></div>`;

        if (hoveredSession.hasSpammer) {
            html += `<div style="color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; padding: 4px 8px; font-size: 11px; margin-bottom: 10px; text-align:center;"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${loc('spammeranomaly', 'Spammer anomaly detected')}</div>`;
        }

        html += `<div style="font-size: 12px; border-top: 1px solid #f3f4f6; padding-top: 8px; margin-top: 8px;">`;
        html += `<div style="font-weight: 600; margin-bottom: 6px; color:#4b5563; font-size: 11px; text-transform: uppercase;">${loc('toptasks', 'Top Tasks')}</div>`;

        hoveredSession.topTasks.forEach(task => {
            let taskName = taskMap[task[0]] || `Task ${task[0]}`;
            if (taskName.length > 22) taskName = taskName.substring(0, 20) + '...';
            html += `<div style="display:flex; justify-content:space-between; margin-bottom: 3px; color: #374151;">
                <span title="${taskMap[task[0]]}">${taskName}</span>
                <span style="font-weight:600; background: #f3f4f6; padding: 0 4px; border-radius: 3px;">${task[1]}</span>
            </div>`;
        });
        html += `</div>`;
        html += `<div style="margin-top: 10px; font-size: 11px; color: #3b82f6; text-align: center; font-weight: 500;">${loc('clicktoviewsubs', 'Click to view all submissions')}</div>`;

        sessionTooltip.innerHTML = html;

        let ttX = e.clientX - canvas.getBoundingClientRect().left + 15;
        let ttY = e.clientY - canvas.getBoundingClientRect().top + 15;
        if (ttX + 240 > canvas.getBoundingClientRect().width) ttX = x - 255;

        sessionTooltip.style.left = ttX + 'px';
        sessionTooltip.style.top = ttY + 'px';
        sessionTooltip.style.display = 'block';

    } else {
        canvas.style.cursor = 'default';
        sessionTooltip.style.display = 'none';
    }
  };

  window._bacsTaskDynMouseUp = () => { isDragging = false; dragTooltip.style.opacity = '0'; };
  canvas._bacsMouseOut = () => { if (sessionTooltip) sessionTooltip.style.display = 'none'; };

  canvas.addEventListener('mousedown', canvas._bacsMouseDown);
  canvas.addEventListener('mousemove', canvas._bacsMouseMove);
  canvas.addEventListener('mouseout', canvas._bacsMouseOut);
  window.addEventListener('mouseup', window._bacsTaskDynMouseUp);

  const updateManualZoomInputs = ({chart}) => {
    if (!zoomStartInput || !zoomEndInput || finalBuckets.length === 0) return;
    const minIdx = Math.max(0, Math.round(chart.scales.x.min));
    const maxIdx = Math.min(finalBuckets.length - 1, Math.round(chart.scales.x.max));

    if (finalBuckets[minIdx]) zoomStartInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[minIdx].startSec * 1000);
    if (finalBuckets[maxIdx]) zoomEndInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[maxIdx].endSec * 1000);

    if (resetZoomBtn) resetZoomBtn.classList.remove('d-none');
  };

  if (zoomStartInput && zoomEndInput && finalBuckets.length > 0) {
    zoomStartInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[0].startSec * 1000);
    zoomEndInput.value = window.BacsUtils.toDateTimeLocal(finalBuckets[finalBuckets.length - 1].endSec * 1000);
  }

  const TEXT_COLOR = '#6b7280';

  const tooltipConfig = BacsUtils.getTooltipBaseConfig({
    title: function(context) {
      if (context[0]) {
        const b = finalBuckets[context[0].dataIndex];
        const minStr = new Date(b.startSec * 1000).toLocaleString(currentLocale, {day: 'numeric', month: 'short', hour: '2-digit', minute:'2-digit'});
        const maxStr = new Date(b.endSec * 1000).toLocaleString(currentLocale, {day: 'numeric', month: 'short', hour: '2-digit', minute:'2-digit'});

        const rangeStr = b.startSec === b.endSec ? minStr : `${minStr} —\n${maxStr}`;
        const timeFromStartStr = BacsUtils.formatTime(b.startSec - contestData.starttime);
        return [`📅 ${loc('period', 'Period:')}`, `${rangeStr}`, ``, `⏱️ ${loc('elapsedfromstart', 'Elapsed from start: +')} ${timeFromStartStr}`];
      }
      return '';
    },
    label: function(context) {
      const b = finalBuckets[context.dataIndex];
      return context.dataset.label + ": " + context.raw;
    },
    afterBody: function(context) {
        const b = finalBuckets[context[0].dataIndex];
        if (b && !b.isGap && b.firstBloods && b.firstBloods.length > 0) {
            let lines = [`\n🏆 ${loc('firstaccepted', 'First Accepted:')}`];
            b.firstBloods.forEach(sub => {
                lines.push(`  • ${taskMap[sub.task_id] || loc('task', 'Task') + ' ' + sub.task_id}`);
            });
            return lines;
        }
        return [];
    }
  });

  tooltipConfig.mode = 'index';
  tooltipConfig.intersect = true;

  tooltipConfig.filter = function(tooltipItem) {
    if (finalBuckets[tooltipItem.dataIndex].isGap) return false;
    return true;
  };

  const backgroundZonesPlugin = {
      id: 'backgroundZones',
      beforeDraw(chart) {
          const ctx = chart.ctx;
          const {top, bottom, left, right} = chart.chartArea;

          let boundaryIdx = finalBuckets.findIndex(b => b.startSec >= contestData.endtime);
          if (boundaryIdx === -1) boundaryIdx = finalBuckets.findIndex(b => b.endSec >= contestData.endtime);

          if (boundaryIdx !== -1) {
              const meta = chart.getDatasetMeta(0);
              if (meta && meta.data[boundaryIdx]) {
                  const bar = meta.data[boundaryIdx];
                  const upsolvingPixel = bar.x - bar.width / 2;

                  ctx.save();
                  ctx.fillStyle = 'rgba(248, 250, 252, 0.8)';
                  ctx.fillRect(upsolvingPixel, top, right - upsolvingPixel, bottom - top);

                  ctx.beginPath();
                  ctx.strokeStyle = 'rgba(239, 68, 68, 0.5)';
                  ctx.lineWidth = 2;
                  ctx.setLineDash([5, 5]);
                  ctx.moveTo(upsolvingPixel, top);
                  ctx.lineTo(upsolvingPixel, bottom);
                  ctx.stroke();

                  ctx.fillStyle = 'rgba(239, 68, 68, 0.8)';
                  ctx.font = "600 10px 'Inter', sans-serif";
                  ctx.textAlign = 'left';
                  ctx.textBaseline = 'bottom';
                  ctx.fillText(loc('endofcontest', 'End of Contest'), upsolvingPixel + 4, top + 12);
                  ctx.restore();
              }
          }
      }
  };

  const cleanGapPlugin = {
    id: 'cleanGap',
    afterDatasetsDraw(chart) {
      const ctx = chart.ctx;
      const yAxis = chart.scales.y;

      const gapGroups = {};
      chart.data.labels.forEach((_, i) => {
        if (finalBuckets[i] && finalBuckets[i].isGap) {
          const gid = finalBuckets[i].gapId;
          if (!gapGroups[gid]) gapGroups[gid] = [];
          gapGroups[gid].push(i);
        }
      });

      const meta = chart.getDatasetMeta(0);
      Object.values(gapGroups).forEach(indices => {
        const firstIdx = indices[0];
        const lastIdx = indices[indices.length - 1];
        if (!meta.data[firstIdx] || !meta.data[lastIdx]) return;

        const firstBar = meta.data[firstIdx];
        const lastBar = meta.data[lastIdx];

        const left = firstBar.x - firstBar.width / 2;
        const right = lastBar.x + lastBar.width / 2;
        const width = right - left;
        const top = yAxis.top;
        const height = yAxis.bottom - yAxis.top;

        const b = finalBuckets[firstIdx];
        const skipDur = b.skipDur;

        ctx.save();
        ctx.beginPath();
        ctx.rect(left, top, width, height);
        ctx.clip();

        ctx.strokeStyle = 'rgba(209, 213, 219, 0.8)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(left, top); ctx.lineTo(left, top + height);
        ctx.moveTo(right, top); ctx.lineTo(right, top + height);
        ctx.stroke();

        const d = Math.floor(skipDur / 86400);
        const h = Math.floor((skipDur % 86400) / 3600);
        let durStr = "";
        if (d > 0) durStr += `${d} ${loc('days_short', 'd')} `;
        if (h > 0 || d === 0) durStr += `${h} ${loc('hours_short', 'h')}`;

        const fmt = (sec) => {
            const dt = new Date(sec * 1000);
            return `${dt.getDate()} ${dt.toLocaleString(currentLocale, {month: 'short'}).replace('.', '')}`;
        };

        const textL1 = `${fmt(b.startSec)} — ${fmt(b.endSec)}`;
        const textL2 = `(${durStr.trim()})`;
        const textFull = `${textL1} ${textL2}`;

        ctx.translate(left + width / 2, top + height / 2);
        ctx.font = "500 11px 'Inter', sans-serif";

        const wFull = ctx.measureText(textFull).width;
        const maxW2 = Math.max(ctx.measureText(textL1).width, ctx.measureText(textL2).width);

        let angle = 0;
        let lines = [];
        let boxW = 0;
        let boxH = 0;
        let shouldDraw = true;

        if (width >= wFull + 20) {
            angle = 0;
            lines = [textFull];
            boxW = wFull + 16;
            boxH = 20;
        } else if (width >= maxW2 + 16) {
            angle = 0;
            lines = [textL1, textL2];
            boxW = maxW2 + 16;
            boxH = 34;
        } else if (width >= 40) {
            angle = -Math.PI / 4;
            lines = [textL1, textL2];
            boxW = maxW2 + 16;
            boxH = 34;
        } else {
            shouldDraw = false;
        }

        if (shouldDraw && height > boxH + 10) {
            ctx.rotate(angle);

            ctx.beginPath();
            ctx.roundRect(-boxW / 2, -boxH / 2, boxW, boxH, 4);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
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
        }

        ctx.restore();
      });
    }
  };

  const sessionAnalyticsPlugin = {
      id: 'sessionAnalytics',
      beforeDatasetsDraw(chart) {
          const ctx = chart.ctx;
          const meta = chart.getDatasetMeta(0);
          const {top, bottom, left, right} = chart.chartArea;

          ctx.save();
          ctx.beginPath();
          ctx.rect(left, top, right - left, bottom - top);
          ctx.clip();

          validSessions.forEach(s => {
              const barStart = meta.data[s.startIdx];
              const barEnd = meta.data[s.endIdx];
              if (!barStart || !barEnd) return;

              const startX = barStart.x - barStart.width / 2;
              const endX = barEnd.x + barEnd.width / 2;
              const width = endX - startX;

              let bgColor = 'rgba(252, 211, 77, 0.15)';
              if (s.successRate >= 0.7) bgColor = 'rgba(16, 185, 129, 0.12)';
              else if (s.successRate < 0.4) bgColor = 'rgba(239, 68, 68, 0.12)';

              ctx.fillStyle = bgColor;
              ctx.beginPath();
              ctx.roundRect(startX, top, width, bottom - top, 4);
              ctx.fill();

              s._hitbox = {x: startX, w: width};
          });
          ctx.restore();
      },
      afterDatasetsDraw(chart) {
          const ctx = chart.ctx;
          const meta0 = chart.getDatasetMeta(0);
          const meta1 = chart.getDatasetMeta(1);
          const {top, left, right} = chart.chartArea;

          ctx.save();
          ctx.beginPath();
          ctx.rect(left, top - 25, right - left, chart.chartArea.bottom - top + 25);
          ctx.clip();

          finalBuckets.forEach((b, i) => {
              if (b.firstBloods && b.firstBloods.length > 0) {
                  const bar0 = meta0.data[i];
                  const bar1 = meta1.data[i];
                  if (!bar0 && !bar1) return;

                  const x = bar0 ? bar0.x : bar1.x;
                  if (x < left || x > right) return;

                  let y = Math.min(bar0 ? bar0.y : Infinity, bar1 ? bar1.y : Infinity);
                  if (y === Infinity) y = chart.chartArea.bottom;

                  ctx.fillStyle = '#eab308';
                  ctx.font = "11px Arial";
                  ctx.textAlign = 'center';
                  ctx.textBaseline = 'bottom';
                  ctx.fillText('★', x, y - 2);

                  if (b.firstBloods.length > 1) {
                     ctx.font = "bold 9px 'Inter', sans-serif";
                     ctx.fillText('+' + (b.firstBloods.length - 1), x + 10, y - 2);
                  }
              }
          });

          validSessions.forEach(s => {
              const barStart = meta0.data[s.startIdx];
              const barEnd = meta0.data[s.endIdx];
              if (!barStart || !barEnd) return;

              const startX = barStart.x - barStart.width / 2;
              const endX = barEnd.x + barEnd.width / 2;
              const width = endX - startX;
              const midX = startX + width / 2;

              let localPeakY = chart.chartArea.bottom;
              for (let i = s.startIdx; i <= s.endIdx; i++) {
                  const b0 = meta0.data[i];
                  const b1 = meta1.data[i];
                  if (b0 && b0.y < localPeakY) localPeakY = b0.y;
                  if (b1 && b1.y < localPeakY) localPeakY = b1.y;
              }

              const text = `${s.totalSubs} ${loc('subs', 'subs')} / ${s.uniqueUsers} ${loc('usr', 'usr')}`;
              ctx.font = "600 10px 'Inter', sans-serif";
              const textWidth = ctx.measureText(text).width;
              let boxW = textWidth + 16;
              if (s.hasSpammer) boxW += 16;
              const boxH = 20;

              let labelY = localPeakY - 14;
              if (labelY < top + 12) labelY = top + 12;

              let labelX = midX;
              if (labelX - boxW / 2 < left) labelX = left + boxW / 2 + 2;
              if (labelX + boxW / 2 > right) labelX = right - boxW / 2 - 2;

              let bgSolid = '#fffbeb';
              let textColor = '#b45309';
              let borderColor = '#fcd34d';

              if (s.successRate >= 0.7) {
                  bgSolid = '#ecfdf5';
                  textColor = '#059669';
                  borderColor = '#a7f3d0';
              } else if (s.successRate < 0.4) {
                  bgSolid = '#fef2f2';
                  textColor = '#dc2626';
                  borderColor = '#fecaca';
              }

              ctx.fillStyle = bgSolid;
              ctx.beginPath();
              ctx.roundRect(labelX - boxW / 2, labelY - boxH / 2, boxW, boxH, 4);
              ctx.fill();
              ctx.lineWidth = 1;
              ctx.strokeStyle = borderColor;
              ctx.stroke();

              let icon = '';
              if (s.hasSpammer) {
                  icon = '⚠️ ';
                  ctx.fillStyle = '#dc2626';
              } else {
                  ctx.fillStyle = textColor;
              }

              ctx.textAlign = 'center';
              ctx.textBaseline = 'middle';
              ctx.fillText(icon + text, labelX, labelY + 1);

              s._labelBox = {x: labelX - boxW / 2, y: labelY - boxH / 2, w: boxW, h: boxH};
          });

          ctx.restore();
      }
  };

  const ctx = canvas.getContext('2d');
  window.taskDynamicsChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
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
      layout: {padding: {top: 20, right: 20, left: 10, bottom: 10}},
      scales: {
        x: {
          stacked: true,
          border: {display: false},
          grid: {
              color: function(context) {
                  const chart = context.chart;
                  if (!chart) return 'transparent';
                  const visibleSpan = chart.scales.x.max - chart.scales.x.min;
                  return visibleSpan < 50 ? 'rgba(0, 0, 0, 0.03)' : 'transparent';
              },
              drawBorder: false
          },
          ticks: {
            maxRotation: 0,
            autoSkipPadding: 35,
            font: {size: 11, family: "'Inter', monospace", weight: '500'},
            color: (c) => [TEXT_COLOR, bucketColors[c.index] || TEXT_COLOR],
          },
        },
        y: {
          stacked: true,
          beginAtZero: true,
          border: {display: false},
          title: {
            display: true,
            text: loc('submits', 'Submissions'),
            color: TEXT_COLOR,
            font: {weight: '500'},
          },
          ticks: {color: TEXT_COLOR, precision: 0, padding: 10},
          grid: {color: 'rgba(0, 0, 0, 0.04)', drawBorder: false},
        },
      },
      animation: {duration: 1000, easing: 'easeOutQuart'},
      transitions: {zoom: {animation: {duration: 0}}},
      plugins: {
        legend: {position: 'top', labels: {color: '#374151', boxWidth: 12, usePointStyle: true}},
        tooltip: tooltipConfig,
        zoom: {
          limits: {x: {min: 'original', max: 'original', minRange: 2}},
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
      onClick: (e) => {
        const chart = window.taskDynamicsChartInstance;
        if (!chart) return;

        const populateTable = (subsList, titleHtml) => {
            subsList.sort((a, b) => b.submit_time - a.submit_time);

            if (detailsTitle) detailsTitle.innerHTML = titleHtml;
            if (detailsTableBody) {
                detailsTableBody.innerHTML = subsList.map((sub) => {
                    const studentName = studentMap[sub.user_id] || `User ${sub.user_id}`;
                    const taskName = taskMap[sub.task_id] || sub.task_id;
                    const isOk = sub.result_id == VERDICT_ACCEPTED;
                    const timeFromStartStr = BacsUtils.formatTime(sub.submit_time - contestData.starttime);
                    
                    const dt = new Date(sub.submit_time * 1000);
                    const realDateStr = `${dt.toLocaleString(currentLocale, {month: 'short', day: 'numeric'})}, ${dt.getHours().toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')}`;

                    const starIcon = sub.isFirstBlood ? `<i class="bi bi-star-fill text-warning me-1" title="${loc('firstaccepted', 'First Accepted in contest!')}"></i>` : '';

                    const statusBadge = isOk
                      ? `<span style="background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">${loc('verdict_ok', 'OK')}</span>`
                      : `<span style="background: #ffe4e6; color: #e11d48; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">${loc('verdict_not_ok', 'FAIL')}</span>`;
                    const prog = BacsUtils.getContestProgress(sub.submit_time, contestData.starttime, contestData.endtime);
                    const barHtml = prog.isUpsolving
                      ? `<div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">[${loc('upsolving_label', 'Upsolving')}]</div>`
                      : `<div style="width: 100%; height: 4px; background: #e5e7eb; border-radius: 2px; margin-top: 6px; overflow: hidden;"><div style="width: ${prog.percent}%; height: 100%; background: ${prog.color};"></div></div><div style="font-size: 0.7rem; color: ${prog.color}; text-align: right; line-height: 1;">${prog.percent.toFixed(0)}%</div>`;

                    return `<tr><td class="align-middle" style="width: 180px;"><div class="fw-bold" style="font-size: 0.85rem; color: #111827;">${realDateStr}</div><div style="font-family: monospace; font-size: 0.8rem; color: #6b7280; margin-top: 2px;">+ ${timeFromStartStr}</div>${barHtml}</td><td class="align-middle fw-500">${studentName}</td><td class="align-middle">${starIcon}${taskName}</td><td class="align-middle">${statusBadge}</td><td class="align-middle fw-bold">${sub.points !== null ? sub.points : '-'}</td></tr>`;
                }).join('');
            }
            if (detailsContainer) {
                detailsContainer.style.display = 'block';
                detailsContainer.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        };

        const activeElements = chart.getElementsAtEventForMode(e, 'index', {intersect: true}, false);
        if (activeElements.length > 0) {
            const index = activeElements[0].index;
            const b = finalBuckets[index];
            if (b && !b.isGap && b.subs.length > 0) {
                populateTable(b.subs, `<i class="bi bi-list-check me-2 text-primary"></i> ${loc('submissionsdetails', 'Submissions details')} <span class="badge bg-secondary ms-2">${b.subs.length} ${loc('total', 'total')}</span>`);
                return;
            }
        }

        const x = e.x;
        const y = e.y;
        let clickedSession = null;
        for (let s of validSessions) {
            const inBg = s._hitbox && (x >= s._hitbox.x && x <= s._hitbox.x + s._hitbox.w);
            const inLabel = s._labelBox && (x >= s._labelBox.x && x <= s._labelBox.x + s._labelBox.w && y >= s._labelBox.y && y <= s._labelBox.y + s._labelBox.h);
            if (inBg || inLabel) {
                clickedSession = s;
                break;
            }
        }

        if (clickedSession) {
            populateTable(clickedSession.allSubs, `<i class="bi bi-people-fill me-2 text-primary"></i> ${loc('classsessiondetails', 'Class Session Details')} <span class="badge bg-secondary ms-2">${clickedSession.totalSubs} ${loc('total', 'total')}</span>`);
        } else {
            if (detailsContainer) detailsContainer.style.display = 'none';
        }
      },
    },
  });

  const addSafeListener = (element, event, handler) => {
    if (!element) return;
    if (!element._bacsListeners) element._bacsListeners = {};
    if (!element._bacsListeners[event]) {
        element.addEventListener(event, handler);
        element._bacsListeners[event] = true;
    }
  };

  if (zoomApplyBtn) {
    zoomApplyBtn.removeEventListener('click', zoomApplyBtn._bacsClick);
    zoomApplyBtn._bacsClick = () => {
      const chart = window.taskDynamicsChartInstance;
      if (!chart) return;
      const t1 = new Date(zoomStartInput.value).getTime() / 1000;
      const t2 = new Date(zoomEndInput.value).getTime() / 1000;
      if (isNaN(t1) || isNaN(t2)) return;

      const startSec = Math.min(t1, t2);
      const endSec = Math.max(t1, t2);

      let idx1 = 0, idx2 = finalBuckets.length - 1;
      for (let i = 0; i < finalBuckets.length; i++) {
          if (finalBuckets[i].endSec >= startSec) { idx1 = i; break; }
      }
      for (let i = finalBuckets.length - 1; i >= 0; i--) {
          if (finalBuckets[i].startSec <= endSec) { idx2 = i; break; }
      }

      chart.zoomScale('x', {min: idx1, max: idx2}, 'default');
      if (resetZoomBtn) resetZoomBtn.classList.remove('d-none');
    };
    zoomApplyBtn.addEventListener('click', zoomApplyBtn._bacsClick);
  }

  addSafeListener(resetZoomBtn, 'click', function() {
    if (window.taskDynamicsChartInstance) window.taskDynamicsChartInstance.resetZoom();
    this.classList.add('d-none');
  });

  addSafeListener(hideUpsolvingCheckbox, 'change', () => {
    if (typeof window.renderTaskDynamicsGraph === 'function') window.renderTaskDynamicsGraph();
  });

  addSafeListener(intervalSelect, 'change', () => {
    if (typeof window.renderTaskDynamicsGraph === 'function') window.renderTaskDynamicsGraph();
  });
};