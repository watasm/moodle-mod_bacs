function load_manage_tasks_css(){
    var css_link = document.createElement("link");
    css_link.setAttribute("rel", "stylesheet");
    css_link.setAttribute("type", "text/css");
    css_link.setAttribute("href", "/mod/bacs/manage_tasks.css");
    document.head.appendChild(css_link);
}
function collection_selector_change() {
    let selector = document.getElementById('collection_container_selector');
    let collections_containers =  selector.children;
    for (let collection_container_info of collections_containers)
    {
        let collection_container =
            document.getElementById('collection_container_' + collection_container_info.value);
        collection_container.style.display = 'none';
    }
    let curr_collection_container =  document.getElementById('collection_container_' + selector.value);
    curr_collection_container.style.display = 'block';
}
function tableSearch() {
    let timestamp_begin = performance.now();

    let phrase = document.getElementById('search-text').value;
    let selector = document.getElementById('collection_container_selector');
    let curr_collection_container = document.getElementById('collection_container_' + selector.value);
    let curr_table_info =
        Array.from(curr_collection_container.getElementsByTagName('table')[0].getElementsByTagName('tbody'));
    let table = curr_table_info[0].getElementsByTagName('tr');
    let phrase_regex = new RegExp(phrase, 'i');

    curr_collection_container.style.display = 'none';
    let visible_rows = 0;

    for (let row of table) {
        let current_row_visible = false;
        let i=0;

        for (let cell of row.children) {
            // ...skip actions column.
            if (i != row.children.length-1 && phrase_regex.test(cell.innerText)) {
                current_row_visible = true;
                break;
            }
            i++;
        }
        if (current_row_visible) {
            row.style.display = '';
            visible_rows++;
        } else {
            row.style.display = 'none';
        }
    }

    curr_collection_container.style.display = 'block';

    let timestamp_end = performance.now();
    console.log('' + (timestamp_end - timestamp_begin) + ' ms used for tableSearch.');
}
function blueShine() {
    var elem = document.getElementById('srchFld');
    // ...elem.style.outline = '1px solid #666';.
    elem.style.boxShadow  = ' 0 0 0 .2rem rgba(17,119,209,.75)';
}
function offShine()
{
    document.getElementById("srchFld").style.boxShadow  = 'none';
}
function cleanSearch() {
    var elem = document.getElementById('search-text');
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