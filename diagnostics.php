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


require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login();

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

$PAGE->set_title('MoodlePL plugin diagnostics');
$PAGE->set_heading('MoodlePL plugin diagnostics');

$PAGE->set_url(new moodle_url('/mod/bacs/diagnostics.php', []));

// Output starts here.
print $OUTPUT->header();

if (!has_capability('moodle/site:config', $systemcontext)) {
    throw new moodle_exceprion('generalnopermission', 'bacs');
}

$selectedchecksparam = optional_param('checks', '', PARAM_RAW);
$selectedchecks = explode(',', $selectedchecksparam);

$availablechecks = bacs_diagnostics_available_checks();

if ($selectedchecksparam == '') {
    $selectedchecks = $availablechecks;
}

$checkresults = [];

// ...run checks and write logs.
print "<div class='container'><p><button class='btn btn-info collapsed' data-toggle='collapse' data-target='#logscontainer'>" .
        get_string('diagnostics:showdetailedlogs', 'mod_bacs') .
    "</button></p>";
print "<div id='logscontainer' class='collapse'>";

foreach ($selectedchecks as $diagnosticcheck) {
    $checkfunction = "bacs_diagnostics_$diagnosticcheck";

    print "<h2>$diagnosticcheck</h2>";

    $timestart = microtime(true);

    try {
        $checkresult = $checkfunction();
    } catch (Throwable $e) {
        print $e->getMessage();

        $checkresult = (object) [
            'error_level' => 2,
            'message_short' => get_string('diagnostics:error', 'mod_bacs'),
            'message_long' => $e->getMessage(),
        ];
    }

    $timeend = microtime(true);

    if (!($checkresult instanceof stdClass)) {
        $checkresult = new stdClass();
    }

    if (!isset($checkresult->error_level)) {
        $checkresult->error_level = 2;
    }

    if (!isset($checkresult->message_short)) {
        $checkresult->message_short = get_string('diagnostics:error', 'mod_bacs');
    }

    if (!isset($checkresult->message_long)) {
        $checkresult->message_long = "";
    }

    $checkresult->duration = round(($timeend - $timestart) * 1e3);
    $checkresult->check_name = $diagnosticcheck;

    $checkresults[] = $checkresult;
}

print "</div>";

print "<table class='table table-sm'>
    <thead><tr>
        <th>" . get_string('diagnostics:check', 'mod_bacs') . "</th>
        <th>" . get_string('diagnostics:message', 'mod_bacs') . "</th>
        <th>" . get_string('diagnostics:duration', 'mod_bacs') . "</th>
        <th>" . get_string('diagnostics:status', 'mod_bacs') . "</th>
    </tr></thead>
    <tbody>";

$visualclassbyerrorlevel = [
    0 => "bg-success text-light",
    1 => "bg-warning text-dark",
    2 => "bg-danger text-light",
];

foreach ($checkresults as $checkresult) {
    $visualclass = $visualclassbyerrorlevel[$checkresult->error_level];

    print "<tr>
        <td>$checkresult->check_name</td>
        <td>$checkresult->message_long</td>
        <td>$checkresult->duration " . get_string('diagnostics:milliseconds_short', 'mod_bacs') . "</td>
        <td><span class='badge $visualclass'>$checkresult->message_short</span></td>
    </tr>";
}

print "</tbody></table></div>";

print $OUTPUT->footer();
