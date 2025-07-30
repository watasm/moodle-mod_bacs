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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/bacs/lib.php');

/**
 * Class mod_bacs_mod_form
 * @package mod_bacs
 */
class mod_bacs_mod_form extends moodleform_mod {
    /**
     * This function
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition() {
        global $DB, $PAGE;

        $mform = $this->_form;

        $stringman = get_string_manager();
        $strings = $stringman->load_component_strings('bacs', 'ru');
        $PAGE->requires->strings_for_js(array_keys($strings), 'bacs');
        $PAGE->requires->js('/mod/bacs/thirdparty/sortablejs/Sortable.js', true);
        $PAGE->requires->js('/mod/bacs/manage_tasks.js', true);
        $PAGE->requires->js('/mod/bacs/mod_form.js', true);
        $PAGE->requires->js('/mod/bacs/manage_test_points.js', true);

        $id = optional_param('update', 0, PARAM_INT);
        $groupmode = 0; // ...no groups by default.
        if ($id) {
            // ...load bacs.
            $cm = get_coursemodule_from_id('bacs', $id, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $bacs = $DB->get_record('bacs', ['id' => $cm->instance], '*', MUST_EXIST);
            $groupmode = groups_get_activity_groupmode($cm);
        }

        $mform->addElement(
            'text',
            'name',
            get_string('contestname', 'bacs'),
            ['size' => '50']
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule(
            'name',
            null,
            'required',
            null,
            'client'
        );

        $mform->addElement(
            'select',
            'mode',
            get_string('contestmode', 'bacs'),
            [
                    0 => "IOI",
                    1 => "ICPC",
                    2 => "General",
            ]
        );

        $mform->addElement(
            'date_time_selector',
            'starttime',
            get_string('from', 'bacs'),
            [
                    'startyear' => get_config('mod_bacs', 'minselectableyear'),
                    'stopyear'  => get_config('mod_bacs', 'maxselectableyear'),
                    'step' => 5,
            ]
        );
        $mform->addElement(
            'date_time_selector',
            'endtime',
            get_string('to', 'bacs'),
            [
                    'startyear' => get_config('mod_bacs', 'minselectableyear'),
                    'stopyear'  => get_config('mod_bacs', 'maxselectableyear'),
                    'step' => 5,
            ]
        );

        $mform->addElement(
            'select',
            'virtual_mode',
            get_string('virtualparticipation', 'bacs'),
            [
                    0 => get_string('virtualparticipationdisable', 'bacs'),
                    1 => get_string('virtualparticipationallow', 'bacs'),
                    2 => get_string('virtualparticipationonly', 'bacs'),
            ]
        );

        $mform->addElement(
            'advcheckbox',
            'upsolving',
            get_string('upsolving', 'bacs'),
            '',
            ['group' => 1],
            [0, 1]
        );
        $mform->setDefault('upsolving', 1);

        $mform->addElement(
            'advcheckbox',
            'presolving',
            get_string('presolving', 'bacs'),
            '',
            [],
            [0, 1]
        );
        $mform->setDefault('presolving', 0);

        $mform->addElement(
            'advcheckbox',
            'isolate_participants',
            get_string('isolateparticipants', 'bacs'),
            '',
            [],
            [0, 1]
        );
        $mform->setDefault('isolate_participants', 0);
        if ($groupmode) {
            $groupsettingshtml = $this->load_groups($bacs, $course);
            $mform->addElement('html', $this->get_group_settings($groupsettingshtml, $id));
        }

        // ...load contest tasks and test points if contest exists.
        $presetcontesttaskids = '';
        $presetcontesttasktestpoints = '';

        if ($id) {
            // ...load contest tasks.

            $loadcontesttasks = $this->load_contest_tasks($bacs);

            $contesttasktestpoints = $loadcontesttasks->contesttasktestpoints;
            $contesttaskids = $loadcontesttasks->contesttaskids;
            $loadtasksjs = $loadcontesttasks->loadtasksjs;

            $presetcontesttaskids = implode('_', $contesttaskids);
            $presetcontesttasktestpoints = implode('_', $contesttasktestpoints);

            $mform->addElement('html', html_writer::script("window.addEventListener('load', function() { $loadtasksjs });"));
        }
        $taskids = $this->load_task_ids();
        $alltasks = $DB->get_records('bacs_tasks');
        $collectionsinfo = $DB->get_records('bacs_tasks_collections', [], 'id ASC');
        $globaltasksinfoscript = $this->load_tasks($taskids);
        $mform->addElement('html', html_writer::script($globaltasksinfoscript));

        // Tasks tab settings.
        $mform->addElement('header', 'tasks_header', get_string('tasks', 'bacs'));
        $mform->addElement('html', $this->get_tasks_header($collectionsinfo, $alltasks, $taskids));

        // Test points tab settings.
        $mform->addElement('header', 'testpoints_header', get_string('testpoints', 'bacs'));

        $mform->addElement('html', $this->get_testpoints_header());

        // Incidents tab settings.
        $mform->addElement('header', 'incidents_header', get_string('incidents', 'bacs'));

        $mform->addElement(
            'advcheckbox',
            'detect_incidents',
            get_string('detectincidents', 'bacs'),
            '',
            [],
            [0, 1]
        );
        $mform->setDefault('detect_incidents', 0);

        $mform->addElement('textarea', 'incidents_settings', get_string("incidentssettings", "bacs"), 'wrap="virtual" rows="10" cols="70"');

        // ...advanced contest settings tab and hidden fields.
        $mform->addElement('header', 'advanced_settings_header', get_string('advancedcontestsettings', 'bacs'));

        $mform->addElement('html', $this->get_advanced_settings_header());

        $mform->addElement(
            'text',
            'contest_task_ids',
            get_string('rawcontesttaskids', 'bacs'),
            [
                    'value' => $presetcontesttaskids,
                    'size' => 80,
            ]
        );
        $mform->setType('contest_task_ids', PARAM_RAW);

        $mform->addElement(
            'text',
            'contest_task_test_points',
            get_string('rawcontesttasktestpoints', 'bacs'),
            [
                    'value' => $presetcontesttasktestpoints,
                    'size' => 80,
            ]
        );
        $mform->setType('contest_task_test_points', PARAM_RAW);

        // ...add standart elements.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * This function
     * @param string $groupsettingshtml
     * @param int $id
     * @return string
     * @throws coding_exception
     */
    private function get_group_settings($groupsettingshtml, $id) {
        return  '<p style="text-align: center;">' .
                $groupsettingshtml . '
                    <br>
                    <a href="/mod/bacs/groups_settings.php?id=' . $id . '" target="_blank">
                        ' . get_string('gotogroupsettings', 'bacs') . '
                    </a>
                </p>';
    }

