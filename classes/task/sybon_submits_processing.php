<?php

namespace mod_bacs\task;

defined('MOODLE_INTERNAL') || die();

class sybon_submits_processing extends \core\task\adhoc_task
{

    public function get_name(): string
    {
        return 'mod_bacs: Sybon send submits and get results';
    }

    public function execute()
    {
        global $DB, $CFG;

        print("Start sybon send");

        require_once($CFG->dirroot . '/mod/bacs/lib.php');
        require_once($CFG->dirroot . '/mod/bacs/utils.php');
        require_once($CFG->dirroot . '/mod/bacs/submit_verdicts.php');


        $factory = \core\lock\lock_config::get_lock_factory('cron');
        $lock = $factory->get_lock('mod_bacs_sybon_submits_processing', 5); // sleep 5s
        if (!$lock) {
            mtrace('mod_bacs sybon_submits_processing: lock busy, reschedule');
            $next = new self();
            $next->set_custom_data(['singleton' => 3]);
            $next->set_next_run_time(time() + 10);
            \core\task\manager::reschedule_or_queue_adhoc_task($next);
            return;
        }


        try {
            \mod_bacs\cron_lib::cron_send(false);
        } catch (\Throwable $e) {
            mtrace('mod_bacs sybon_submits_processing error: ' . $e->getMessage());
        } finally {
            $lock->release();
        }

        $haspending = $DB->record_exists('bacs_submits', ['result_id' => VERDICT_PENDING]);

        $hasrunning = $DB->record_exists_select(
            'bacs_submits',
            "result_id = :vrun AND sync_submit_id > 0",
            ['vrun' => VERDICT_RUNNING]
        );

        if ($haspending || $hasrunning) {
            $next = new self();
            $next->set_custom_data(['singleton' => 2]);
            $next->set_next_run_time(time() + 10);
            \core\task\manager::reschedule_or_queue_adhoc_task($next);
        }
    }

    public static function kick_if_needed(): void
    {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/bacs/submit_verdicts.php');

        $haspending = $DB->record_exists('bacs_submits', ['result_id' => VERDICT_PENDING]);
        $hasrunning = $DB->record_exists_select(
            'bacs_submits',
            "result_id = :vrun AND sync_submit_id > 0",
            ['vrun' => VERDICT_RUNNING]
        );

        if (!$haspending && !$hasrunning) {
            return;
        }

        $task = new self();
        $task->set_next_run_time(time());
        \core\task\manager::queue_adhoc_task($task);
    }
}
