/* eslint-disable */
/* jshint ignore:start */
function collection_selector_change() {
    const selector = document.getElementById('collection_container_selector');
    const collections_containers = selector.children;
    
    Array.from(collections_containers).forEach(collection_container_info => {
        const collection_container = document.getElementById('collection_container_' + collection_container_info.value);
        collection_container.style.display = 'none';
    });
    
    const curr_collection_container = document.getElementById('collection_container_' + selector.value);
    curr_collection_container.style.display = 'block';

    apply_sort();
}

function tableSearch() {
    const timestamp_begin = performance.now();

    const phrase = document.getElementById('search-text').value;
    const selector = document.getElementById('collection_container_selector');
    const curr_collection_container = document.getElementById('collection_container_' + selector.value);
    const curr_table_info = Array.from(curr_collection_container.getElementsByTagName('table')[0].getElementsByTagName('tbody'));
    const table = curr_table_info[0].getElementsByTagName('tr');
    const phrase_regex = new RegExp(phrase, 'i');

    curr_collection_container.style.display = 'none';
    let visible_rows = 0;

    Array.from(table).forEach(row => {
        let current_row_visible = false;

        Array.from(row.children).forEach((cell, i) => {
            // skip actions column
            if (i !== row.children.length - 1 && phrase_regex.test(cell.innerText)) {
                current_row_visible = true;
            }
        });

        row.style.display = current_row_visible ? '' : 'none';
        if (current_row_visible) visible_rows++;
    });

    curr_collection_container.style.display = 'block';

    const timestamp_end = performance.now();
    console.log(`${timestamp_end - timestamp_begin} ms used for tableSearch.`);
}

function blueShine() {
    const elem = document.getElementById('srchFld');
    elem.style.boxShadow = '0 0 0 .2rem rgba(17,119,209,.75)';
}

function offShine() {
    document.getElementById("srchFld").style.boxShadow = 'none';
}

function cleanSearch() {
    const elem = document.getElementById('search-text');
    elem.value = '';
    tableSearch();
}

function getSortable() {
    Sortable.create(
        document.getElementById('tasks_reorder_list'),
        {
            animation: 150,
            filter: '.tm_ignore_move',
            onEnd: trl_update_event
        }
    );
}

function apply_sort() {
    var collSelect = document.getElementById('collection_container_selector');
    var containerId = 'collection_container_' + collSelect.value;
    var container = document.getElementById(containerId);

    if (!container) return;

    var sortSelect = document.getElementById('bacs_sort_selector');
    var sortValue = sortSelect.value;

    var parts = sortValue.split('_');
    var type = parts[0];
    var dir = parts[1];

    var colIndex = (type === 'rating') ? 2 : 0;

    var table = container.querySelector('table');
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.rows);

    rows.sort(function(rowA, rowB) {
        var cellA = rowA.cells[colIndex].innerText.trim();
        var cellB = rowB.cells[colIndex].innerText.trim();
        
        var a = parseFloat(cellA);
        var b = parseFloat(cellB);

        var infiniteVal = (dir === 'asc') ? Infinity : -Infinity;

        if (isNaN(a)) a = infiniteVal;
        if (isNaN(b)) b = infiniteVal;

        if (dir === 'asc') {
            return a - b;
        } else {
            return b - a;
        }
    });

    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}
