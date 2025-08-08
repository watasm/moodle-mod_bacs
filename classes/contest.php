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


namespace mod_bacs;

use coding_exception;
use context_course;
use context_module;
use dml_exception;
use Exception;
use mod_bacs\output\contest_header;
use mod_bacs\output\contest_nav_menu;
use moodle_exception;
use moodle_url;
use require_login_exception;
use stdClass;

/**
 * Class contest
 * @package mod_bacs
 */
class contest {
    /**
     * @var mixed
     */
    public $specificmodebacs;
    /**
     * @var mixed
     */
    public $usercapabilitiesbacs;

    /**
     * @var mixed
     */
    public $coursemodule;
    /**
     * @var mixed
     */
    public $bacs;
    /**
     * @var mixed
     */
    public $bacsoutput;
    /**
     * @var mixed
     */
    public $course;

    /**
     * @var mixed
     */
    public $currentgroupidbacs;
    /**
     * @var mixed
     */
    public $currentgroupidorzerobacs;
    /**
     * @var mixed
     */
    public $currentgroupinfobacs;
    /**
     * @var mixed
     */
    public $groupmode;
    /**
     * @var mixed
     */
    public $allowedgroupsbacs;

    /**
     * @var mixed
     */
    public $virtualmodebacs = 0;
    /**
     * @var mixed
     */
    public $isinvirtualparticipationbacs = false;
    /**
     * @var mixed
     */
    public $virtualparticipationisforcedbacs = false;
    /**
     * @var mixed
     */
    public $nonvirtualstarttimebacs;
    /**
     * @var mixed
     */
    public $nonvirtualendtimebacs;

    /**
     * @var mixed
     */
    public $isolateparticipantsbacs;
    /**
     * @var mixed
     */
    public $isolatedparticipantmodeisforcedbacs;
    /**
     * @var mixed
     */
    public $pageisallowedforisolatedparticipantbacs = false;

    /**
     * @var mixed
     */
    public $virtualparticipationallrecordsbacs = false;
    /**
     * @var mixed
     */
    public $virtualparticipationbyuseridbacs = [];
    /**
     * @var mixed
     */
    public $virtualparticipationbygroupanduseridbacs = [];

    /**
     * @var mixed
     */
    public $pageurlbacs;
    /**
     * @var mixed
     */
    public $tasks = [];
    /**
     * @var mixed
     */
    public $langs = [];

    /**
     * @var mixed
     */
    public $langbylangidarraybacs = [];

    /**
     * @var mixed
     */
    public $queryparamsarraybacs = [];
    /**
     * @var mixed
     */
    public $queryparamsbacs = [];

    /**
     * @var mixed
     */
    public $cachedstudentsbacs = false;

    /**
     * @var mixed
     */
    public $targetuseridbacs;
    /**
     * @var mixed
     */
    public $targetuserfirstnamebacs;
    /**
     * @var mixed
     */
    public $targetuserlastnamebacs;

    /**
     * @var mixed
     */
    public $activemenutabbacs = '';
    /**
     * @var mixed
     */
    public $menushownbacs = true;
    /**
     * @var mixed
     */
    public $groupselectorshownbacs = true;

    /**
     * @var mixed
     */
    public $aceeditorshownbacs;
    /**
     * @var mixed
     */
    public $aceeditorredirecturlbacs;


    /**
     * @var mixed
     */
    public $groupinfobygroupidbacs;
    /**
     * @var mixed
     */
    public $groupsenabledbacs;
    /**
     * @var mixed
     */
    public $noavailablegroupsbacs;
    /**
     * @var mixed
     */
    public $usecurrentgroupsettingsbacs;
    /**
     * @var mixed
     */
    public $useearliestgroupsettingsbacs;
    /**
     * @var mixed
     */
    public $earliestgroupinfobacs;
    /**
     * @var mixed
     */
    public $starttime;
    /**
     * @var mixed
     */
    public $endtime;
    /**
     * @var mixed
     */
    public $upsolving;
    /**
     * @var mixed
     */
    public $presolving;
    /**
     * @var mixed
     */
    public $context;

    /**
     *
     */
    public function __construct() {
    }

