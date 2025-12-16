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

namespace core_courseformat\local;

use core\lang_string;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Class course navigation
 *
 * @package    core_courseformat
 * @copyright  2025 Laurent David <laurent.david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_linear_navigation_settings {
    /**
     * Get the setting to enable linear navigation in a course format
     *
     * @param string $formatname The course format name
     * @param \admin_settingpage $settings The admin setting page
     * @return void
     */
    public static function add_linear_navigation_global_settings(string $formatname, \admin_settingpage $settings): void {
        $label = get_string_manager()->string_exists('linearnavigationsettings', $formatname) ?
            new lang_string('linearnavigationsettings', $formatname) :
            new lang_string('linearnavigationsettings', 'core_courseformat');
        $description = get_string_manager()->string_exists('linearnavigationsettings_help', $formatname) ?
            new lang_string('linearnavigationsettings_help', $formatname) :
            new lang_string('linearnavigationsettings_help', 'core_courseformat');
        $settings->add(
            new \admin_setting_configcheckbox(
                "$formatname/enablelinearnav",
                $label,
                $description,
                1
            )
        );
    }
}
