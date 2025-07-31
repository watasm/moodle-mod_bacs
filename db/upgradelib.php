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

use core\session\exception;

/**
 * This function
 * @param string $msg
 * @return void
 */
function tov3_process_log($msg) {
    $timestamp = date('Y-m-d H:i:s');
    print "<p><b>$timestamp - $msg</b></p>";
}

/**
 * This function
 * @param string $oldname
 * @param string $newname
 * @return bool
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 */
function rename_table_safe($oldname, $newname) {
    global $dbman;

    tov3_process_log("Renaming '$oldname' => '$newname'...");

    $oldtable = new xmldb_table($oldname);
    $newtable = new xmldb_table($newname);
    if (!$dbman->table_exists($oldtable) || $dbman->table_exists($newtable)) {
        return false;
    }

    $dbman->rename_table($oldtable, $newname);
    return true;
}

/**
 * This function
 * @param string $table
 * @param mixed $oldfield
 * @param string $newname
 * @return bool
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 */
function rename_field_safe($table, $oldfield, $newname) {
    global $dbman;

    if (is_string($table)) {
        $table = new xmldb_table($table);
    }
    if (!$dbman->table_exists($table)) {
        return false;
    }

    $newfield = new xmldb_field(
        $newname,
        $oldfield->getType(),
        $oldfield->getLength() || $oldfield->getDecimals(),
        $oldfield->getUnsigned(),
        $oldfield->getNotnull(),
        $oldfield->getSequence(),
        $oldfield->getDefault()
    );

    if (
        !$dbman->field_exists($table, $oldfield)
        || $dbman->field_exists($table, $newfield)
    ) {
        return false;
    }

    $dbman->rename_field($table, $oldfield, $newname);
    return true;
}

/**
 * This function
 * @param string $tablename
 * @param string $fieldsinfo
 * @param string $keysinfo
 * @return bool
 * @throws ddl_exception
 */
function create_table_safe($tablename, $fieldsinfo, $keysinfo) {
    global $DB, $dbman;

    $table = new xmldb_table($tablename);

    if ($dbman->table_exists($table)) {
        return false;
    }

    foreach ($fieldsinfo as $curfieldinfo) {
        $table->add_field(...$curfieldinfo);
    }

    foreach ($keysinfo as $curkeyinfo) {
        $table->add_key(...$curkeyinfo);
    }

    $dbman->create_table($table);

    return true;
}

/**
 * This function
 * @param string $tablename
 * @return bool
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 */
function drop_table_safe($tablename) {
    global $dbman;

    tov3_process_log("Dropping $tablename...");

    $table = new xmldb_table($tablename);

    if (!$dbman->table_exists($table)) {
        return false;
    }

    $dbman->drop_table($table);

    return true;
}

/**
 * This function
 * @param string $char
 * @return int
 */
function lang_char_to_lang_id($char) {
    switch ($char) {
        case "C":
            return 1; // C.
        case "+":
            return 2; // C++.
        case "P":
            return 5; // Pascal.
        case "J":
            return 8; // Java.
        case "T":
            return 7; // Python 3.
    }
    return -1;
}

/**
 * This function
 * @param string $answer
 * @return mixed
 */
