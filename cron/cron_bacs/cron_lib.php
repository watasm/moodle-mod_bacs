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

use mod_bacs\api\sybon_client;
use core\session\exception;

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/utils.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/submit_verdicts.php');
require_once(dirname(__FILE__, 3) . '/classes/api/sybon_client.php');

require_login();

/**
 * This function
 * @param int $contestid
 * @param int $groupid
 * @param array $changedcontests
 * @return void
 * @package mod_bacs
 */
function mark_to_rebuild_standings($contestid, $groupid, &$changedcontests) {
    if (!array_key_exists($contestid, $changedcontests)) {
        $changedcontests[$contestid] = [];
    }

    $changedcontests[$contestid][$groupid] = true;
}

/**
 * This function
 * @return void
 * @throws exception
 * @throws dml_exception
 * @throws dml_transaction_exception
 * @package mod_bacs
 */
function cron_langs() {
    global $DB;

    $sybonapikey = get_config('mod_bacs', 'sybonapikey');
    $sybonclient = new sybon_client($sybonapikey);

    $langs = $sybonclient->get_compilers();

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('bacs_langs');

    foreach ($langs as $lang) {
        $langrecord = new stdClass();
        $langrecord->compiler_type         = $lang->type;
        $langrecord->lang_id               = $lang->id;
        $langrecord->name                  = $lang->name;
        $langrecord->description           = $lang->description;
        $langrecord->time_limit_millis     = $lang->timeLimitMillis;
        $langrecord->memory_limit_bytes    = $lang->memoryLimitBytes;
        $langrecord->number_of_processes   = $lang->numberOfProcesses;
        $langrecord->output_limit_bytes    = $lang->outputLimitBytes;
        $langrecord->real_time_limit_mills = $lang->realTimeLimitMillis;
        $langrecord->compiler_args         = $lang->args;

        $DB->insert_record('bacs_langs', $langrecord, false);
    }

    $transaction->allow_commit();
}

/**
 * Send some bunch of pending submits to Sybon
 *
 * @param array $changedcontests
 *
 * @throws exception
 * @throws dml_exception
 * @package mod_bacs
 */
function cron_sendsubmits(&$changedcontests): void {
    global $DB;

    $sybonapikey = get_config('mod_bacs', 'sybonapikey');
    $sybonclient = new sybon_client($sybonapikey);

    $maximumrecordsforsinglerequest = 20;

    $submitsforsending = $DB->get_records(
        'bacs_submits',
        ['result_id' => VERDICT_PENDING],
        'submit_time ASC',
        'id, task_id, source, lang_id, contest_id, group_id',
        0,
        $maximumrecordsforsinglerequest
    );

    // Array-sequence of objects that hold together submit IDs
    // and submits as models ready for sending to Sybon.
    $matchedsubmits = [];

    foreach ($submitsforsending as $submit) {
        // Make safety checks and ignore inconsistent records.
        if (!$DB->record_exists('bacs_langs', ['lang_id' => $submit->lang_id])) {
            trigger_error("Submit language not found, submit_id=$submit->id lang_id=$submit->lang_id", E_USER_WARNING);
            // Invalidate submit.
            $submit->result_id = VERDICT_SERVER_ERROR;
            $DB->update_record('bacs_submits', $submit);
            continue;
        }

        if (!$DB->record_exists('bacs_tasks', ['task_id' => $submit->task_id])) {
            trigger_error("Submit task not found, submit_id=$submit->id task_id=$submit->task_id", E_USER_WARNING);
            // Invalidate submit.
            $submit->result_id = VERDICT_SERVER_ERROR;
            $DB->update_record('bacs_submits', $submit);
            continue;
        }

        // Mark changes.
        mark_to_rebuild_standings($submit->contest_id, $submit->group_id, $changedcontests);

        // Prepare Sybon submit.
        $sybonsubmit = (object) [
            'compilerId' => $submit->lang_id,
            'solution' => base64_encode($submit->source),
            'solutionFileType' => 'Text',
            'problemId' => $submit->task_id,
            'pretestsOnly' => false,
            'continueCondition' => 'Always',
        ];

        // Add.
        $matchedsubmits[] = (object) [
            'as_sybon_submit' => $sybonsubmit,
            'submit_id' => $submit->id,
        ];
    }

    if (count($matchedsubmits) == 0) {
        return;
    }

    $sybonsubmits = array_map(
        function ($ms) {
            return $ms->as_sybon_submit;
        },
        $matchedsubmits
    );
    $syncids = $sybonclient->send_all_submits($sybonsubmits);

    foreach (array_map(null, $matchedsubmits, $syncids) as [$matchedsubmit, $syncid]) {
        $updaterecord = new stdClass();
        $updaterecord->id = $matchedsubmit->submit_id;
        $updaterecord->sync_submit_id = $syncid;
        $updaterecord->result_id = VERDICT_RUNNING;

        $DB->update_record('bacs_submits', $updaterecord);
    }
}


