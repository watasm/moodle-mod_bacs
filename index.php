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
 * @package     mod_bacs
 * @copyright   VADIMKIRPIKOV228
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__, 3) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');


/**
 * Creating the table
 *
 * @param array $contests
 * @return string
 * @throws coding_exception
 * @throws moodle_exception
 */
function render_table($contests) {
    $row = 1;
    $table = html_writer::start_tag('table', ['class' => 'table table-hover table-striped']);
    $table .= html_writer::start_tag('thead');
    $table .= html_writer::tag('th', '', ['scope' => 'col']);
    $table .= html_writer::tag('th', get_string("contestname", "mod_bacs"), ['scope' => 'col']);
    $table .= html_writer::tag('th', get_string("from", "mod_bacs"), ['scope' => 'col']);
    $table .= html_writer::tag('th', get_string("to", "mod_bacs"), ['scope' => 'col']);
    $table .= html_writer::end_tag('thead');
    $table .= html_writer::start_tag('tbody');
    foreach ($contests as $contest) {
        $table .= html_writer::start_tag('tr');
        $table .= html_writer::tag('th', $row++, ['scope' => 'col']);
        $table .= html_writer::tag(
            'td',
            html_writer::link(new moodle_url('/mod/bacs/view.php', ['id' => $contest->coursemodule]), $contest->name)
        );
        $table .= html_writer::tag('td', userdate($contest->starttime));
        $table .= html_writer::tag('td', userdate($contest->endtime));
        $table .= html_writer::end_tag('tr');
    }
    $table .= html_writer::end_tag('tbody');
    $table .= html_writer::end_tag('table');
    return $table;
}

global $PAGE, $OUTPUT, $DB;

// Id of the course.
$courseid = required_param('id', PARAM_INT);

require_login($courseid);

// Getting the course by id.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);


$strcontests = get_string('modulenameplural', 'bacs');

// Setting page configuration.
$PAGE->set_url('/mod/bacs/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add($strcontests);
$PAGE->set_title($strcontests);
$PAGE->set_heading($course->fullname);


if (!$contests = get_all_instances_in_course('bacs', $course)) {
    notice(get_string('thereareno', 'moodle', $strcontests), "../../course/view.php?id=$course->id");
    die();
}

// Rendering the page.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($strcontests));
echo render_table($contests);
echo $OUTPUT->footer();
