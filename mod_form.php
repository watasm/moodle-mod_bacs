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

/**
 * Class mod_bacs_mod_form
 * @package mod_bacs
 */
class mod_bacs_mod_form extends moodleform_mod
{

    private $has_rating_table = false;

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
        $cmid_for_analysis = $id ? $id : optional_param('course', 0, PARAM_INT);

        $stringman = get_string_manager();
        $strings = $stringman->load_component_strings('bacs', current_language());
        $PAGE->requires->strings_for_js(array_keys($strings), 'bacs');
        
        $mammothurl = new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.4.21/mammoth.browser.min.js');
        $PAGE->requires->js($mammothurl, true);
        $PAGE->requires->js('/mod/bacs/thirdparty/sortablejs/Sortable.js', true);
        
        $PAGE->requires->css('/mod/bacs/thirdparty/Flatpickr/flatpickr.min.css');
        $PAGE->requires->js('/mod/bacs/thirdparty/Flatpickr/flatpickr.min.js', true);
        
        if (current_language() === 'ru') {
            $PAGE->requires->js('/mod/bacs/thirdparty/Flatpickr/ru.js', true);
            $PAGE->requires->js_init_code("flatpickr.localize(flatpickr.l10ns.ru);");
        }
        
        $PAGE->requires->css('/mod/bacs/mod_form.css');
        $PAGE->requires->js('/mod/bacs/mod_form.js', true);
        $PAGE->requires->js('/mod/bacs/points_editor.js', true);
        
        $PAGE->requires->js('/mod/bacs/manage_tasks.js', true);
        $PAGE->requires->js('/mod/bacs/manage_test_points.js', true);

        $this->init_difficulty_analysis($cmid_for_analysis);

        $initial_task_ids = [];
        $initial_test_points =[];
        $groupmode = 0;

        if ($id) {
            $cm = get_coursemodule_from_id('bacs', $id, 0, false, MUST_EXIST);
            $course = $DB->get_record('course',['id' => $cm->course], '*', MUST_EXIST);
            $bacs_instance = $DB->get_record('bacs',['id' => $cm->instance], '*', MUST_EXIST);
            $groupmode = groups_get_activity_groupmode($cm);

            $contest_tasks = $DB->get_records('bacs_tasks_to_contests',['contest_id' => $bacs_instance->id], 'task_order ASC', 'task_id, test_points');
            foreach ($contest_tasks as $ct) {
                $initial_task_ids[] = $ct->task_id;
                $initial_test_points[$ct->task_id] = $ct->test_points;
            }
        }

        $pluginman = \core\plugin_manager::instance();
        $rating_plugin_info = $pluginman->get_plugin_info('block_bacs_rating');
        $is_plugin_presented = $rating_plugin_info && $rating_plugin_info->is_installed_and_upgraded() && $rating_plugin_info->is_enabled();
        $this->has_rating_table = $is_plugin_presented && $DB->get_manager()->table_exists('bacs_rating_tasks');

        $bacs_tasks_columns = $DB->get_columns('bacs_tasks');
        $has_expert_rating = array_key_exists('expert_rating', $bacs_tasks_columns);
        $expert_select = $has_expert_rating ? "t.expert_rating" : "NULL as expert_rating";

        if ($this->has_rating_table) {
            $sql_tasks = "SELECT t.task_id, t.name, t.author, t.statement_format, 
                                 t.statement_url, t.test_points as default_points,
                                 t.count_tests, t.count_pretests, t.time_limit_millis, t.memory_limit_bytes,
                                 tc.collection_id, 
                                 rt.id as rt_id, rt.elo_rating, rt.init_rating, rt.min_rating, rt.max_rating, rt.confidence, rt.submit_count, rt.contest_count, rt.seen_by_count, rt.solved,
                                 $expert_select
                          FROM {bacs_tasks} t
                          LEFT JOIN {bacs_tasks_to_collections} tc ON t.task_id = tc.task_id
                          LEFT JOIN {bacs_rating_tasks} rt ON t.task_id = rt.task_id";

            $raw_ratings = $DB->get_records('bacs_rating_tasks',[], '', 'task_id, elo_rating');
            foreach ($raw_ratings as $r) {
                $task_ratings[$r->task_id] = round($r->elo_rating);
            }
        } else {
            $sql_tasks = "SELECT t.task_id, t.name, t.author, t.statement_format, 
                                 t.statement_url, t.test_points as default_points,
                                 t.count_tests, t.count_pretests, t.time_limit_millis, t.memory_limit_bytes,
                                 tc.collection_id, NULL as elo_rating,
                                 $expert_select
                          FROM {bacs_tasks} t
                          LEFT JOIN {bacs_tasks_to_collections} tc ON t.task_id = tc.task_id";
        }
        
