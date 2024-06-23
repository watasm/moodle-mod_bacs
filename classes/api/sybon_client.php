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

namespace mod_bacs\api;

use core\session\exception;
use stdClass;

/**
 * Sybon API client
 *
 * @package    mod_bacs
 * @copyright  SybonTeam, sybon.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sybon_client {
    /** @var string Sybon API key */
    private $apikey;

    /** @var string Sybon checking API URL */
    private $checkingurl = "https://checking.sybon.org";

    /** @var string Sybon archive API URL */
    private $archiveurl = "https://archive.sybon.org";

    /**
     * Construct instance of Sybon API client
     *
     * @param string $apikey Sybon API key
     */
    public function __construct($apikey) {
        $this->apikey = $apikey;
    }

    /**
     * Generic function for calling Sybon API
     *
     * @param string $url Requesting resource URL
     * @param string $method HTTP method ('GET', 'POST', ...)
     * @param array $queryparams HTTP query params to be encoded in URL
     * @param string|null $requestbody HTTP request body (null if empty)
     * @param string|null $requesttype Request data type (null if undefined)
     * @param string|null $responsetype Desired response data type (null if any)
     *
     * @return string Response body
     *
     * @throws exception API fail
     */
    private function api_call(
        $url,
        $method = 'GET',
        $queryparams = [],
        $requestbody = null,
        $requesttype = 'application/json',
        $responsetype = 'application/json'
    ) {
        $curl = curl_init();

        $query = http_build_query(array_merge($queryparams, [ 'api_key' => $this->apikey ]), '', '&');
        curl_setopt($curl, CURLOPT_URL, "$url?$query");

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $httpheader = [];
        if (!is_null($requesttype)) {
            $httpheader[] = "Content-Type: $requesttype";
        }
        if (!is_null($responsetype)) {
            $httpheader[] = "Accept: $responsetype";
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $httpheader);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if (!is_null($requestbody)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestbody);
        }

        $responsebody = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpcode == 200) {
            return $responsebody;
        } else {
            $errormsg = curl_error($curl);
            throw new exception("API call failed (code $httpcode): $errormsg");
        }
    }

    /**
     * Get available compilers
     *
     * @return stdClass[]   List of available compilers. Each compiler is an object that contains next properties:
     *                          id, type, timeLimitMillis, memoryLimitBytes, numberOfProcesses, outputLimitBytes,
     *                          realTimeLimitMillis, name, description, args
     *
     * @throws exception API fail
     */
    public function get_compilers(): array {
        return json_decode($this->api_call("$this->checkingurl/api/Compilers"));
    }

    /**
     * Send multiple submits to Sybon in single API call
     *
     * @param stdClass $submits List of submits to send. Each submit is an object that must contain next properties:
     *                              compilerId - ID of compiler to check submit with
     *                              solution - Text of solution encoded as Base64 string
     *                              solutionFileType - 'Text' or 'Zip'
     *                              problemId - Sybon ID of submit's problem
     *                              pretestsOnly - check only pretests (boolean)
     *                              continueCondition - 'Default', 'WhileOk' or 'Always'
     *
     * @return int[] List of Sybon submit IDs
     *
     * @throws exception API fail
     */
    public function send_all_submits($submits): array {
        return json_decode($this->api_call(
            "$this->checkingurl/api/Submits/sendall",
            'POST',
            [],
            json_encode($submits)
        ));
    }


    /**
     * Send submit to Sybon
     *
     * @param stdClass $submit Submit object that must contain next properties:
     *                              compilerId - ID of compiler to check submit with
     *                              solution - Text of solution encoded as Base64 string
     *                              solutionFileType - 'Text' or 'Zip'
     *                              problemId - Sybon ID of submit's problem
     *                              pretestsOnly - check only pretests (boolean)
     *                              continueCondition - 'Default', 'WhileOk' or 'Always'
     *
     * @return int Sybon submit ID
     *
     * @throws exception API fail
     */
    public function send_submit($submit): int {
        return json_decode($this->api_call(
            "$this->checkingurl/api/Submits/send",
            'POST',
            [],
            json_encode($submit)
        ));
    }

    /**
     * Rejudge multiple submits at Sybon
     *
     * @param int[] $submitids List of Sybon submit IDs to rejudge
     *
     * @throws exception API fail
     */
    public function rejudge_submits($submitids): void {
        $this->api_call(
            "$this->checkingurl/api/Submits/rejudge",
            'POST',
            [],
            json_encode($submitids)
        );
    }

    /**
     * Get submits results from Sybon
     *
     * @param int[] $submitids List of Sybon submit IDs to get results of
     *
     * @return stdClass[] List of submit results
     *
     * @throws exception API fail
     */
    public function get_submits_results($submitids): array {
        return json_decode($this->api_call(
            "$this->checkingurl/api/Submits/results",
            'GET',
            [ 'ids' => implode(',', $submitids) ]
        ));
    }

    /**
     * Get available collections from Sybon
     *
     * @return stdClass[] List of available collections
     *
     * @throws exception API fail
     */
    public function get_collections(): array {
        return json_decode($this->api_call(
            "$this->archiveurl/api/Collections",
            'GET',
            [ 'Offset' => 0, 'Limit' => 1000000000 ] // All collections, no limit.
        ));
    }

    /**
     * Get Sybon tasks collection
     *
     * @param int $id Sybon collection ID
     *
     * @return stdClass Sybon collection info, including complete list of problems in that collection
     *
     * @throws exception API fail
     */
    public function get_collection($id): stdClass {
        return json_decode($this->api_call("$this->archiveurl/api/Collections/$id"));
    }

    /**
     * Get Sybon problem
     *
     * @param int $id Sybon problem ID
     *
     * @return stdClass Sybon problem info
     *
     * @throws exception
     */
    public function get_problem($id): stdClass {
        return json_decode($this->api_call("$this->archiveurl/api/Problems/$id"));
    }

    /**
     * Get Sybon problem statement
     *
     * @param int $id Sybon problem ID
     *
     * @return string Problem statement URL
     *
     * @throws exception
     */
    public function get_problem_statement($id): string {
        return json_decode($this->api_call("$this->archiveurl/api/Problems/$id/statement"));
    }
}
