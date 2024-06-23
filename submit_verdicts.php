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
 *
 */
const VERDICT_UNKNOWN                  = 0;
/**
 *
 */
const VERDICT_PENDING                  = 1;
/**
 *
 */
const VERDICT_RUNNING                  = 2;
/**
 *
 */
const VERDICT_SERVER_ERROR             = 3;
/**
 *
 */
const VERDICT_COMPILE_ERROR            = 4;
/**
 *
 */
const VERDICT_RUNTIME_ERROR            = 5;
/**
 *
 */
const VERDICT_FAIL_TEST                = 6;
/**
 *
 */
const VERDICT_CPU_TIME_LIMIT_EXCEEDED  = 7;
/**
 *
 */
const VERDICT_REAL_TIME_LIMIT_EXCEEDED = 8;
/**
 *
 */
const VERDICT_MEMORY_LIMIT_EXCEEDED    = 9;
/**
 *
 */
const VERDICT_OUTPUT_LIMIT_EXCEEDED    = 10;
/**
 *
 */
const VERDICT_PRESENTATION_ERROR       = 11;
/**
 *
 */
const VERDICT_WRONG_ANSWER             = 12;
/**
 *
 */
const VERDICT_ACCEPTED                 = 13;
/**
 *
 */
const VERDICT_INCORRECT_REQUEST        = 14;
/**
 *
 */
const VERDICT_INSUFFICIENT_DATA        = 15;
/**
 *
 */
const VERDICT_QUERIES_LIMIT_EXCEEDED   = 16;
/**
 *
 */
const VERDICT_EXCESS_DATA              = 17;
/**
 *
 */
const VERDICT_REJECTED                 = 18;

/**
 * This function
 * @param string $status
 * @return int
 */
function submit_verdict_by_server_status($status) {
    switch ($status) {
        case "OK":
            return VERDICT_ACCEPTED;
        case "WRONG_ANSWER":
            return VERDICT_WRONG_ANSWER;
        case "PRESENTATION_ERROR":
            return VERDICT_PRESENTATION_ERROR;
        case "QUERIES_LIMIT_EXCEEDED":
            return VERDICT_QUERIES_LIMIT_EXCEEDED;
        case "INCORRECT_REQUEST":
            return VERDICT_INCORRECT_REQUEST;
        case "INSUFFICIENT_DATA":
            return VERDICT_INSUFFICIENT_DATA;
        case "EXCESS_DATA":
            return VERDICT_EXCESS_DATA;
        case "OUTPUT_LIMIT_EXCEEDED":
            return VERDICT_OUTPUT_LIMIT_EXCEEDED;
        case "TERMINATION_REAL_TIME_LIMIT_EXCEEDED":
            return VERDICT_REAL_TIME_LIMIT_EXCEEDED;
        case "ABNORMAL_EXIT":
            return VERDICT_RUNTIME_ERROR;
        case "MEMORY_LIMIT_EXCEEDED":
            return VERDICT_MEMORY_LIMIT_EXCEEDED;
        case "TIME_LIMIT_EXCEEDED":
            return VERDICT_CPU_TIME_LIMIT_EXCEEDED;
        case "REAL_TIME_LIMIT_EXCEEDED":
            return VERDICT_REAL_TIME_LIMIT_EXCEEDED;
        case "TERMINATED_BY_SYSTEM":
            return VERDICT_SERVER_ERROR;
        case "CUSTOM_FAILURE":
            return VERDICT_SERVER_ERROR;
        case "FAIL_TEST":
            return VERDICT_FAIL_TEST;
        case "FAILED":
            return VERDICT_WRONG_ANSWER;
        case "SKIPPED":
            return VERDICT_UNKNOWN;

        default:
            return VERDICT_UNKNOWN;
    }
}
