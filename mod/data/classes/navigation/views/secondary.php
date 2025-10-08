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

namespace mod_data\navigation\views;

use core\navigation\views\secondary as core_secondary;

/**
 * Custom secondary navigation class
 *
 * A custom construct of secondary nav for database. This rearranges the nodes for the secondary.
 *
 * @package     mod_data
 * @category    navigation
 * @copyright   2025 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secondary extends core_secondary {
    protected function get_default_module_mapping(): array {
        $basenodes = parent::get_default_module_mapping();
        $basenodes[self::TYPE_CUSTOM] += [
            'templates' => 5,
            'reviewentries' => 2,
            'presets' => 3,
            'fields' => 4,
        ];

        return $basenodes;
    }
}
