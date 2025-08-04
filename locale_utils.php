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
 * Multilingual data handling utilities
 *
 * @package    mod_bacs
 * @copyright  SybonTeam, sybon.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Filters multilingual data by preferred languages
 * 
 * @param array $data Multilingual data in format ['lang' => 'value']
 * @param array $preferedlanguages Array of preferred languages
 * @param string $valueKey Key for value in resulting array ('url' or 'name')
 * @return array Filtered data in format [['lang' => 'lang', $valueKey => 'value']]
 */
function bacs_filter_multilingual_data($data, $preferedlanguages, $valueKey) {
    if (empty($data)) {
        return [];
    }
    
    $filtered_data = array_intersect_key($data, array_flip($preferedlanguages));
    
    if (!empty($filtered_data)) {
        return array_map(function($lang, $value) use ($valueKey) {
            return ['lang' => strtoupper($lang), $valueKey => $value];
        }, array_keys($filtered_data), array_values($filtered_data));
    } else {
        return array_map(function($lang, $value) use ($valueKey) {
            return ['lang' => strtoupper($lang), $valueKey => $value];
        }, array_keys($data), array_values($data));
    }
}

/**
 * Finds value by language in array of associative arrays
 * 
 * @param array $data Array of associative arrays with keys 'lang' and 'valueKey'
 * @param string $lang Language to search for
 * @param string $valueKey Key for value ('url' or 'name')
 * @return mixed|null Found value or null
 */
function bacs_find_value_by_lang($data, $lang, $valueKey) {
    foreach ($data as $item) {
        if ($item['lang'] === strtoupper($lang)) {
            return $item[$valueKey];
        }
    }
    return null;
}

/**
 * Gets localized name for task
 * 
 * @param object $task Task object
 * @return string Localized name
 */
function bacs_get_localized_name($task) {
    $preferedlanguages = [current_language()];
    
    $localized_name = $task->name;
    
    if (isset($task->names) && !empty($task->names)) {
        $names = json_decode($task->names, true);
        
        if (!empty($names)) {
            $found_name = null;
            
            foreach ($preferedlanguages as $lang) {
                if (isset($names[$lang])) {
                    $found_name = $names[$lang];
                    break;
                }
            }

            if ($found_name === null && isset($names['C'])) {
                $found_name = $names['C'];
            }
            
            if ($found_name === null && isset($names['ru'])) {
                $found_name = $names['ru'];
            }
            
            if ($found_name === null) {
                $found_name = reset($names);
            }
            
            if ($found_name !== null) {
                $localized_name = $found_name;
            }
        }
    }
    
    return $localized_name;
} 