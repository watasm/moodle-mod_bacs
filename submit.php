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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');

require_login();

$contest = new contest();
$contest->pageisallowedforisolatedparticipantbacs = true;
$contest->initialize_page();

$contest->pageurlbacs = new moodle_url('/mod/bacs/submit.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->aceeditorshownbacs = false;
$contest->aceeditorredirecturlbacs = "tasks.php?id=" . $contest->coursemodule->id . "&acetheme={acetheme}";
$contest->print_contest_header('tasks');

$contest->register_query_param('task_id', 0, PARAM_INT);
$contest->register_query_param('lang_id', 0, PARAM_INT);
$contest->register_query_param('key', 0, PARAM_RAW);

$taskfound = false;
foreach ($contest->tasks as $task) {
    if ($contest->queryparamsbacs->task_id == $task->task_id) {
        $taskfound = true;
    }
}

$now = time();
$recenttime = $now - 5 * 60;
$recentsubmits = $DB->count_records_select('bacs_submits', "submit_time > $recenttime AND user_id = $USER->id");

$showsubmitsspamwarning = ($recentsubmits > 40);
$showsubmitsspampenalty = ($recentsubmits > 50);

$cansubmit =
    $taskfound &&
    $contest->usercapabilitiesbacs->submit &&
    ($contest->upsolving == 1 || $contest->endtime > $now) &&
    !$showsubmitsspampenalty;

$submitkey = md5($USER->email . $USER->sesskey . $contest->coursemodule->id . $contest->queryparamsbacs->task_id);

if ($cansubmit && $contest->queryparamsbacs->key == $submitkey) {
    $source = optional_param("source", null, PARAM_RAW);

    if (isset($source) && ($source != "")) {
        $record = new stdClass();

        $record->user_id = $USER->id;
        $record->contest_id = $contest->bacs->id;
        $record->group_id = $contest->currentgroupidbacs;
        $record->task_id = $contest->queryparamsbacs->task_id;
        $record->lang_id = $contest->queryparamsbacs->lang_id;
        $record->source = $source;
        $record->result_id = 1;
        $record->submit_time = time();

        $submitid = $DB->insert_record('bacs_submits', $record);

        if ($contest->bacs->detect_incidents == 1) {
            bacs_mark_submit_for_incidents_recalc($submitid);
        }

        // ...redirect.
        print "Successful submit / Успешная отправка";
        bacs_redirect_via_js("results.php?id=" . $contest->coursemodule->id);
    } else {
        print "No submit / Нет посылки";
        bacs_redirect_via_js("tasks.php?id=" . $contest->coursemodule->id);
    }
} else {
    print "Error occured on submitting / Произошла ошибка при отправке";
}

die();

// ...never printed due to faster redirection.
echo $OUTPUT->footer();
