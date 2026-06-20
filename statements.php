<?php
use mod_bacs\contest;
use mod_bacs\output\statements;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');
require_once(dirname(__FILE__) . '/locale_utils.php');

require_login();

$contest = new contest();
$contest->pageisallowedforisolatedparticipantbacs = true;
$contest->initialize_page();

$contest->pageurlbacs = new moodle_url('/mod/bacs/statements.php', ['id' => $contest->coursemodule->id]);

echo $OUTPUT->header();

$contest->aceeditorshownbacs = false;
$contest->print_contest_header('statements');

$statements_output = new statements();
$statements_output->coursemoduleidbacs = $contest->coursemodule->id;
$statements_output->usercapabilitiesbacs = $contest->usercapabilitiesbacs;

foreach ($contest->tasks as $task) {
    if ($task->is_missing) continue;

    $stmt_task = new stdClass();
    $stmt_task->letter = $task->letter;
    $stmt_task->task_id = $task->task_id;

    $stmt_task->name = bacs_get_localized_name($task);

    if (!isset($task->statement_urls) || $task->statement_urls == "null") {
        $urls_assoc = ["ru" => $task->statement_url];
    } else {
        $urls_assoc = is_string($task->statement_urls) ? json_decode($task->statement_urls, true) : $task->statement_urls;
    }

    $statements_list = [];
    $default_embed_url = "";
    // Single source of truth for the default active statement language.
    $final_url = bacs_get_localized_statement_url($task);

    if (!empty($urls_assoc)) {
        foreach ($urls_assoc as $lang => $url) {
            $format = strtolower($task->statement_format);
            if (preg_match('/\.(doc|docx)$/i', $url)) $format = 'doc';

            $embed_url = ($format === 'doc' || $format === 'docx')
                ? "https://docs.google.com/viewer?url=" . urlencode($url) . "&embedded=true"
                : $url;

            $is_active = ($url === $final_url);
            if ($is_active) {
                $default_embed_url = $embed_url;
            }

            $statements_list[] = [
                'lang' => strtoupper($lang),
                'embed_url' => $embed_url,
                'is_active' => $is_active
            ];
        }
    }

    if (empty($default_embed_url)) {
        $format = strtolower($task->statement_format);
        $default_embed_url = ($format === 'doc' || $format === 'docx')
            ? "https://docs.google.com/viewer?url=" . urlencode($task->statement_url) . "&embedded=true"
            : $task->statement_url;

        $statements_list[] = [
            'lang' => 'RU',
            'embed_url' => $default_embed_url,
            'is_active' => true
        ];
    }

    $stmt_task->statements_list = $statements_list;
    $stmt_task->has_multiple_statements = (count($statements_list) > 1);
    $stmt_task->default_embed_url = $default_embed_url;

    $statements_output->add_task($stmt_task);
}

print $contest->bacsoutput->render($statements_output);

echo $OUTPUT->footer();
