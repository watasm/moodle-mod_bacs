function isInt(value) {
  if (isNaN(value)) { return false; }
  var x = parseFloat(value);
  return (x | 0) === x;
}

function test_editor_clear_contents(){
    'use strict';

    var editor_table = document.getElementById("test_editor_table");

    while (editor_table.rows.length > 1){
        editor_table.deleteRow(-1);
    }
}

function test_editor_check_range(first_, last_){
    'use strict';

    var first = Number(first_);
    var last = Number(last_);

    if ((last < first) || (first < 1)) {
        alert(M.util.get_string('invalidrange', 'bacs'));
        return false;
    }

    var editor_table = document.getElementById("test_editor_table");
    for (var i = 1; i < editor_table.rows.length-1; i++){
        var cur_range = editor_table.rows[i].children[1].innerHTML.split(' - ');
        var cur_first = Number(cur_range[0]);
        var cur_last = Number(cur_range[1]);

        if (cur_last < first) continue;
        if (last < cur_first) continue;

        alert(
            M.util.get_string('testgroup', 'bacs') + ' ' + i +
            ' [' + cur_first + ', ' + cur_last + ']' +
            ' ' + M.util.get_string('coverssametests', 'bacs') + '.'
        );
        return false;
    }
    return true;
}

function test_editor_delete_cur_row(node){
    'use strict';

    var table_row = node;
    while (table_row.tagName !== 'TR') table_row = table_row.parentNode;

    var table_ = table_row.parentNode;
    table_.removeChild(table_row);

    for (var i = 1; i < table_.children.length; i++){
        table_.children[i].firstElementChild.innerHTML = i;
    }

    test_editor_update();
}

function test_editor_add_group(first, last, value){
    'use strict';

    var editor_table = document.getElementById("test_editor_table");

    if (document.getElementById("test_editor_use_custom").checked) {
        editor_table.deleteRow(-1);
    }

    editor_table.insertRow(-1);
    var new_tr = editor_table.rows[editor_table.rows.length - 1];

    for (var i = 0; i < 5; i++) new_tr.insertCell(i);
    new_tr.children[0].innerHTML = editor_table.rows.length-1;
    new_tr.children[1].innerHTML = first.toString() + ' - ' + last.toString();
    new_tr.children[2].innerHTML = value;
    new_tr.children[3].innerHTML = (last - first + 1) * value;

    if (document.getElementById("test_editor_use_custom").checked) {
        new_tr.children[4].innerHTML =
            '<span class="tm_clickable" onclick="test_editor_delete_cur_row(this)">' +
            M.util.get_string('delete', 'bacs') + '</span>';
    } else {
        new_tr.children[4].innerHTML = '-';
    }

    if (document.getElementById("test_editor_use_custom").checked) {
        test_editor_add_input_row();
    }
}

function test_editor_user_add_group(){
    'use strict';

    var first = document.getElementById("test_editor_input_first").value;
    var last  = document.getElementById("test_editor_input_last").value;
    var value = document.getElementById("test_editor_input_value").value;

    if (!(isInt(first) && isInt(last) && isInt(value))) {
        alert(M.util.get_string('fillwithintegers', 'bacs'));
        return false;
    }

    if (!test_editor_check_range(first, last)) return false;

    if (Number(value) < 0) {
        alert(M.util.get_string('negativepointsnotallowed', 'bacs'));
        return false;
    }

    test_editor_add_group(first, last, value);
    test_editor_update();

    return true;
}

function test_editor_change_accepted_points() {
    'use strict';

    var accepted_points_edit = document.getElementById("test_editor_accepted_points");
    var accepted_points_value = accepted_points_edit.value;

    if ( !isInt(accepted_points_value)) {
        alert(M.util.get_string('fillwithintegers', 'bacs'));
        accepted_points_edit.value = 0;
    }

    if (Number(accepted_points_value) < 0) {
        alert(M.util.get_string('negativepointsnotallowed', 'bacs'));
        accepted_points_edit.value = 0;
    }

    test_editor_update();

    return true;
}

function test_editor_get(){
    'use strict';

    var editor_table = document.getElementById("test_editor_table");
    var tests_amount = Number(document.getElementById("test_editor_tests_amount").innerHTML);
    var accepted_points_value = document.getElementById("test_editor_accepted_points").value;
    var result = accepted_points_value + '';

    for (var test = 0; test < tests_amount; test++){
        var cur_value = 0;
        for (var i = 1; i < editor_table.rows.length; i++){
            var cur_range = editor_table.rows[i].children[1].innerHTML.split(' - ');
            var cur_first = Number(cur_range[0]);
            var cur_last = Number(cur_range[1]);

            if ((cur_first <= test+1) && (cur_last >= test+1))
                cur_value = editor_table.rows[i].children[2].innerHTML;
        }
        result += ',' + cur_value;
    }

    console.log(result);
    return result;
}

function test_editor_set(test_string){
    'use strict';

    var accepted_points_edit = document.getElementById("test_editor_accepted_points");

    test_editor_clear_contents();
    if (document.getElementById("test_editor_use_custom").checked) {
        test_editor_add_input_row();
    }

    var values = test_string.split(',');
    
    accepted_points_edit.value = values[0];
    values = values.slice(1);

    if (values.length > 0){
        var first = 0, last = 0, value = values[0];

        for (var i = 0; i < values.length; i++){
            if (values[i] === value){
                last = i;
            } else {
                test_editor_add_group(Number(first)+1, Number(last)+1, value);
                first = i; last = i; value = values[i];
            }
        }
        test_editor_add_group(Number(first)+1, Number(last)+1, value);
    }

    test_editor_update();
}

