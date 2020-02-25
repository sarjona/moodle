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
 * Generator for the core_contentbank subsystem.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/contentbank/tests/fixtures/testable_plugin.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Generator for the core_contentbank subsystem.
 *
 * @package    core_contentbank
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_contentbank_generator extends \component_generator_base {

    /**
     * Populate contentbank database tables with relevant data to simulate the process of adding items to the content bank.
     *
     * @param string $contenttype Content bank plugin type to add. If none is defined, contentbank_testable is used.
     * @param int $itemstocreate Number of items to add to the content bank.
     * @param int $userid The user identifier creating the content.
     * @param bool $convert2class Whether the class should return stdClass or plugin instance.
     * @return array An array with all the records added to the content bank.
     */
    public function generate_contentbank_data(?string $contenttype, int $itemstocreate = 1, int $userid = 0, bool $convert2class = true): array {
        global $DB, $USER;

        $contenttype = $contenttype ?? contentbank_testable\plugin::COMPONENT;
        $records = [];
        for($i = 0; $i < $itemstocreate; $i++) {
            // Create content.
            $record = new stdClass();
            $record->name = 'Test content ' . $i;
            $record->contenttype = $contenttype;
            $record->contextid = \context_system::instance()->id;
            $record->configdata = '';
            $record->usercreated = $userid ?? $USER->id;

            $record->id = $DB->insert_record('contentbank_content', $record);
            if ($convert2class) {
                $managerclass = "\\$record->contenttype\\plugin";
                if (class_exists($managerclass)) {
                    $content = new $managerclass($record);
                    $records[$record->id] = $content;
                }
            } else {
                $records[$record->id] = $record;
            }
        }

        return $records;
    }
}
