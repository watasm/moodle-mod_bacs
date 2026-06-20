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
 * Gets localized name for task
 * 
 * @param object $task Task object
 * @return string Localized name
 */
function bacs_get_localized_name($task) {
    // Name language priority: interface language, then the admin's preferred
    // languages, then C (author default), then ru. Mirrors the statement links.
    $preferedlanguages = array_merge(
        [current_language()],
        array_filter(explode(',', (string) get_config('mod_bacs', 'preferedlanguages'))),
        ['C', 'ru']
    );
    
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

/**
 * Gets the statement URL for a task in the best available language.
 *
 * Uses the same priority as bacs_get_localized_name(): interface language, then
 * the admin's preferred languages, then C (author default), then ru, then the
 * first available. Returns $task->statement_url when there is no multilingual data.
 *
 * @param object $task Task object (expects ->statement_url and ->statement_urls)
 * @return string Statement URL
 */
function bacs_get_localized_statement_url($task) {
    $url = $task->statement_url;

    if (isset($task->statement_urls) && $task->statement_urls !== '' && $task->statement_urls !== 'null') {
        $urls = is_string($task->statement_urls) ? json_decode($task->statement_urls, true) : $task->statement_urls;

        if (!empty($urls)) {
            $prefs = array_filter(explode(',', (string) get_config('mod_bacs', 'preferedlanguages')));
            $order = array_merge([current_language()], $prefs, ['C', 'ru']);

            $found = null;
            foreach ($order as $lang) {
                if (isset($urls[$lang])) {
                    $found = $urls[$lang];
                    break;
                }
            }

            if ($found === null) {
                $found = reset($urls);
            }

            if ($found !== false && $found !== null && $found !== '') {
                $url = $found;
            }
        }
    }

    return $url;
}