    /**
     * This function
     * @param object $bacs
     * @param object $course
     * @return lang_string|string
     * @throws coding_exception
     * @throws dml_exception
     */
    private function load_groups($bacs, $course) {
        global $DB;
        $groups = groups_get_all_groups($course->id);

        $groupstotalcount = count($groups);
        $groupswithgroupsettings = 0;

        foreach ($groups as $curgroup) {
            $groupid = $curgroup->id;
            $groupinfo = $DB->get_record(
                'bacs_group_info',
                ['contest_id' => $bacs->id, 'group_id' => $groupid],
                '*',
                IGNORE_MISSING
            );

            if ($groupinfo && $groupinfo->use_group_settings) {
                $groupswithgroupsettings++;
            }
        }

        if ($groupswithgroupsettings == 0) {
            $groupsettingshtml = get_string('groupsettingsarenotused', 'bacs');
        } else {
            $strparamsobj = (object) [
                    'with_group_settings' => $groupswithgroupsettings,
                    'total_count' => $groupstotalcount,
            ];

            $groupsettingshtml = get_string('groupsettingsareused', 'bacs', $strparamsobj);
        }
        return $groupsettingshtml;
    }

    /**
     * This function
     * @param object $bacs
     * @return stdClass
     * @throws dml_exception
     */
    private function load_contest_tasks($bacs) {
        global $DB;

        $data = new stdClass();
        $loadtasksjs = '';

        $contesttaskids = [];
        $contesttasktestpoints = [];

        $contesttasks = $DB->get_records(
            'bacs_tasks_to_contests',
            ['contest_id' => $bacs->id],
            'task_order ASC',
            'task_id, test_points'
        );
        foreach ($contesttasks as $curcontesttask) {
            $taskid = $curcontesttask->task_id;

            $contesttaskids[] = $taskid;
            $contesttasktestpoints[] = $curcontesttask->test_points;

            $loadtasksjs .= 'trl_add_task("' . $taskid . '");';

            if (isset($curcontesttask->test_points)) {
                $loadtasksjs .= 'global_tasks_info["' . $taskid . '"].test_points = "' . $curcontesttask->test_points . '";';
            }
        }

        $data->loadtasksjs = $loadtasksjs;
        $data->contesttaskids = $contesttaskids;
        $data->contesttasktestpoints = $contesttasktestpoints;
        return $data;
    }

