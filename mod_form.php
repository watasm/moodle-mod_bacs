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
require_once(dirname(__FILE__) . '/locale_utils.php');
require_once(dirname(__FILE__) . '/utils.php');

/**
 * Class mod_bacs_mod_form
 * @package mod_bacs
 */
class mod_bacs_mod_form extends moodleform_mod
{
    /**
     * This function
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition()
    {
        global $DB, $PAGE;

        $mform = $this->_form;
        $id = optional_param('update', 0, PARAM_INT);

        $stringman = get_string_manager();
        $strings = $stringman->load_component_strings('bacs', 'ru');
        $PAGE->requires->strings_for_js(array_keys($strings), 'bacs');
        $PAGE->requires->js('/mod/bacs/thirdparty/sortablejs/Sortable.js', true);
        $PAGE->requires->js('/mod/bacs/manage_tasks.js', true);
        $PAGE->requires->js('/mod/bacs/mod_form.js', true);
        $PAGE->requires->js('/mod/bacs/manage_test_points.js', true);
        $this->init_difficulty_analysis($id ? $id : optional_param('course', 0, PARAM_INT));


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
                'stopyear' => get_config('mod_bacs', 'maxselectableyear'),
                'step' => 5,
            ]
        );
        $mform->addElement(
            'date_time_selector',
            'endtime',
            get_string('to', 'bacs'),
            [
                'startyear' => get_config('mod_bacs', 'minselectableyear'),
                'stopyear' => get_config('mod_bacs', 'maxselectableyear'),
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

        $pluginman = \core\plugin_manager::instance();
        $rating_plugin_info = $pluginman->get_plugin_info('block_bacs_rating');
        $has_rating = $rating_plugin_info && $rating_plugin_info->is_installed_and_upgraded() && $rating_plugin_info->is_enabled();

        $task_ratings = [];
        if ($has_rating) {
            $raw_ratings = $DB->get_records('bacs_rating_tasks', [], '', 'task_id, elo_rating');
            foreach ($raw_ratings as $r) {
                $task_ratings[$r->task_id] = round($r->elo_rating);
            }
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
        $globaltasksinfoscript = $this->load_tasks($taskids, $has_rating, $task_ratings);
        $mform->addElement('html', html_writer::script($globaltasksinfoscript));
        $participants_rating_html = $this->get_participants_rating_summary($has_rating);

        // Tasks tab settings.

        $mform->addElement('header', 'tasks_header', get_string('tasks', 'bacs'));
        $mform->addElement('html', $this->get_tasks_header($collectionsinfo, $alltasks, $taskids, $has_rating, $task_ratings, $participants_rating_html));

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
    private function get_group_settings($groupsettingshtml, $id)
    {
        return '<p style="text-align: center;">' .
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
    private function load_groups($bacs, $course)
    {
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
    private function load_contest_tasks($bacs)
    {
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
    private function load_task_ids()
    {
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
    private function load_tasks($taskids, $has_rating = false, $task_ratings = []) {
        $globaltasksinfoscript = '
            var global_notify_user_to_recalc_points = true;
            var global_tasks_info = { };
        ';

        foreach ($taskids as $curtask) {
            $names = json_decode($curtask->names, true);
            $statement_urls = json_decode($curtask->statement_urls, true);
            $names_json = json_encode($names, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $statement_urls_json = json_encode($statement_urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $rating_val = ($has_rating && isset($task_ratings[$curtask->task_id])) ? $task_ratings[$curtask->task_id] : '-';
            $globaltasksinfoscript .=
                'global_tasks_info["' . $curtask->task_id . '"] = {
                    task_id:             "' . $curtask->task_id . '",
                    name:                "' . bacs_get_localized_name($curtask) . '",
                    names:                ' . $names_json . ',
                    author:              "' . $curtask->author . '",   
                    statement_format:    "' . $curtask->statement_format . '",
                    default_test_points: "' . $curtask->test_points . '",
                    count_tests:         "' . $curtask->count_tests . '",
                    count_pretests:      "' . $curtask->count_pretests . '",
                    statement_url:       "' . $curtask->statement_url . '",
                    statement_urls:       ' . $statement_urls_json . ',
                    rating:              "' . $rating_val . '",
                };';
        }
        return $globaltasksinfoscript;
    }

    /**
     * This function
     * @param int $containerid
     * @param bool $has_rating
     * @return string
     * @throws coding_exception
     */
    private function get_collection_container($containerid, $has_rating) {
        $html = "<div id='" . $containerid . "'
                style='width: 99%; max-height: 80vh; overflow: auto; display: none;' >
                <table class='generaltable accordion' style = 'white-space: nowrap;'>
                <thead><tr class='bacs-mod-form'>
                    <td><b>" . get_string('taskid', 'bacs') . "</b></td>
                    <td><b>" . get_string('taskname', 'bacs') . "</b></td>";

        if ($has_rating) {
            $html .= "<td><b>" . get_string('bacsrating:rating', 'bacs') . "</b></td>";
        }

        $html .= "<td><b>" . get_string('format', 'bacs') . "</b></td>
                    <td><b>" . get_string('author', 'bacs') . "</b></td>
                    <td><b>" . get_string('actions', 'bacs') . "</b></td>
                    </tr></thead>
                <tbody class='chesspaint-bacs-mod-form'>";

        return $html;
    }