        $all_tasks_raw = $DB->get_records_sql($sql_tasks);
        $collections = $DB->get_records('bacs_tasks_collections',[], 'id ASC');

        $lang = current_language();

        $js_data =[
            'tasks' => array_values($all_tasks_raw),
            'collections' => array_values($collections),
            'selectedTaskIds' => $initial_task_ids,
            'savedTestPoints' => $initial_test_points,
            'hasRatingTable' => $this->has_rating_table,
            'strings' =>[
                'search' => get_string('search', 'bacs'),
                'add' => get_string('add', 'bacs'),
                'remove' => get_string('delete', 'bacs'),
                'tasks' => get_string('tasks', 'bacs'),
                'tasknotfoundid' => get_string('tasknotfoundid', 'bacs'),
                'warningzeropoints' => get_string('warningzeropoints', 'bacs'),
                'taskdeletedfromdb' => get_string('taskdeletedfromdb', 'bacs'),
                'allmaintestsconfigured' => get_string('allmaintestsconfigured', 'bacs'),
                'onlypretests' => get_string('onlypretests', 'bacs'),
                'taskrating' => get_string('taskrating', 'bacs'),
                'cannotconfigdeletedtask' => get_string('cannotconfigdeletedtask', 'bacs'),
                'configpoints' => get_string('configpoints', 'bacs'),
                'removefromcontest' => get_string('removefromcontest', 'bacs'),
                'tests_count' => get_string('tests_count', 'bacs'),
                'pre_count' => get_string('pre_count', 'bacs'),
                'full_points' => get_string('full_points', 'bacs'),
                'dragreorder' => get_string('dragreorder', 'bacs'),
                'showstatement_btn' => get_string('showstatement_btn', 'bacs'),
                'hidestatement_btn' => get_string('hidestatement_btn', 'bacs'),
                'allcollections' => get_string('allcollections', 'bacs'),
                'timelimit' => get_string('timelimit', 'bacs'),
                'memorylimit' => get_string('memorylimit', 'bacs'),
                'testspretests' => get_string('testspretests', 'bacs'),
                'add_btn' => get_string('add_btn', 'bacs'),
                'removetask' => get_string('removetask', 'bacs'),
                'pointssettings' => get_string('pointssettings', 'bacs'),
                'nostatement' => get_string('nostatement', 'bacs'),
                'pretest' => get_string('pretest', 'bacs'),
                'maintest' => get_string('maintest', 'bacs'),
                'value_short' => get_string('value_short', 'bacs'),
                'stat_submits' => get_string('stat_submits', 'bacs'),
                'stat_contests' => get_string('stat_contests', 'bacs'),
                'stat_seen' => get_string('stat_seen', 'bacs'),
                'stat_solved' => get_string('stat_solved', 'bacs'),
                'stat_rating' => get_string('stat_rating', 'bacs'),
                'stat_init' => get_string('stat_init', 'bacs'),
                'stat_expert' => get_string('stat_expert', 'bacs'),
                'stat_min' => get_string('stat_min', 'bacs'),
                'stat_max' => get_string('stat_max', 'bacs'),
                'stat_confidence' => get_string('stat_confidence', 'bacs'),
            ]
        ];