    /**
     * This function
     * @return array
     * @throws dml_exception
     */
    private function load_task_ids() {
        // ...load tasks.
        global $DB;
        $sql = "SELECT tasks_to_collections.id,
                       tasks_to_collections.collection_id,
                       tasks.task_id,
                       tasks.name,
                       tasks.names,
                       tasks.statement_url,
                       tasks.statement_urls,
                       tasks.count_tests,
                       tasks.count_pretests,
                       tasks.test_points,
                       tasks.author,
                       tasks.statement_format
                  FROM {bacs_tasks_to_collections} tasks_to_collections
            INNER JOIN {bacs_tasks} tasks ON tasks_to_collections.task_id = tasks.task_id";
        $taskids = $DB->get_records_sql($sql, [], 0, 1000000);
        return $taskids;
    }

    /**
     * This function
     * @param array $taskids
     * @return string
     */
    private function load_tasks($taskids) {
        $globaltasksinfoscript = '
            var global_notify_user_to_recalc_points = true;
            var global_tasks_info = { };
        ';
        foreach ($taskids as $curtask) {
            $globaltasksinfoscript .=
                    'global_tasks_info["' . $curtask->task_id . '"] = {
                    task_id:             "' . $curtask->task_id . '",
                    name:                "' . $curtask->name . '",
                    names:               JSON.parse(`' . $curtask->names . '`),
                    author:              "' . $curtask->author . '",   
                    statement_format:    "' . $curtask->statement_format . '",
                    default_test_points: "' . $curtask->test_points . '",
                    count_tests:         "' . $curtask->count_tests . '",
                    count_pretests:      "' . $curtask->count_pretests . '",
                    statement_url:       "' . $curtask->statement_url . '",
                    statement_urls:       JSON.parse(`' . $curtask->statement_urls . '`),
                };';
        }
        return $globaltasksinfoscript;
    }

    /**
     * This function
     * @param int $containerid
     * @return string
     * @throws coding_exception
     */
    private function get_collection_container($containerid) {
        return "<div id='" . $containerid . "'
                style='width: 99%; max-height: 80vh; overflow: auto; display: none;' >
                <table class='generaltable accordion' style = 'white-space: nowrap;'>
                <thead><tr class='bacs-mod-form'>
                    <td><b>" . get_string('taskid', 'bacs') . "</b></td>
                    <td><b>" . get_string('taskname', 'bacs') . "</b></td>
                    <td><b>" . get_string('format', 'bacs') . "</b></td>
                    <td><b>" . get_string('author', 'bacs') . "</b></td>
                    <td><b>" . get_string('actions', 'bacs') . "</b></td>
                    </tr></thead>
                <tbody class='chesspaint-bacs-mod-form'>";
    }

    /**
     * This function
     * @param object $task
     * @return string
     * @throws coding_exception
     */
    private function get_tablein($task) {
        return "<tr class='bacs-mod-form' style='background-color: transparent;'
                     onmouseover=\"this.style.backgroundColor='#ececec';\"
                      onmouseout=\"this.style.backgroundColor='transparent';\">" .
                "<td>" . $task->task_id . "</td>" .
                "<td><a href='" . $task->statement_url . "' target='_blank'>"
                . htmlspecialchars($task->name) . "</a></td>" .
                "<td>" . strtoupper($task->statement_format) . "</td>" .
                "<td>" . $task->author . "</td>" .
                "<td><span class='tm_clickable' onclick='trl_add_task(" .
                $task->task_id . ")'>" .
                get_string('add', 'bacs') . "</span></td>" .
                "</tr>";
    }

    /**
     * This function
     * @param array $collectionsinfo
     * @param array $alltasks
     * @param array $taskids
     * @return string
     * @throws coding_exception
     */
    private function get_tasks_header($collectionsinfo, $alltasks, $taskids) {
        $result = '
             <p class="tm_caption_p">' . get_string('contesttasks', 'bacs') . ':</p>
             <table width="100%"><tr class="bacs-mod-form">
                <td width="1%"><div id="letters_column"></div></td>
                <td><div id="tasks_reorder_list"></div></td>
            </tr></table>
            <script>getSortable();</script>' .
                '<div style="margin-top: 20px"><b>' . get_string('alltasksfrom', 'bacs') . ':</b>
             <select
                class="form-control"
                style="margin-left: 5px; width:200px; display:inline-block;"
                id="collection_container_selector"
                onchange="collection_selector_change(); tableSearch();">
                <option value="all">' . get_string('allcollections', 'bacs') . '</option>';
        foreach ($collectionsinfo as $collectioninfo) {
            $result .= "<option value='$collectioninfo->collection_id'>$collectioninfo->name</option>";
        }
        $result .= '</select><div class="form-control" id="srchFld" >
            <input class="bacs-mod-form" type="search"
                placeholder="' . get_string('search', 'bacs') . '" id="search-text"
                onkeyup="tableSearch()" onfocus="blueShine()" onblur="offShine()">
            <input class="bacs-mod-form"
                type="button"
                value="&#10006;"
                onclick="cleanSearch()">
                </div></div>';

        foreach ($collectionsinfo as $collectioninfo) {
            $result .= $this->get_collection_container("collection_container_" . $collectioninfo->collection_id);
            foreach ($taskids as $taskid) {
                if ($taskid->collection_id == $collectioninfo->collection_id) {
                    $result .= $this->get_tablein($taskid);
                }
            }
            $result .= "</tbody></table></div>";
        }
        $result .= "<div id='collection_container_all' style='width: 99%; max-height: 80vh; overflow: auto' >
            <table class='generaltable accordion' style = 'white-space: nowrap;'>
            <thead><tr class='bacs-mod-form'>
                <td><b>" . get_string('taskid', 'bacs') . "</b></td>
                <td><b>" . get_string('taskname', 'bacs') . "</b></td>
                <td><b>" . get_string('format', 'bacs') . "</b></td>
                <td><b>" . get_string('author', 'bacs') . "</b></td>
                <td><b>" . get_string('actions', 'bacs') . "</b></td>
                </tr></thead>
            <tbody class='chesspaint-bacs-mod-form'>";
        foreach ($alltasks as $curtask) {
            $result .= $this->get_tablein($curtask);
        }
        $result .= "</tbody></table></div>";
        return $result;
    }

    /**
     * This function
     * @return string
     * @throws coding_exception
     */
    private function get_testpoints_header() {
        return '<p class="tm_caption_p"> ' . get_string('choosetask', 'bacs') . ':</p>' .
                '<select id="test_editor_task_selector"
                onchange="test_editor_load_task()"><option value="" selected>-</option></select>' .
                '<div id="test_editor_container" style="display: none;">
                <input class="bacs-mod-form" type="checkbox" id="test_editor_use_custom" onclick="test_editor_switch_mode()">
                <label for="test_editor_use_custom">' . get_string('usecustomtestpoints', 'bacs') . '</label><br><br>
                ' . get_string('amountoftests', 'bacs') . ': <span id="test_editor_tests_amount">0</span><br>
                ' . get_string('amountofpretests', 'bacs') . ': <span id="test_editor_pretests_amount">0</span><br>
                ' . get_string('sumofpoints', 'bacs') . ': <span id="test_editor_points_sum">0</span><br>
                ' . get_string('pointsforfullsolution', 'bacs') . ':
                    <input class="bacs-mod-form" type="text"
                    id="test_editor_accepted_points" size=3 onchange="test_editor_change_accepted_points()">
                <table id="test_editor_table" style="margin-top: 5px;" class="generaltable accordion">
                <thead><tr class="bacs-mod-form">
                    <td><b>' . get_string('n', 'bacs') . '</b></td>
                    <td><b>' . get_string('tests', 'bacs') . '</b></td>
                    <td><b>' . get_string('pointspertest', 'bacs') . '</b></td>
                    <td><b>' . get_string('pointspergroup', 'bacs') . '</b></td>
                    <td><b>' . get_string('actions', 'bacs') . '</b></td>
                </tr></thead>
                <tbody></tbody>
                </table>
            </div>';
    }

    /**
     * This function
     * @return string
     * @throws coding_exception
     */
    private function get_advanced_settings_header() {
        return '<div class="alert alert-warning">' .
                '<p>' . get_string('advancedsettingsmessage1', 'bacs') . '</p>' .
                '<p>' . get_string('advancedsettingsmessage2', 'bacs') . '</p>' .
                '<p>' .
                '<b>' . get_string('advancedwarning', 'bacs') . '</b>' . ' ' .
                get_string('advancedsettingsmessage3', 'bacs') .
                '</p>' .
                '</div>';
    }
}