    /**
     * This function
     * @param object $task
     * @param bool $has_rating
     * @param array $task_ratings
     * @return string
     * @throws coding_exception
     */
    private function get_tablein($task, $has_rating, $task_ratings) {
        $html = "<tr class='bacs-mod-form' style='background-color: transparent;'
                     onmouseover=\"this.style.backgroundColor='#ececec';\"
                      onmouseout=\"this.style.backgroundColor='transparent';\">" .
            "<td>" . $task->task_id . "</td>" .
            "<td><a href='" . $task->statement_url . "' target='_blank'>"
            . htmlspecialchars(bacs_get_localized_name($task)) . "</a></td>";

        if ($has_rating) {
            $rating_val = isset($task_ratings[$task->task_id]) ? $task_ratings[$task->task_id] : '-';
            $html .= "<td>" . $rating_val . "</td>";
        }

        $html .= "<td>" . strtoupper($task->statement_format) . "</td>" .
            "<td>" . $task->author . "</td>" .
            "<td><span class='tm_clickable' onclick='trl_add_task(" .
            $task->task_id . ")'>" .
            get_string('add', 'bacs') . "</span></td>" .
            "</tr>";
      
        return $html;
    }

    private function init_difficulty_analysis($cmid = 0)
    {
        global $PAGE;

        $notasksselected_text = get_string('notasksselected', 'bacs');
        $students_can_solve_text = get_string('difficulty_analysis_students_can_solve', 'bacs');
        $ideal_curve_text = get_string('difficulty_analysis_ideal_curve', 'bacs');
        $number_of_students_text = get_string('difficulty_analysis_number_of_students', 'bacs');
        $tasks_text = get_string('difficulty_analysis_tasks', 'bacs');

        // Load difficulty analysis JavaScript
        $PAGE->requires->js('/mod/bacs/difficulty_analysis.js', true);

        // Load jQuery, Chart.js and initialize difficulty analysis module
        $PAGE->requires->js_init_code("
            (function() {
                var initDifficultyAnalysis = function() {
                    // Ensure jQuery is available globally
                    require(['jquery', 'core/chartjs'], function($, ChartJS) {
                        // Make jQuery and Chart available globally
                        if (typeof window.jQuery === 'undefined') {
                            window.jQuery = $;
                            window.$ = $;
                        }
                        window.Chart = ChartJS;
                        
                        // Initialize difficulty analysis after dependencies are loaded
                        if (typeof window.bacsDifficultyAnalysisInit === 'function') {
                            window.bacsDifficultyAnalysisInit(
                                " . json_encode($cmid) . ",
                                " . json_encode($notasksselected_text) . ",
                                " . json_encode($students_can_solve_text) . ",
                                " . json_encode($ideal_curve_text) . ",
                                " . json_encode($number_of_students_text) . ",
                                " . json_encode($tasks_text) . "
                            );
                        } else {
                            // Retry if script not loaded yet
                            setTimeout(initDifficultyAnalysis, 100);
                        }
                    });
                };
                // Wait for DOM and scripts to be ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initDifficultyAnalysis);
                } else {
                    setTimeout(initDifficultyAnalysis, 100);
                }
            })();
        ");

       
    }

