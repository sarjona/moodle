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
    /** @var string Setting name for enabling linear navigation */
    public const SETTING_ENABLE_LINEAR_NAV = 'enablelinearnav';

    /**
     * Get course format options related to linear navigation
     *
     * @param string $formatname The course format name
     * @return array
     */
    public static function get_course_format_options_edit_form(string $formatname): array {
        $label = get_string_manager()->string_exists('linearnavigationsettings', $formatname) ?
            new lang_string('linearnavigationsettings', $formatname) :
            new lang_string('linearnavigationsettings', 'core_courseformat');
        $helpcomponent = get_string_manager()->string_exists('linearnavigationsettings_help', $formatname) ?
            $formatname : 'core_courseformat';
        return [
            self::SETTING_ENABLE_LINEAR_NAV => [
                'label' => $label,
                'element_type' => 'select',
                'element_attributes' => [
                    [
                        0 => new lang_string('no'),
                        1 => new lang_string('yes'),
                    ],
                ],
                'help' => 'linearnavigationsettings',
                'help_component' => $helpcomponent,
            ],
        ];
    }
}
