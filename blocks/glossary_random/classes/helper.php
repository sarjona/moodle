<?php
// This file is part of Moodle - http://moodle.org/
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
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Random glossary block helper.
 *
 * @package    block_glossary_random
 * @copyright  2020 Adrian Perez, Fernfachhochschule Schweiz (FFHS) <adrian.perez@ffhs.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_glossary_random;

use coding_exception;
use context_module;
use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

define('BGR_RANDOMLY',     '0');
define('BGR_LASTMODIFIED', '1');
define('BGR_NEXTONE',      '2');
define('BGR_NEXTALPHA',    '3');
define('BGR_RELOAD0', '0');
define('BGR_RELOAD30', '1');
define('BGR_RELOAD60', '2');
define('BGR_RELOAD120', '3');
define('BGR_RELOAD300', '4');

/**
 * Random glossary block helper.
 *
 * @package    block_glossary_random
 * @copyright  2020 Adrian Perez, Fernfachhochschule Schweiz (FFHS) <adrian.perez@ffhs.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Returns the given glossary entry depending on the block configuration.
     * Given $config is updated, taking into account the values of the new entry to display.
     *
     * @param  stdClass &$config    Glossary random block configuration.
     * @param  \cm_info $glossarycm course_module data for the glossary displayed into the block.
     * @return stdClass|false Glossary entry; otherwise false.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_entry(stdClass &$config, \cm_info $glossarycm) {
        global $DB;

        $entry = new stdClass();

        if (empty($config->glossary)) {
            return false;
        }

        if (!$glossarycm || !$glossarycm->uservisible) {
            // Skip generating of the cache if we can't display anything to the current user.
            return false;
        }

        if (!isset($config->nexttime)) {
            $config->nexttime = 0;
        }

        // Check if it's time to put a new entry in cache; otherwise, the existing cache entry will be returned.
        if (time() <= $config->nexttime) {
            return $config->cache;
        }

        // Get the new glossary entry to display.
        if ($numberofentries = $DB->count_records('glossary_entries', ['glossaryid' => $config->glossary, 'approved' => 1])) {
            if (!isset($config->previous)) {
                $config->previous = null;
            }
            list($orderby, $limitfrom, $limitnum, $i) = self::get_next_entry($config->type, $config->previous, $numberofentries);

            if ($entry = $DB->get_records_sql("SELECT id, concept, definition, definitionformat, definitiontrust
                                                 FROM {glossary_entries}
                                                WHERE glossaryid = ? AND approved = 1
                                             ORDER BY $orderby", [$config->glossary], $limitfrom, $limitnum)) {
                $entry = reset($entry);

                // Update definition and showconcept properly.
                $options = new stdClass();
                $options->trusted = $entry->definitiontrust;
                $options->overflowdiv = true;
                $glossaryctx = context_module::instance($glossarycm->id);
                $entry->definition = file_rewrite_pluginfile_urls($entry->definition, 'pluginfile.php', $glossaryctx->id,
                    'mod_glossary', 'entry', $entry->id);
                $entry->definition = format_text($entry->definition, $entry->definitionformat, $options);
                $entry->showconcept = !empty($config->showconcept);

                // Update block configuration, with nexttime and previous entry displayed.
                $config->nexttime = usergetmidnight(time()) + DAYSECS * $config->refresh;
                $config->previous = $i;
            }
        }
        // Update the cache with the entry.
        $config->cache = $entry;

        return $entry;
    }

    /**
     * Checks if glossary is available - it should be either located in the same course or be global.
     *
     * @param  stdClass $config Glossary random block configuration.
     * @param  stdClass $course Course object where the glossary belongs.
     * @return null|cm_info|stdClass object with properties 'id' (course module id) and 'uservisible'
     */
    public static function get_glossary_cm(?stdClass $config, stdClass $course) {
        global $DB;

        if (empty($config->glossary)) {
            // No glossary is configured.
            return null;
        }

        if (!empty($course)) {
            // Check if glossary belongs to the courseid.
            $modinfo = get_fast_modinfo($course);
            if (isset($modinfo->instances['glossary'][$config->glossary])) {
                $glossarycm = $modinfo->instances['glossary'][$config->glossary];
                if ($glossarycm->uservisible) {
                    // The glossary is in the same course and is already visible to the current user,
                    // no need to check if it is global, save on DB query.
                    return $glossarycm;
                } else {
                    return null;
                }
            }
        }

        // Find course module id for the given glossary, only if it is global.
        // If it exists, it will be a global glossary, create an object with properties 'id' and 'uservisible'. We don't need any
        // other information so why bother retrieving it. Full access check is skipped for global glossaries for
        // performance reasons.
        $sql = "SELECT cm.id, cm.visible AS uservisible
                  FROM {course_modules} cm
                  JOIN {modules} md ON md.id = cm.module
                  JOIN {glossary} g ON g.id = cm.instance
                 WHERE g.id = :instance AND md.name = :modulename AND g.globalglossary = 1";
        $params = ['instance' => $config->glossary, 'modulename' => 'glossary'];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get the following values for the next glossary entry to display: limitfrom, limitnum, orderby and previous.
     * It depends on the type, the number of entries and the previous value.
     *
     * @param  string $type            [description]
     * @param  int    $previous        [description]
     * @param  int    $numberofentries [description]
     * @return array  Array with the following values: limitfrom, limitnum, orderby and previous.
     */
    private static function get_next_entry(string $type, ?int $previous, int $numberofentries) {
        $limitfrom = 0;
        $limitnum = 1;

        $orderby = 'timemodified ASC';

        switch ($type) {
            case BGR_RANDOMLY:
                $i = ($numberofentries > 1) ? rand(1, $numberofentries) : 1;
                $limitfrom = $i - 1;
                break;

            case BGR_NEXTONE:
                if (isset($previous)) {
                    $i = $previous + 1;
                } else {
                    $i = 1;
                }

                // Loop back to beginning.
                if ($i > $numberofentries) {
                    $i = 1;
                }

                $limitfrom = $i - 1;
                break;

            case BGR_NEXTALPHA:
                $orderby = 'concept ASC';
                if (isset($previous)) {
                    $i = $previous + 1;
                } else {
                    $i = 1;
                }
                // Loop back to beginning.
                if ($i > $numberofentries) {
                    $i = 1;
                }

                $limitfrom = $i - 1;
                break;

            default:  // BGR_LASTMODIFIED.
                $i = $numberofentries;
                $limitfrom = 0;
                $orderby = 'timemodified DESC, id DESC';
                break;
        }

        return [$orderby, $limitfrom, $limitnum, $i];
    }

    public static function get_updatedynamically_time($updatedynamically) {
        switch ($updatedynamically) {
            case BGR_RELOAD0:
                $time = 0;
                break;
            case BGR_RELOAD30:
                $time = 30 * 1000;
                break;
            case BGR_RELOAD60:
                $time = 60 * 1000;
                break;
            case BGR_RELOAD120:
                $time = 120 * 1000;
                break;
            case BGR_RELOAD300:
                $time = 300 * 1000;
                break;
            default:
                $time = 0;
        }

        return $time;
    }
}