        // >>> GENERAL <<<
        $mform->addElement('header', 'general_header', get_string('general', 'core'));
        $mform->addElement('text', 'name', get_string('contestname', 'bacs'),['size' => '50', 'class' => 'modern-input']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('select', 'mode', get_string('contestmode', 'bacs'),[0 => "IOI", 1 => "ICPC", 2 => "General"],['class' => 'hidden-controller', 'id' => 'id_mode_select']);
        
        $mode_cards_html = '
        <div class="fitem">
            <div class="fitemtitle"><label>' . get_string('contestmode', 'bacs') . '</label></div>
            <div class="felement mode-selection-container">
                <div class="mode-card" data-value="0"><div class="mode-icon"><i class="bi bi-list-ol"></i></div><div class="mode-content"><div class="mode-title">IOI Mode</div><div class="mode-desc">' . get_string('mode_ioi_desc', 'bacs') . '</div></div><div class="mode-check"></div></div>
                <div class="mode-card" data-value="1"><div class="mode-icon"><i class="bi bi-clock-history"></i></div><div class="mode-content"><div class="mode-title">ICPC (ACM)</div><div class="mode-desc">' . get_string('mode_icpc_desc', 'bacs') . '</div></div><div class="mode-check"></div></div>
                <div class="mode-card" data-value="2"><div class="mode-icon"><i class="bi bi-gear-wide-connected"></i></div><div class="mode-content"><div class="mode-title">' . get_string('mode_general', 'bacs') . '</div><div class="mode-desc">' . get_string('mode_general_desc', 'bacs') . '</div></div><div class="mode-check"></div></div>
            </div>
        </div>';
        $mform->addElement('html', $mode_cards_html);

        // >>> TIMING <<<
        $mform->addElement('header', 'timing_header', get_string('timing', 'bacs'));
        $mform->addElement('date_time_selector', 'starttime', get_string('from', 'bacs'),['optional' => false]);
        $mform->addElement('date_time_selector', 'endtime', get_string('to', 'bacs'),['optional' => false]);

        $flatpickr_html = '
            <div class="fitem modern-date-wrapper">
                <div class="fitemtitle"><label>' . get_string('date', 'core') . '</label></div>
                <div class="felement w-100">
                    <div class="d-flex flex-column flex-md-row gap-3">
                        <div class="flex-grow-1">
                            <label class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-play-circle me-1"></i> ' . get_string('flatpickr_start', 'bacs') . '</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-primary border-end-0"><i class="bi bi-calendar3"></i></span>
                                <input type="text" id="modern_starttime" class="form-control border-start-0 px-2 fw-medium" placeholder="YYYY-MM-DD HH:MM">
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <label class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-stop-circle me-1"></i> ' . get_string('flatpickr_end', 'bacs') . '</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-danger border-end-0"><i class="bi bi-calendar-x"></i></span>
                                <input type="text" id="modern_endtime" class="form-control border-start-0 px-2 fw-medium" placeholder="YYYY-MM-DD HH:MM">
                            </div>
                        </div>
                    </div>
                    <div class="form-text text-muted mt-2" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle text-primary"></i> ' . get_string('flatpickr_help', 'bacs') . '
                    </div>
                </div>
            </div>';
        $mform->addElement('html', $flatpickr_html);

        // >>> SETTINGS <<<
        $mform->addElement('header', 'options_header', get_string('settings', 'bacs'));
        $mform->addElement('select', 'virtual_mode', get_string('virtualparticipation', 'bacs'),[0 => get_string('virtualparticipationdisable', 'bacs'), 1 => get_string('virtualparticipationallow', 'bacs'), 2 => get_string('virtualparticipationonly', 'bacs')], ['class' => 'modern-select']);
        
        $mform->addElement('html', '<div class="modern-toggles-container">');
        $mform->addElement('advcheckbox', 'upsolving', get_string('upsolving', 'bacs'), '', [],[0, 1]);
        $mform->setDefault('upsolving', 1);
        $mform->addElement('advcheckbox', 'presolving', get_string('presolving', 'bacs'), '',[], [0, 1]);
        $mform->addElement('advcheckbox', 'isolate_participants', get_string('isolateparticipants', 'bacs'), '', [], [0, 1]);
        $mform->addElement('advcheckbox', 'show_max_points', get_string('showmaxpoints', 'mod_bacs'), '', [],[0, 1]);
        $mform->setDefault('show_max_points', 1);
        $mform->addHelpButton('show_max_points', 'showmaxpoints', 'mod_bacs');
        $mform->addElement('html', '</div>');

        if ($groupmode && isset($bacs_instance) && isset($course)) {
            $groupsettingshtml = $this->load_groups($bacs_instance, $course);
            $mform->addElement('html', $this->get_group_settings($groupsettingshtml, $id));
        }

        // >>> TASKS MANAGEMENT <<<
        $mform->addElement('header', 'tasks_header', get_string('tasks', 'bacs'));
        $mform->addElement('html', '<script>window.BACS_FORM_DATA = ' . json_encode($js_data) . ';</script>');

        $mform->addElement('html', '<div id="bacs-classic-ui" style="display: block;">');
        
        $participants_rating_html = $this->get_participants_rating_summary($this->has_rating_table);
        
        $mform->addElement('html', $this->get_classic_tasks_html($collections, $all_tasks_raw, $is_plugin_presented, $participants_rating_html));
        $mform->addElement('html', '</div>');

        $mform->addElement('html', $this->get_modals_html());

        // >>> INCIDENTS <<<
        $mform->addElement('header', 'incidents_header', get_string('incidents', 'bacs'));
        $mform->addElement('advcheckbox', 'detect_incidents', get_string('detectincidents', 'bacs'), '', [], [0, 1]);
        $mform->setDefault('detect_incidents', 0);
        $mform->addElement('textarea', 'incidents_settings', get_string("incidentssettings", "bacs"),['rows' => 10, 'class' => 'modern-textarea code-font']);


        // >>> ADVANCED SETTINGS <<<
        $mform->addElement('header', 'advanced_settings_header', get_string('advancedcontestsettings', 'bacs'));
        $mform->addElement('html', '<div class="alert alert-warning"><p>' . get_string('advancedsettingsmessage1', 'bacs') . '</p><p><b>' . get_string('advancedwarning', 'bacs') . '</b></p></div>');

        $mform->addElement('text', 'contest_task_ids', get_string('rawcontesttaskids', 'bacs'),['size' => 80, 'class' => 'modern-input code-font', 'id' => 'id_contest_task_ids']);
        $mform->setType('contest_task_ids', PARAM_RAW);
        $mform->setDefault('contest_task_ids', implode('_', $initial_task_ids));

        $mform->addElement('text', 'contest_task_test_points', get_string('rawcontesttasktestpoints', 'bacs'),['size' => 80, 'class' => 'modern-input code-font', 'id' => 'id_contest_task_test_points']);
        $mform->setType('contest_task_test_points', PARAM_RAW);
        $mform->setDefault('contest_task_test_points', implode('_', array_values($initial_test_points)));

        $mform->setExpanded('timing_header');
        $mform->setExpanded('options_header');
        $mform->setExpanded('tasks_header');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }


