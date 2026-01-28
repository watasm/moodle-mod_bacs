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
 * AJAX endpoint for contest difficulty analysis
 *
 * @package    mod_bacs
 * @copyright  SybonTeam, sybon.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__, 3) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/utils.php');
require_once(dirname(__FILE__) . '/locale_utils.php');

require_login();

$cmid = required_param('cmid', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('bacs', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$bacs = $DB->get_record('bacs', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);
require_capability('mod/bacs:edit', $context);


$task_ids = optional_param_array('task_ids', [], PARAM_INT);

if (empty($task_ids)) {
    echo json_encode([
        'success' => false,
        'error' => get_string('notasksselected', 'bacs')
    ]);
    exit;
}

// Build contest_tasks array with task_order based on the order they were sent
$contest_tasks = [];
foreach ($task_ids as $index => $task_id) {
    $contest_tasks[] = (object) [
        'task_id' => $task_id,
        'task_order' => $index + 1
    ];
}

// Get all students enrolled in the course (use course context for enrollment)
$course_context = context_course::instance($course->id);
$enrolled_users = get_enrolled_users($course_context, 'mod/bacs:view', 0, 'u.id, u.firstname, u.lastname');
$total_students = count($enrolled_users);

if ($total_students == 0) {
    echo json_encode([
        'success' => false,
        'error' => 'No students enrolled in course'
    ]);
    exit;
}

// Get task ratings from bacs_rating_tasks
$task_ids_for_query = array_column($contest_tasks, 'task_id');
if (empty($task_ids_for_query)) {
    echo json_encode([
        'success' => false,
        'error' => 'No task IDs found'
    ]);
    exit;
}

list($task_ids_sql, $task_params) = $DB->get_in_or_equal($task_ids_for_query, SQL_PARAMS_NAMED);
$task_ratings_records = $DB->get_records_sql(
    "SELECT task_id, elo_rating 
     FROM {bacs_rating_tasks} 
     WHERE task_id $task_ids_sql",
    $task_params
);

// Index by task_id
$task_ratings = [];
foreach ($task_ratings_records as $record) {
    $task_ratings[$record->task_id] = $record;
}

// Get user ratings from bacs_user_ratings
$user_ids = array_keys($enrolled_users);
if (empty($user_ids)) {
    echo json_encode([
        'success' => false,
        'error' => 'No user IDs found'
    ]);
    exit;
}

list($user_ids_sql, $user_params) = $DB->get_in_or_equal($user_ids, SQL_PARAMS_NAMED);
$user_ratings_records = $DB->get_records_sql(
    "SELECT userid, rating 
     FROM {bacs_user_ratings} 
     WHERE userid $user_ids_sql",
    $user_params
);

// Index by userid
$user_ratings = [];
foreach ($user_ratings_records as $record) {
    $user_ratings[$record->userid] = $record;
}

// Calculate probability for each task
$task_data = [];
$task_labels = [];
$students_can_solve = [];
$ideal_curve = [];
$debug_info = []; // For debugging (only if DEBUG_DIFFICULTY_ANALYSIS is defined)

$base_rating = BACS_ELO_BASE_RATING;

foreach ($contest_tasks as $contest_task) {
    $task_id = $contest_task->task_id;
    $task_rating = isset($task_ratings[$task_id]) ? (float) $task_ratings[$task_id]->elo_rating : $base_rating;

    // Get task name
    $task = $DB->get_record('bacs_tasks', ['task_id' => $task_id], 'name, names');
    $task_name = $task ? bacs_get_localized_name($task) : "Task $task_id";
    $task_labels[] = $task_name;

    // Calculate how many students can solve this task (P > 0.7)
    $can_solve_count = 0;

    // Debug info (only collected if DEBUG_DIFFICULTY_ANALYSIS is defined)
    $task_debug = null;
    if (defined('DEBUG_DIFFICULTY_ANALYSIS') && DEBUG_DIFFICULTY_ANALYSIS) {
        $task_debug = ['task_id' => $task_id, 'task_rating' => $task_rating, 'probabilities' => []];
    }

    foreach ($enrolled_users as $user_id => $user) {
        $user_rating = isset($user_ratings[$user_id]) ? (float) $user_ratings[$user_id]->rating : $base_rating;

        // Calculate probability using bacs_calculate_solve_probability
        // This uses the standard Elo formula: P = 1 / (1 + 10^((R_task - R_user) / 400))
        $probability = bacs_calculate_solve_probability($task_rating, $user_rating);

        if ($task_debug !== null) {
            $task_debug['probabilities'][] = [
                'user_id' => $user_id,
                'user_rating' => $user_rating,
                'probability' => $probability,
                'can_solve' => $probability > 0.7
            ];
        }

        if ($probability > 0.7) {
            $can_solve_count++;
        }
    }

    if ($task_debug !== null) {
        $debug_info[] = $task_debug;
    }
    $students_can_solve[] = $can_solve_count;
}

// Calculate ideal curve: starts at total_students, ends at 5% of total_students
$num_tasks = count($contest_tasks);
$ideal_start = $total_students;
$ideal_end = $total_students * 0.05;

for ($i = 0; $i < $num_tasks; $i++) {
    // Smooth decay curve
    $progress = $i / max(1, $num_tasks - 1);
    $ideal_value = $ideal_start * pow($ideal_end / $ideal_start, $progress);
    $ideal_curve[] = round($ideal_value);
}

$response = [
    'success' => true,
    'task_labels' => $task_labels,
    'students_can_solve' => $students_can_solve,
    'ideal_curve' => $ideal_curve,
    'total_students' => $total_students
];

// Add debug info only if needed (for development)
if (defined('DEBUG_DIFFICULTY_ANALYSIS') && DEBUG_DIFFICULTY_ANALYSIS) {
    $response['debug'] = $debug_info;
}

echo json_encode($response);

