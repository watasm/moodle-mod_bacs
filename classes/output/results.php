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


namespace mod_bacs\output;

use mod_bacs\contest;
use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Class results
 * @package mod_bacs
 */
class results implements renderable, templatable {
    /**
     * @var mixed
     */
    public $submits = [];
    /**
     * @var mixed
     */
    public $contesttasksbacs = [];
    /**
     * @var mixed
     */
    public $coursemoduleidbacs = null;
    /**
     * @var mixed
     */
    public $usercapabilitiesbacs;

    /**
     * @var mixed
     */
    public $config;

    /**
     * Initializing
     * @param contest $contest
     */
    public function __construct($contest) {
        require_once(dirname(dirname(dirname(__FILE__))) . '/submit_verdicts.php');

        // This component is now bound to be used only in single-contest mode
        // also this component must be unique within HTML page,
        // multiple instances are not supported.

        // ...prepare default config.
        $this->config = (object) [
            'show_dates_at_separate_rows' => false,
            'show_dates_with_sent_at_time' => true,
            'show_full_task_names' => true,
            'provide_submit_links_in_header' => false,
            'provide_submit_links_in_body' => false,
            'can_be_collapsed' => false,
            'solutions_can_be_collapsed' => true,
            'ace_editor_big' => false,
            'show_submit_actions_panel' => false,

            'show_detailed_info' => false,

            'show_column_collapse' => true,
            'show_column_n'        => true,
            'show_column_id'       => true,
            'show_column_task'     => true,
            'show_column_language' => true,
            'show_column_author'   => true,
            'show_column_points'   => '', // ...defined by contest mode.
            'show_column_result'   => true,
            'show_column_time'     => true,
            'show_column_memory'   => true,
            'show_column_sent_at'  => true,
        ];

        // ...load default parameters from the contest.
        $this->usercapabilitiesbacs = $contest->usercapabilitiesbacs;
        $this->coursemoduleidbacs = $contest->coursemodule->id;

        $this->config->show_column_points = $contest->get_show_points();

        foreach ($contest->tasks as $task) {
            $resultstask = (object) [
                'lettered_name' => $task->lettered_name,
                'task_id'       => $task->task_id,
            ];

            $this->contesttasksbacs[] = $resultstask;
        }
    }

    /**
     * Updates config options with all key/value pairs
     * from provided array or object
     *
     * @param array|object $newconfig Collection of key/value pairs to update
     */
    public function configure($newconfig) {
        $this->config = (object) array_merge(
            (array) $this->config,
            (array) $newconfig
        );
    }

    /**
     * This function
     * @param object $submit
     * @return void
     */
    public function add_submit($submit) {
        $this->submits[] = $submit;
    }

    /**
     * This function
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $previousdate = "";
        $idx = 0;
        foreach ($this->submits as &$cursubmit) {
            $idx++;
            $cursubmit->idx = $idx;

            $cursubmit->coursemoduleidbacs = $this->coursemoduleidbacs;

            $cursubmit->rcfgshowfulltasknamesbacs           = $this->config->show_full_task_names;
            $cursubmit->rcfgprovidesubmitlinksinheaderbacs = $this->config->provide_submit_links_in_header;
            $cursubmit->rcfgprovidesubmitlinksinbodybacs   = $this->config->provide_submit_links_in_body;
            $cursubmit->rcfgshowdateswithsentattimebacs   = $this->config->show_dates_with_sent_at_time;

            $newdate = userdate($cursubmit->submittimebacs, "%d %B %Y (%A)");

            $cursubmit->showdateatseparaterowbacs =
                ($previousdate != $newdate) &&
                $this->config->show_dates_at_separate_rows;

            $previousdate = $newdate;
        }

        $data = new stdClass();

        $data->contest_tasks   = $this->contesttasksbacs;
        $data->coursemodule_id = $this->coursemoduleidbacs;

        $data->rcfg_show_detailed_info             = $this->config->show_detailed_info;
        $data->rcfg_provide_submit_links_in_header = $this->config->provide_submit_links_in_header;
        $data->rcfg_provide_submit_links_in_body   = $this->config->provide_submit_links_in_body;
        $data->rcfg_ace_editor_big                 = $this->config->ace_editor_big;
        $data->rcfg_can_be_collapsed               = $this->config->can_be_collapsed;
        $data->rcfg_solutions_can_be_collapsed     = $this->config->solutions_can_be_collapsed;
        $data->rcfg_show_submit_actions_panel      = $this->config->show_submit_actions_panel;

        $data->rcfg_show_column_collapse = $this->config->show_column_collapse;
        $data->rcfg_show_column_n        = $this->config->show_column_n;
        $data->rcfg_show_column_id       = $this->config->show_column_id;
        $data->rcfg_show_column_task     = $this->config->show_column_task;
        $data->rcfg_show_column_language = $this->config->show_column_language;
        $data->rcfg_show_column_author   = $this->config->show_column_author;
        $data->rcfg_show_column_points   = $this->config->show_column_points;
        $data->rcfg_show_column_result   = $this->config->show_column_result;
        $data->rcfg_show_column_time     = $this->config->show_column_time;
        $data->rcfg_show_column_memory   = $this->config->show_column_memory;
        $data->rcfg_show_column_sent_at  = $this->config->show_column_sent_at;

        $data->user_capability_viewany = $this->usercapabilitiesbacs->viewany;
        $data->user_capability_edit    = $this->usercapabilitiesbacs->edit;

        $data->submits = [];
        foreach ($this->submits as &$cursubmit) {
            $data->submits[] = $cursubmit->export_for_template();
        }

        return $data;
    }
}