function test_editor_update(){
    'use strict';

    var task_id = document.getElementById("test_editor_task_selector").value;
    var editor_table = document.getElementById("test_editor_table");
    var accepted_points_value = Number(document.getElementById("test_editor_accepted_points").value);

    var points_sum = accepted_points_value;
    for (var i = 1; i < editor_table.rows.length; i++){
        points_sum += Number(editor_table.rows[i].children[3].innerHTML);
    }

    if ( !(task_id in global_tasks_info)) {
        test_editor_collapse();
        alert(M.util.get_string('pointsformissingtask', 'bacs'));
        return;
    }

    document.getElementById("test_editor_points_sum").innerHTML = points_sum;
    //console.log(task_id);
    document.getElementById("test_editor_tests_amount").innerHTML = global_tasks_info[task_id].count_tests;
    document.getElementById("test_editor_pretests_amount").innerHTML = global_tasks_info[task_id].count_pretests;

    if (document.getElementById("test_editor_use_custom").checked) {
        global_tasks_info[task_id].test_points = test_editor_get();
    }
    
    trl_update_sending_data();
}

function test_editor_collapse(){
    'use strict';

    document.getElementById("test_editor_task_selector").value = '';
    document.getElementById("test_editor_container").classList.add('d-none');
    document.getElementById("test_editor_container").classList.remove('d-block');
    document.getElementById("test_editor_container").classList.remove('d-inline-block');
}

function test_editor_load_task(){
    'use strict';

    var task_id = document.getElementById("test_editor_task_selector").value;

    if (task_id === ""){
        test_editor_collapse();
        return;
    }
    if ( !(task_id in global_tasks_info)) {
        test_editor_collapse();
        alert(M.util.get_string('pointsformissingtask', 'bacs'));
        return;
    }

    if (global_notify_user_to_recalc_points){
        global_notify_user_to_recalc_points = false;
        alert(M.util.get_string('futurepointsnotification', 'bacs'));
    }

    var test_editor_use_custom_checkbox = document.getElementById("test_editor_use_custom");
    var test_editor_accepted_points_edit = document.getElementById("test_editor_accepted_points");

    if (global_tasks_info[task_id].test_points){
        test_editor_use_custom_checkbox.checked = true;
        test_editor_accepted_points_edit.removeAttribute("disabled");

        test_editor_set(global_tasks_info[task_id].test_points);
    } else {
        test_editor_use_custom_checkbox.checked = false;
        test_editor_accepted_points_edit.setAttribute("disabled", true);

        test_editor_set(global_tasks_info[task_id].default_test_points);
    }

    document.getElementById("test_editor_container").classList.add('d-block');
    document.getElementById("test_editor_container").classList.remove('d-none');
    document.getElementById("test_editor_container").classList.remove('d-inline-block');
}

function test_editor_update_options(){
    'use strict';

    test_editor_collapse();

    var task_selector = document.getElementById("test_editor_task_selector");
    while (task_selector.length > 1){
        task_selector.remove(1);
    }

    var tasks_reorder_list = document.getElementById("tasks_reorder_list");
    for (var i = 0; i < tasks_reorder_list.children.length; i++){
        var task_id = tasks_reorder_list.children[i].firstChild.innerHTML;
        var task_name = (task_id in global_tasks_info ? global_tasks_info[task_id].name : "[" + M.util.get_string('uppercasetasknotfound', 'bacs') + "]");
        var new_option = document.createElement("option");
        new_option.innerHTML = task_name + ' (id: ' + task_id +')';
        new_option.value = task_id;
        task_selector.add(new_option);
    }
}

function test_editor_add_input_row(){
    'use strict';

    var editor_table = document.getElementById("test_editor_table");
    editor_table.insertRow(-1);
    var new_tr = editor_table.rows[editor_table.rows.length - 1];

    for (var i = 0; i < 5; i++) new_tr.insertCell(i);
    new_tr.children[0].innerHTML = editor_table.rows.length-1;
    new_tr.children[1].innerHTML =
        "<input type='text' id='test_editor_input_first' size=3>" + "-" +
        "<input type='text' id='test_editor_input_last' size=3>";
    new_tr.children[2].innerHTML = "<input type='text' id='test_editor_input_value' size=3>";
    new_tr.children[3].innerHTML = '';
    new_tr.children[4].innerHTML =
        '<span class="tm_clickable" onclick="test_editor_user_add_group()">' +
            M.util.get_string('add', 'bacs') + 
        '</span>';
}

function test_editor_switch_mode(){
    'use strict';

    var test_editor_accepted_points_edit = document.getElementById("test_editor_accepted_points");
    var task_id = document.getElementById("test_editor_task_selector").value;

    if (document.getElementById("test_editor_use_custom").checked) {
        test_editor_accepted_points_edit.removeAttribute("disabled");

        global_tasks_info[task_id].test_points =
            global_tasks_info[task_id].default_test_points;
    } else {
        test_editor_accepted_points_edit.setAttribute("disabled", true);

        global_tasks_info[task_id].test_points = '';
    }

    test_editor_set(global_tasks_info[task_id].default_test_points);
}
