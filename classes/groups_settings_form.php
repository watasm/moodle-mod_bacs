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

defined('MOODLE_INTERNAL') || die();
// ...moodleform is defined in formslib.php.
require_once("$CFG->libdir/formslib.php");

use coding_exception;
use dml_exception;
use moodleform;

/**
 * Class groups_settings_form
 * @package mod_bacs
 */
class groups_settings_form extends moodleform {
    /**
     * This function
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition() {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $mform = $this->_form;

        $id = optional_param('id', 0, PARAM_INT);
        if (isset($_POST['bacs_id'])) {
            $id = $_POST['bacs_id'];
        }

        $mform->addElement('hidden', 'bacs_id', $id);
        $mform->setType('bacs_id', PARAM_INT);

        $groupmode = 0; // ...no groups by default.

        $cm = get_coursemodule_from_id('bacs', $id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $bacs = $DB->get_record('bacs', ['id' => $cm->instance], '*', MUST_EXIST);

        $groups = groups_get_all_groups($course->id);

        foreach ($groups as $curgroup) {
            $headerelementname = $curgroup->id . '_group';

            $mform->addElement('header', $headerelementname, $curgroup->name);
            $mform->setExpanded($headerelementname, false);

            $mform->addElement(
                'advcheckbox',
                $curgroup->id . '_use_group_settings',
                get_string('usegroupsettings', 'bacs'),
                '',
                [],
                [0, 1]
            );

            $mform->addElement(
                'date_time_selector',
                $curgroup->id . '_starttime',
                get_string('from', 'bacs'),
                [
                    'startyear' => get_config('mod_bacs', 'minselectableyear'),
                    'stopyear'  => get_config('mod_bacs', 'maxselectableyear'),
                    'step' => 5,
                ]
            );
            $mform->addElement(
                'date_time_selector',
                $curgroup->id . '_endtime',
                get_string('to', 'bacs'),
                [
                    'startyear' => get_config('mod_bacs', 'minselectableyear'),
                    'stopyear'  => get_config('mod_bacs', 'maxselectableyear'),
                    'step' => 5,
                ]
            );

            $mform->addElement(
                'advcheckbox',
                $curgroup->id . '_upsolving',
                get_string('upsolving', 'bacs'),
                '',
                [],
                [0, 1]
            );

            $mform->addElement(
                'advcheckbox',
                $curgroup->id . '_presolving',
                get_string('presolving', 'bacs'),
                '',
                [],
                [0, 1]
            );
        }

        $this->add_action_buttons(false);
    }
}
