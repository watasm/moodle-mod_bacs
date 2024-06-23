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
require_once(dirname(__FILE__) . '/submit_verdicts.php');

require_login();

$contest = new contest();
$contest->pageisallowedforisolatedparticipantbacs = true;
$contest->initialize_page('virtual_contest');

$contest->pageurlbacs = new moodle_url('/mod/bacs/virtual_contest.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->register_query_param('confirmstart', 0, PARAM_INT);

// ...count user submits.
$conditions = [
    "contest_id = " . $contest->bacs->id,
    "user_id = $USER->id",
    "result_id != " . VERDICT_REJECTED,
];
if ($contest->groupsenabledbacs) {
    $conditions[] = "group_id = $contest->currentgroupidbacs";
}
$select = implode(" AND ", $conditions);

$userhasnosubmitshere = ($DB->count_records_select('bacs_submits', $select) == 0);

// ...check confirmed start.
if ($contest->queryparamsbacs->confirmstart == 1) {
    $canvpinnogroups =
        $contest->groupmode == NOGROUPS;

    $canvpingroups =
        $contest->groupsenabledbacs &&
        $contest->currentgroupidbacs != 0 &&
        groups_is_member($contest->currentgroupidbacs, $USER->id);

    $canvp =
        $userhasnosubmitshere &&
        $contest->virtualmodebacs != 0 /* virtual disabled */ &&
        !$contest->isinvirtualparticipationbacs &&
        ($canvpingroups || $canvpinnogroups);

    // ...apply confirmed start.
    if ($canvp) {
        $contestduration = $contest->endtime - $contest->starttime;
        $now = time();

        $newvprecord = new stdClass();
        $newvprecord->user_id = $USER->id;
        $newvprecord->contest_id = $contest->bacs->id;
        $newvprecord->group_id = ($contest->groupmode == NOGROUPS ? 0 : $contest->currentgroupidbacs);
        $newvprecord->starttime = $now;
        $newvprecord->endtime   = $now + $contestduration;

        $DB->insert_record('bacs_virtual_participants', $newvprecord);

        redirect_via_js("view.php?id=" . $contest->coursemodule->id);
        die('Virtual participation registered successfully / Виртуальное участие успешно зарегистрировано.');
    }
}

// ...print page.

$contest->aceeditorshownbacs = false;
$contest->menushownbacs = !$contest->virtualparticipationisforcedbacs;
$contest->print_contest_header('virtual_contest');

$canenterwithoutvirtual =
    !$contest->isinvirtualparticipationbacs &&
    ($contest->usercapabilitiesbacs->viewany || $contest->virtualmodebacs == 1 /* allow virtual */);

$groupisnotselectedproperly =
    $contest->groupsenabledbacs &&
    ($contest->currentgroupidbacs == 0 || !groups_is_member($contest->currentgroupidbacs, $USER->id));

print "<p class='alert alert-warning text-center' role='alert'>"
        . get_string('virtualparticipationgeneralwarning', 'bacs') .
    "</p>";

if ($contest->isinvirtualparticipationbacs) {
    print "<p class='text-center'>"
            . get_string('virtualparticipationstartedat', 'bacs') . " "
            . userdate($contest->get_virtual_participation_for_user()->starttime, "%d %B %Y (%A) %H:%M:%S") .
        "</p>";

    print "<p class='text-center'>
            <a href='view.php?id=" . $contest->coursemodule->id . "'>
                <button class='btn btn-primary'>" . get_string('entercontest', 'bacs') . "</button>
            </a>
        </p>";
} else if ($contest->virtualmodebacs == 0 /* disabled virtual */) {
    print "<p class='alert alert-danger text-center' role='alert'>
            " . get_string('virtualparticipationdisabledmsg', 'bacs')  . "
        </p>";
} else if ($groupisnotselectedproperly) {
    print "<p class='alert alert-danger text-center' role='alert'>
            " . get_string('virtualparticipationselectyourgroup', 'bacs')  . "
        </p>";
} else if (!$userhasnosubmitshere) {
    print "<p class='alert alert-danger text-center' role='alert'>
            " . get_string('virtualparticipationalreadyhavesubmits', 'bacs')  . "
        </p>";
} else {
    // ...if everything is fine.
    print "
        <script type='text/javascript'>
            function bacs_start_virtual() {
                var msg = '" . get_string('virtualparticipationconfirmstartdmsg', 'bacs') . "';

                if (window.confirm(msg)) {
                    window.location.href = 'virtual_contest.php?id=" . $contest->coursemodule->id . "&confirmstart=1';
                }
            }
        </script>

        <p class='text-center'><button class='btn btn-primary' onclick='bacs_start_virtual();'>"
            . get_string('startvirtualparticipationnow', 'bacs') .
        "</button></p>";
}

if ($canenterwithoutvirtual) {
    print "<p class='text-center'>
            <a href='view.php?id=" . $contest->coursemodule->id . "'>
                <button class='btn btn-secondary'>"
                . get_string('entercontestwithoutvirtual', 'bacs') .
                "</button>
            </a>
        </p>";
}

if ($contest->usercapabilitiesbacs->edit) {
    print "<p class='text-center'>
            <a href='virtual_participants_list.php?id=" . $contest->coursemodule->id . "'>
                <button class='btn btn-secondary'>"
                . get_string('virtualparticipantslist', 'bacs') .
                "</button>
            </a>
        </p>";
}

echo $OUTPUT->footer();
