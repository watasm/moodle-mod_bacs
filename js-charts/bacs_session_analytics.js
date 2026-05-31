const SESSION_ICONS = {
  STAR: '★',
  WARNING: '⚠️ ',
};

window.BacsSessionAnalytics = {
  MIN_SUBS_FOR_SESSION: 8,
  MIN_USERS_FOR_SESSION: 2,
  findSessions: function (finalBuckets, verdictAcceptedId) {
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

    return activeSessions.reduce((acc, session) => {
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

          if (s.result_id == verdictAcceptedId) {
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
      session.successRate = session.totalSubs > 0 ? okCount / session.totalSubs : 0;
      session.uniqueUsers = users.size;
      session.allSubs = allSubs;

      session.hasSpammer =
        session.totalSubs - okCount > 0 && maxFailsByUser / (session.totalSubs - okCount) > 0.5 && maxFailsByUser >= 5;

      session.topTasks = Object.entries(taskCounts)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3);

      if (session.totalSubs >= this.MIN_SUBS_FOR_SESSION && session.uniqueUsers >= this.MIN_USERS_FOR_SESSION) {
        acc.push(session);
      }
      return acc;
    }, []);
  },

  getChartPlugin: function (validSessions, finalBuckets, getSessionColorScheme, loc) {
    const withClip = (ctx, { left, top, right, bottom }, extraTop, fn) => {
      ctx.save();
      ctx.beginPath();
      ctx.rect(left, top - extraTop, right - left, bottom - top + extraTop);
      ctx.clip();
      fn();
      ctx.restore();
    };

    return {
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
            const bar0 = meta0.data[i],
              bar1 = meta1.data[i];
            if (!bar0 && !bar1) return;

            const activeBar = bar0 || bar1;
            if (activeBar.x < left || activeBar.x > right) return;

            let y = Math.min(bar0 ? bar0.y : Infinity, bar1 ? bar1.y : Infinity);
            if (y === Infinity) y = bottom;

            const starSize = Math.max(11, Math.min(32, activeBar.width * 0.6));
            const textSize = Math.max(9, Math.min(16, activeBar.width * 0.3));

            ctx.fillStyle = '#eab308';
            ctx.font = `${starSize}px Arial`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(SESSION_ICONS.STAR, activeBar.x, y - 2);

            if (b.firstSolvedTasks.length > 1) {
              ctx.font = `bold ${textSize}px 'Inter', sans-serif`;
              ctx.textAlign = 'left';
              ctx.fillText('+' + (b.firstSolvedTasks.length - 1), activeBar.x + starSize / 2 + 2, y - 2);
            }
          });

          validSessions.forEach((s) => {
            const barStart = meta0.data[s.startIdx],
              barEnd = meta0.data[s.endIdx];
            if (!barStart || !barEnd) return;

            const startX = barStart.x - barStart.width / 2;
            const width = barEnd.x + barEnd.width / 2 - startX;
            const midX = startX + width / 2;

            let localPeakY = bottom;
            for (let i = s.startIdx; i <= s.endIdx; i++) {
              if (meta0.data[i] && meta0.data[i].y < localPeakY) localPeakY = meta0.data[i].y;
              if (meta1.data[i] && meta1.data[i].y < localPeakY) localPeakY = meta1.data[i].y;
            }

            const text = `${s.totalSubs} ${loc('subs', 'subs')} / ${s.uniqueUsers} ${loc('usr', 'usr')}`;
            ctx.font = "600 10px 'Inter', sans-serif";
            const boxW = ctx.measureText(text).width + 16 + (s.hasSpammer ? 16 : 0);
            const boxH = 20;

            const labelY = Math.max(localPeakY - 14, top + 12);
            const labelX = Math.min(Math.max(midX, left + boxW / 2 + 2), right - boxW / 2 - 2);

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
            ctx.fillText((s.hasSpammer ? SESSION_ICONS.WARNING : '') + text, labelX, labelY + 1);

            s._labelBox = { x: labelX - boxW / 2, y: labelY - boxH / 2, w: boxW, h: boxH };
          });
        });
      },
    };
  },
};
