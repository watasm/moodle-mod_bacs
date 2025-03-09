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

require_once(dirname(__FILE__, 3) . '/config.php');
require_once(dirname(__FILE__, 1) . '/lib.php');
require_once(dirname(__FILE__, 1) . '/utils.php');

require_login();

$contest = new contest();
$contest->initialize_page();

$contest->pageurlbacs = new moodle_url('/mod/bacs/incidents.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->aceeditorshownbacs = false;
$contest->print_contest_header('incidents');

// ...check rights.
if (!$contest->usercapabilitiesbacs->viewany) {
    throw new moodle_exception('generalnopermission', 'bacs');
}

// ...load incidents
$incidents = $DB->get_records('bacs_incidents', ['contest_id' => $contest->bacs->id]);

$ims = []; // Lookup dict in form of "$incident->method (incident submit_ids increasing)" => true

$submitsbyincidentid = [];
foreach ($incidents as $incident) {
    $sql = "SELECT submit.id,
                   submit.user_id,
                   submit.contest_id,
                   submit.task_id,
                   submit.lang_id,
                   submit.submit_time,
                   submit.result_id,
                   submit.test_num_failed,
                   submit.points,
                   submit.group_id
              FROM {bacs_incidents_to_submits} its
              JOIN {bacs_submits} submit ON its.submit_id = submit.id
             WHERE its.incident_id = :incident_id
          ORDER BY submit.submit_time DESC
    ";
    $submits = $DB->get_records_sql($sql, ['incident_id' => $incident->id]);

    $submitsbyincidentid[$incident->id] = $submits;

    // Add incident to $ims
    $submitids = [];
    foreach ($submits as $submit) $submitids[] = $submit->id;
    sort($submitids);

    $submitidsasstr = implode(',', $submitids);
    $ims["$incident->method $submitidsasstr"] = true;
}

// Apply incidents elision
$methodselisiontable = [
    'tokenseq' => [],
    'satokenseq' => ['tokenseq'],
    'tokencounts' => ['tokenseq'],
    'satokencounts' => ['tokenseq', 'satokenseq', 'tokencounts'],
    'tokenset' => ['tokenseq', 'tokencounts'],
    'kangaroo' => [],
];

$filteredincidents = [];
foreach ($incidents as $incident) {
    $submitids = [];
    foreach ($submitsbyincidentid[$incident->id] as $submit) $submitids[] = $submit->id;
    sort($submitids);
    $submitidsasstr = implode(',', $submitids);

    $applyelision = false;
    foreach ($methodselisiontable[$incident->method] as $dominatingmethod) {
        if (array_key_exists("$dominatingmethod $submitidsasstr", $ims)) {
            $applyelision = true;
            break;
        }
    }

    if (!$applyelision) {
        $filteredincidents[] = $incident;
    }
}

$incidents = $filteredincidents;

// Count
$incidentcountbymethod = [];
foreach ($incidents as $incident) {
    // Add incident to $incidentcountbymethod
    if (!array_key_exists($incident->method, $incidentcountbymethod)) {
        $incidentcountbymethod[$incident->method] = 0;
    }
    $incidentcountbymethod[$incident->method] += 1;
}

// Output general info
if ($contest->bacs->detect_incidents == 0) {
    print "<p class='alert alert-warning text-center' role='alert'>" . 
        get_string('incidentdetectiondisabledalert', 'mod_bacs') . 
    "</p>";
}

print "<p>" . get_string('totalincidents', 'mod_bacs')  . ": " . count($incidents) . "</p>";

if (count($incidents) > 0) {
    print "<table class='table table-bordered w-auto'>
        <thead>
            <tr>
                <th>" . get_string('method', 'mod_bacs') . "</th>
                <th>" . get_string('count', 'mod_bacs') . "</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($incidentcountbymethod as $method => $incidentcount) {
        print "<tr>
            <td>$method</td>
            <td>$incidentcount</td>
        </tr>";
    }

    print "</tbody>";
    print "</table>";
}

// Iterate incidents
foreach ($incidents as $incident) {
    $incidentinfo = json_decode($incident->info);

    // Prepare submits table
    $results = new results($contest);
    $results->configure([
        'show_dates_at_separate_rows' => false,
        'show_dates_with_sent_at_time' => true,
        'provide_submit_links_in_header' => true,
    
        'show_column_collapse' => false,
        'show_column_id'       => true,
        'show_column_time'     => false,
        'show_column_memory'   => false,
    ]);
    
    foreach ($submitsbyincidentid[$incident->id] as $submit) {
        $resultssubmit = new results_submit();
        $resultssubmit->load_from($submit, $contest);
        $results->add_submit($resultssubmit);
    }

    // Output
    print "<h2>" . get_string('incident', 'mod_bacs') . " $incident->id / " . get_string('method', 'mod_bacs') . " $incident->method</h2>";

    if ($incident->method == 'kangaroo') {
        print "<p>kangaroo_score KS = $incidentinfo->kangaroo_score</p>";
    }

    print $contest->bacsoutput->render($results);
}

echo $OUTPUT->footer();
