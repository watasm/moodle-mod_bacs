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
$contest->initialize_page('contest_not_started');

$PAGE->set_url('/mod/bacs/contest_not_started.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->aceeditorshownbacs = false;
$contest->menushownbacs = false;
$contest->print_contest_header();

if ($contest->virtualmodebacs == 1 /* virtual participation allowed */) {
    print "<p class='alert alert-warning text-center' role='alert'>
        " . get_string('virtualparticipationallowmsg', 'mod_bacs') . "
    </p>";
}
if ($contest->virtualmodebacs == 2 /* virtual participation only */) {
    print "<p class='alert alert-warning text-center' role='alert'>
        " . get_string('virtualparticipationonlymsg', 'mod_bacs') . "
    </p>";
}

print '
    <div class="text-center w-100">
        <h2>' . get_string('beforethecontest', 'mod_bacs') . ':</h2>
        <p class="h1" id="time_before_contest_start">0:00</p>
    </div>

    <script type="text/javascript">
        function recalcExpectationTime(){
            "use strict";

            var container = document.getElementById("time_before_contest_start");
            var time_left = Math.max(0, ' . $contest->starttime . '*1000 - Date.now())

            var time_mod = time_left / 1000 | 0;
            var seconds_left = time_mod % 60;
            time_mod = time_mod / 60 | 0;
            var minutes_left = time_mod % 60;
            time_mod = time_mod / 60 | 0;
            var hours_left = time_mod % 24;
            time_mod = time_mod / 24 | 0;
            var days_left = time_mod;

            var new_time = "";
            if (days_left >= 3) {
                new_time = "' . get_string('morethan', 'mod_bacs') .
    ' " + days_left + " ' . get_string('days_morethanxdays', 'mod_bacs') . '";
            } else {
                new_time =
                    days_left + ":" +
                    ("0"+hours_left).slice(-2) + ":" +
                    ("0"+minutes_left).slice(-2) + ":" +
                    ("0"+seconds_left).slice(-2);
                new_time = new_time.replace(/^[0\:]{1,6}/, "");
            }
            container.innerHTML = new_time;
            //container.innerHTML = time_left;

            if (time_left === 0) {
                if (!container.hasAttribute("asked")) {
                    container.setAttribute("asked", "asked");
                    if (confirm("' . get_string('contesthasstartednotification', 'mod_bacs') . '"))
                        window.location.href = "tasks.php?id=' . $contest->coursemodule->id . '";
                }
            }
        }

        recalcExpectationTime();

        window.setTimeout(function(){
            window.setInterval(function(){
                recalcExpectationTime();
            }, 1000);
        }, 1000 - Date.now() % 1000);
    </script>
';

echo $OUTPUT->footer();
