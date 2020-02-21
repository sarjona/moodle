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
 * Contains API class for the H5P area.
 *
 * @package    core_h5p
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

/**
 * Contains API class for the H5P area.
 *
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Delete an H5P package.
     *
     * @param stdClass $content The H5P package to delete with, at least content['id].
     * @param factory $factory The \core_h5p\factory object
     */
    public static function delete_content(\stdClass $content, factory $factory): void {
        $h5pstorage = $factory->get_storage();

        // Add an empty slug to the content if it's not defined, because the H5P library requires this field exists.
        // It's not used when deleting a package, so the real slug value is not required at this point.
        $content->slug = $content->slug ?? '';
        $h5pstorage->deletePackage( (array) $content);
    }

    /**
     * Delete an H5P package deployed from the defined $url.
     *
     * @param string $url pluginfile URL of the H5P package to delete.
     * @param factory $factory The \core_h5p\factory object
     */
    public static function delete_content_from_url(string $url, factory $factory): void {
        // Get the H5P to delete.
        list($file, $h5p) = \core_h5p\helper::get_h5p_from_pluginfile_url($url);
        if ($h5p) {
            self::delete_content($h5p, $factory);
        }
    }
}
