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

namespace tool_admin_presets\local\setting;

/**
 * TODO:
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_preset_admin_setting_bloglevel extends admin_preset_admin_setting_configselect {

    /**
     * Extended to change the tool visibility
     */
    public function save_value($name = false, $value = false) {

        global $DB;

        if (!$id = parent::save_value($name, $value)) {
            return false;
        }

        // Pasted from admin_setting_bloglevel (can't use write_config).
        if ($value == 0) {
            $DB->set_field('tool', 'visible', 0, array('name' => 'blog_menu'));
        } else {
            $DB->set_field('tool', 'visible', 1, array('name' => 'blog_menu'));
        }

        return $id;
    }
}
