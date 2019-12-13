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
 * Handler for the version 1.24 of the H5P library.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p\local\library;

defined('MOODLE_INTERNAL') || die();

/**
 * Handler for the version 1.30 of the H5P library.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handler_v130 extends handler {

    /**
     * Get the current version of the H5P core library.
     *
     * @return string
     */
    public static function get_h5p_version(): string {
        return '1.30';
    }

    /**
     * Get the base path for the current H5P Core Library.
     *
     * @param string $filepath The path within the H5P root
     * @return null|string
     */
    public static function get_h5p_core_library_base(?string $filepath = null): ?string {
        $h5pversion = static::get_h5p_version();
        return "/lib/h5p/core/v{$h5pversion}/{$filepath}";
    }
}
