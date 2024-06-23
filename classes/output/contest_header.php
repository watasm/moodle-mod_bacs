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


namespace mod_bacs\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Class contest_header
 * @package mod_bacs
 */
class contest_header implements renderable, templatable {
    /**
     * @var mixed
     */
    public $contestnamebacs = "";
    /**
     * @var mixed
     */
    public $coursemoduleidbacs = 0;
    /**
     * @var mixed
     */
    public $usercapabilitiesbacs;

    /**
     * @var mixed
     */
    public $minutesfromstartbacs = 0;
    /**
     * @var mixed
     */
    public $minutestotalbacs = 0;
    /**
     * @var mixed
     */
    public $conteststatusbacs = "";

    /**
     * @var mixed
     */
    public $isolateparticipantsbacs = false;
    /**
     * @var mixed
     */
    public $isolatedparticipantmodeisforcedbacs = false;
    /**
     * @var mixed
     */
    public $isinvirtualparticipationbacs = false;

    /**
     *
     */
    public function __construct() {
    }

    /**
     * This function
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();

        $data->user_capability_edit = $this->usercapabilitiesbacs->edit;

        $data->contest_name    = $this->contestnamebacs;
        $data->coursemodule_id = $this->coursemoduleidbacs;

        $data->minutes_from_start = $this->minutesfromstartbacs;
        $data->minutes_total      = $this->minutestotalbacs;
        $data->contest_status     = $this->conteststatusbacs;

        $data->isolate_participants = $this->isolateparticipantsbacs;
        $data->isolated_participant_mode_is_forced = $this->isolatedparticipantmodeisforcedbacs;
        $data->is_in_virtual_participation = $this->isinvirtualparticipationbacs;

        return $data;
    }
}
