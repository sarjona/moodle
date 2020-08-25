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

/**
 * Random glossary block helper.
 *
 * @package    block_glossary_random
 * @copyright  2020 Adrian Perez, Fernfachhochschule Schweiz (FFHS) <adrian.perez@ffhs.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Returns a array of the given glossary entry depending the block configuration.
     *
     * @param $blockinstance
     * @param $cm
     * @return array|false Array of glossary entry or false otherwise.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_entry($blockinstance, $cm) {
        global $DB;

        $config = $blockinstance->get_config_for_external()->instance;

        // Place glossary concept and definition in cache.
        $entry = (object) ['concept' => '', 'definition' => ''];
        if (!$numberofentries = $DB->count_records('glossary_entries',
                array('glossaryid' => $config->glossary, 'approved' => 1))) {
            $entry->definition = get_string('noentriesyet', 'block_glossary_random');
            $config->cache = $entry;
            $blockinstance->instance_config_commit();
        }

        $glossaryctx = context_module::instance($cm->id);

        $limitfrom = 0;
        $limitnum = 1;

        $orderby = 'timemodified ASC';

        switch ($config->type) {
            case BGR_RANDOMLY:
                $i = ($numberofentries > 1) ? rand(1, $numberofentries) : 1;
                $limitfrom = $i - 1;
                break;
            case BGR_NEXTONE:
                if (isset($config->previous)) {
                    $i = $config->previous + 1;
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
                if (isset($config->previous)) {
                    $i = $config->previous + 1;
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

        if (!$entry = $DB->get_records_sql("SELECT id, concept, definition, definitionformat, definitiontrust
                                                 FROM {glossary_entries}
                                                WHERE glossaryid = ? AND approved = 1
                                             ORDER BY $orderby", array($config->glossary), $limitfrom, $limitnum)) {
            $entry->definition = get_string('noentriesyet', 'block_glossary_random');
            $config->cache = $entry;
            $blockinstance->instance_config_commit();
        }

        $entry = reset($entry);

        if (!empty($config->showconcept)) {
            $entry->concept = format_string($entry->concept, true);
        }

        $options = new stdClass();
        $options->trusted = $entry->definitiontrust;
        $options->overflowdiv = true;
        $entry->definition =
                file_rewrite_pluginfile_urls($entry->definition, 'pluginfile.php', $glossaryctx->id, 'mod_glossary', 'entry',
                        $entry->id);
        $entry->definition = format_text($entry->definition, $entry->definitionformat, $options);

        $config->nexttime = usergetmidnight(time()) + DAYSECS * $config->refresh;
        $config->previous = $i;

        $blockinstance->instance_config_save($config);

        return $entry;
    }
}