    /**
     * This function
     * @param array $collectionsinfo
     * @param array $alltasks
     * @param array $taskids
     * @param bool $has_rating
     * @param array $task_ratings
     * @return string
     * @throws coding_exception
     */
    private function get_tasks_header($collectionsinfo, $alltasks, $taskids, $has_rating, $task_ratings, $participants_rating_html = '')
    {
        // Initialize difficulty analysis JavaScript if contest exists
        $difficulty_analysis_html = '';
        $button_text = get_string('analyzecontestdifficulty', 'bacs');
        $is_plugin_presented = bacs_is_plugin_presented('block_bacs_rating');

        $button_id = 'bacs-difficulty-analysis-btn';
        $loader_id = 'bacs-difficulty-analysis-loader';
        $result_id = 'bacs-difficulty-analysis-result';

        $is_disabled = !$is_plugin_presented;
        $disabled_attr = $is_disabled ? 'disabled="true"' : '';

        $difficulty_analysis_html = '
            <div id="bacs-difficulty-analysis-container" style="margin-top: 20px; margin-bottom: 20px;">
                <button id="' . $button_id . '" class="btn btn-primary" type="button" ' . $disabled_attr . '>
                    ' . $button_text . '
                </button>
                <div id="' . $loader_id . '" style="display: none; margin-top: 10px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
                <div id="' . $result_id . '" style="display: none; margin-top: 20px;">
                    <canvas id="bacs-difficulty-chart" style="max-width: 100%; height: 400px;"></canvas>
                </div>
            </div>';

        if (!$is_plugin_presented) {
            $difficulty_analysis_html = $difficulty_analysis_html . '<p>' . get_string('no_plugin_installed', 'bacs') . '</p>';
        }
        $result = '
             <p class="tm_caption_p">' . get_string('contesttasks', 'bacs') . ':</p>
             <table width="100%"><tr class="bacs-mod-form">
                <td width="1%"><div id="letters_column"></div></td>
                <td><div id="tasks_reorder_list"></div></td>
            </tr></table>
            <script>getSortable();</script>' .
            $difficulty_analysis_html;

        if (!empty($participants_rating_html)) {
            $result .= $participants_rating_html;
        }

        $result .= '<div style="margin-top: 20px"><b>' . get_string('alltasksfrom', 'bacs') . ':</b>
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

        if ($has_rating) {
            $result .= '<div style="margin-top: 10px; margin-bottom: 10px"><b>' . get_string('bacsrating:sortby', 'bacs') . ':</b>
            <select
                class="form-control"
                style="margin-left: 5px; width:200px; display:inline-block;"
                id="bacs_sort_selector"
                onchange="apply_sort()">
                
                <option value="" selected disabled hidden>...</option>
                <option value="rating_desc">'. get_string('bacsrating:sortby:rating_desc', 'bacs') . '</option>
                <option value="rating_asc">'. get_string('bacsrating:sortby:rating_asc', 'bacs') . '</option></select></div>';
        }

        foreach ($collectionsinfo as $collectioninfo) {
            $result .= $this->get_collection_container("collection_container_" . $collectioninfo->collection_id, $has_rating);
            foreach ($taskids as $taskid) {
                if ($taskid->collection_id == $collectioninfo->collection_id) {
                    $result .= $this->get_tablein($taskid, $has_rating, $task_ratings);
                }
            }
            $result .= "</tbody></table></div>";
        }
        $result .= "<div id='collection_container_all' style='width: 99%; max-height: 80vh; overflow: auto' >
            <table class='generaltable accordion' style = 'white-space: nowrap;'>
            <thead><tr class='bacs-mod-form'>
                <td><b>" . get_string('taskid', 'bacs') . "</b></td>
                <td><b>" . get_string('taskname', 'bacs') . "</b></td>";

        if ($has_rating) {
            $rating_str = get_string('bacsrating:rating', 'bacs');
            $result .= "<td><b>" . $rating_str . "</b></td>";
        }

        $result .= "<td><b>" . get_string('format', 'bacs') . "</b></td>
                <td><b>" . get_string('author', 'bacs') . "</b></td>
                <td><b>" . get_string('actions', 'bacs') . "</b></td>
                </tr></thead>
            <tbody class='chesspaint-bacs-mod-form'>";
        foreach ($alltasks as $curtask) {
            $result .= $this->get_tablein($curtask, $has_rating, $task_ratings);
        }
        $result .= "</tbody></table></div>";
        return $result;
    }

