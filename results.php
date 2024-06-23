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
$contest->pageisallowedforisolatedparticipantbacs = true;
$contest->initialize_page();

$contest->pageurlbacs = new moodle_url('/mod/bacs/results.php', ['id' => $contest->coursemodule->id]);

print $OUTPUT->header();

// ...setup targets.
$contest->register_query_param('user_id', 0, PARAM_INT);
$contest->register_query_param('task_id', 0, PARAM_INT);
$contest->register_query_param('submission_id', 0, PARAM_INT);

$targetuserid = $USER->id;
if ($contest->usercapabilitiesbacs->viewany) {
    $targetuserid = $contest->queryparamsbacs->user_id;
}
if ($targetuserid == 0) {
    $targetuserid = $USER->id;
}

$targettaskid = $contest->queryparamsbacs->task_id;
if (!$DB->record_exists("bacs_tasks_to_contests", ['contest_id' => $contest->bacs->id, 'task_id' => $targettaskid])) {
    $targettaskid = 0;
}

// ...contest header.
$contest->set_results_active_menu_tab_on_user_id($targetuserid);
$contest->aceeditorshownbacs = true;
$contest->aceeditorredirecturlbacs =
    "results.php?id=" . $contest->coursemodule->id . "&user_id=$targetuserid&task_id=$targettaskid&acetheme={acetheme}";
$contest->print_contest_header();

// ...tasks filter selector.
print
'<script type="text/javascript">
    function change_tasks_filter() {
        var task_select = document.getElementById("task_filter_select");
        window.location.href =
        "/mod/bacs/results.php?id=' . $contest->coursemodule->id . '&user_id=' . $targetuserid . '&task_id=" + task_select.value;
    }
</script>';

$htmltasksasoptions = '';
foreach ($contest->tasks as $task) {
    $selectedvalue = ($targettaskid == $task->task_id ? 'selected' : '');
    $htmltasksasoptions .= "<option value=$task->task_id $selectedvalue>$task->lettered_name</option>";
}
$textshowsubmits = get_string('showsubmitsfor', 'bacs');
$textalltasks = get_string('alltasks', 'bacs');
print "
    <div class='form-inline float-left'>
        <b class='mr-2'>$textshowsubmits</b>
        <select id='task_filter_select' class='form-control m-1'
         onchange='change_tasks_filter();' value={{target_task_id}}>
            <option value=0>$textalltasks</option>
            $htmltasksasoptions
        </select>
    </div>";

// ...select submits.
$conditions = [
    'contest_id' => $contest->bacs->id,
    'user_id' => $targetuserid,
];
if ($targettaskid > 0) {
    $conditions['task_id'] = $targettaskid;
}
if ($contest->currentgroupidbacs != 0) {
    $conditions['group_id'] = $contest->currentgroupidbacs;
}

$submits = $DB->get_records('bacs_submits', $conditions, 'submit_time DESC');

// ...setup and render submits.
$results = new results($contest);
$results->configure([
    'show_full_task_names' => false,

    'show_dates_at_separate_rows' => true,
    'show_dates_with_sent_at_time' => false,
    'provide_submit_links_in_body' => true,
    'show_detailed_info' => true,
    'can_be_collapsed' => true,

    'show_column_author' => false,
    'show_column_id' => false,
]);

foreach ($submits as $cursubmit) {
    $resultssubmit = new results_submit();
    $resultssubmit->load_from($cursubmit, $contest, true /* full info */);
    $results->add_submit($resultssubmit);
}

echo $contest->bacsoutput->render($results);

echo $OUTPUT->footer();