    /**
     * This function
     * @param string $specificmode
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws require_login_exception
     */
    public function initialize_page($specificmode = '') {
        global $DB, $PAGE;

        require_once(dirname(dirname(__FILE__)) . '/submit_verdicts.php');

        $this->specificmodebacs = $specificmode;

        $this->register_query_param('id', 0, PARAM_INT); // ...course_module ID.
        $this->register_query_param('b', 0, PARAM_INT);  // ...bacs instance ID.
        $this->register_query_param('acetheme', '', PARAM_TEXT); // Ace editor theme.

        if ($this->queryparamsbacs->id) {
            $this->coursemodule = get_coursemodule_from_id('bacs', $this->queryparamsbacs->id, 0, false, MUST_EXIST);
            $this->course       = $DB->get_record('course', ['id' => $this->coursemodule->course], '*', MUST_EXIST);
            $this->bacs         = $DB->get_record('bacs', ['id' => $this->coursemodule->instance], '*', MUST_EXIST);
        } else if ($this->queryparamsbacs->b) {
            $this->bacs         = $DB->get_record('bacs', ['id' => $this->queryparamsbacs->b], '*', MUST_EXIST);
            $this->course       = $DB->get_record('course', ['id' => $this->bacs->course], '*', MUST_EXIST);
            $this->coursemodule = get_coursemodule_from_instance('bacs', $this->bacs->id, $this->course->id, false, MUST_EXIST);
        } else {
            throw new Exception('You must specify a course_module ID or an instance ID');
        }

        require_login($this->course, true, $this->coursemodule);
        $this->context = context_module::instance($this->coursemodule->id);

        $this->usercapabilitiesbacs = (object) [
            'addinstance'     => has_capability('mod/bacs:addinstance', $this->context),
            'edit'            => has_capability('mod/bacs:edit', $this->context),
            'view'            => has_capability('mod/bacs:view', $this->context),
            'submit'          => has_capability('mod/bacs:submit', $this->context),
            'readtasks'       => has_capability('mod/bacs:readtasks', $this->context),
            'viewany'         => has_capability('mod/bacs:viewany', $this->context),
            'accessallgroups' => has_capability('moodle/site:accessallgroups', $this->context),
        ];

        // ...prepare group, virtual participation and contest data.
        $allgroupinfoavailable = $DB->get_records('bacs_group_info', ['contest_id' => $this->bacs->id]);
        $this->groupinfobygroupidbacs = [];
        foreach ($allgroupinfoavailable as $curgroupinfo) {
            $this->groupinfobygroupidbacs[$curgroupinfo->group_id] = $curgroupinfo;
        }

        $this->groupmode = groups_get_activity_groupmode($this->coursemodule);
        $this->groupsenabledbacs = ($this->groupmode != NOGROUPS);
        $this->currentgroupidbacs = groups_get_activity_group($this->coursemodule, /* update */ true);
        $this->currentgroupidorzerobacs = ($this->groupsenabledbacs ? $this->currentgroupidbacs : 0);
        $this->currentgroupinfobacs = $this->get_group_info_by_group_id($this->currentgroupidbacs);
        $this->allowedgroupsbacs = groups_get_activity_allowed_groups($this->coursemodule);
        $this->noavailablegroupsbacs = (count($this->allowedgroupsbacs) == 0);

        $this->usecurrentgroupsettingsbacs =
                $this->groupsenabledbacs &&
                $this->currentgroupidbacs > 0 &&
                $this->currentgroupinfobacs &&
                $this->currentgroupinfobacs->use_group_settings;

        $this->useearliestgroupsettingsbacs =
                $this->groupsenabledbacs &&
                $this->currentgroupidbacs == 0 &&
                count($this->allowedgroupsbacs) > 0;

        // ...if using earliest group, build earliest group info.
        if ($this->useearliestgroupsettingsbacs) {
            $this->earliestgroupinfobacs = $this->get_earliest_group_settings();
        }

        $currentgroupisforbidden =
            $this->groupsenabledbacs &&
            $this->currentgroupidbacs != 0 &&
            !in_array($this->currentgroupidbacs, array_keys($this->allowedgroupsbacs));

        if ($currentgroupisforbidden) {
            throw new moodle_exception('groupnotamember', 'group');
        }

        $fitsnoseparategroup =
            $this->groupmode == SEPARATEGROUPS &&
            !$this->usercapabilitiesbacs->accessallgroups &&
            $this->noavailablegroupsbacs;

        if ($fitsnoseparategroup) {
            throw new moodle_exception('groupnotamember', 'group');
        }

        if ($this->usecurrentgroupsettingsbacs) {
            $this->starttime  = $this->currentgroupinfobacs->starttime;
            $this->endtime    = $this->currentgroupinfobacs->endtime;
            $this->upsolving  = $this->currentgroupinfobacs->upsolving;
            $this->presolving = $this->currentgroupinfobacs->presolving;
        } else if ($this->useearliestgroupsettingsbacs) {
            $this->starttime  = $this->earliestgroupinfobacs->starttime;
            $this->endtime    = $this->earliestgroupinfobacs->endtime;
            $this->upsolving  = $this->earliestgroupinfobacs->upsolving;
            $this->presolving = $this->earliestgroupinfobacs->presolving;
        } else {
            $this->starttime  = $this->bacs->starttime;
            $this->endtime    = $this->bacs->endtime;
            $this->upsolving  = $this->bacs->upsolving;
            $this->presolving = $this->bacs->presolving;
        }

        $this->nonvirtualstarttimebacs = $this->starttime;
        $this->nonvirtualendtimebacs = $this->endtime;

        $this->isolateparticipantsbacs = $this->bacs->isolate_participants;

        $this->isolatedparticipantmodeisforcedbacs =
            $this->isolateparticipantsbacs &&
            !$this->usercapabilitiesbacs->viewany;

        $this->virtualmodebacs = $this->bacs->virtual_mode;

        $this->isinvirtualparticipationbacs =
            ($this->get_virtual_participation_for_user() instanceof stdClass);

        $this->virtualparticipationisforcedbacs =
            $this->bacs->virtual_mode == 2 /* virtual only */ &&
            !$this->isinvirtualparticipationbacs &&
            !$this->usercapabilitiesbacs->viewany;

        if ($this->isinvirtualparticipationbacs) {
            $this->starttime = $this->get_virtual_participation_for_user()->starttime;
            $this->endtime   = $this->get_virtual_participation_for_user()->endtime;
        }

        // ...prepare page.
        if (is_null($this->pageurlbacs)) {
            $this->pageurlbacs = new moodle_url('/mod/bacs/view.php', ['id' => $this->coursemodule->id]);
        }
        $PAGE->set_url($this->pageurlbacs);

        $this->bacsoutput = $PAGE->get_renderer('mod_bacs');

        $this->aceeditorredirecturlbacs = "view.php?id=" . $this->coursemodule->id . "&acetheme={acetheme}";

        $PAGE->requires->js('/mod/bacs/thirdparty/ace/src-min-noconflict/ace.js', true);

        $PAGE->set_title(format_string($this->bacs->name));
        $PAGE->set_heading(format_string($this->course->fullname));
        $PAGE->set_context($this->context);

        // ...make redirect checks.
        $now = time();
        if (!$this->presolving && $this->starttime > $now && $specificmode != 'contest_not_started') {
            bacs_redirect_via_js("contest_not_started.php?id=" . $this->coursemodule->id);
            die('Contest has not started / Контест ещё не начался.');
        }

        $virtualparticipationforcedredirect =
            $this->virtualparticipationisforcedbacs &&
            $specificmode != 'contest_not_started' &&
            $specificmode != 'virtual_contest';

        if ($virtualparticipationforcedredirect) {
            bacs_redirect_via_js("virtual_contest.php?id=" . $this->coursemodule->id);
            die('Contest is virtual only / Контест только виртуальный.');
        }

        $forbiddenasforisolatedparticipant =
            $this->isolatedparticipantmodeisforcedbacs &&
            !$this->pageisallowedforisolatedparticipantbacs;

        if ($forbiddenasforisolatedparticipant) {
            bacs_redirect_via_js("tasks.php?id=" . $this->coursemodule->id);
            die('This page is forbidden for isolated participant / Эта страница недоступна изолированным участникам.');
        }

        // ...prepare tasks.
        $taskstocontest = $DB->get_records('bacs_tasks_to_contests', ['contest_id' => $this->bacs->id], 'task_order ASC');
        foreach ($taskstocontest as $tasktocontest) {
            $task = $DB->get_record('bacs_tasks', ['task_id' => $tasktocontest->task_id], '*', IGNORE_MISSING);
            $taskismissing = (!$task);

            if ($taskismissing) {
                $task = $this->get_missing_task($tasktocontest->task_id);
            }

            $task->task_order  = $tasktocontest->task_order;
            $task->test_points = $tasktocontest->test_points;
            $task->letter = chr(ord('A') + $task->task_order - 1);
            $task->lettered_name = "$task->letter. $task->name";
            $task->is_missing = $taskismissing;

            $this->tasks[] = $task;
        }

        // ...prepare langs.
        $acemodebycompilertype = [
            'd' => 'd',
            'dart' => 'dart',
            'dotnet' => 'csharp',
            'fpc' => 'pascal',
            'gcc' => 'c_cpp',
            'golang' => 'golang',
            'haskell' => 'haskell',
            'java' => 'java',
            'kotlin' => 'kotlin',
            'mono' => 'csharp',
            'node' => 'javascript',
            'perl' => 'perl',
            'php' => 'php',
            'python' => 'python',
            'ruby' => 'ruby',
            'rust' => 'rust',
            'scala' => 'scala',
            'zig' => 'zig',
        ];


        foreach ($DB->get_records('bacs_langs') as $lang) {
            if (array_key_exists($lang->compiler_type, $acemodebycompilertype)) {
                $lang->acemode = $acemodebycompilertype[$lang->compiler_type];
            } else {
                $lang->acemode = 'c_cpp';
            }

            $this->langbylangidarraybacs[$lang->lang_id] = $lang;
            $this->langs[] = $lang;
        }
    }

