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

use mod_bacs\groups_settings_form;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');

$id = optional_param('id', 0, PARAM_INT);
if (isset($_POST['bacs_id'])) {
    $id = $_POST['bacs_id'];
}

$cm = get_coursemodule_from_id('bacs', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$bacs = $DB->get_record('bacs', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

if (!has_capability('mod/bacs:edit', $context)) {
    echo "You have no permission for this operation!";
    die();
}

$PAGE->set_url('/mod/bacs/groups_settings.php', ['id' => $cm->id]);

echo $OUTPUT->header();

print "<h1>$bacs->name</h1>";

$mform = new groups_settings_form();

$toform = [];

$groups = groups_get_all_groups($course->id);

foreach ($groups as $curgroup) {
    $groupid = $curgroup->id;
    $groupinfo = $DB->get_record('bacs_group_info', ['contest_id' => $bacs->id, 'group_id' => $groupid], '*', IGNORE_MISSING);

    if ($groupinfo && $groupinfo->use_group_settings) {
        $toform[$groupid . '_upsolving']          = (int)$groupinfo->upsolving;
        $toform[$groupid . '_presolving']         = (int)$groupinfo->presolving;
        $toform[$groupid . '_starttime']          = (int)$groupinfo->starttime;
        $toform[$groupid . '_endtime']            = (int)$groupinfo->endtime;
        $toform[$groupid . '_use_group_settings'] = true;
    } else {
        $toform[$groupid . '_upsolving']          = (int)$bacs->upsolving;
        $toform[$groupid . '_presolving']         = (int)$bacs->presolving;
        $toform[$groupid . '_starttime']          = (int)$bacs->starttime;
        $toform[$groupid . '_endtime']            = (int)$bacs->endtime;
        $toform[$groupid . '_use_group_settings'] = false;
    }
}


$mform->set_data((object)$toform);

// ...form processing and displaying.
if ($mform->is_cancelled()) {
    debugging("cancelled");
} else if ($fromform = $mform->get_data()) {
    $transaction = $DB->start_delegated_transaction();

    foreach ($groups as $curgroup) {
        $contestid = $bacs->id;
        $groupid = $curgroup->id;

        // ...skip missing groups.
        if (!isset($fromform->{$groupid . '_use_group_settings'})) {
            continue;
        }

        $groupinfo = new stdClass();
        $groupinfo->upsolving          = $fromform->{$groupid . '_upsolving'};
        $groupinfo->presolving         = $fromform->{$groupid . '_presolving'};
        $groupinfo->starttime          = $fromform->{$groupid . '_starttime'};
        $groupinfo->endtime            = $fromform->{$groupid . '_endtime'};
        $groupinfo->use_group_settings = $fromform->{$groupid . '_use_group_settings'};

        if ($DB->record_exists('bacs_group_info', ['contest_id' => $contestid, 'group_id' => $groupid])) {
            $record = $DB->get_record('bacs_group_info', ['contest_id' => $contestid, 'group_id' => $groupid]);
            $groupinfo->id = $record->id;
            $DB->update_record('bacs_group_info', $groupinfo);
        } else {
            $groupinfo->contest_id = $contestid;
            $groupinfo->group_id = $groupid;
            $DB->insert_record('bacs_group_info', $groupinfo);
        }
    }

    $transaction->allow_commit();

    redirect_via_js('/mod/bacs/groups_settings.php?id=' . $id);
} else {
    // This branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
    // or on the first display of the form.

    // Set default data (if any).

    // ...displays the form.
    $mform->display();
}


echo $OUTPUT->footer();