function tov3_answer_to_result_id($answer) {
    require_once(dirname(dirname(__FILE__)) . '/utils.php');

    switch (strtolower($answer)) {
        case "accepted":
            return submit_verdict_id('Accepted');
        case "wrong answer":
            return submit_verdict_id('WrongAnswer');
        case "compilation error":
            return submit_verdict_id('CompileError');
        case "runtime error":
            return submit_verdict_id('RuntimeError');
        case "output limit exceede":
            return submit_verdict_id('OutputLimitExceeded');
        case "time limit exceeded":
            return submit_verdict_id('CPUTimeLimitExceeded');
        case "real time limit exce":
            return submit_verdict_id('RealTimeLimitExceeded');
        case "memory limit exceede":
            return submit_verdict_id('MemoryLimitExceeded');
        case "presentation error":
            return submit_verdict_id('PresentationError');
    }
    return submit_verdict_id('Unknown');
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 */
function tov3_migrate_bacs() {
    global $DB, $dbman;

    tov3_process_log('Migrating bacs...');

    $bacs = new xmldb_table('bacs');
    $bacsupsolving = new xmldb_field('upsolving', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, 0);

    if (!$dbman->field_exists($bacs, $bacsupsolving)) {
        $dbman->add_field($bacs, $bacsupsolving);
    }
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 */
function tov3_create_tasks_to_contests() {
    tov3_process_log('Creating bacs_tasks_to_contests...');

    create_table_safe(
        'bacs_tasks_to_contests',
        [
            ['id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['task_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['contest_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['task_order', XMLDB_TYPE_INTEGER, 7, null, XMLDB_NOTNULL, null, 0],
            ['test_points', XMLDB_TYPE_CHAR, 255, null, null, null, null],
        ],
        [
            ['primary', XMLDB_KEY_PRIMARY, ['id']],
        ]
    );
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 */
function tov3_create_cron() {
    tov3_process_log('Creating bacs_cron...');

    create_table_safe(
        'bacs_cron',
        [
            ['id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['submit_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['sync_submit_id', XMLDB_TYPE_INTEGER, 10, null, null, null, null],
            ['submit_type', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0],
            ['status_id', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0],
            ['flag', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, 0],
            ['error', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['timestamp', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
        ],
        [
            ['primary', XMLDB_KEY_PRIMARY, ['id']],
            ['submit_id', XMLDB_KEY_FOREIGN, ['submit_id'], 'bacs_submits', ['id']],
        ]
    );
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 */
function tov3_create_tasks() {
    tov3_process_log('Creating bacs_tasks...');

    create_table_safe(
        'bacs_tasks',
        [
            ['id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['task_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['name', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL, null, null],
            ['names', XMLDB_TYPE_TEXT, null, null, null, null, null],
            ['time_limit_millis', XMLDB_TYPE_INTEGER, 7, null, XMLDB_NOTNULL, null, 0],
            ['memory_limit_bytes', XMLDB_TYPE_INTEGER, 7, null, XMLDB_NOTNULL, null, 0],
            ['count_tests', XMLDB_TYPE_INTEGER, 3, null, null, null, null],
            ['count_pretests', XMLDB_TYPE_INTEGER, 3, null, null, null, null],
            ['test_points', XMLDB_TYPE_CHAR, 255, null, null, null, null],
            ['statement_url', XMLDB_TYPE_CHAR, 511, null, null, null, null],
            ['statement_urls', XMLDB_TYPE_TEXT, null, null, null, null],
            ['revision', XMLDB_TYPE_CHAR, 255, null, null, null, null],
        ],
        [
            ['primary', XMLDB_KEY_PRIMARY, ['id']],
        ]
    );
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 */
function tov3_create_tasks_test_expected() {
    tov3_process_log('Creating bacs_tasks_test_expected...');

    create_table_safe(
        'bacs_tasks_test_expected',
        [
            ['id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['task_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['test_id', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0],
            ['input', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null],
            ['expected', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null],
        ],
        [
            ['primary', XMLDB_KEY_PRIMARY, ['id']],
        ]
    );
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 */
function tov3_create_submits_tests() {
    tov3_process_log('Creating bacs_submits_tests...');

    create_table_safe(
        'bacs_submits_tests',
        [
            ['id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['submit_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['test_id', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0],
            ['status_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['time_used', XMLDB_TYPE_INTEGER, 10, null, null, null, null],
            ['memory_used', XMLDB_TYPE_INTEGER, 10, null, null, null, null],
        ],
        [
            ['primary', XMLDB_KEY_PRIMARY, ['id']],
        ]
    );
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 */
function tov3_create_submits_tests_output() {
    tov3_process_log('Creating bacs_submits_tests_output...');

    create_table_safe(
        'bacs_submits_tests_output',
        [
            ['id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['submit_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0],
            ['test_id', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0],
            ['output', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null],
        ],
        [
            ['primary', XMLDB_KEY_PRIMARY, ['id']],
        ]
    );
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 */
function tov3_create_langs() {
    tov3_process_log('Creating bacs_langs...');

    create_table_safe(
        'bacs_langs',
        [
            ['id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null],
            ['compiler_type', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL, null, null],
            ['lang_id', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, null],
            ['name', XMLDB_TYPE_CHAR, 255, null, null, null, null],
            ['description', XMLDB_TYPE_TEXT, null, null, null, null, null],
            ['time_limit_millis', XMLDB_TYPE_INTEGER, 7, null, XMLDB_NOTNULL, null, 0],
            ['memory_limit_bytes', XMLDB_TYPE_INTEGER, 7, null, XMLDB_NOTNULL, null, 0],
            ['number_of_processes', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0],
            ['output_limit_bytes', XMLDB_TYPE_INTEGER, 7, null, XMLDB_NOTNULL, null, 0],
            ['real_time_limit_mills', XMLDB_TYPE_INTEGER, 7, null, XMLDB_NOTNULL, null, 0],
            ['compiler_args', XMLDB_TYPE_TEXT, null, null, null, null, null],
        ],
        [
            ['primary', XMLDB_KEY_PRIMARY, ['id']],
        ]
    );
}

/**
 * This function
 * @return void
 * @throws dml_exception
 */
function tov3_generate_tasks_to_contests() {
    global $DB;

    tov3_process_log('Generating bacs_tasks_to_contests...');

    // ...fill tasks_to_contests with task_ids from m2m with given contest_id.
    $DB->delete_records('bacs_tasks_to_contests');

    $contests = $DB->get_recordset('bacs');

    foreach ($contests as $curcontest) {
        upgrade_set_timeout(0);

        $tasks = $DB->get_records(
            'bacs_m2m',
            ['contest_id' => $curcontest->contest_id],
            '',
            'task_id, task_order'
        );

        foreach ($tasks as $curtask) {
            $taskgrade = $DB->get_record(
                'bacs_grades',
                ['task_id' => $curtask->task_id]
            );

            if (isset($taskgrade)) {
                $curtask->test_points =
                        str_replace(' ', '', $taskgrade->task_grade);
            }

            $curtask->contest_id = $curcontest->id;
            $curtask->task_id =
                tasks_inner_id_to_task_info($curtask->task_id)['task_id'];

            if (isset($curtask->task_id)) {
                $DB->insert_record('bacs_tasks_to_contests', $curtask, false);
            }
        }
    }

    $contests->close();
}

/**
 * This function
 * @return void
 * @throws dml_exception
 */
function tov3_generate_cron() {
    global $DB;

    tov3_process_log('Generating bacs_cron...');

    $submits = $DB->get_recordset('bacs_submits', [], '', 'id');

    foreach ($submits as $cursubmit) {
        upgrade_set_timeout(0);

        $record = new stdClass();
        $record->submit_id = $cursubmit->id;
        $record->sync_submit_id = null;
        $record->submit_type = 0;
        $record->status_id = 44; /* legacy status */
        $record->flag = 0;
        $record->error = 0;
        $record->timestamp = time();
        $lastinsertid = $DB->insert_record('bacs_cron', $record, false);
    }

    $submits->close();
}

/**
 * This function
 * @return void
 * @throws dml_exception
 * @throws dml_transaction_exception
 */
function tov3_m2m_normalize_task_order() {
    global $DB;

    tov3_process_log("Normalizing task_order in bacs_m2m...");

    $transaction = $DB->start_delegated_transaction();

    $sql = "SELECT DISTINCT contest_id FROM {bacs_m2m};";
    $contests = $DB->get_records_sql($sql);

    foreach ($contests as $curcontest) {
        upgrade_set_timeout(0);

        $contesttasks = $DB->get_recordset(
            'bacs_m2m',
            ['contest_id' => $curcontest->contest_id],
            'task_order ASC, task_id ASC',
            'id, task_order'
        );

        $newtaskorder = 0;
        foreach ($contesttasks as $curtask) {
            $newtaskorder++;
            $curtask->task_order = $newtaskorder;
            $DB->update_record('bacs_m2m', $curtask);
        }

        $contesttasks->close();
    }

    $transaction->allow_commit();
}

/**
 * This function
 * @return void
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 * @throws dml_exception
 */
function tov3_migrate_submits() {
    global $DB, $dbman;

    tov3_process_log('Migrating bacs_submits...');

    $bacssubmits = new xmldb_table('bacs_submits');

    $bacssubmitsacm = new xmldb_field('acm', XMLDB_TYPE_INTEGER, 4, null, XMLDB_NOTNULL, null, 1);
    if ($dbman->field_exists($bacssubmits, $bacssubmitsacm)) {
        $DB->delete_records('bacs_submits', ['acm' => 1]);
    }

    $bacssubmitstestnum = new xmldb_field('testnum', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
    rename_field_safe('bacs_submits', $bacssubmitstestnum, 'test_num_failed');

    $bacssubmitstaskinnerid = new xmldb_field('task_id', XMLDB_TYPE_CHAR, 255, null, null, null, null);
    rename_field_safe('bacs_submits', $bacssubmitstaskinnerid, 'task_inner_id');

    $bacssubmitstaskid = new xmldb_field('task_id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
    if (!$dbman->field_exists($bacssubmits, $bacssubmitstaskid)) {
        $dbman->add_field($bacssubmits, $bacssubmitstaskid);
    }

    $bacssubmitslangid = new xmldb_field('lang_id', XMLDB_TYPE_INTEGER, 3, null, XMLDB_NOTNULL, null, 0);
    if (!$dbman->field_exists($bacssubmits, $bacssubmitslangid)) {
        $dbman->add_field($bacssubmits, $bacssubmitslangid);
    }

    $bacssubmitsbacsid = new xmldb_field('bacs_id', XMLDB_TYPE_INTEGER, 11, null, null, null, null);
    rename_field_safe('bacs_submits', $bacssubmitsbacsid, 'contest_id');

    $bacssubmitspoints = new xmldb_field('points', XMLDB_TYPE_INTEGER, 7, null, null, null, 0);
    if (!$dbman->field_exists($bacssubmits, $bacssubmitspoints)) {
        $dbman->add_field($bacssubmits, $bacssubmitspoints);
    }

    $bacsgrades = new xmldb_table('bacs_grades');

    tov3_process_log('Migrating bacs_submits: iterating over submits...');

    $submits = $DB->get_recordset('bacs_submits');
    foreach ($submits as $cursubmit) {
        upgrade_set_timeout(0);

        $curtaskinfo = ['task_id' => null, 'count_pretests' => 0];

        if (isset($cursubmit->task_inner_id)) {
            $curtaskinfo = tasks_inner_id_to_task_info(
                $cursubmit->task_inner_id
            );

            $cursubmit->task_id = $curtaskinfo['task_id'];
        }
        if (isset($cursubmit->lang)) {
            $cursubmit->lang_id = lang_char_to_lang_id(
                $cursubmit->lang
            );
        }
        if (isset($cursubmit->answer)) {
            $cursubmit->result_id = tov3_answer_to_result_id(
                $cursubmit->answer
            );
        }

        if (is_null($cursubmit->task_id)) {
            $DB->delete_records('bacs_submits', ['id' => $cursubmit->id]);
            continue;
        }

        if (
            $dbman->table_exists($bacsgrades)
            && isset($cursubmit->test_results)
        ) {
            $grade = $DB->get_record(
                'bacs_grades',
                ['task_id' => $cursubmit->task_inner_id],
                'task_grade'
            );
            if (isset($grade)) {
                $grade->task_grade = str_replace(' ', '', $grade->task_grade);

                $cursubmit->points = 0;

                if (preg_match('/^\d+[\,\d+]*$/', $cursubmit->test_results)) {
                    $pointspertest = explode(',', $grade->task_grade);
                    $verdictspertest = explode(',', $cursubmit->test_results);
                    $commonlength = min(count($verdictspertest), count($pointspertest));

                    for ($i = 0; $i < $commonlength; $i++) {
                        if ($verdictspertest[$i] == 1 /* old Accepted */) {
                            $cursubmit->points += $pointspertest[$i];
                        } else {
                            if ($i < $curtaskinfo['pretests_count']) {
                                $cursubmit->points = 0;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $cursubmit->max_time_used = 0;
        $cursubmit->max_memory_used = 0;
        $cursubmit->info = '';

        $DB->update_record('bacs_submits', $cursubmit);
    }
    $submits->close();

    // ...drop fields.
    $oddfields = [
        new xmldb_field('acm', XMLDB_TYPE_INTEGER, 4, null, XMLDB_NOTNULL, null, 1),
        new xmldb_field('lang', XMLDB_TYPE_CHAR, 1, null, null, null, null),
        new xmldb_field('task_inner_id', XMLDB_TYPE_CHAR, 255, null, null, null, null),
        new xmldb_field('answer', XMLDB_TYPE_CHAR, 20, null, null, null, null),
        new xmldb_field('test_results', XMLDB_TYPE_TEXT, null, null, null, null, null),
    ];

    foreach ($oddfields as $curfield) {
        if ($dbman->field_exists($bacssubmits, $curfield)) {
            $dbman->drop_field($bacssubmits, $curfield);
        }
    }
}

/**
 * This function
 * @return void
 * @throws dml_exception
 */
function tov3_diagnostic_list_damaged_contests() {
    global $DB;

    $missingtaskinnerids = [];
    $damagedcontestids = [];

    $tasks = $DB->get_records('bacs_m2m');

    foreach ($tasks as $task) {
        if (is_null(tasks_inner_id_to_task_info($task->task_id))) {
            $missingtaskinnerids[$task->task_id] = true;
            $damagedcontestids[$task->contest_id] = true;
        }
    }

    print '<p><b>List of missing tasks:</b><br>';
    foreach ($missingtaskinnerids as $innerid => $v) {
        print "$innerid<br>";
    }
    print '</p>';

    print '<p><b>List of damaged contests:</b><br>';
    foreach ($damagedcontestids as $contestid => $v) {
        print "$contestid<br>";
    }
    print '</p>';
}

/**
 * This function
 * @param int $innerid
 * @return array|mixed|null
 * @throws exception
 */
function tasks_inner_id_to_task_info($innerid) {
    static $tasksinfo = null;

    if (is_null($tasksinfo)) {
        $tasksinfo = [];

        require_once(dirname(dirname(__FILE__)) . '/cron/cron_bacs/api_key.php');
        require_once(dirname(dirname(__FILE__)) . '/classes/api/sybon_client.php');

        $sybonclient = new \mod_bacs\api\sybon_client(SYBON_API_KEY);

        $collections = $sybonclient->get_collections();

        foreach ($collections as $collectioninfo) {
            $collection = $sybonclient->get_collection($collectioninfo->id);

            $collectionproblems = $collection->problems;

            foreach ($collectionproblems as $problem) {
                $tasksinfo[$problem->internalProblemId] = [
                    'task_id' => $problem->id,
                    'pretests_count' => count($problem->pretests),
                ];
            }
        }
    }

    $taskinfo = $tasksinfo[$innerid];
    if (is_null($taskinfo)) {
        return null;
    } else {
        return $taskinfo;
    }
}