    /**
     * This function
     * @param int $taskid
     * @return stdClass
     * @throws coding_exception
     */
    public function get_missing_task($taskid) {
        $task = new stdClass();

        $task->id = 0;
        $task->task_id = $taskid;
        $task->name = "[" . get_string('uppercasetasknotfound', 'mod_bacs') . ", ID = $taskid]";
        $task->names = [];
        $task->time_limit_millis = 0;
        $task->memory_limit_bytes = 0;
        $task->count_tests = 0;
        $task->count_pretests = 0;
        $task->test_points = "";
        $task->statement_url = "";
        $task->statement_urls = [];
        $task->author = "";
        $task->revision = "";
        $task->statement_format = "";

        return $task;
    }

    /**
     * This function
     * @param int $userid
     * @return object
     */
    public function get_earliest_group_settings($userid = 0) {
        $earliestgroupsettings = (object) [
            'starttime' => INF,
            'endtime' => INF,
            'upsolving' => false,
            'presolving' => false,
        ];

        if ($userid == 0) {
            $allowedgroupsforgivenuser = $this->allowedgroupsbacs;
        } else {
            $allowedgroupsforgivenuser =
                groups_get_activity_allowed_groups($this->coursemodule, $userid);
        }

        foreach ($allowedgroupsforgivenuser as $curgroup) {
            $groupinfo = $this->get_group_info_by_group_id($curgroup->id);
            $virtualpinfo = $this->get_virtual_participation_for_user($userid, $curgroup->id);

            if ($groupinfo && $groupinfo->use_group_settings) {
                $cursettings = $groupinfo;
            } else {
                $cursettings = $this->bacs;
            }

            if ($virtualpinfo) {
                $virtualdelta = $virtualpinfo->starttime - $cursettings->starttime;

                $cursettings->starttime += $virtualdelta;
                $cursettings->endtime += $virtualdelta;
            }

            if ($earliestgroupsettings->starttime > $cursettings->starttime) {
                $earliestgroupsettings->starttime = $cursettings->starttime;
                $earliestgroupsettings->endtime   = $cursettings->endtime;
            }

            if ($cursettings->upsolving) {
                $earliestgroupsettings->upsolving = true;
            }
            if ($cursettings->presolving) {
                $earliestgroupsettings->presolving = true;
            }
        }

        $noavailablegroup =
            (count($allowedgroupsforgivenuser) == 0);

        if ($noavailablegroup) {
            $earliestgroupsettings->starttime = $this->bacs->starttime;
            $earliestgroupsettings->endtime   = $this->bacs->endtime;
        }

        return $earliestgroupsettings;
    }

