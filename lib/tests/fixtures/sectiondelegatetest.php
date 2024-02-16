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

namespace test_component\courseformat;

use core_courseformat\output\local\content\section\controlmenu;
use core_courseformat\sectiondelegate as sectiondelegatebase;
use core_courseformat\base as course_format;
use renderer_base;
use action_menu;

/**
 * Test class for section delegate.
 *
 * @package    core_courseformat
 * @copyright  2023 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sectiondelegate extends sectiondelegatebase {

    /**
     * @var string|null Status to define which action menu to return when calling get_section_action_menu().
     * Alternatively, different testing classes could be created, but it wasn't worth it for this case.
     */
    protected ?string $actionmenustatus = 'parent';

    /**
     * Helper to change the behaviour of the get_section_action_menu(), for testing purposes, to return a different action menu
     * based on the value of $actionmenustatus. For instance:
     * - 'parent' returns the parent action menu.
     * - 'empty' returns an empty action menu.
     * - 'null' or null returns null action menu.
     *
     * @param string|null $actionmenustatus The status of the action menu.
     */
    public function set_section_action_menu(
        ?string $actionmenustatus,
    ) {
        $this->actionmenustatus = $actionmenustatus;
    }

    /**
     * Allow delegate plugin to modify the available section menu.
     * By default, it returns the parent action menu.
     *
     * @param course_format $format The course format instance.
     * @param controlmenu $controlmenu The control menu instance.
     * @param renderer_base $output The renderer instance.
     * @return action_menu|null The new action menu with the list of edit control items or null if no action menu is available.
     */
    public function get_section_action_menu(
        course_format $format,
        controlmenu $controlmenu,
        renderer_base $output,
    ): ?action_menu {
        switch ($this->actionmenustatus) {
            case 'parent':
                return parent::get_section_action_menu($format, $controlmenu, $output);

            case 'empty':
                return new action_menu();

            case 'null':
            default:
                return null;
        }
    }
}
