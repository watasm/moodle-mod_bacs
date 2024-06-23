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

$contest->pageurlbacs = new moodle_url('/mod/bacs/results_for_submit.php', ['id' => $contest->coursemodule->id]);

print $OUTPUT->header();

// ...setup targets.
$contest->register_query_param('submit_id', 0, PARAM_INT);

$submitid = $contest->queryparamsbacs->submit_id;

// ...check rights.
$contest->require_capability_for_exact_submit($submitid);

// ...find submit.
$submit = $DB->get_record('bacs_submits', ['id' => $submitid], '*', MUST_EXIST);
$targettaskid = $submit->task_id;
$targetuserid = $submit->user_id;

// ...contest header.
$contest->aceeditorshownbacs = true;
$contest->aceeditorredirecturlbacs =
    "results_for_submit.php?id=" . $contest->coursemodule->id . "&submit_id=$submitid&acetheme={acetheme}";
$contest->print_contest_header();

// ...buttons.
print "<a href='results.php?id=" . $contest->coursemodule->id . "&user_id=$targetuserid'>
        <button class='btn btn-info m-1'>" . get_string('backtosubmits', 'mod_bacs') . "</button>
    </a>";

// ...setup and render submits.
$results = new results($contest);
$results->configure([
    'show_detailed_info' => true,
    'can_be_collapsed' => false,
    'solutions_can_be_collapsed' => false,
    'ace_editor_big' => true,
    'show_column_collapse' => false,
    'show_column_n' => false,
    'show_submit_actions_panel' => true,
]);

$resultssubmit = new results_submit();
$resultssubmit->load_from($submit, $contest, true /* full info */);
$resultssubmit->iscollapsedbacs = false;
$resultssubmit->solutioniscollapsedbacs = false;
$results->add_submit($resultssubmit);

echo $contest->bacsoutput->render($results);

echo $OUTPUT->footer();
