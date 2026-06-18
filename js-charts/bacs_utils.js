/* eslint-disable max-len */
/* eslint-disable object-curly-spacing */
/* global BacsUtils */
window.BacsUtils = {
  COLORS: [
    '#2196F3',
    '#F44336',
    '#4CAF50',
    '#FFC107',
    '#9C27B0',
    '#00BCD4',
    '#FF9800',
    '#E91E63',
    '#3F51B5',
    '#CDDC39',
    '#795548',
    '#607D8B',
    '#673AB7',
    '#009688',
    '#FF5722',
    '#8BC34A',
    '#03A9F4',
    '#E040FB',
    '#FFEB3B',
    '#9E9E9E',
  ],

  getUserColor: (userId) => {
    window._bacsUserColors = window._bacsUserColors || {};
    window._bacsNextColorIdx = window._bacsNextColorIdx || 0;
    if (!window._bacsUserColors[userId]) {
      window._bacsUserColors[userId] = BacsUtils.COLORS[window._bacsNextColorIdx % BacsUtils.COLORS.length];
      window._bacsNextColorIdx++;
    }
    return window._bacsUserColors[userId];
  },

  getStudentStartsMap(students, defaultStart) {
    const studentStarts = {};
    students.forEach((s) => (studentStarts[s.id] = s.starttime || defaultStart));
    return studentStarts;
  },

  padZero: (n, len = 2) => n.toString().padStart(len, '0'),

  currentLocale: () => document.documentElement.lang || 'en-US',
  loc: (key, fallback) =>
    window.BACS_LOCALIZED_STRINGS && window.BACS_LOCALIZED_STRINGS[key] ? window.BACS_LOCALIZED_STRINGS[key] : fallback,

  formatDateTimeBase: (ms, locale) => {
    const d = new Date(ms);
    return `${d.toLocaleDateString(locale, { day: 'numeric', month: 'short' })} ${d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })}`;
  },

  toDateTimeLocal: (ms) => {
    const d = new Date(ms);
    return `${d.getFullYear()}-${BacsUtils.padZero(d.getMonth() + 1)}-${BacsUtils.padZero(d.getDate())}T${BacsUtils.padZero(d.getHours())}:${BacsUtils.padZero(d.getMinutes())}`;
  },

  formatTime: (totalSeconds) => {
    const locD = BacsUtils.loc('days_short', 'd');
    totalSeconds = Math.max(0, Math.floor(totalSeconds));
    const days = Math.floor(totalSeconds / 86400);
    totalSeconds %= 86400;
    const hours = Math.floor(totalSeconds / 3600);
    totalSeconds %= 3600;
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    const timeStr = `${days > 0 ? days + ' ' + locD + ' ' : ''}${BacsUtils.padZero(hours)}:${BacsUtils.padZero(minutes)}`;
    return days > 0 ? timeStr : `${timeStr}:${BacsUtils.padZero(seconds)}`;
  },

  formatShortDate: (ms, locale = 'en-US') => BacsUtils.formatDateTimeBase(Math.max(0, ms), locale),

  formatTooltipDate: (uStart, realTime, contestStartSec, locale) => {
    const isVirtual = uStart > contestStartSec;
    const targetMs = (isVirtual ? uStart : contestStartSec) * 1000 + realTime;
    const dateStr = BacsUtils.formatDateTimeBase(targetMs, locale);
    const locVirtual = BacsUtils.loc('virtual', 'Virtual');
    return isVirtual ? ` (${dateStr}) [${locVirtual}]` : ` (${dateStr})`;
  },

  bindTimeZoomControls: (getChartFn, startId, endId, applyId, resetId, contestStartSec, signal) => {
    const startInput = document.getElementById(startId);
    const endInput = document.getElementById(endId);
    const applyBtn = document.getElementById(applyId);
    const resetBtn = document.getElementById(resetId);

    if (applyBtn && startInput && endInput) {
      applyBtn.addEventListener(
        'click',
        () => {
          BacsUtils.applyManualZoomTimeScale(getChartFn(), startInput, endInput, contestStartSec, resetBtn);
        },
        { signal },
      );
    }

    if (resetBtn) {
      resetBtn.addEventListener(
        'click',
        function () {
          const chart = getChartFn();
          if (chart) chart.resetZoom();
          this.classList.add('d-none');
          if (startInput && endInput && chart) {
            startInput.value = BacsUtils.toDateTimeLocal(contestStartSec * 1000);
            endInput.value = BacsUtils.toDateTimeLocal(contestStartSec * 1000 + chart.scales.x.max);
          }
        },
        { signal },
      );
    }
  },

  getContestProgress: (timestampSec, startTime, endTime) => {
    if (timestampSec > endTime) {
      return { percent: 100, text: '[Upsolving]', color: '#6b7280', isUpsolving: true };
    }
    const duration = endTime - startTime;
    let p = 0;
    if (duration > 0) {
      p = ((timestampSec - startTime) / duration) * 100;
      p = Math.max(0, Math.min(100, p));
    }
    let color = '#10b981';
    if (p >= 50) {
      color = '#f59e0b';
    }
    if (p >= 85) {
      color = '#ef4444';
    }
    return { percent: p, text: `${p.toFixed(0)}%`, color: color, isUpsolving: false };
  },

  createEmptyState: (id, parentNode, insertBeforeNode, iconClass, titleText, descText) => {
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement('div');
      el.id = id;
      el.style.cssText = `
            background-color: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 12px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            min-height: 400px; color: #6c757d; text-align: center; padding: 2rem;
            animation: fadeIn 0.3s ease-in-out; display: none;
        `;
      if (parentNode) {
        if (insertBeforeNode) {
          parentNode.insertBefore(el, insertBeforeNode);
        } else {
          parentNode.appendChild(el);
        }
      }
    }
    const iconColor = iconClass.includes('rocket') || iconClass.includes('graph') ? '#0d6efd' : '#6c757d';

    el.innerHTML = `
        <i class="bi ${iconClass}" style="font-size: 4.5rem; opacity: 0.3; margin-bottom: 1rem; color: ${iconColor};"></i>
        <h4 style="font-weight: 600; color: #495057;">${titleText}</h4>
        <p style="max-width: 450px; margin: 0 auto; font-size: 0.95rem;">${descText}</p>
    `;
    return el;
  },

  createChartLayout: (canvasId, prefix, emptyIcon, emptyTitle, emptyDesc) => {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
      return null;
    }

    let emptyStateContainer = BacsUtils.createEmptyState(
      `${prefix}-empty`,
      canvas.parentNode,
      canvas,
      emptyIcon,
      emptyTitle,
      emptyDesc,
    );

    let flexContainer = document.getElementById(`${prefix}-flex`);
    let legendContainer = document.getElementById(`custom-${prefix}-legend`);

    if (!flexContainer) {
      flexContainer = document.createElement('div');
      flexContainer.id = `${prefix}-flex`;
      flexContainer.style.cssText = `display: flex; flex-direction: row; 
      align-items: stretch; gap: 20px; min-height: 600px; margin-top: 10px;`;

      const canvasDiv = document.createElement('div');
      canvasDiv.style.cssText = `flex: 1; min-width: 0; position: relative;`;

      legendContainer = document.createElement('div');
      legendContainer.id = `custom-${prefix}-legend`;
      legendContainer.className = 'custom-scroll';
      legendContainer.style.cssText = `width: 240px; overflow-y: auto; max-height: 600px; padding-right: 10px;`;

      canvas.parentNode.insertBefore(flexContainer, canvas);
      canvasDiv.appendChild(canvas);
      flexContainer.appendChild(canvasDiv);
      flexContainer.appendChild(legendContainer);
    }

    return { flexContainer, legendContainer, emptyStateContainer };
  },

  createLegendItem: (container, colorHex, title, rightText, onClickCallback) => {
    const item = document.createElement('div');
    item.className = 'custom-legend-item d-flex align-items-center mb-2 px-2 py-1 rounded';
    item.style.cursor = 'pointer';
    item.style.transition = 'background-color 0.2s';
    item.innerHTML = `
      <span style="display:inline-block; width:10px; height:10px; 
      border-radius:50%; background-color:${colorHex}; margin-right:10px; flex-shrink:0;"></span>
      <span style="font-size:0.85rem; color:#4b5563; font-weight:500; 
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${title}">${title}</span>
      <span style="margin-left:auto; font-size:0.8rem; font-weight:700; color:#111827;">${rightText}</span>`;

    item.addEventListener('mouseenter', () => {
      item.style.backgroundColor = '#f3f4f6';
    });
    item.addEventListener('mouseleave', () => {
      item.style.backgroundColor = 'transparent';
    });
    item.addEventListener('click', onClickCallback);
    container.appendChild(item);
  },

  getTimelinePlugin: (durationMs, startText = 'Start', endText = 'End') => {
    return {
      id: 'contestTimeline',
      beforeDraw: (chart) => {
        const xAxis = chart.scales.x;
        const yAxis = chart.scales.y;
        const ctx = chart.ctx;

        if (xAxis.max <= 10000) {
          return;
        }

        const drawLine = (xVal, color, text, align) => {
          const xPixel = xAxis.getPixelForValue(xVal);
          if (xPixel >= xAxis.left && xPixel <= xAxis.right) {
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(xPixel, yAxis.top);
            ctx.lineTo(xPixel, yAxis.bottom);
            ctx.lineWidth = 2;
            ctx.strokeStyle = color;
            ctx.setLineDash([4, 4]);
            ctx.stroke();

            ctx.fillStyle = color;
            ctx.font = "bold 11px 'Inter', sans-serif";
            ctx.textAlign = align;
            ctx.fillText(text, align === 'left' ? xPixel + 6 : xPixel - 6, yAxis.top + 15);
            ctx.restore();
          }
        };

        drawLine(0, 'rgba(16, 185, 129, 0.8)', startText, 'left');
        if (durationMs > 0) {
          drawLine(durationMs, 'rgba(239, 68, 68, 0.8)', endText, 'right');
        }
      },
    };
  },

  smoothStepData: (rawData, curveOffset) => {
    let smoothData = [];
    for (let i = 0; i < rawData.length; i++) {
      let pt = rawData[i];
      if (i > 0 && pt.x - rawData[i - 1].x > curveOffset * 1.2 && rawData[i - 1].y !== pt.y) {
        smoothData.push({
          ...rawData[i - 1],
          x: pt.x - curveOffset,
          realTime: pt.realTime || pt.x - curveOffset,
          isDummy: true,
          taskName: null,
          delta: 0,
        });
      }
      smoothData.push({ ...pt });
    }
    for (let i = smoothData.length - 1; i >= 0; i--) {
      if (!smoothData[i].isDummy) {
        smoothData[i].isLast = true;
        break;
      }
    }
    return smoothData;
  },

  createLineDataset: (user, data, tension = 0.4) => {
    const baseHex = BacsUtils.getUserColor(user.id || user.userId);
    const colorNormal = baseHex + 'E6';

    return {
      label: user.name,
      userId: user.id || user.userId,
      data: data,
      baseColor: baseHex,
      borderColor: colorNormal,
      backgroundColor: colorNormal,
      fill: false,
      stepped: false,
      tension: tension,
      cubicInterpolationMode: 'monotone',
      borderWidth: 2,
      clip: false,
      pointRadius: data.map((p) => (p.isLast ? 4 : 0)),
      pointBackgroundColor: data.map(() => '#FFFFFF'),
      pointBorderColor: data.map(() => colorNormal),
      pointBorderWidth: data.map(() => 2),
      pointHoverRadius: data.map((p) => (p.isDummy ? 0 : 6)),
      pointHoverBackgroundColor: colorNormal,
      pointHoverBorderColor: '#FFFFFF',
      pointHoverBorderWidth: 2,
    };
  },

  applyManualZoomTimeScale: (chart, inputStart, inputEnd, contestStartSec, resetBtn) => {
    if (!chart) return;
    const t1 = new Date(inputStart.value).getTime();
    const t2 = new Date(inputEnd.value).getTime();
    if (isNaN(t1) || isNaN(t2)) return;

    const startMs = Math.min(t1, t2) - contestStartSec * 1000;
    const endMs = Math.max(t1, t2) - contestStartSec * 1000;

    chart.zoomScale('x', { min: Math.max(0, startMs), max: endMs }, 'default');
    if (resetBtn) resetBtn.classList.remove('d-none');
  },

  initDragZoomTooltip: (canvasId, prefix, formatValueCallback, getChartCallback, signal) => {
    const canvasEl = document.getElementById(canvasId);
    if (!canvasEl) return;

    let dragTooltip = document.getElementById(`${prefix}-drag-tooltip`);
    if (!dragTooltip) {
      dragTooltip = document.createElement('div');
      dragTooltip.id = `${prefix}-drag-tooltip`;
      dragTooltip.innerHTML = `<i class="bi bi-zoom-in" style="color: #60a5fa; margin-right: 5px;"></i> <span id="${prefix}-drag-text"></span>`;
      dragTooltip.style.cssText = `position: absolute; top: 40px; left: 50%; transform: translateX(-50%); background: rgba(17, 24, 39, 0.9); color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif; pointer-events: none; opacity: 0; transition: opacity 0.15s; z-index: 100; white-space: nowrap; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);`;
      canvasEl.parentElement.appendChild(dragTooltip);
      canvasEl.parentElement.style.position = 'relative';
    }

    let isDragging = false;
    let startX = 0;

    canvasEl.addEventListener(
      'mousedown',
      (e) => {
        isDragging = true;
        startX = e.offsetX;
      },
      { signal },
    );

    canvasEl.addEventListener(
      'mousemove',
      (e) => {
        if (!isDragging) return;
        const currentX = e.offsetX;
        if (Math.abs(currentX - startX) > 20) {
          const chart = getChartCallback();
          if (!chart) return;

          const val1 = chart.scales.x.getValueForPixel(startX);
          const val2 = chart.scales.x.getValueForPixel(currentX);

          const str1 = formatValueCallback(Math.min(val1, val2));
          const str2 = formatValueCallback(Math.max(val1, val2));

          document.getElementById(`${prefix}-drag-text`).innerText = `${str1}  →  ${str2}`;
          dragTooltip.style.left = (startX + currentX) / 2 + 'px';
          dragTooltip.style.opacity = '1';
        } else {
          dragTooltip.style.opacity = '0';
        }
      },
      { signal },
    );

    window.addEventListener(
      'mouseup',
      () => {
        isDragging = false;
        dragTooltip.style.opacity = '0';
      },
      { signal },
    );
  },

  toggleDatasetFocus: (chart, datasetIndex, legendContainerId, getFocusedRadiusFn) => {
    const focusedIndices = [];
    chart.data.datasets.forEach((d, i) => {
      if (d.borderWidth === 4) {
        focusedIndices.push(i);
      }
    });

    if (focusedIndices.length === 0) {
      focusedIndices.push(datasetIndex);
    } else {
      const pos = focusedIndices.indexOf(datasetIndex);
      if (pos !== -1) {
        focusedIndices.splice(pos, 1);
      } else {
        focusedIndices.push(datasetIndex);
      }
    }

    if (focusedIndices.length === 0) {
      chart.data.datasets.forEach((d, i) => {
        const colorNormal = d.baseColor + 'E6';
        d.borderColor = colorNormal;
        d.borderWidth = 2;
        d.pointRadius = d.data.map((p) => (p.isLast ? 4 : 0));
        d.pointBackgroundColor = d.data.map(() => '#FFFFFF');
        d.pointBorderColor = d.data.map(() => colorNormal);
        chart.getDatasetMeta(i).order = 0;
      });
      document.querySelectorAll(`#${legendContainerId} .custom-legend-item`).forEach((el) => {
        el.style.opacity = '1';
      });
    } else {
      chart.data.datasets.forEach((d, i) => {
        if (focusedIndices.includes(i)) {
          d.borderColor = d.baseColor;
          d.borderWidth = 4;
          d.pointRadius = d.data.map((p) => getFocusedRadiusFn(p));
          d.pointBackgroundColor = d.data.map(() => d.baseColor);
          d.pointBorderColor = d.data.map(() => '#FFFFFF');
          chart.getDatasetMeta(i).order = -1;
        } else {
          d.borderColor = d.baseColor + '1A';
          d.borderWidth = 1.5;
          d.pointRadius = 0;
          chart.getDatasetMeta(i).order = 0;
        }
      });
      document.querySelectorAll(`#${legendContainerId} .custom-legend-item`).forEach((el, i) => {
        el.style.opacity = focusedIndices.includes(i) ? '1' : '0.3';
      });
    }
    chart.update();
  },

  getLineClickPlugin: (onHitCallback) => {
    return {
      id: 'lineClick',
      afterEvent(chart, args) {
        if (args.event.type !== 'click') {
          return;
        }
        const clickX = args.event.x,
          clickY = args.event.y,
          chartArea = chart.chartArea;

        if (
          clickX < chartArea.left ||
          clickX > chartArea.right ||
          clickY < chartArea.top ||
          clickY > chartArea.bottom
        ) {
          return;
        }

        let closestDatasetIndex = -1;
        let minDistance = Infinity;

        chart.data.datasets.forEach((dataset, i) => {
          const meta = chart.getDatasetMeta(i);
          if (meta.hidden) {
            return;
          }

          for (let j = 0; j < meta.data.length - 1; j++) {
            const p1 = meta.data[j],
              p2 = meta.data[j + 1];
            if (!p1 || !p2 || p1.skip || p2.skip) {
              continue;
            }

            const l2 = Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2);
            let dist;
            if (l2 === 0) {
              dist = Math.hypot(clickX - p1.x, clickY - p1.y);
            } else {
              let t = ((clickX - p1.x) * (p2.x - p1.x) + (clickY - p1.y) * (p2.y - p1.y)) / l2;
              t = Math.max(0, Math.min(1, t));
              const projX = p1.x + t * (p2.x - p1.x);
              const projY = p1.y + t * (p2.y - p1.y);
              dist = Math.hypot(clickX - projX, clickY - projY);
            }

            if (dist < minDistance) {
              minDistance = dist;
              closestDatasetIndex = i;
            }
          }
        });

        if (minDistance < 15 && closestDatasetIndex !== -1) {
          onHitCallback(chart, closestDatasetIndex);
          args.changed = true;
        }
      },
    };
  },

  getTooltipBaseConfig: (customCallbacks) => {
    return {
      backgroundColor: 'rgba(255, 255, 255, 0.95)',
      titleColor: '#1f2937',
      bodyColor: '#4b5563',
      borderColor: '#e5e7eb',
      borderWidth: 1,
      titleFont: { size: 13, weight: 'bold', family: "'Inter', sans-serif" },
      bodyFont: { size: 12, family: "'Inter', sans-serif" },
      padding: 12,
      cornerRadius: 6,
      displayColors: true,
      boxPadding: 6,
      callbacks: customCallbacks,
    };
  },
};
