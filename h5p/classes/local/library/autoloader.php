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
 * H5P autoloader management class.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p\local\library;
use h5plib_v124\local\library\handler as handler_v124;

defined('MOODLE_INTERNAL') || die();

/**
 * H5P autoloader management class.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autoloader {

    /**
     * Returns the list of plugins that can work as H5P library handlers (have class PLUGINNAME\local\library\handler)
     * @return array
     */
    public static function get_all_handlers() {
        $handlers = [];
        foreach (\core_component::get_plugin_types() as $ptype => $unused) {
            $plugins = \core_component::get_plugin_list_with_class($ptype, 'local\library\handler') +
                \core_component::get_plugin_list_with_class($ptype, 'local_library_handler');
            // Allow plugins to have the class either with namespace or without (useful for unittest).
            foreach ($plugins as $pname => $class) {
                $handlers[$pname] = $class;
            }
        }

        return $handlers;
    }

    /**
     * Returns the current H5P library handler
     *
     * @return handler
     */
    public static function get_handler_classname() {
        global $CFG;

        if (!empty($CFG->h5plibraryhandler)) {
            $handlers = self::get_all_handlers();

            if (isset($handlers[$CFG->h5plibraryhandler])) {
                return $handlers[$CFG->h5plibraryhandler];
            }
        }
        // If no handler has been found or defined, return the default one.
        // TODO: Load by default the plugin libraries (components.json is not working as expected).
        require_once($CFG->dirroot . '/lib/h5p/v124/classes/local/library/handler.php');
        return handler_v124::class;
    }

    /**
     * Get the current version of the H5P core library.
     *
     * @return string
     */
    public static function get_h5p_version(): string {
        return component_class_callback(self::get_handler_classname(), 'get_h5p_version', []);
    }

    /**
     * Get a URL for the current H5P Core Library.
     *
     * @param string $filepath The path within the h5p root
     * @return null|moodle_url
     */
    public static function get_h5p_core_library_url(?string $filepath = null): ?\moodle_url {
        return component_class_callback(self::get_handler_classname(), 'get_h5p_core_library_url', [$filepath]);
    }

    /**
     * Register the H5P autoloader.
     */
    public static function register(): void {
        component_class_callback(self::get_handler_classname(), 'register', []);
    }

    /**
     * SPL Autoloading function for H5P.
     *
     * @param string $classname The name of the class to load
     */
    public static function autoload($classname): void {
        component_class_callback(self::get_handler_classname(), 'autoload', [$classname]);
    }
}