    /**
     * This function
     * @param int $groupid
     * @return false|mixed
     */
    public function get_group_info_by_group_id($groupid) {
        if (is_null($groupid)) {
            return false;
        }
        if ($groupid === false) {
            return false;
        }

        if (array_key_exists($groupid, $this->groupinfobygroupidbacs)) {
            return $this->groupinfobygroupidbacs[$groupid];
        } else {
            return false;
        }
    }

    /**
     * This function
     * @return array|mixed
     * @throws dml_exception
     */
    public function get_students() {
        global $DB;

        if (is_array($this->cachedstudentsbacs)) {
            return $this->cachedstudentsbacs;
        }

        // ...select students.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $contextinstance = context_course::instance($this->course->id);
        $studentswithfullinfo = get_role_users($studentrole->id, $contextinstance);

        if ($this->currentgroupidorzerobacs == 0) {
            $selectedstudentswithfullinfo = $studentswithfullinfo;
        } else {
            $groupmembers = groups_get_members($this->currentgroupidbacs);
            $groupstudentswithfullinfo = [];
            foreach ($studentswithfullinfo as $curstudent) {
                if (array_key_exists($curstudent->id, $groupmembers)) {
                    $groupstudentswithfullinfo[$curstudent->id] = $curstudent;
                }
            }

            $selectedstudentswithfullinfo = $groupstudentswithfullinfo;
        }

        // ...build and fill required info.
        $selectedstudents = [];
        foreach ($selectedstudentswithfullinfo as $curstudent) {
            $starttimeforstudent = $this->nonvirtualstarttimebacs;
            $endtimeforstudent   = $this->nonvirtualendtimebacs;

            if ($this->groupsenabledbacs && $this->currentgroupidbacs == 0) { // ...if all groups.
                $usercontestsettings =
                    $this->get_earliest_group_settings($curstudent->id); // ...this includes virtual participation.

                $starttimeforstudent = $usercontestsettings->starttime;
                $endtimeforstudent   = $usercontestsettings->endtime;
            } else {
                $curvp = $this->get_virtual_participation_for_user($curstudent->id, $this->currentgroupidorzerobacs);

                if ($curvp) {
                    $starttimeforstudent = $curvp->starttime;
                    $endtimeforstudent   = $curvp->endtime;
                }
            }

            $selectedstudents[$curstudent->id] = (object) [
                'id'        => $curstudent->id,
                'firstname' => $curstudent->firstname,
                'lastname'  => $curstudent->lastname,
                'starttime' => $starttimeforstudent,
                'endtime'   => $endtimeforstudent,
            ];
        }

        $this->cachedstudentsbacs = $selectedstudents;

        return $this->cachedstudentsbacs;
    }

