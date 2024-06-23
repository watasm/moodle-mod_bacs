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
use mod_bacs\output\results;
use mod_bacs\output\results_submit;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');

require_login();

$contest = new contest();
$contest->initialize_page();

$contest->pageurlbacs = new moodle_url('/mod/bacs/status.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->aceeditorshownbacs = false;
$contest->print_contest_header('status');

// ...select submits.
$conteststudents = $contest->get_students();

$contest->register_query_param('task_id', 0, PARAM_INT);

$targettaskid = $contest->queryparamsbacs->task_id;
if (!$DB->record_exists("bacs_tasks_to_contests", ['contest_id' => $contest->bacs->id, 'task_id' => $targettaskid])) {
    $targettaskid = 0;
}

$contestsubmitsconditions = ['contest_id' => $contest->bacs->id];
if ($contest->groupsenabledbacs && $contest->currentgroupidbacs != 0) {
    $contestsubmitsconditions['group_id'] = $contest->currentgroupidbacs;
}
if ($targettaskid != 0) {
    $contestsubmitsconditions['task_id'] = $targettaskid;
}

$contestsubmits = $DB->get_records(
    'bacs_submits',
    $contestsubmitsconditions,
    'submit_time DESC',
    '*',
    0,
    1000
);

// ...tasks filter selector.
print '
    <script type="text/javascript">
        function change_tasks_filter() {
            var task_select = document.getElementById("task_filter_select");
            window.location.href = "/mod/bacs/status.php?id=' . $contest->coursemodule->id . '&task_id=" + task_select.value;
        }
    </script>';

$htmltasksasoptions = '';
foreach ($contest->tasks as $task) {
    $selectedvalue = ($targettaskid == $task->task_id ? 'selected' : '');
    $htmltasksasoptions .= "<option value=$task->task_id $selectedvalue>$task->lettered_name</option>";
}

print '
    <div class="form-inline float-left">
        <b class="mr-2">' . get_string('showsubmitsfor', 'bacs') . '</b>
        <select id="task_filter_select" class="form-control m-1"
         onchange="change_tasks_filter();" value={{target_task_id}}>
            <option value=0>' . get_string('alltasks', 'bacs') . '</option>
            ' . $htmltasksasoptions . '
        </select>
    </div>';

// ...render submits.
$results = new results($contest);
$results->configure([
    'show_dates_at_separate_rows' => true,
    'show_dates_with_sent_at_time' => false,
    'provide_submit_links_in_header' => true,

    'show_column_collapse' => false,
    'show_column_id'       => false,
    'show_column_time'     => false,
    'show_column_memory'   => false,
]);

foreach ($contestsubmits as $submit) {
    if (!array_key_exists($submit->user_id, $conteststudents)) {
        continue;
    }

    $resultssubmit = new results_submit();
    $resultssubmit->load_from($submit, $contest);
    $results->add_submit($resultssubmit);
}

echo $contest->bacsoutput->render($results);

echo $OUTPUT->footer();
