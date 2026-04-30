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
use mod_bacs\output\standings;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');
require_once(dirname(__FILE__) . '/locale_utils.php');

require_login();

$contest = new contest();
$contest->initialize_page();

$contest->pageurlbacs = new moodle_url('/mod/bacs/view.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->aceeditorshownbacs = false;
$contest->print_contest_header('view');

$standings = new standings();

$standings->usercapabilitiesbacs = $contest->usercapabilitiesbacs;
$standings->coursemoduleidbacs   = $contest->coursemodule->id;
$standings->mode                 = $contest->bacs->mode;

$standings->endtime   = $contest->endtime;
$standings->starttime = $contest->starttime;

if ($contest->currentgroupidbacs == 0) {
    $standings->submitsjsonbacs = $contest->bacs->standings;
} else {
    $groupinfoentry = $contest->currentgroupinfobacs;
    if ($groupinfoentry) {
        $standings->submitsjsonbacs = $groupinfoentry->standings;
    }
    if ($groupinfoentry && $groupinfoentry->use_group_settings) {
        $standings->endtime   = $groupinfoentry->endtime;
        $standings->starttime = $groupinfoentry->starttime;
    }
}

$standings->incidentsjsonbacs = ($contest->usercapabilitiesbacs->viewany ? $contest->bacs->incidents_info : "[]");

// ...prepare students.
$selectedstudents = $contest->get_students();
$formattedstudents = [];
foreach ($selectedstudents as $curstudent) {
    $formattedstudents[] = [
        'id'        => $curstudent->id,
        'firstname' => $curstudent->firstname,
        'lastname'  => $curstudent->lastname,
        'starttime' => $curstudent->starttime,
        'endtime'   => $curstudent->endtime,
    ];
}
$standings->studentsjsonbacs = json_encode($formattedstudents);

// ...prepare tasks.
$tasks_for_js = []; 
$tasks_for_template = []; 
foreach ($contest->tasks as $task) {
    $tasks_for_js[] = [
        'task_id'    => $task->task_id,
        'name'       => bacs_get_localized_name($task),
        'task_order' => $task->task_order, 
    ];
    $tasks_for_template[] = (object)[
        'task_id'    => $task->task_id,
        'name'       => bacs_get_localized_name($task),
        'task_order' => $task->letter,
    ];
}
$standings->tasksjsonbacs = json_encode($tasks_for_js);
// $standings->tasks_for_template = $tasks_for_template;


// ...prepare localized strings.
$strings_for_js = [
    //general
    'submits'          => get_string('submits', 'mod_bacs'),
    'submitslowercase' => get_string('submitslowercase', 'mod_bacs'),
    'username'         => get_string('username', 'mod_bacs'),
    'points'           => get_string('points', 'mod_bacs'),
    'penalty'          => get_string('penalty', 'mod_bacs'),
    'lastimprovedat'   => get_string('lastimprovedat', 'mod_bacs'),
    'amountofaccepted' => get_string('amountofaccepted', 'mod_bacs'),
    'amountoftried'    => get_string('amountoftried', 'mod_bacs'),
    'timefromstart'    => get_string('timefromstart', 'mod_bacs'),
    'showupsolving'    => get_string('showupsolving', 'mod_bacs'),
    'hideupsolving'    => get_string('hideupsolving', 'mod_bacs'),
    'verdict_ok'       => get_string('verdict_ok', 'mod_bacs'),
    'verdict_not_ok'   => get_string('verdict_not_ok', 'mod_bacs'),
    'firstname'        => get_string('firstname', 'mod_bacs'),
    'lastname'         => get_string('lastname', 'mod_bacs'),
    //charts
    'allparticipants'         => get_string('allparticipants', 'mod_bacs'),
    'interval'                => get_string('interval', 'mod_bacs'),
    'auto'                    => get_string('auto', 'mod_bacs'),
    'min_short'               => get_string('min_short', 'mod_bacs'),
    'hour_short'              => get_string('hour_short', 'mod_bacs'),
    'hours_short'             => get_string('hours_short', 'mod_bacs'),
    'day_short'               => get_string('day_short', 'mod_bacs'),
    'days_short'              => get_string('days_short', 'mod_bacs'),
    'week_short'              => get_string('week_short', 'mod_bacs'),
    'resetzoom'               => get_string('resetzoom', 'mod_bacs'),
    'view'                    => get_string('view', 'mod_bacs'),
    'apply'                   => get_string('apply', 'mod_bacs'),
    'nodata'                  => get_string('nodata', 'mod_bacs'),
    'nodatadesc'              => get_string('nodatadesc', 'mod_bacs'),
    'notasksyet'              => get_string('notasksyet', 'mod_bacs'),
    'notasksyetdesc'          => get_string('notasksyetdesc', 'mod_bacs'),
    'tasknotsolvedyet'        => get_string('tasknotsolvedyet', 'mod_bacs'),
    'tasknotsolvedyetdesc'    => get_string('tasknotsolvedyetdesc', 'mod_bacs'),
    'usernotsubmittedyet'     => get_string('usernotsubmittedyet', 'mod_bacs'),
    'usernotsubmittedyetdesc' => get_string('usernotsubmittedyetdesc', 'mod_bacs'),
    'nosubmits'               => get_string('nosubmits', 'mod_bacs'),
    'nosubmitsdesc'           => get_string('nosubmitsdesc', 'mod_bacs'),
    'endofcontest'            => get_string('endofcontest', 'mod_bacs'),
    'classsession'            => get_string('classsession', 'mod_bacs'),
    'usersactive'             => get_string('usersactive', 'mod_bacs'),
    'totalsubmits'            => get_string('totalsubmits', 'mod_bacs'),
    'spammeranomaly'          => get_string('spammeranomaly', 'mod_bacs'),
    'toptasks'                => get_string('toptasks', 'mod_bacs'),
    'clicktoviewsubs'         => get_string('clicktoviewsubs', 'mod_bacs'),
    'period'                  => get_string('period', 'mod_bacs'),
    'elapsedfromstart'        => get_string('elapsedfromstart', 'mod_bacs'),
    'firstaccepted'           => get_string('firstaccepted', 'mod_bacs'),
    'submissionsdetails'      => get_string('submissionsdetails', 'mod_bacs'),
    'classsessiondetails'     => get_string('classsessiondetails', 'mod_bacs'),
    'total'                   => get_string('total', 'mod_bacs'),
    'upsolving_label'         => get_string('upsolving_label', 'mod_bacs'),
    'fail'                    => get_string('fail', 'mod_bacs'),
    'subs'                    => get_string('subs', 'mod_bacs'),
    'usr'                     => get_string('usr', 'mod_bacs'),
    'participant'             => get_string('participant', 'mod_bacs'),
    'statstotal'              => get_string('statstotal', 'mod_bacs'),
    'statsok'                 => get_string('statsok', 'mod_bacs'),
    'statssuccess'            => get_string('statssuccess', 'mod_bacs'),
    'resultsgraphempty'       => get_string('resultsgraphempty', 'mod_bacs'),
    'resultsgraphemptydesc'   => get_string('resultsgraphemptydesc', 'mod_bacs'),
    'leadergraphempty'        => get_string('leadergraphempty', 'mod_bacs'),
    'leadergraphemptydesc'    => get_string('leadergraphemptydesc', 'mod_bacs'),
    'participants'            => get_string('participants', 'mod_bacs'),
    'topparticipants'         => get_string('topparticipants', 'mod_bacs'),
    'rank'                    => get_string('rank', 'mod_bacs'),
    'event'                   => get_string('event', 'mod_bacs'),
    'start'                   => get_string('start', 'mod_bacs'),
    'end'                     => get_string('end', 'mod_bacs'),
];
$standings->localizedstringsjsonbacs = json_encode($strings_for_js);

print $contest->bacsoutput->render($standings);

echo $OUTPUT->footer();