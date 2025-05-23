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
use core\session\exception;
use core\task\scheduled_task;
use dml_exception;
use dml_transaction_exception;

/**
 * Class cron_incidents
 *
 * @package mod_bacs
 */
class cron_incidents extends scheduled_task {
    /**
     * This function
     * @return string
     */
    public function get_name() {
        return "Processing incidents";
    }

    /**
     * This function
     * @return void
     * @throws exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     */
    public function execute() {

        cron_lib::cron_incidents();
    }
}
