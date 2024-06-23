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

// HEADER STANDART START.
require_once(dirname(__FILE__, 5) . '/config.php');
require_once(dirname(__FILE__) . '/cron_lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_title('MoodlePL plugin cron');
$PAGE->set_heading('MoodlePL plugin');

$PAGE->set_url(new moodle_url('/mod/bacs/cron/cron_bacs/run_cron.php', []));

// Output starts here.
echo $OUTPUT->header();
echo $OUTPUT->heading('Manual cron run');

// HEADER STANDART END.

if (has_capability('mod/bacs:addinstance', $context)) {
    $student = false;
} else {
    $student = true;
}

if ($student) {
    die('You have no permission for this operation!');
}

$cronaction = optional_param('action', '', PARAM_TEXT);

switch ($cronaction) {
    case 'langs':
        cron_langs();
        break;
    case 'tasks':
        cron_tasks();
        break;
    case 'task_url':
        cron_task_url();
        break;
    case 'send':
        cron_send(true);
        break;

    case 'special':
        // ...some dark debugging rituals might happen there.
        bacs_delete_submits(0, 3);
        break;

    default:
        if ($cronaction == '') {
            print "You must specify cron 'action' parameter.";
        } else {
            print "Unknown cron 'action' parameter - '$cronaction'.";
        }
        break;
}

echo $OUTPUT->footer();
