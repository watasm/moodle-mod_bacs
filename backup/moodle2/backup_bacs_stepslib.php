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
 * Class backup_bacs_activity_structure_step
 * @package mod_bacs
 */class backup_bacs_activity_structure_step extends backup_activity_structure_step {
    /**
     * This function
     * @return backup_nested_element
     * @throws base_element_struct_exception
     */
    protected function define_structure() {

        // Define each element separated.
        $bacs = new backup_nested_element('bacs', ['id'], [
            'course',
            'name',
            'starttime',
            'endtime',
            'upsolving',
            'mode',
            'presolving',
            'virtual_mode',
            'isolate_participants',
        ]);

        $contest = new backup_nested_element('contest', ['task_id'], [
            'task_id',
            'contest_id',
            'task_order',
            'test_points',
        ]);

        $bacs->add_child($contest);

        // Define sources.
        $bacs->set_source_table('bacs', ['id' => backup::VAR_ACTIVITYID]);
        $contest->set_source_table('bacs_tasks_to_contests', ['contest_id' => backup::VAR_PARENTID]);

        return $this->prepare_activity_structure($bacs);
    }
}