/**
 * Fetch results of given submits from Sybon
 *
 * @param array $changedcontests
 * @param array $submits
 *
 * @throws exception
 * @throws coding_exception
 * @throws dml_exception
 * @throws dml_transaction_exception
 * @package mod_bacs
 */
function cron_getresults_for_submits(&$changedcontests, &$submits): void {
    require_once(dirname(__FILE__) . '/encoding.php');

    $sybonapikey = get_config('mod_bacs', 'sybonapikey');
    $sybonclient = new sybon_client($sybonapikey);

    $syncids = [];
    $syncidtosubmitid = [];

    foreach ($submits as $runningsubmit) {
        $syncid = $runningsubmit->sync_submit_id;

        $syncids[] = $syncid;
        $syncidtosubmitid[$syncid] = $runningsubmit->id;

        // Mark changes.
        mark_to_rebuild_standings(
            $runningsubmit->contest_id,
            $runningsubmit->group_id,
            $changedcontests
        );
    }

    $checkingresults = $sybonclient->get_submits_results($syncids);

    foreach ($checkingresults as $checkingresult) {
        global $DB;

        $syncid = $checkingresult->id;
        $submitid = $syncidtosubmitid[$syncid];

        if (is_null($checkingresult)) {
            continue;
        }
        if (!property_exists($checkingresult, 'buildResult')) {
            continue;
        }
        if ($checkingresult->buildResult->status == "PENDING") {
            // Check running submit next time.
            unset($submits[$submitid]);
            continue;
        }

        $transaction = $DB->start_delegated_transaction();

        $maxtimeused = 0;
        $maxmemoryused = 0;
        $testnumfailed = null;
        $pretest = true;
        $pretestfailed = false;
        $testfailedverdict = null;
        $failed = false;

        $testgroups = $checkingresult->testGroupResults;
        if ($testgroups) {
            $testsinfo = [];
            $testsinfooutput = [];

            $testid = 0 - 1;
            foreach ($testgroups as $testgroup) {
                if ($testgroup->internalId != "pre") {
                    $pretest = false;
                }

                foreach ($testgroup->testResults as $testresult) {
                    $testid++;

                    $timeused = $testresult->resourceUsage->timeUsageMillis;
                    $memoryused = $testresult->resourceUsage->memoryUsageBytes;

                    $maxtimeused = max($maxtimeused, $timeused);
                    $maxmemoryused = max($maxmemoryused, $memoryused);

                    $testinfo = new stdClass();
                    $testinfo->submit_id = $submitid;
                    $testinfo->status_id = submit_verdict_by_server_status($testresult->status);
                    $testinfo->test_id = $testid;
                    $testinfo->time_used = $timeused;
                    $testinfo->memory_used = $memoryused;

                    $testsinfo[] = $testinfo;

                    if (property_exists($testresult, 'actualResult')) {
                        $testinfooutput = new stdClass();
                        $testinfooutput->submit_id = $submitid;
                        $testinfooutput->test_id = $testid;
                        $testinfooutput->output = $testresult->actualResult;

                        $testsinfooutput[] = $testinfooutput;
                    }

                    if ($testinfo->status_id != VERDICT_ACCEPTED) {
                        if (!$failed) {
                            $failed = true;
                            $testnumfailed = $testid;
                            $testfailedverdict = $testinfo->status_id;
                        }
                        if ($pretest) {
                            $pretestfailed = true;
                        }
                    }
                }
            }

            // Cleanup possible previous tests info.
            $DB->delete_records('bacs_submits_tests', ['submit_id' => $submitid]);
            $DB->delete_records('bacs_submits_tests_output', ['submit_id' => $submitid]);

            $DB->insert_records('bacs_submits_tests', $testsinfo);
            $DB->insert_records('bacs_submits_tests_output', $testsinfooutput);
        }

        $submit = new stdClass();
        $submit->id = $submitid;

        if ($checkingresult->buildResult->status == "FAILED") {
            $submit->result_id = VERDICT_COMPILE_ERROR;
        } else if ($checkingresult->buildResult->status == "SERVER_ERROR") {
            $submit->result_id = VERDICT_SERVER_ERROR;
        } else if (is_null($testnumfailed)) {
            $submit->result_id = VERDICT_ACCEPTED;
        } else {
            $submit->result_id = $testfailedverdict;
        }

        $submit->test_num_failed = $testnumfailed;
        $submit->points = 0;
        $submit->info = Encoding::fixUTF8(base64_decode($checkingresult->buildResult->output));
        $submit->max_time_used = $maxtimeused;
        $submit->max_memory_used = $maxmemoryused;
        $DB->update_record('bacs_submits', $submit);

        calculate_sumbit_points($submitid);

        $transaction->allow_commit();

        unset($submits[$submitid]);
    }

    if (!empty($submits)) {
        throw new Exception("Failed to process some submits");
    }
}