    private function get_classic_tasks_html($collectionsinfo, $alltasks, $is_plugin_presented, $participants_rating_html = '') {
        
        $button_text = get_string('analyzecontestdifficulty', 'bacs');
        $disabled_attr = !$is_plugin_presented ? 'disabled="true"' : '';
        
        $difficulty_analysis_html = '
            <div id="bacs-difficulty-analysis-container" class="mt-4 mb-4 p-3 bg-light rounded border">
                <button id="bacs-difficulty-analysis-btn" class="btn btn-primary btn-sm" type="button" ' . $disabled_attr . '>
                    <i class="bi bi-bar-chart-line"></i> ' . $button_text . '
                </button>
                <div id="bacs-difficulty-analysis-loader" style="display: none; margin-top: 10px;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div>
                </div>
                <div id="bacs-difficulty-analysis-result" style="display: none; margin-top: 20px;">
                    <canvas id="bacs-difficulty-chart" style="max-width: 100%; height: 400px;"></canvas>
                </div>';
        
        if (!$is_plugin_presented) {
            $difficulty_analysis_html .= '<p class="text-muted small mt-2 mb-0">' . get_string('no_plugin_installed', 'bacs') . '</p>';
        }
        $difficulty_analysis_html .= '</div>';

        $result = '
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <span class="font-weight-bold text-dark fs-5">' . get_string('contesttasks', 'bacs') . '</span>
                <button type="button" id="open-task-manager-btn" class="btn btn-outline-primary btn-sm fw-medium shadow-sm">
                    <i class="bi bi-arrows-fullscreen me-1"></i> ' . get_string('advancededitor', 'bacs') . '
                </button>
            </div>
            
            <div class="mb-4">
                <div id="classic_tasks_reorder_list" class="list-group shadow-sm border"></div>
            </div>' . 
            
            $difficulty_analysis_html . 
            $participants_rating_html . 
            
            '<div class="d-flex align-items-center bg-light p-2 border rounded mb-3 flex-wrap gap-2">
                <span class="text-muted small fw-bold text-uppercase me-2 ms-2">' . get_string('alltasksfrom', 'bacs') . ':</span>
                <select class="form-select form-select-sm w-auto border-0 bg-white shadow-sm flex-grow-1" id="collection_container_selector" style="max-width: 300px;">
                    <option value="all">' . get_string('allcollections', 'bacs') . '</option>';
        
        foreach ($collectionsinfo as $collectioninfo) {
            $result .= "<option value='{$collectioninfo->collection_id}'>{$collectioninfo->name}</option>";
        }
        $result .= '</select>';

        if ($this->has_rating_table) {
            $result .= '<div class="d-inline-flex align-items-center ms-2">
                <span class="text-muted small fw-bold text-uppercase me-2">' . get_string('bacsrating:sortby', 'bacs') . ':</span>
                <select class="form-select form-select-sm border-0 bg-white shadow-sm" style="width:160px;" id="bacs_sort_selector">
                    <option value="" selected disabled hidden>...</option>
                    <option value="rating_desc">'. get_string('bacsrating:sortby:rating_desc', 'bacs') . '</option>
                    <option value="rating_asc">'. get_string('bacsrating:sortby:rating_asc', 'bacs') . '</option>
                </select></div>';
        }
        
        $result .= '
                <div class="input-group input-group-sm shadow-sm ms-auto" style="width: 250px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input class="form-control border-start-0 border-end-0 px-0 search-tasks" type="search" placeholder="' . get_string('search', 'bacs') . '" id="search-text">
                    <button class="btn btn-white border border-start-0 text-muted hover-danger" type="button" onclick="window.cleanSearch()"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>';

        $rating_th = $this->has_rating_table ? "<th class='py-2 text-center' style='width: 105px;'>" . get_string('bacsrating:rating', 'bacs') . "</th>" : "";

        $result .= "<div id='classic_tasks_container_dynamic' class='classic-tasks-container border rounded shadow-sm' style='width: 100%; max-height: 400px; overflow-y: auto; display: block; margin-top: 15px; overflow-x: hidden;'>
                <table class='table table-hover table-sm mb-0 bg-white align-middle' style='white-space: nowrap; table-layout: fixed; width: 100%;'>
                <thead class='table-light text-muted small text-uppercase'><tr>
                    <th class='ps-3 py-2' style='width: 65px;'>ID</th>
                    <th class='py-2' style='width: auto; overflow: hidden; text-overflow: ellipsis;'>" . get_string('taskname', 'bacs') . "</th>
                    <th class='py-2 text-center' style='width: 105px;'>" . get_string('tests_pre_header', 'bacs') . "</th>
                    <th class='py-2 text-center' style='width: 60px;'>Fmt</th>
                    {$rating_th}
                    <th class='py-2' style='width: 90px; overflow: hidden; text-overflow: ellipsis;'>" . get_string('author', 'bacs') . "</th>
                    <th class='pe-2 py-2 text-end' style='width: 110px;'></th>
                    </tr></thead>
                <tbody id='dynamic_classic_tbody'></tbody></table></div>";

        return $result;
    }