    /**
     * This function
     * @return string
     * @throws coding_exception
     */
    private function get_testpoints_header()
    {
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
    private function get_advanced_settings_header()
    {
        return '<div class="alert alert-warning">' .
            '<p>' . get_string('advancedsettingsmessage1', 'bacs') . '</p>' .
            '<p>' . get_string('advancedsettingsmessage2', 'bacs') . '</p>' .
            '<p>' .
            '<b>' . get_string('advancedwarning', 'bacs') . '</b>' . ' ' .
            get_string('advancedsettingsmessage3', 'bacs') .
            '</p>' .
            '</div>';
    }

    /**
    * This function
    * @param bool $has_rating
    * @return string
    * @throws coding_exception|dml_exception
     */
    private function get_participants_rating_summary($has_rating) {
        global $DB, $PAGE;

        if (!$has_rating) {
            return '';
        }

        $context = $PAGE->context;

        $users = get_enrolled_users($context, 'mod/bacs:view', 0, 'u.*');

        if (empty($users)) {
            return '';
        }

        $user_ids = array_keys($users);
        $count_rated = 0;
        $sum_rating = 0;
        $users_data = [];

        list($insql, $inparams) = $DB->get_in_or_equal($user_ids);
        $sql = "SELECT userid, rating FROM {bacs_user_ratings} WHERE userid $insql";
        $ratings = $DB->get_records_sql($sql, $inparams);

        foreach ($users as $uid => $user) {
            $rating = 0;
            $has_val = false;

            if (isset($ratings[$uid])) {
                $rating = round($ratings[$uid]->rating);
                $sum_rating += $rating;
                $count_rated++;
                $has_val = true;
            }

            $users_data[] = [
                'name' => fullname($user),
                'rating' => $has_val ? $rating : '-',
                'sort_val' => $has_val ? $rating : -1
            ];
        }

        $average = $count_rated > 0 ? round($sum_rating / $count_rated) : 0;

        usort($users_data, function($a, $b) {
            return $b['sort_val'] - $a['sort_val'];
        });

        $str_avg = get_string('bacsrating:averagerating', 'bacs');
        $str_participants = get_string('bacsrating:participants', 'bacs');
        $str_participants_list = get_string('bacsrating:participantslist', 'bacs');
        $str_participant = get_string('bacsrating:participant', 'bacs'); // Для заголовка таблицы
        $str_rating = get_string('bacsrating:rating', 'bacs');
        $str_rated = get_string('bacsrating:rated', 'bacs');

        $html = '<div style="margin: 20px 0; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; background-color: #f8f9fa;">';

        $html .= '<div style="font-size: 1.1em; margin-bottom: 10px;">';
        $html .= '<b>' . $str_avg . ': <span style="color: #0f6cbf;">' . $average . '</span></b> ';
        $html .= '<span style="color: #666; font-size: 0.9em;">(' . $str_participants . ': ' . count($users) . ', '. $str_rated . ': ' . $count_rated . ')</span>';
        $html .= '</div>';

        $html .= '<details>';
        $html .= '<summary style="cursor: pointer; color: #0f6cbf; font-weight: bold;">' . $str_participants_list . ' &#9662;</summary>';

        $html .= '<div style="max-height: 300px; overflow-y: auto; margin-top: 10px; border-top: 1px solid #ddd;">';
        $html .= '<table class="generaltable" style="width: 100%; font-size: 0.9em;">';
        $html .= '<thead><tr><th style="position: sticky; top: 0; background: #eee;">' . $str_participant . '</th><th style="position: sticky; top: 0; background: #eee;">'. $str_rating .'</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($users_data as $ud) {
            $html .= '<tr>';
            $html .= '<td>' . $ud['name'] . '</td>';

            $style = ($ud['rating'] !== '-') ? 'font-weight: bold;' : 'color: #999;';
            $html .= '<td style="' . $style . '">' . $ud['rating'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';
        $html .= '</details>';
        $html .= '</div>';

        return $html;
    }
}