    /**
     * This function
     * @return void
     * @throws dml_exception
     */
    public function prepare_virtual_participation_data() {
        global $DB, $USER;

        // ...only call once.
        if ($this->virtualparticipationallrecordsbacs) {
            return;
        }

        // ...prepare all records.
        $conditions = ['contest_id' => $this->bacs->id];

        if ($this->groupsenabledbacs && $this->currentgroupidbacs != 0) {
            $conditions['group_id'] = $this->currentgroupidbacs;
        }

        $this->virtualparticipationallrecordsbacs = $DB->get_records('bacs_virtual_participants', $conditions);

        // ...prepare earliest vp for each user.
        $vpbyuserid = & $this->virtualparticipationbyuseridbacs;

        foreach ($this->virtualparticipationallrecordsbacs as $curvp) {
            $changetocurvp =
                !array_key_exists($curvp->user_id, $vpbyuserid) ||
                $curvp->starttime < $vpbyuserid[$curvp->user_id]->starttime;

            if ($changetocurvp) {
                $vpbyuserid[$curvp->user_id] = $curvp;
            }
        }

        // ...prepare earliest vp for each group & user.
        $vpbygu = & $this->virtualparticipationbygroupanduseridbacs;

        foreach ($this->virtualparticipationallrecordsbacs as $curvp) {
            if (!array_key_exists($curvp->group_id, $vpbygu)) {
                $vpbygu[$curvp->group_id] = [];
            }

            $vpbyg = & $vpbygu[$curvp->group_id];

            $changetocurvp =
                !array_key_exists($curvp->user_id, $vpbyg) ||
                $curvp->starttime < $vpbyg[$curvp->user_id]->starttime;

            if ($changetocurvp) {
                $vpbyg[$curvp->user_id] = $curvp;
            }
        }
    }

