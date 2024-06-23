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

$contest->pageurlbacs = new moodle_url('/mod/bacs/results_diff.php', ['id' => $contest->coursemodule->id]);

print $OUTPUT->header();

// ...setup targets.
$contest->register_query_param('submit_id', 0, PARAM_INT);
$contest->register_query_param('pretest_id', 0, PARAM_INT);

$submitid = $contest->queryparamsbacs->submit_id;
$pretestid = $contest->queryparamsbacs->pretest_id;

// ...check rights.
$contest->require_capability_for_exact_submit($submitid);

// ...find submit.
$submit = $DB->get_record('bacs_submits', ['id' => $submitid], '*', MUST_EXIST);
$submitpretest = $DB->get_record('bacs_submits_tests_output', ['submit_id' => $submitid, 'test_id' => $pretestid], '*', MUST_EXIST);
$taskpretest = $DB->get_record(
    'bacs_tasks_test_expected',
    ['task_id' => $submit->task_id, 'test_id' => $pretestid],
    '*',
    MUST_EXIST
);
$targetuserid = $submit->user_id;

// ...contest header.
$contest->set_results_active_menu_tab_on_user_id($targetuserid);
$contest->aceeditorshownbacs = false;
$contest->print_contest_header();

print "<script type='text/javascript' src='diff_match_patch_uncompressed.js'></script>";

print "<a href='results.php?id=" . $contest->coursemodule->id . "&user_id=$targetuserid'>
        <button class='btn btn-info m-1'>" . get_string('backtosubmits', 'bacs') . "</button>
    </a>

    <p>
        <b>" . get_string('input', 'bacs') . ":</b><br>
        <pre>" . htmlspecialchars($taskpretest->input) . "</pre>
    </p>
    <p>
        <b>" . get_string('outputexpected', 'bacs') . ":</b><br>
        <pre id='answer'>" . htmlspecialchars($taskpretest->expected) . "</pre>
    </p>
    <p>
        <b>" . get_string('outputreal', 'bacs') . ":</b><br>
        <pre id='correct_answer'>" . htmlspecialchars($submitpretest->output) . "</pre>
    </p>
    <p>
        <b>" . get_string('comparison', 'bacs') . ":</b><br>
        <pre id='diff_output'></pre>
    </p>
    <div>
        <p><span class='diff_delete white-space-pre'> </span> - " . get_string('charactermustberemoved', 'bacs') . "</p>
        <p><span class='diff_add white-space-pre'> </span> - " . get_string('charactermustbeadded', 'bacs') . "</p>
    </div>

    <script type='text/javascript'>

        let text1 = document.getElementById('answer');
        let text2 = document.getElementById('correct_answer');
        let diff_output = document.getElementById('diff_output');

        let dmp = new diff_match_patch();
        let diff = dmp.diff_main(text2.innerText, text1.innerText);

        for (let element of diff) for (let character of element[1]) {
            let tagForAdd = document.createElement('span');
            if (element[0] === -1) {
                tagForAdd.className = 'diff_delete';
            } else if (element[0] === 1) {
                tagForAdd.className = 'diff_add';
            }
            if (character === '\\n') {
                character = '\u00B6';
            }
            tagForAdd.innerHTML = character;
            diff_output.append(tagForAdd);
            if (character === '\u00B6') {
                diff_output.innerHTML += '<br>';
            }
        }

    </script>
";


echo $OUTPUT->footer();
