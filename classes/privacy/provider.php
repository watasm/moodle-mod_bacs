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


namespace mod_bacs\privacy;

use context;
use context_module;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\writer;
use core_privacy\local\request\helper;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use dml_exception;
use tool_dataprivacy\context_instance;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/bacs/lib.php');

/**
 * Class provider
 * @package mod_bacs
 */
class provider implements
        // This plugin is capable of determining which users have data within it.
    core_userlist_provider,

        // This plugin currently implements the original plugin\provider interface.
    \core_privacy\local\request\plugin\provider,

        // This plugin does store personal user data.
    \core_privacy\local\metadata\provider {
    /**
     * This function
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        // Sybon checking system.
        $collection->add_external_location_link(
            'sybon_checking_service',
            [
                'lang_id'   => 'privacy:metadata:sybon_checking_service:lang_id',
                'task_id'   => 'privacy:metadata:sybon_checking_service:task_id',
                'source'    => 'privacy:metadata:sybon_checking_service:source',
                'timestamp' => 'privacy:metadata:sybon_checking_service:timestamp',
            ],
            'privacy:metadata:sybon_checking_service'
        );

        // Database tables, that store personal data:
        // ...bacs.
        // ...bacs_group_info.
        // ...bacs_submits.
        // ...bacs_submits_tests.
        // ...bacs_submits_tests_output.

        // Database tables, that do not store personal data:
        // ...bacs_cron (if connected submit is removed then there is no link to exact user_id).
        // ...bacs_langs.
        // ...bacs_tasks.
        // ...bacs_tasks_collections.
        // ...bacs_tasks_test_expected.
        // ...bacs_tasks_to_collections.
        // ...bacs_tasks_to_contests (settings do not remember the user that set them).

        // ...bacs standings stores cached submits data for all users in the contest.
        $collection->add_database_table(
            'bacs',
            ['standings' => 'privacy:metadata:bacs:standings'],
            'privacy:metadata:bacs'
        );

        // ...bacs_group_info standings stores cached submits data for all users in the group.
        $collection->add_database_table(
            'bacs_group_info',
            ['standings' => 'privacy:metadata:bacs_group_info:standings'],
            'privacy:metadata:bacs_group_info'
        );

        // ...bacs_submits stores information about each submit.
        $collection->add_database_table(
            'bacs_submits',
            [
                'user_id'         => 'privacy:metadata:bacs_submits:user_id',
                'contest_id'      => 'privacy:metadata:bacs_submits:contest_id',
                'task_id'         => 'privacy:metadata:bacs_submits:task_id',
                'lang_id'         => 'privacy:metadata:bacs_submits:lang_id',
                'source'          => 'privacy:metadata:bacs_submits:source',
                'submit_time'     => 'privacy:metadata:bacs_submits:submit_time',
                'result_id'       => 'privacy:metadata:bacs_submits:result_id',
                'test_num_failed' => 'privacy:metadata:bacs_submits:test_num_failed',
                'points'          => 'privacy:metadata:bacs_submits:points',
                'max_time_used'   => 'privacy:metadata:bacs_submits:max_time_used',
                'max_memory_used' => 'privacy:metadata:bacs_submits:max_memory_used',
                'info'            => 'privacy:metadata:bacs_submits:info',
                'group_id'        => 'privacy:metadata:bacs_submits:group_id',
            ],
            'privacy:metadata:bacs_submits'
        );

        // ...bacs_submits_tests stores information about each test.
        $collection->add_database_table(
            'bacs_submits_tests',
            [
                'submit_id'   => 'privacy:metadata:bacs_submits_tests:submit_id',
                'test_id'     => 'privacy:metadata:bacs_submits_tests:test_id',
                'status_id'   => 'privacy:metadata:bacs_submits_tests:status_id',
                'time_used'   => 'privacy:metadata:bacs_submits_tests:time_used',
                'memory_used' => 'privacy:metadata:bacs_submits_tests:memory_used',
            ],
            'privacy:metadata:bacs_submits_tests'
        );

        // ...bacs_submits_tests_output stores output for each pretest.
        $collection->add_database_table(
            'bacs_submits_tests_output',
            [
                'submit_id' => 'privacy:metadata:bacs_submits_tests_output:submit_id',
                'test_id'   => 'privacy:metadata:bacs_submits_tests_output:test_id',
                'output'    => 'privacy:metadata:bacs_submits_tests_output:output',
            ],
            'privacy:metadata:bacs_submits_tests_output'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = [
            'modname'       => 'bacs',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];

        // ...just submits.
        // ...everything else depends on these submits (if data is consistent).

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {bacs} b ON b.id = cm.instance
                  JOIN {bacs_submits} bs ON bs.contest_id = b.id
                 WHERE bs.user_id = :userid
        ";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof context_module) {
            return;
        }

        $params = [
            'instanceid'    => $context->instanceid,
            'modulename'    => 'bacs',
        ];

        // ...just submits.
        // ...everything else depends on these submits (if data is consistent).

        $sql = "SELECT bs.user_id AS user_id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {bacs} b ON b.id = cm.instance
                  JOIN {bacs_submits} bs ON bs.contest_id = b.id
                 WHERE cm.id = :instanceid";

        $userlist->add_from_sql('user_id', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                       bs.id AS submit_id,
                       bs.contest_id AS contest_id,
                       bs.lang_id AS lang_id,
                       bs.task_id AS task_id,
                       bs.source AS source,
                       bs.submit_time AS submit_time,
                       bs.result_id AS submit_result_id,
                       bs.test_num_failed AS test_num_failed,
                       bs.points AS points,
                       bs.max_time_used AS max_time_used,
                       bs.max_memory_used AS max_memory_used,
                       bs.info AS info
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {bacs} b ON b.id = cm.instance
            INNER JOIN {bacs_submits} bs ON bs.contest_id = b.id
                 WHERE c.id {$contextsql} AND bs.user_id = :userid
              ORDER BY cm.id, bs.id";

        $params = ['modname' => 'bacs', 'contextlevel' => CONTEXT_MODULE, 'userid' => $user->id] + $contextparams;

        // This query sets up contest data to be contiguous.
        // Complete information about concrete contest can be retrieved via loop.
        $previouscmid = 0;

        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $r) {
            $currentcoursemoduleid = $r->cmid;

            // If new course module id is encountered,
            // then extract and write general information about this contest.
            if ($previouscmid != $currentcoursemoduleid) {
                $context = context_module::instance($currentcoursemoduleid);
                $contextdata = helper::get_context_data($context, $user);

                writer::with_context($context)
                    ->export_data([], (object)$contextdata);
            }

            $currentsubmitdata = [
                'id'               => $r->submit_id,
                'lang_id'          => $r->lang_id,
                'task_id'          => $r->task_id,
                'source'           => htmlspecialchars($r->source),
                'submit_time'      => transform::datetime($r->submit_time),
                'submit_result_id' => $r->submit_result_id,
                'test_num_failed'  => $r->test_num_failed,
                'points'           => $r->points,
                'max_time_used'    => $r->max_time_used,
                'max_memory_used'  => $r->max_memory_used,
                'info'             => htmlspecialchars($r->info),

                'tests' => [],
            ];

            // Export submit tests.
            $sql = "SELECT bst.id AS id,
                           bst.submit_id AS submit_id,
                           bst.test_id AS test_id,
                           bst.status_id AS status_id,
                           bst.time_used AS time_used,
                           bst.memory_used AS memory_used,
                           bsto.output AS test_output
                      FROM {bacs_submits_tests} bst
                 LEFT JOIN {bacs_submits_tests_output} bsto ON bsto.submit_id = bst.submit_id AND bsto.test_id = bst.test_id
                     WHERE bst.submit_id = :submit_id
                  ORDER BY bst.test_id";

            $currentsubmittests = $DB->get_records_sql($sql, ['submit_id' => $r->submit_id]);
            foreach ($currentsubmittests as $testrecord) {
                // Add current test data to current submit data.
                $currenttestdata = [
                    'test_id'          => $testrecord->test_id,
                    'test_status_id'   => $testrecord->status_id,
                    'test_time_used'   => $testrecord->time_used,
                    'test_memory_used' => $testrecord->memory_used,
                    'test_output'      => htmlspecialchars($testrecord->test_output),
                ];

                $currentsubmitdata['tests'][] = $currenttestdata;
            }

            // Write current submit.
            $context = context_module::instance($currentcoursemoduleid);
            self::export_submit_data_for_user($currentsubmitdata, $context);

            // Update previous course module id.
            $previouscmid = $currentcoursemoduleid;
        }
        $recordset->close();
    }

    /**
     * Export the supplied personal data for a single submit.
     *
     * @param array $submitdata the personal data to export for the submit.
     * @param context_module $context the context of the submit.
     */
    protected static function export_submit_data_for_user(array $submitdata, context_module $context) {
        writer::with_context($context)
            ->export_data(["Submits", $submitdata['id']], (object)$submitdata);
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('bacs', $context->instanceid);
        if (!$cm) {
            return;
        }

        $contestid = $cm->instance;

        bacs_delete_submits($contestid, 0 /* all users */);
    }

    /**
     * This function
     * @param approved_contextlist $contextlist
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            bacs_delete_submits($instanceid, $userid);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $bacs = $DB->get_record('bacs', ['id' => $cm->instance]);

        foreach ($userlist->get_userids() as $userid) {
            bacs_delete_submits($bacs->id, $userid);
        }
    }
}