    /**
     * This function
     * @param int $userid
     * @param int $groupid
     * @return false|mixed
     * @throws dml_exception
     */
    public function get_virtual_participation_for_user($userid = 0, $groupid = 0) {
        global $DB, $USER;

        $this->prepare_virtual_participation_data();

        if ($userid == 0) {
            $userid = $USER->id;
        }

        if ($groupid == 0) {
            if (array_key_exists($userid, $this->virtualparticipationbyuseridbacs)) {
                return $this->virtualparticipationbyuseridbacs[$userid];
            } else {
                return false;
            }
        }

        // ...if $groupid != 0.
        $vpexists =
            array_key_exists($groupid, $this->virtualparticipationbygroupanduseridbacs) &&
            array_key_exists($userid, $this->virtualparticipationbygroupanduseridbacs[$groupid]);

        if ($vpexists) {
            return $this->virtualparticipationbygroupanduseridbacs[$groupid][$userid];
        } else {
            return false;
        }
    }

    /**
     * This function
     * @return bool
     */
    public function get_show_points() {
        return ($this->bacs->mode != 1 /* mode ICPC */);
    }

    /**
     * This function
     * @param int $taskid
     * @return mixed|null
     */
    public function get_contest_task_by_task_id($taskid) {
        foreach ($this->tasks as $task) {
            if ($task->task_id == $taskid) {
                return $task;
            }
        }

        return null;
    }

    /**
     * This function
     *
     * @param int $langid
     * @return mixed|stdClass
     * @throws coding_exception
     */
    public function get_lang_by_lang_id($langid) {
        // ...later to be changed into contest_lang with lang limitations.

        if (array_key_exists($langid, $this->langbylangidarraybacs)) {
            return $this->langbylangidarraybacs[$langid];
        }

        $missinglang = new stdClass();

        $missinglang->id = 0;
        $missinglang->compiler_type = '';
        $missinglang->lang_id = 0;
        $missinglang->name = "[" . get_string('uppercaselanguagenotfound', 'mod_bacs') . ", ID = $langid]";
        $missinglang->description = "";
        $missinglang->time_limit_millis = 0;
        $missinglang->memory_limit_bytes = 0;
        $missinglang->number_of_processes = 0;
        $missinglang->output_limit_bytes = 0;
        $missinglang->real_time_limit_mills = 0;
        $missinglang->compiler_args = "";
        $missinglang->acemode = 'c_cpp';

        return $missinglang;
    }

    /**
     * This function
     * @param int $submitid
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function require_capability_for_exact_submit($submitid) {
        global $DB, $USER;

        $submit = $DB->get_record('bacs_submits', ['id' => $submitid], '*', MUST_EXIST);

        if ($submit->contest_id != $this->bacs->id) {
            throw new moodle_exception('cannotviewsubmit', 'bacs');
        }

        $canviewgivensubmit =
            $this->usercapabilitiesbacs->viewany ||
            $submit->user_id == $USER->id;

        if (!$canviewgivensubmit) {
            throw new moodle_exception('cannotviewsubmit', 'bacs');
        }

        // ...check group rights.
        $submitgroupisnotallowed =
            $this->groupmode != 0 &&
            $submit->group_id > 0 &&
            !in_array($submit->group_id, array_keys($this->allowedgroupsbacs));

        if ($submitgroupisnotallowed) {
            throw new moodle_exception('groupnotamember', 'group');
        }
    }

    /**
     * This function
     * @param int $userid
     * @return void
     * @throws dml_exception
     */
    public function set_results_active_menu_tab_on_user_id($userid) {
        global $DB, $USER;

        if ($userid == $USER->id) {
            $this->activemenutabbacs = 'results';
        } else {
            $this->activemenutabbacs = 'anothers_results';
            $targetuser = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname', MUST_EXIST);
            $this->targetuseridbacs        = $targetuser->id;
            $this->targetuserfirstnamebacs = $targetuser->firstname;
            $this->targetuserlastnamebacs  = $targetuser->lastname;
        }
    }

    /**
     * This function
     * @return void
     * @throws dml_exception
     */
    public function prepare_last_used_lang() {
        global $DB, $USER;

        $lastusedlangid = -1;

        $lastsubmit = $DB->get_records('bacs_submits', ['user_id' => $USER->id], 'submit_time DESC', '*', 0, 1);

        foreach ($lastsubmit as $submit) {
            $lastusedlangid = $submit->lang_id;

            foreach ($this->langs as &$lang) {
                $lang->is_last_used = ($lang->lang_id == $lastusedlangid);
            }
            unset($lang); // ...unlink last element reference.
        }
    }