    private function get_collection_container($containerid) {
        $display = ($containerid === 'collection_container_all') ? 'block' : 'none';
        
        $rating_th = $this->has_rating_table ? "<th class='py-2 text-center' style='width: 105px;'>" . get_string('bacsrating:rating', 'bacs') . "</th>" : "";

        return "<div id='{$containerid}' class='classic-tasks-container border rounded shadow-sm' style='width: 100%; max-height: 400px; overflow-y: auto; display: {$display}; margin-top: 15px; overflow-x: hidden;'>
                <table class='table table-hover table-sm mb-0 bg-white align-middle' style='white-space: nowrap; table-layout: fixed; width: 100%;'>
                <thead class='table-light text-muted small text-uppercase'><tr>
                    <th class='ps-3 py-2' style='width: 65px;'>ID</th>
                    <th class='py-2' style='width: auto; overflow: hidden; text-overflow: ellipsis;'>" . get_string('taskname', 'bacs') . "</th>
                    <th class='py-2 text-center' style='width: 105px;'>" . get_string('tests_pre_header', 'bacs') . "</th>
                    <th class='py-2 text-center' style='width: 60px;'>Fmt</th>
                    {$rating_th}
                    <th class='py-2' style='width: 90px; overflow: hidden; text-overflow: ellipsis;'>" . get_string('author', 'bacs') . "</th>
                    <th class='pe-2 py-2 text-end' style='width: 110px;'></th>
                    </tr></thead>
                <tbody>";
    }


    private function get_rating_badge_class(int $r_val): string {
        if ($r_val > 1500) return 'bg-danger text-white border-danger';
        if ($r_val < 1000) return 'bg-success text-white border-success';
        return 'bg-warning text-dark border-warning';
    }