/**
 * Fetch results of some bunch of submits from Sybon
 *
 * @param array $changedcontests
 *
 * @throws dml_exception
 * @package mod_bacs
 */
function cron_getresults(&$changedcontests): void {
    global $DB;

    $maximumrecordsforsinglerequest = 50;

    $runningsubmits = $DB->get_records_select(
        'bacs_submits',
        // Sync_submit_id > 0 also means it is not NULL.
        "result_id = :verdict_running AND sync_submit_id > 0",
        ['verdict_running' => VERDICT_RUNNING],
        'submit_time ASC',
        '*',
        0,
        $maximumrecordsforsinglerequest
    );

    if (count($runningsubmits) == 0) {
        return;
    }

    // Try to proccess all selected submits.
    try {
        cron_getresults_for_submits($changedcontests, $runningsubmits);
    } catch (Exception $e) {
        // Failed to process some of selected submits, processing them one by one.
        foreach ($runningsubmits as $submitid => $runningsubmit) {
            try {
                $unprocessedrunningsubmit = [ $submitid => $runningsubmit ];
                cron_getresults_for_submits($changedcontests, $unprocessedrunningsubmit);
            } catch (Exception $e) {
                // Failed to process single submit so it's invalidated.
                $runningsubmit->result_id = VERDICT_SERVER_ERROR;
                $DB->update_record('bacs_submits', $runningsubmit);
            }
        }
    }
}

/**
 * Send some pending submits and fetch some checked submits information, rebuild standings
 *
 * @param mixed $verbose
 *
 * @throws dml_exception
 * @package mod_bacs
 */
function cron_send($verbose): void {
    $changedcontests = [];

    if ($verbose) {
        print "<p><b>cron_getresults...</b></p>";
    }
    cron_getresults($changedcontests);

    if ($verbose) {
        print "<p><b>cron_sendsubmits...</b></p>";
    }
    cron_sendsubmits($changedcontests);

    foreach ($changedcontests as $bacsid => $groupidsset) {
        if ($verbose) {
            print "<p><b>rebuilding contest $bacsid...</b></p>";
        }

        bacs_rebuild_common_standings($bacsid);

        foreach ($groupidsset as $groupid => $v) {
            if ($verbose) {
                print "<p>rebuilding group $groupid for contest $bacsid...</p>";
            }
            bacs_rebuild_standings_for_group($bacsid, $groupid);
        }
    }
}

/**
 * This function
 *
 * @return void
 * @throws dml_exception
 * @package mod_bacs
 */
