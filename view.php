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
$standings->mode              = $contest->bacs->mode;

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
$tasks = [];
foreach ($contest->tasks as $task) {
    $standingstask = new stdClass();

    $standingstask->task_id    = $task->task_id;
    $standingstask->name       = $task->name;
    $standingstask->task_order = $task->task_order;

    $tasks[] = $standingstask;
}
$standings->tasksjsonbacs = json_encode($tasks);

// ...prepare localized strings.
$standings->localizedstringsjsonbacs = json_encode([
    'submits'          => get_string('submits', 'mod_bacs'),
    'username'         => get_string('username', 'mod_bacs'),
    'points'           => get_string('points', 'mod_bacs'),
    'penalty'          => get_string('penalty', 'mod_bacs'),
    'lastimprovedat'   => get_string('lastimprovedat', 'mod_bacs'),
    'amountofaccepted' => get_string('amountofaccepted', 'mod_bacs'),
    'amountoftried'    => get_string('amountoftried', 'mod_bacs'),
]);


print $contest->bacsoutput->render($standings);

echo $OUTPUT->footer();
