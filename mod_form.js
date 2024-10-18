function load_manage_tasks_css() {
    const css_link = document.createElement("link");
    css_link.rel = "stylesheet";
    css_link.type = "text/css";
    css_link.href = "/mod/bacs/manage_tasks.css";
    document.head.appendChild(css_link);
}

function collection_selector_change() {
    const selector = document.getElementById('collection_container_selector');
    const collections_containers = selector.children;
    
    Array.from(collections_containers).forEach(collection_container_info => {
        const collection_container = document.getElementById('collection_container_' + collection_container_info.value);
        collection_container.style.display = 'none';
    });
    
    const curr_collection_container = document.getElementById('collection_container_' + selector.value);
    curr_collection_container.style.display = 'block';
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
