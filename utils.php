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

/**
 * Elo rating calculation constants for difficulty analysis
 */
if (!defined('BACS_ELO_BASE_RATING')) {
    define('BACS_ELO_BASE_RATING', 1200);
}
if (!defined('BACS_ELO_LOG_SCALE_FACTOR')) {
    define('BACS_ELO_LOG_SCALE_FACTOR', 500);
}
if (!defined('BACS_ELO_K_FACTOR')) {
    define('BACS_ELO_K_FACTOR', 16);
}

/**
 * Class status_contest
 * @package mod_bacs
 */
class status_contest {
    /**
     * @var mixed
     */
    private $starttime = 0;
    /**
     * @var mixed
     */
    private $endtime = 0;
    /**
     * @var mixed
     */
    private $runtime = 0;
    /**
     * @var mixed
     */
    private $tottime = 0;
    /**
     * @var mixed
     */
    private $status = 0;

    /**
     *
     */
    public function __construct() {
    }

    /**
     * This function
     * @return void
     */
    public function bacs_set() {
        global $bacs;
        $starttime = (int)$bacs->starttime;
        $endtime = (int)$bacs->endtime;

        if ($endtime < $starttime) {
            $endtime = $starttime;
        }

        $runtime = (time() - $starttime) / 60;
        $tottime = ($endtime - $starttime) / 60;
        $status = 0;

        if ($status == 0 && $runtime < 0) {
            $status = -1;
        } else if ($status == 0 && $runtime > $tottime) {
            $status = 2;
        }

        if ($runtime < 0) {
            $runtime = 0;
        } else if ($runtime > $tottime) {
            $runtime = $tottime;
        }

        $this->starttime = $starttime;
        $this->endtime = $endtime;
        $this->runtime = $runtime;
        $this->tottime = $tottime;
        $this->status = $status;
    }

    /**
     * This function
     * @return int|mixed
     */
    public function bacs_get_status() {
        return $this->status;
    }

    /**
     * This function
     * @return lang_string|string
     * @throws coding_exception
     */
    public function bacs_get_statustext() {
        switch ($this->status) {
            case -1:
                $statustext = get_string('statusnotstarted', 'mod_bacs');
                break;
            case 0:
                $statustext = get_string('statusrunning', 'mod_bacs');
                break;
            case 1:
                $statustext = get_string('statusfrozen', 'mod_bacs');
                break;
            case 2:
                $statustext = get_string('statusover', 'mod_bacs');
                break;
            default:
                $statustext = get_string('statusunknown', 'mod_bacs');
        }
        return $statustext;
    }

    /**
     * This function
     * @return string
     * @throws coding_exception
     */
    public function bacs_get_fullstatusstring() {
        return get_string('time', 'mod_bacs') .
            ': <b>' . (int)$this->runtime . '</b> / <b>' . (int)$this->tottime . '</b>. ' .
            get_string('status', 'mod_bacs') .
            ': <b>' . $this->bacs_get_statustext() . '</b>.<br>';
    }

    /**
     * This function
     * @return int|mixed
     */
    public function bacs_get_endtime() {
        return $this->endtime;
    }
}

/**
 * This function
 * @return array|array[]
 */
function bacs_get_my_groups() {
    $mygroups = groups_get_my_groups();
    $group = [[]];
    foreach ($mygroups as $msg) {
        $group['id'][] = $msg->id;
        $group['name'][] = $msg->name;
    }
    return $group;
}

/**
 * This function
 * @param string $link
 * @return string
 * @throws coding_exception
 * @throws dml_exception
 */
function bacs_menu($link) {
    global $cm, $DB, $USER, $student;
    if (is_null($link) || $link == "") {
        $link = 'view';
    }

    if (!$student && $link == 'results') {
        $customuserid = optional_param('user_id', 0, PARAM_INT);
        if ($customuserid > 0 && $customuserid != $USER->id) {
            $customuser = $DB->get_record('user', ['id' => $customuserid], 'firstname, lastname', IGNORE_MISSING);
            if (isset($customuser)) {
                $link = 'anothers_results';
            }
        }
    }

    $menuitems = [
        'view' => '<i class="icon-flag"></i> ' . get_string('standings', 'mod_bacs'),
        'tasks' => '<i class="icon-list"></i> ' . get_string('tasklist', 'mod_bacs'),
        'results' => '<i class="icon-envelope"></i> ' . get_string('mysubmits', 'mod_bacs'),
    ];

    $msg = '<ul class="nav nav-tabs">';
    foreach ($menuitems as $menuitemid => $menuitem) {
        $msg .= '<li class="nav-item">';
            $msg .= '<a class="nav-link ';
            $msg .= ($menuitemid == $link ? 'active' : '');
            $msg .= '" href="' . $menuitemid . '.php?id=' . $cm->id . '">' . $menuitem . '</a>';
        $msg .= '</li>';
    }
    if ($link == 'anothers_results') {
        $msg .= '<li class="nav-item"><a class="nav-link active" href=' .
                '"results.php?id=' . $cm->id .
                '&user_id=' . $customuserid . '">' .
                '<i class="icon-eye-open"></i> ' . get_string('submitsfrom', 'mod_bacs') . ' ' .
                $customuser->firstname . ' ' . $customuser->lastname . '</a></li>';
    }
    $msg .= '</ul>';
    return $msg;
}