function cron_tasks() {
    global $DB;

    $sybonapikey = get_config('mod_bacs', 'sybonapikey');
    $sybonclient = new sybon_client($sybonapikey);

    try {
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('bacs_tasks');
        $DB->delete_records('bacs_tasks_test_expected');
        $DB->delete_records('bacs_tasks_collections');
        $DB->delete_records('bacs_tasks_to_collections');

        $collections = $sybonclient->get_collections();

        $insertedtskids = [];
        foreach ($collections as $collectioninfo) {
            $collectionrecord = new stdClass();
            $collectionrecord->name = $collectioninfo->name;
            $collectionrecord->description = $collectioninfo->description;
            $collectionrecord->collection_id = $collectioninfo->id;
            // ...fill collections table information.
            $DB->insert_record('bacs_tasks_collections', $collectionrecord, false);

            $collection = $sybonclient->get_collection($collectioninfo->id);

            $collectionproblems = $collection->problems;

            foreach ($collectionproblems as $item) {
                if (array_key_exists($item->id, $insertedtskids)) {
                    continue;
                }

                $insertedtskids[$item->id] = true;

                $record = new stdClass();
                $record->task_id = $item->id;
                $record->name = $item->name;
                $record->author = $item->author;
                $record->time_limit_millis = $item->resourceLimits->timeLimitMillis;
                $record->memory_limit_bytes = $item->resourceLimits->memoryLimitBytes;
                $record->count_tests = abs($item->testsCount);
                $record->count_pretests = count($item->pretests);

                // ...fill tasks to collections table information.
                $tasktocollectionrecord = new stdClass();
                $tasktocollectionrecord->task_id = $record->task_id;
                $tasktocollectionrecord->collection_id = $collectionrecord->collection_id;
                $DB->insert_record('bacs_tasks_to_collections', $tasktocollectionrecord, false);

                // ...find min index and renumerate them from 0.
                $minarg = array_map(
                    function ($pretest) {
                        return $pretest->id;
                    },
                    $item->pretests
                );

                // PHP < 8 behavior.
                if (!$minarg) {
                    $pretestindexfixdelta = false;
                } else {
                    $pretestindexfixdelta = min($minarg);
                }

                foreach ($item->pretests as $pretest) {
                    $dbpretest = new stdClass();
                    $dbpretest->task_id = $record->task_id;
                    $dbpretest->test_id = $pretest->id - $pretestindexfixdelta;
                    $dbpretest->input = $pretest->input;
                    $dbpretest->expected = $pretest->output;

                    print "<p>Inserting pretest $dbpretest->test_id for problem $dbpretest->task_id...</p>";
                    $DB->insert_record('bacs_tasks_test_expected', $dbpretest, false);
                    print "<p>Inserted pretest!</p>";
                }

                $record->test_points = default_test_string(
                    $record->count_tests,
                    $record->count_pretests
                );

                if (strlen($record->test_points) > 250) {
                    print "<p><b>Error!</b><br>Task '$record->name' with
 id=$record->task_id has $record->count_tests tests and cannot be inserted into collection!</p>";
                    continue;
                }

                $record->statement_url = $item->statementUrl;
                $record->statement_format = $item->format;
                $record->revision = md5($item->statementUrl);

                print "<p>Inserting problem $record->task_id...</p>";
                $lastinsertid = $DB->insert_record('bacs_tasks', $record, false);
                print "<p>Inserted problem!</p>";
            }
        }

        $transaction->allow_commit();
    } catch (Exception $e) {
        debugging($e->getMessage());
    }
}

/**
 * This function
 *
 * @return void
 * @throws dml_exception
 * @package mod_bacs
 */
function cron_task_url() {
    global $DB;

    $sybonapikey = get_config('mod_bacs', 'sybonapikey');
    $sybonclient = new sybon_client($sybonapikey);

    $tasks = $DB->get_records('bacs_tasks', [], null, 'id, task_id');
    foreach ($tasks as $task) {
        try {
            $newstatementurl = $sybonclient->get_problem_statement($task->task_id);
            $rec = new stdClass();
            $rec->id = $task->id;
            $rec->task_id = $task->task_id;
            $rec->statement_url = $newstatementurl;
            $lastinsertid = $DB->update_record_raw('bacs_tasks', $rec);
        } catch (Exception $e) {
            debugging($e->getMessage());
        }
    }
}
