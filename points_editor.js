window.BacsPointsEditor = (function () {
  let pointsMap = null;
  let onSaveCallback = null;
  let loc = null;
  let parseRawString = null;

  let editingId = null;
  let currentModalTestCount = 0;
  let currentModalPretestCount = 0;

  const getEl = (id) => document.getElementById(id);

  function updateRawFromGrid() {
    const ptsRawInput = getEl('modal-points-input');
    const modalFullPoints = getEl('modal-full-points');

    let fullPts = parseInt(modalFullPoints?.value) || 0;
    let arr = [fullPts];
    let gridSum = 0;

    getEl('visual-points-grid')
      .querySelectorAll('.grid-input')
      .forEach((inp) => {
        let val = parseInt(inp.value) || 0;
        arr.push(val);
        gridSum += val;
      });

    if (ptsRawInput) ptsRawInput.value = arr.join(',');
    if (getEl('points-total-sum')) getEl('points-total-sum').textContent = fullPts + gridSum;
  }

  function renderGrid(count, pretestsCount, values = []) {
    const visualGrid = getEl('visual-points-grid');
    visualGrid.innerHTML = '';

    for (let i = 0; i < count; i++) {
      const val = values[i] !== undefined ? values[i] : '0';
      const isPretest = i < pretestsCount;

      const div = document.createElement('div');
      div.className = 'point-item';

      const inputClass = isPretest ? 'grid-input pretest-input' : 'grid-input';
      const labelText = isPretest ? `<span class="text-warning">${loc('pretest', 'Pre')} ${i + 1}</span>` : `${i + 1}`;

      div.innerHTML = `<label>${labelText}</label><input type="text" class="${inputClass}" data-index="${i}" value="${val}" title="${isPretest ? loc('pretest', 'Pretest') : loc('maintest', 'Main Test')}">`;
      visualGrid.appendChild(div);
    }
    visualGrid.querySelectorAll('.grid-input').forEach((inp) => {
      inp.addEventListener('input', updateRawFromGrid);
    });
  }

  function applyValueToGrid(val) {
    getEl('visual-points-grid')
      .querySelectorAll('.grid-input')
      .forEach((inp) => {
        inp.value = val;
      });
    updateRawFromGrid();
  }

  function bindEvents() {
    const modalFullPoints = getEl('modal-full-points');
    const ptsRawInput = getEl('modal-points-input');

    if (modalFullPoints) modalFullPoints.addEventListener('input', updateRawFromGrid);

    ptsRawInput?.addEventListener('input', () => {
      let vals = parseRawString(ptsRawInput.value);
      if (vals.length > 0 && modalFullPoints) modalFullPoints.value = vals[0];
      let tests = vals.slice(1);

      getEl('visual-points-grid')
        .querySelectorAll('.grid-input')
        .forEach((inp, i) => {
          if (tests[i] !== undefined) inp.value = tests[i];
        });
      updateRawFromGrid();
    });

    getEl('btn-range-apply')?.addEventListener('click', () => {
      const s = parseInt(getEl('range-start').value) || 1;
      const e = parseInt(getEl('range-end').value) || currentModalTestCount;
      const val = getEl('range-val').value || '0';

      const inputs = getEl('visual-points-grid').querySelectorAll('.grid-input');
      const startIdx = Math.max(1, s) - 1;
      const endIdx = Math.min(inputs.length, e);

      for (let i = startIdx; i < endIdx; i++) {
        if (inputs[i]) inputs[i].value = val;
      }
      updateRawFromGrid();
    });

    document
      .querySelectorAll('.btn-preset')
      .forEach((btn) => btn.addEventListener('click', () => applyValueToGrid(btn.dataset.val)));

    getEl('btn-clear-grid')?.addEventListener('click', () => applyValueToGrid(0));

    getEl('btn-normalize')?.addEventListener('click', () => {
      const targetSum = parseInt(getEl('norm-target').value) || 100;
      const includePretests = getEl('norm-include-pretests') ? getEl('norm-include-pretests').checked : false;

      const allInputs = Array.from(getEl('visual-points-grid').querySelectorAll('.grid-input'));
      if (allInputs.length === 0) return;

      const inputsToNormalize = [];
      allInputs.forEach((inp, idx) => {
        const isPretest = idx < currentModalPretestCount;
        if (!isPretest || includePretests) {
          inputsToNormalize.push(inp);
        }
      });

      if (inputsToNormalize.length === 0) return;

      let currentValues = inputsToNormalize.map((inp) => Math.max(0, parseFloat(inp.value) || 0));
      let currentSum = currentValues.reduce((a, b) => a + b, 0);
      let newValues = [];

      if (currentSum === 0) {
        const base = Math.floor(targetSum / inputsToNormalize.length);
        const remainder = targetSum % inputsToNormalize.length;
        for (let i = 0; i < inputsToNormalize.length; i++) {
          newValues[i] = base + (i < remainder ? 1 : 0);
        }
      } else {
        let floorValues = [];
        let remainderParts = [];

        for (let i = 0; i < inputsToNormalize.length; i++) {
          let ideal = (currentValues[i] / currentSum) * targetSum;
          let floored = Math.floor(ideal);
          floorValues[i] = floored;
          remainderParts.push({ index: i, fraction: ideal - floored });
        }

        let diff = targetSum - floorValues.reduce((a, b) => a + b, 0);
        remainderParts.sort((a, b) => b.fraction - a.fraction);

        for (let i = 0; i < diff; i++) {
          floorValues[remainderParts[i % inputsToNormalize.length].index]++;
        }
        newValues = floorValues;
      }

      inputsToNormalize.forEach((inp, i) => {
        inp.value = newValues[i];
      });
      updateRawFromGrid();
    });

    getEl('save-points-btn')?.addEventListener('click', () => {
      if (!editingId) return;
      pointsMap[editingId] = getEl('modal-points-input').value;
      if (onSaveCallback) onSaveCallback();
      getEl('test-points-modal').classList.add('hidden');
    });

    const ptsModal = getEl('test-points-modal');
    ptsModal?.querySelector('.close-modal')?.addEventListener('click', () => ptsModal.classList.add('hidden'));
  }

  return {
    init: function (config) {
      pointsMap = config.pointsMap;
      onSaveCallback = config.onSave;
      loc = config.loc;
      parseRawString = config.parseRawString;

      bindEvents();
    },
    open: function (task) {
      editingId = String(task.task_id);
      currentModalTestCount = parseInt(task.count_tests) || 0;
      currentModalPretestCount = parseInt(task.count_pretests) || 0;
      getEl('modal-task-name').textContent = `${task.name}`;

      let savedValues = parseRawString(pointsMap[editingId] || task.default_points || '');
      let fullPts = savedValues.length > 0 ? parseInt(savedValues[0]) || 0 : 0;

      const modalFullPoints = getEl('modal-full-points');
      if (modalFullPoints) modalFullPoints.value = fullPts;

      let testValues = savedValues.slice(1);
      if (currentModalTestCount === 0 && testValues.length > 0) {
        currentModalTestCount = testValues.length;
      }

      renderGrid(currentModalTestCount, currentModalPretestCount, testValues);
      updateRawFromGrid();
      getEl('test-points-modal').classList.remove('hidden');
    },
  };
})();
