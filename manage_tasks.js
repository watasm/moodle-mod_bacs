function trl_letter_column_add() {
    const letters_column = document.getElementById("letters_column");
    if (letters_column.children.length < 26) {
        const next_letter_char = String.fromCharCode("A".charCodeAt() + letters_column.children.length);
        const new_letter = document.createElement("div");
        new_letter.setAttribute("class", "letter_list_item");
        new_letter.innerHTML = next_letter_char + ".";
        letters_column.appendChild(new_letter);
    } else {
        alert(M.util.get_string('letterlimit26', 'bacs'));
    }
}

function trl_letter_column_pop() {
    const letters_column = document.getElementById("letters_column");
    if (letters_column.children.length > 0) {
        letters_column.removeChild(letters_column.lastElementChild);
    } else {
        alert(M.util.get_string('letterlistempty', 'bacs'));
    }
}

function trl_add_task(task_id) {
    const tasks_list = document.getElementById("tasks_reorder_list");

    let task_name;
    if (task_id in global_tasks_info) {
        task_name = global_tasks_info[task_id].name;
    } else {
        task_name = "[" + M.util.get_string('uppercasetasknotfound', 'bacs') + ", ID = " + task_id + "]";
    }

    // overflow 26 check
    if (tasks_list.children.length >= 26) {
        alert(M.util.get_string('maximumtasks26', 'bacs'));
        return false;
    }

    // check for duplicate
    for (let i = 0; i < tasks_list.children.length; i++) {
        if (tasks_list.children[i].firstChild.innerHTML === task_id.toString()) {
            alert(M.util.get_string('duplicatetasks', 'bacs'));
            return false;
        }
    }

    // add task
    const delete_task_button = 
        '<a class="tm_ignore_move tm_action_menu_holder cursor-pointer" onclick="trl_delete_task(' + task_id + ')">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash-fill" viewBox="0 0 16 16">' +
                '<path d="M2.5 1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1H3v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4h.5a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1H2.5zm3 4a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5zM8 5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7A.5.5 0 0 1 8 5zm3 .5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 1 0z"/>' +
            '</svg> ' +
            M.util.get_string('delete', 'bacs') + 
        '</a>';

    const new_task_el = document.createElement("div");
    new_task_el.setAttribute("class", "tasks_reorder_list_item");
    new_task_el.innerHTML =
        '<span class="tasks_reorder_list_idholder">' + task_id + '</span>' +
        task_name +
        '<span class="tasks_reorder_list_addinfo">' +
            '<span class="tasks_reorder_list_idinfo">' + '  (id: ' + task_id + ')</span> ' +
            delete_task_button +
        '</span>';
    tasks_list.appendChild(new_task_el);

    trl_letter_column_add();
    trl_update();

    return true;
}

function trl_delete_task(id) {
    const tasks_list = document.getElementById("tasks_reorder_list");
    for (let i = 0; i < tasks_list.children.length; i++) {
        if (tasks_list.children[i].firstElementChild.innerHTML === id.toString()) {
            tasks_list.removeChild(tasks_list.children[i]);
            trl_letter_column_pop();
            trl_update();
            return true;
        }
    }
    alert(M.util.get_string('errordeletingtask', 'bacs') + id);
    return false;
}

function trl_update() {
    trl_update_sending_data();
    test_editor_update_options();
}

function trl_update_sending_data() {
    const tasks_list = document.getElementById("tasks_reorder_list");
    let task_id_list = '';
    let task_tests_list = '';

    for (let i = 0; i < tasks_list.children.length; i++) {
        const task_id = tasks_list.children[i].firstElementChild.innerHTML;
        task_id_list += task_id + '_';

        const task_test_points = (task_id in global_tasks_info) ? (global_tasks_info[task_id].test_points || '') : '';
        task_tests_list += task_test_points + '_';
    }

    task_id_list = task_id_list.slice(0, -1);
    task_tests_list = task_tests_list.slice(0, -1);

    // apply result
    const task_id_list_holder = document.getElementsByName("contest_task_ids")[0];
    task_id_list_holder.setAttribute("value", task_id_list);
    const task_tests_list_holder = document.getElementsByName("contest_task_test_points")[0];
    task_tests_list_holder.setAttribute("value", task_tests_list);
}

function trl_update_event(evt) {
    trl_update();
}