    private function get_tablein($task) {
        $name = htmlspecialchars($task->name);
        $format = strtoupper($task->statement_format);
        $fmt_badge = $format === 'PDF'
            ? 'bg-light text-dark border border-secondary'
            : ($format === 'HTML'
                ? 'bg-white text-dark border border-secondary'
                : 'bg-light text-dark border border-secondary');

        $r_val_data = (!empty($task->elo_rating)) ? round($task->elo_rating) : 0;

        $rating_td = "";
        if ($this->has_rating_table) {
            $is_rating_set = !empty($task->elo_rating) && !(round($task->elo_rating) == 1200 && empty($task->submit_count));
            
            $badge_html = "";
            if ($is_rating_set) {
                $r_val = round($task->elo_rating);
                $badge_class = $this->get_rating_badge_class($r_val);
                $badge_html = "<span class='badge {$badge_class} border shadow-sm' title='Рейтинг задачи: {$r_val}'>
                    <i class='bi bi-star-fill me-1'></i>{$r_val}</span>";
            }
            
            $has_data = !empty($task->rt_id) || isset($task->atstng_rating);
            $info_html = "";
            if ($has_data) {
                $rows = [];
                if (isset($task->submit_count)) $rows[] = "<div class='tooltip-row'><span>Submits:</span> <b>{$task->submit_count}</b></div>";
                if (isset($task->contest_count)) $rows[] = "<div class='tooltip-row'><span>Contests:</span> <b>{$task->contest_count}</b></div>";
                if (isset($task->seen_by_count)) $rows[] = "<div class='tooltip-row'><span>Seen by:</span> <b>{$task->seen_by_count}</b></div>";
                if (isset($task->solved)) $rows[] = "<div class='tooltip-row'><span>Solved:</span> <b>{$task->solved}</b></div>";
                if (isset($task->elo_rating)) $rows[] = "<div class='tooltip-row'><span>Rating:</span> <b>" . round($task->elo_rating, 1) . "</b></div>";
                if (isset($task->atstng_rating)) $rows[] = "<div class='tooltip-row'><span>ATSTNG:</span> <b>" . round($task->atstng_rating, 1) . "</b></div>";
                if (isset($task->rating_min)) $rows[] = "<div class='tooltip-row'><span>Min Rating:</span> <b>{$task->rating_min}</b></div>";
                if (isset($task->rating_max)) $rows[] = "<div class='tooltip-row'><span>Max Rating:</span> <b>{$task->rating_max}</b></div>";
                if (isset($task->confidence)) $rows[] = "<div class='tooltip-row'><span>Confidence:</span> <b>{$task->confidence}</b></div>";
                
                if (!empty($rows)) {
                    $rows_html = implode('', $rows);
                    $info_html = "
                    <span class='bacs-custom-tooltip ms-1'>
                        <i class='bi bi-info-circle-fill text-secondary' style='font-size: 0.85rem; cursor: help; opacity: 0.6;'></i>
                        <div class='tooltip-panel'>
                            {$rows_html}
                        </div>
                    </span>";
                }
            }

            if ($badge_html || $info_html) {
                $rating_td = "<td class='text-center'><div class='d-inline-flex align-items-center justify-content-center'>{$badge_html}{$info_html}</div></td>";
            } else {
                $rating_td = "<td class='text-center text-muted small'>-</td>";
            }
        }

        $tests_info = ($task->count_tests ?? 0) . " <span class='text-muted'>(" . ($task->count_pretests ?? 0) . ")</span>";

        return "<tr data-rating='{$r_val_data}'>" .
                "<td class='ps-3 text-muted small'>{$task->task_id}</td>" .
                "<td class='text-truncate' style='max-width: 250px;'><a href='{$task->statement_url}' target='_blank' class='text-decoration-none fw-medium text-dark hover-primary'>{$name}</a></td>" .
                "<td class='text-center'><span class='small fw-medium'>{$tests_info}</span></td>" .
                "<td><span class='badge {$fmt_badge} bg-opacity-75' style='font-size: 0.7em;'>{$format}</span></td>" .
                $rating_td .
                "<td class='text-muted small text-truncate' style='max-width: 150px;'>{$task->author}</td>" .
                "<td class='pe-2 text-end'><button type='button' class='btn btn-sm btn-light text-primary border shadow-sm px-2 py-1' style='font-size: 0.8rem;' onclick='window.addTaskClassic({$task->task_id})'>
                    <i class=\"bi bi-plus-lg\"></i> " . get_string('add', 'bacs') . "</button></td>" .
                "</tr>";
    }