    /**
     * This function
     * @param int $name
     * @param mixed $defaultvalue
     * @param mixed $type
     * @return void
     * @throws coding_exception
     */
    public function register_query_param($name, $defaultvalue, $type) {
        if (array_key_exists($name, $this->queryparamsarraybacs)) {
            throw new Exception("Query param '$name' was already registered");
        }

        $this->queryparamsarraybacs[$name] = optional_param($name, $defaultvalue, $type);

        $this->queryparamsbacs = (object)$this->queryparamsarraybacs;
    }

    /**
     * This function
     * @param string $activemenutab
     * @return void
     * @throws coding_exception
     */
    public function print_contest_header($activemenutab = '') {
        global $PAGE;

        if ($activemenutab != '') {
            $this->activemenutabbacs = $activemenutab;
        }

        // ...contest header.
        $contestheader = new contest_header();

        $contestheader->coursemoduleidbacs   = $this->coursemodule->id;
        $contestheader->contestnamebacs      = format_string($this->bacs->name, true, ['filter' => true]);
        $contestheader->usercapabilitiesbacs = $this->usercapabilitiesbacs;

        $contestheader->isolateparticipantsbacs = $this->isolateparticipantsbacs;
        $contestheader->isolatedparticipantmodeisforcedbacs = $this->isolatedparticipantmodeisforcedbacs;
        $contestheader->isinvirtualparticipationbacs = $this->isinvirtualparticipationbacs;

        $now = time();
        $timefromstart = $now - $this->starttime;
        $duration = $this->endtime - $this->starttime;

        if ($timefromstart < 0) {
            $contestheader->conteststatusbacs = get_string('statusnotstarted', 'mod_bacs');
        } else if ($timefromstart < $duration) {
            $contestheader->conteststatusbacs = get_string('statusrunning', 'mod_bacs');
        } else if ($timefromstart >= $duration) {
            $contestheader->conteststatusbacs = get_string('statusover', 'mod_bacs');
        } else {
            $contestheader->conteststatusbacs = get_string('statusunknown', 'mod_bacs');
        }

        $contestheader->minutestotalbacs      = max(0, floor($duration / 60));
        $contestheader->minutesfromstartbacs = max(0, floor($timefromstart / 60));
        if ($contestheader->minutesfromstartbacs > $contestheader->minutestotalbacs) {
            $contestheader->minutesfromstartbacs = $contestheader->minutestotalbacs;
        }

        print $this->bacsoutput->render($contestheader);

        // ...groups menu.
        if ($this->groupselectorshownbacs) {
            groups_print_activity_menu($this->coursemodule, $this->pageurlbacs);
        }

        // ...contest nav menu.
        $contestnavmenu = new contest_nav_menu();

        $contestnavmenu->coursemoduleidbacs    = $this->coursemodule->id;
        $contestnavmenu->contestname       = format_string($this->bacs->name, true, ['filter' => true]);
        $contestnavmenu->usercapabilitiesbacs  = $this->usercapabilitiesbacs;

        $contestnavmenu->conteststatusbacs     = $contestheader->conteststatusbacs;
        $contestnavmenu->minutestotalbacs      = $contestheader->minutestotalbacs;
        $contestnavmenu->minutesfromstartbacs = $contestheader->minutesfromstartbacs;

        $contestnavmenu->isolateparticipantsbacs = $this->isolateparticipantsbacs;
        $contestnavmenu->isolatedparticipantmodeisforcedbacs = $this->isolatedparticipantmodeisforcedbacs;
        $contestnavmenu->isvirtualparticipationdisabledbacs = ($this->virtualmodebacs == 0);

        $contestnavmenu->set_active_tab($this->activemenutabbacs);

        $contestnavmenu->aceeditorthemebacs        = $this->queryparamsbacs->acetheme;
        $contestnavmenu->aceeditorshownbacs        = $this->aceeditorshownbacs;
        $contestnavmenu->aceeditorredirecturlbacs = $this->aceeditorredirecturlbacs;

        $contestnavmenu->menushownbacs = $this->menushownbacs;

        $contestnavmenu->targetuseridbacs        = $this->targetuseridbacs;
        $contestnavmenu->targetuserfirstnamebacs = $this->targetuserfirstnamebacs;
        $contestnavmenu->targetuserlastnamebacs  = $this->targetuserlastnamebacs;

        print $this->bacsoutput->render($contestnavmenu);
    }
}
