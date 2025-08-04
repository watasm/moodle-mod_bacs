<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Description
 *
 * @package    mod_bacs
 * @copyright  SybonTeam, sybon.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bacs\contest;
use mod_bacs\output\tasklist;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');
require_once(dirname(__FILE__) . '/locale_utils.php');

require_login();

$contest = new contest();
$contest->pageisallowedforisolatedparticipantbacs = true;
$contest->initialize_page();

$contest->pageurlbacs = new moodle_url('/mod/bacs/tasks.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->aceeditorshownbacs = true;
$contest->print_contest_header('tasks');

$contest->prepare_last_used_lang();

$tasklist = new tasklist();

$now = time();
$recenttime = $now - 5 * 60;
$tasklist->recentsubmitsbacs = $DB->count_records_select('bacs_submits', "submit_time > $recenttime AND user_id = $USER->id");

$tasklist->coursemoduleidbacs   = $contest->coursemodule->id;
$tasklist->usercapabilitiesbacs = $contest->usercapabilitiesbacs;
$tasklist->showpointsbacs       = $contest->get_show_points();

foreach ($contest->tasks as $task) {
    $tasklisttask = new stdClass();

    // Получаем предпочитаемые языки из настроек модуля
    $preferedlanguages = explode(',', get_config('mod_bacs', 'preferedlanguages'));
    $preferedlanguages = array_filter($preferedlanguages); // Убираем пустые значения
    // Получаем текущий язык интерфейса Moodle
    $currentlang = current_language();
    
    $tasklisttask->statement_url = $task->statement_url;

    if(!isset($task->statement_urls) || $task->statement_urls == "null") {
        $task->statement_urls = json_encode(["ru" => $task->statement_url]);
    }

    if(isset($task->statement_urls)) {
        $tasklisttask->statement_urls = json_decode($task->statement_urls, true);
        $tasklisttask->is_multi_statements = empty($tasklisttask->statement_urls) ? false : count($tasklisttask->statement_urls) > 0;
        
        if($tasklisttask->is_multi_statements) {
            $tasklisttask->statement_urls = bacs_filter_multilingual_data($tasklisttask->statement_urls, $preferedlanguages, 'url');

            if(count($preferedlanguages) == 1) {
                // Ищем URL по приоритету: предпочитаемый язык -> C -> RU -> первый доступный
                $preferred_url = bacs_find_value_by_lang($tasklisttask->statement_urls, $preferedlanguages[0], 'url');
                
                if ($preferred_url === null) {
                    $preferred_url = bacs_find_value_by_lang($tasklisttask->statement_urls, 'C', 'url');
                }
                
                if ($preferred_url === null) {
                    $preferred_url = bacs_find_value_by_lang($tasklisttask->statement_urls, 'RU', 'url');
                }
                
                // Если ничего не найдено, берем первый доступный
                if ($preferred_url === null && !empty($tasklisttask->statement_urls)) {
                    $preferred_url = $tasklisttask->statement_urls[0]['url'];
                }
                $tasklisttask->is_multi_statements = false;
                $tasklisttask->statement_url = $preferred_url;
            }
        }
    }

    if(isset($task->names)) {
        $tasklisttask->names = json_decode($task->names, true);
        $tasklisttask->is_multi_names = empty($tasklisttask->names) ? 0 : count($tasklisttask->names) > 0;
        if($tasklisttask->is_multi_names) {
            $tasklisttask->names = bacs_filter_multilingual_data($tasklisttask->names, [$currentlang], 'name');
        }
    }


    $tasklisttask->statement_format = $task->statement_format;
    $tasklisttask->name             = $task->name;
    $tasklisttask->letter           = $task->letter;
    $tasklisttask->task_id          = $task->task_id;
    $tasklisttask->task_order       = $task->task_order;
    $tasklisttask->is_missing       = $task->is_missing;
    $tasklisttask->langs            = $contest->langs;

    $tasklisttask->statement_format_is_html =
        (strtoupper($tasklisttask->statement_format) == 'HTML');


    $showsubmitsspamwarning = ($tasklist->recentsubmitsbacs > 40);
    $showsubmitsspampenalty = ($tasklist->recentsubmitsbacs > 50);

    $tasklisttask->can_submit = true;
    $tasklisttask->can_submit_message = "";

    if ($showsubmitsspamwarning) {
        $tasklisttask->can_submit_message = "<div class='alert alert-warning text-center' role='alert'>" .
            get_string('submissionsspamwarning', 'mod_bacs') .
        "</div>";
    }

    $now = time();

    $showforbiddenupsolving =
        $contest->upsolving == 0 &&
        $contest->endtime <= $now;

    $showvisiblegroupschangetosubmit =
        $contest->groupmode == VISIBLEGROUPS &&
        !$contest->usercapabilitiesbacs->accessallgroups &&
        !groups_is_member($contest->currentgroupidbacs, $USER->id);

    if ($task->is_missing) {
        $tasklisttask->can_submit = false;
        $tasklisttask->can_submit_message = "<div class='alert alert-danger text-center' role='alert'>" .
            get_string('submitmessagetaskismissing', 'mod_bacs') .
        "</div>";
    } else if ($showvisiblegroupschangetosubmit) {
        $tasklisttask->can_submit = false;
        $tasklisttask->can_submit_message = "<div class='alert alert-warning text-center' role='alert'>" .
            get_string('changegrouptosubmit', 'mod_bacs') .
        "</div>";
    } else if ($showforbiddenupsolving) {
        $tasklisttask->can_submit = false;
        $tasklisttask->can_submit_message = "<div class='alert alert-warning text-center' role='alert'>" .
            get_string('upsolvingisdisabled', 'mod_bacs') .
        "</div>";
    } else if ($showsubmitsspampenalty) {
        $tasklisttask->can_submit = false;
        $tasklisttask->can_submit_message = "<div class='alert alert-danger text-center' role='alert'>" .
            get_string('submissionsspampenalty', 'mod_bacs') .
        "</div>";
    } else if (!$contest->usercapabilitiesbacs->submit) {
        $tasklisttask->can_submit = false;
        $tasklisttask->can_submit_message = "<div class='alert alert-danger text-center' role='alert'>" .
            get_string('nopermissiontosubmit', 'mod_bacs') .
        "</div>";
    }

    $submitconditions = [
        'contest_id' => $contest->bacs->id,
        'user_id' => $USER->id,
        'task_id' => $task->task_id,
    ];
    if ($contest->currentgroupidbacs != 0) {
        $submitconditions['group_id'] = $contest->currentgroupidbacs;
    }

    $submits = $DB->get_records('bacs_submits', $submitconditions);

    $tasklisttask->points = "-";
    $tasklisttask->tr_color_class = "verdict-none";

    foreach ($submits as $submit) {
        if ($submit->result_id == VERDICT_PENDING) {
            continue;
        }
        if ($submit->result_id == VERDICT_RUNNING) {
            continue;
        }

        if (is_int($tasklisttask->points)) {
            $tasklisttask->points = max($tasklisttask->points, intval($submit->points));
        } else {
            $tasklisttask->tr_color_class = "verdict-failed";
            $tasklisttask->points = intval($submit->points);
        }

        if ($submit->result_id == VERDICT_ACCEPTED) {
            $tasklisttask->tr_color_class = "verdict-accepted";
        }
    }

    $tasklisttask->time_formatted   = format_time_consumed($task->time_limit_millis);
    $tasklisttask->memory_formatted = format_memory_consumed($task->memory_limit_bytes);

    $tasklisttask->change_lang_js = "aceeditsessions[$task->task_order].session.setMode(
        'ace/mode/' + document.getElementById('acelangselect$task->task_order')
            .options[document.getElementById('acelangselect$task->task_order').selectedIndex]
            .dataset
            .acemode
    );";

    $tasklisttask->submit_onclick_js = "
        document.getElementById('sendbuttonlocked$task->task_order').classList.add('d-inline-block');
        document.getElementById('sendbutton$task->task_order').classList.add('d-none');
    ";

    if ($tasklisttask->can_submit) {
        $prepareaceeditorjs = "prepare_editor($task->task_order)";
    } else {
        $prepareaceeditorjs = '';
    }

    $tasklisttask->td_toggle_attr = "
        data-toggle='collapse'
        data-target='#collapse$task->task_order'
        onclick='$prepareaceeditorjs'
    ";

    $tasklisttask->submit_key = md5($USER->email . $USER->sesskey . $contest->coursemodule->id . $task->task_id);

    $tasklist->add_task($tasklisttask);
}

print $contest->bacsoutput->render($tasklist);


echo $OUTPUT->footer();