/**
 * This function
 * @return void
 * @throws coding_exception
 */
function bacs_print_contest_title() {
    global $bacs, $cm, $student;

    print "<table><tr>";
    print "<td><h1 class='d-inline-block'>$bacs->name</h1><br></td>";
    if (!$student) {
        print "<td><a href='/course/modedit.php?update=$cm->id&return=0&sr=0'>" .
        "<i class='icon-cog'></i>" . get_string('settings', 'mod_bacs') .
        "</a></td>";
    }
    print "</tr></table>";
}

/**
 * This function
 * @param int $submitid
 * @param string $testpointsstring
 * @return void
 * @throws dml_exception
 */
function bacs_calculate_sumbit_points($submitid, $testpointsstring = null) {
    global $DB;

    $submit = $DB->get_record('bacs_submits', ['id' => $submitid], '*', MUST_EXIST);

    if (is_null($testpointsstring)) {
        $tasktocontest = $DB->get_record(
            'bacs_tasks_to_contests',
            ['contest_id' => $submit->contest_id, 'task_id' => $submit->task_id],
            'test_points',
            IGNORE_MISSING
        );

        if ($tasktocontest == false) {
            $submit->info = "[SERVER ERROR]
            \nThis submission was judged at the moment when the task was not set for this contest.
            \nThis submission requires rejudging to properly set submission points.";
            $submit->result_id = VERDICT_SERVER_ERROR;
            $DB->update_record('bacs_submits', $submit);
            return;
        }

        $task = $DB->get_record('bacs_tasks', ['task_id' => $submit->task_id], 'test_points', IGNORE_MISSING);
        if ($task == false) {
            $submit->info = "[SERVER ERROR]
            \nThis submission was judged at the moment when the task was not present in Moodle database.
            \nThis submission requires rejudging to properly set submission points.";
            $submit->result_id = VERDICT_SERVER_ERROR;
            $DB->update_record('bacs_submits', $submit);
            return;
        }

        if (is_null($tasktocontest->test_points)) {
            $testpointsstring = $task->test_points;
        } else {
            $testpointsstring = $tasktocontest->test_points;
        }
    }

    $testpointsparsed = explode(',', $testpointsstring);

    $pointsforaccepted = $testpointsparsed[0];
    $testpoints = array_slice($testpointsparsed, 1);

    $submittests = $DB->get_records(
        'bacs_submits_tests',
        ['submit_id' => $submitid, 'status_id' => 13 /* verdict Accepted */],
        '',
        'test_id'
    );

    $pointssum = 0;
    foreach ($submittests as $test) {
        $pointssum += intval($testpoints[$test->test_id]);
    }

    if ($submit->result_id == 13 /* verdict Accepted */) {
        $pointssum += intval($pointsforaccepted);
    }

    $submit->points = $pointssum;
    $DB->update_record('bacs_submits', $submit);
}

/**
 * This function
 * @param int $testsamount
 * @param int $pretestsamount
 * @param int $points
 * @return string
 */
function bacs_default_test_string($testsamount, $pretestsamount, $points = 100) {
    $pointspertest = [0];
    for ($i = 0; $i < $testsamount; $i++) {
        $pointspertest[] = 0;
    }

    $idx = $testsamount;
    while ($points > 0) {
        $pointspertest[$idx]++;
        $idx--;
        $points--;

        if ($idx <= $pretestsamount) {
            $idx = $testsamount;
        }
    }

    return implode(',', $pointspertest);
}

/**
 * This function
 * @param string $url
 * @return void
 */
function bacs_redirect_via_js($url) {
    print '<script type="text/javascript">
        window.location.href = "' . $url . '";
        </script>';
}

/**
 * This function
 * @param string $verdict
 * @return string
 */
