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


namespace mod_bacs\task;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__, 2) . '/cron_lib.php');

use mod_bacs\cron_lib;
use core\task\scheduled_task;
use dml_exception;

/**
 * Class cron_task_url
 *
 * @package mod_bacs
 */
class cron_task_url extends scheduled_task {
    /**
     * This function
     * @return string
     */
    public function get_name() {
        return "Updating tasks statements";
    }

    /**
     * This function
     * @return void
     * @throws dml_exception
     */
    public function execute() {
        cron_lib::cron_task_url();
    }
}
