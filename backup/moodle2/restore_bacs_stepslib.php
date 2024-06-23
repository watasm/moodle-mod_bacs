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



/**
 * Class restore_bacs_activity_structure_step
 * @package mod_bacs
 */
class restore_bacs_activity_structure_step extends restore_activity_structure_step {
    /**
     * This function
     * @return mixed
     */
    protected function define_structure() {

        $paths = [];

        $paths[] = new restore_path_element('bacs', '/activity/bacs');
        $paths[] = new restore_path_element('bacs_contest', '/activity/bacs/contest');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * This function
     * @param object $data
     * @return void
     * @throws base_step_exception
     * @throws dml_exception
     */
    protected function process_bacs($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

            // Insert the choice record.
        $newitemid = $DB->insert_record('bacs', $data);
            // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * This function
     * @param object $data
     * @return void
     * @throws dml_exception
     */
    protected function process_bacs_contest($data) {
        global $DB;

        $data = (object)$data;
        $data->contest_id = $this->get_new_parentid('bacs');

        // Insert the choice record.
        try {
            $DB->insert_record_raw('bacs_tasks_to_contests', $data);
        } catch (dml_write_exception $e) {
            debugging($e->getMessage());
        }
    }

    /**
     * This function
     * @return void
     */
    protected function after_execute() {
    }
}