function bacs_verdict_to_css_class($verdict) {
    $verdictclass = 'verdict-failed';

    if ($verdict == VERDICT_ACCEPTED) {
        $verdictclass = 'verdict-accepted';
    } else if ($verdict == VERDICT_PENDING) {
        $verdictclass = 'verdict-none';
    } else if ($verdict == VERDICT_RUNNING) {
        $verdictclass = 'verdict-none';
    }

    return $verdictclass;
}

/**
 * This function
 * @return void
 */
function bacs_load_get_string_for_js() {
    global $PAGE;
}

/**
 * This function
 * @param string $url
 * @return string
 * @throws coding_exception
 */
function bacs_ace_theme_selector($url) {
    $msg = "<script type='text/javascript'>
        function ace_theme_selector_change() {
            var selector = document.getElementById('ace_theme_selector');
            localStorage.setItem('ace_saved_theme', selector.value);

            window.location.href = '$url'.replace('{acetheme}', selector.value);
        }
    </script>";

    $msg .= '<div class="form-inline float-right">
        <b>' . get_string('editortheme', 'mod_bacs') . ':</b>
        <select id="ace_theme_selector" class="form-control" onchange="ace_theme_selector_change();">
            <optgroup label="' . get_string('default_defaulttheme', 'mod_bacs') . '">
                <option value="textmate">TextMate</option>
            </optgroup>
            <optgroup label="' . get_string('bright_brighttheme', 'mod_bacs') . '">
                <option value="chrome">Chrome</option>
                <option value="clouds">Clouds</option>
                <option value="crimson_editor">Crimson Editor</option>
                <option value="dawn">Dawn</option>
                <option value="dreamweaver">Dreamweaver</option>
                <option value="eclipse">Eclipse</option>
                <option value="github">GitHub</option>
                <option value="iplastic">IPlastic</option>
                <option value="solarized_light">Solarized Light</option>
                <option value="textmate">TextMate</option>
                <option value="tomorrow">Tomorrow</option>
                <option value="xcode">XCode</option>
                <option value="kuroir">Kuroir</option>
                <option value="katzenmilch">KatzenMilch</option>
                <option value="sqlserver">SQL Server</option>
            </optgroup>
            <optgroup label="' . get_string('dark_darktheme', 'mod_bacs') . '">
                <option value="ambiance">Ambiance</option>
                <option value="chaos">Chaos</option>
                <option value="clouds_midnight">Clouds Midnight</option>
                <option value="dracula">Dracula</option>
                <option value="cobalt">Cobalt</option>
                <option value="gruvbox">Gruvbox</option>
                <option value="gob">Green on Black</option>
                <option value="idle_fingers">idle Fingers</option>
                <option value="kr_theme">krTheme</option>
                <option value="merbivore">Merbivore</option>
                <option value="merbivore_soft">Merbivore Soft</option>
                <option value="mono_industrial">Mono Industrial</option>
                <option value="monokai">Monokai</option>
                <option value="pastel_on_dark">Pastel on dark</option>
                <option value="solarized_dark">Solarized Dark</option>
                <option value="terminal">Terminal</option>
                <option value="tomorrow_night">Tomorrow Night</option>
                <option value="tomorrow_night_blue">Tomorrow Night Blue</option>
                <option value="tomorrow_night_bright">Tomorrow Night Bright</option>
                <option value="tomorrow_night_eighties">Tomorrow Night 80s</option>
                <option value="twilight">Twilight</option>
                <option value="vibrant_ink">Vibrant Ink</option>
            </optgroup>
        </select>
    </div>';

    return $msg;
}

/**
 * Calculates the probability that a user can solve a task based on Elo ratings.
 * Uses the standard Elo formula: P = 1 / (1 + 10^((R_task - R_user) / 500))
 * 
 * @param float $R_task Task Elo rating
 * @param float $R_user User Elo rating
 * @return float Probability between 0 and 1
 */
function bacs_calculate_solve_probability(float $R_task, float $R_user): float
{
    return 1 / (1 + pow(10, ($R_task - $R_user) / BACS_ELO_LOG_SCALE_FACTOR));
}

function bacs_is_plugin_presented($plugin_name)
{
    $pluginmanager = \core_plugin_manager::instance();
    $parsed_plugin_name_str = explode("_", $plugin_name);
    $name = implode("_", array_slice($parsed_plugin_name_str, 1));
    $plugins_by_type = $pluginmanager->get_present_plugins($parsed_plugin_name_str[0]);
    return isset($plugins_by_type[$name]);
}