    private function get_group_settings($groupsettingshtml, $id) {
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
                'bacs_group_info',['contest_id' => $bacs->id, 'group_id' => $groupid],
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
            $strparamsobj = (object)[
                'with_group_settings' => $groupswithgroupsettings,
                'total_count' => $groupstotalcount,
            ];

            $groupsettingshtml = get_string('groupsettingsareused', 'bacs', $strparamsobj);
        }
        return $groupsettingshtml;
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
                    require(['jquery', 'core/chartjs'], function($, ChartJS) {
                        if (typeof window.jQuery === 'undefined') {
                            window.jQuery = $;
                            window.$ = $;
                        }
                        window.Chart = ChartJS;
                        
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
                            setTimeout(initDifficultyAnalysis, 100);
                        }
                    });
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initDifficultyAnalysis);
                } else {
                    setTimeout(initDifficultyAnalysis, 100);
                }
            })();
        ");
    }

    private function get_participants_rating_summary($has_rating) {
        global $DB, $PAGE;

        if (!$has_rating) return '';

        $context = $PAGE->context;
        $users = get_enrolled_users($context, 'mod/bacs:view', 0, 'u.*');

        if (empty($users)) return '';

        $user_ids = array_keys($users);
        $count_rated = 0;
        $sum_rating = 0;
        $users_data =[];

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

            $users_data[] =[
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
        $str_participant = get_string('bacsrating:participant', 'bacs');
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
        $html .= '<table class="table table-sm table-hover bg-white mb-0" style="width: 100%; font-size: 0.9em;">';
        $html .= '<thead class="table-light"><tr><th style="position: sticky; top: 0;">' . $str_participant . '</th><th style="position: sticky; top: 0;">'. $str_rating .'</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($users_data as $ud) {
            $html .= '<tr>';
            $html .= '<td>' . $ud['name'] . '</td>';
            $style = ($ud['rating'] !== '-') ? 'font-weight: bold;' : 'color: #999;';
            $html .= '<td style="' . $style . '">' . $ud['rating'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></details></div>';
        return $html;
    }

    private function get_modals_html() {
        return '
            <div id="bacs-manager-modal" class="bacs-manager-modal hidden">
                <div class="manager-content">
                    <div class="manager-header">
                        <div class="d-flex align-items-center gap-3">
                            <h2 style="margin:0;">' . get_string('taskmanager', 'bacs') . '</h2>
                            <button type="button" id="toggle-statement-btn" class="btn btn-sm btn-outline-secondary">
                                ' . get_string('hidestatement_btn', 'bacs') . '
                            </button>
                        </div>
                        <button type="button" class="close-manager-btn">&times;</button>
                    </div>
                    <div class="manager-grid">
                        <div class="manager-col col-statement" data-col="statement">
                            <div class="col-header">
                                <div class="d-flex align-items-center gap-2"><span class="col-drag-handle">⋮⋮</span><span>' . get_string('statement', 'bacs') . '</span></div>
                                <a id="statement-external-link" href="#" target="_blank" class="btn btn-sm btn-link hidden">' . get_string('open_link', 'bacs') . '</a>
                            </div>
                            <div class="statement-container">
                                <div id="statement-placeholder" class="statement-placeholder"><p>' . get_string('nostatement', 'bacs') . '</p></div>
                                <iframe id="statement-frame" class="statement-frame hidden" src=""></iframe>
                                <img id="statement-image" class="statement-image hidden" src="">
                            </div>
                        </div>
                        <div class="manager-col col-source" data-col="source">
                            <div class="col-header">
                                <div class="d-flex align-items-center gap-2"><span class="col-drag-handle">⋮⋮</span><span>' . get_string('availabletasks', 'bacs') . '</span></div>
                            </div>
                            <div class="col-filters">
                                <input type="text" id="manager-search" class="modern-input-sm" placeholder="' . get_string('search', 'bacs') . '...">
                                <select id="manager-collection" class="modern-select-sm"></select>
                            </div>
                            <div id="manager-source-list" class="manager-list custom-scroll"></div>
                        </div>
                        <div class="manager-col col-target" data-col="target">
                            <div class="col-header">
                                <div class="d-flex align-items-center gap-2"><span class="col-drag-handle">⋮⋮</span><span>' . get_string('selectedtasks', 'bacs') . ' <span id="selected-count-badge" class="badge">0</span></span></div>
                            </div>
                            <div id="manager-target-list" class="manager-list custom-scroll"></div>
                        </div>
                    </div>
                    <div class="manager-footer">
                        <button type="button" class="btn btn-success fw-bold px-4 close-manager-btn">' . get_string('save', 'core') . '</button>
                    </div>
                </div>
            </div>

            <div id="test-points-modal" class="bacs-modal hidden">
                <div class="bacs-modal-content points-modal-content">
                    <div class="modal-header">
                        <h3 class="m-0">' . get_string('testpoints', 'bacs') . '</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    <p id="modal-task-name" class="text-primary fw-bold mb-3" style="font-size: 1rem;"></p>

                    <div class="mb-3 d-flex align-items-center justify-content-between bg-light p-2 rounded border">
                        <label class="mb-0 fw-bold text-dark ms-2">' . get_string('pointsforfullsolution_label', 'bacs') . '</label>
                        <input type="number" id="modal-full-points" class="form-control text-center me-2 border-primary" style="width: 100px; font-weight: bold;" value="0" min="0">
                    </div>

                    <div class="toolbar-box">
                        <div class="toolbar-row main-tools d-flex flex-wrap align-items-center" style="gap: 10px;">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-light border btn-preset" data-val="1">1</button>
                                <button type="button" class="btn btn-sm btn-light border btn-preset" data-val="5">5</button>
                                <button type="button" class="btn btn-sm btn-light border btn-preset" data-val="10">10</button>
                                <button type="button" class="btn btn-sm btn-light border btn-preset" data-val="25">25</button>
                            </div>
                            <div class="separator d-none d-md-block"></div>
                            
                            <div class="range-inputs d-flex align-items-center flex-wrap" style="gap: 6px;">
                                <label class="fw-bold" style="font-size: 0.85rem;">' . get_string('tests_label', 'bacs') . '</label>
                                <input type="number" id="range-start" class="form-control form-control-sm text-center px-1" value="1" min="1" style="width: 50px;">
                                <span class="text-muted fw-bold">-</span>
                                <input type="number" id="range-end" class="form-control form-control-sm text-center px-1" value="" min="1" style="width: 50px;">
                                <span class="text-muted fw-bold">=</span>
                                <input type="number" id="range-val" class="form-control form-control-sm border-primary text-center px-1" placeholder="' . get_string('value_short', 'bacs') . '" style="width: 60px; background: #f0fdfa;">
                                <button type="button" id="btn-range-apply" class="btn btn-sm btn-secondary ms-1">' . get_string('set_btn', 'bacs') . '</button>
                            </div>
                        </div>
                        
                        <div class="w-100 my-2 border-top"></div>
                        
                        <div class="toolbar-row secondary-tools d-flex flex-wrap align-items-center mt-2" style="gap: 15px;">
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <label class="mb-0" style="font-size: 0.85rem; font-weight: 600; white-space: nowrap;">' . get_string('normalizeto', 'bacs') . '</label>
                                <input type="number" id="norm-target" class="form-control form-control-sm text-center" value="100" min="1" style="width: 65px;">
                                <button type="button" id="btn-normalize" class="btn btn-sm btn-info text-white" style="white-space: nowrap;">' . get_string('scale_btn', 'bacs') . '</button>
                            </div>
                            
                            <div class="form-check mb-0 d-flex align-items-center">
                                <input class="form-check-input mt-0" type="checkbox" id="norm-include-pretests">
                                <label class="form-check-label ms-2" for="norm-include-pretests" style="font-size: 0.85rem; white-space: nowrap;">' . get_string('includepretests', 'bacs') . '</label>
                            </div>

                            <div class="ms-auto">
                                <button type="button" id="btn-clear-grid" class="btn btn-sm btn-outline-danger" style="white-space: nowrap;">' . get_string('clearall', 'bacs') . '</button>
                            </div>
                        </div>
                    </div>

                    <div id="visual-points-container" class="points-grid-wrapper custom-scroll">
                        <div id="visual-points-grid" class="points-grid"></div>
                        <div id="visual-unknown-count" class="hidden text-center py-4">
                            <p class="text-muted">' . get_string('testcountundefined', 'bacs') . '</p>
                            <button type="button" id="btn-gen-10" class="btn btn-sm btn-outline-primary">' . get_string('create10', 'bacs') . '</button>
                            <button type="button" id="btn-gen-20" class="btn btn-sm btn-outline-primary">' . get_string('create20', 'bacs') . '</button>
                        </div>
                    </div>

                    <div class="raw-footer-area">
                        <div class="raw-section">
                            <label for="modal-points-input" style="font-size: 0.8rem; font-weight: bold;">' . get_string('rawdatacomma', 'bacs') . '</label>
                            <textarea id="modal-points-input" class="modern-textarea code-font" rows="2"></textarea>
                        </div>
                        <div class="modal-footer-row mt-2">
                            <div class="total-score">' . get_string('total_label', 'bacs') . ' <strong id="points-total-sum" class="text-success">0</strong></div>
                            <button type="button" id="save-points-btn" class="btn btn-success fw-bold px-4">' . get_string('save', 'core') . '</button>
                        </div>
                    </div>
                </div>
            </div>
        ';
    }
}