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
 * References files that should be automatically loaded
 *
 * @package    core_h5p
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * A simple autoloader which makes it easy to load classes when you need them.
 *
 * @param string $class name
 */
function h5p_autoloader($class) {
    global $CFG;
    static $classmap;
    if (!isset($classmap)) {
        $classmap = array(
            // Core.
            'H5PCore' => '/lib/h5p/h5p.classes.php',
            'H5PFrameworkInterface' => '/lib/h5p/h5p.classes.php',
            'H5PContent/Validator' => 'lib/h5p/h5p.classes.php',
            'H5PValidator' => '/lib/h5p/h5p.classes.php',
            'H5PStorage' => '/lib/h5p/h5p.classes.php',
            'H5PExport' => '/lib/h5p/h5p.classes.php',
            'H5PDevelopment' => '/lib/h5p/h5p-development.class.php',
            'H5PFileStorage' => '/lib/h5p/h5p-file-storage.interface.php',
            'H5PDefaultStorage' => '/lib/h5p/h5p-default-storage.class.php',
            'H5PEventBase' => '/lib/h5p/h5p-event-base.class.php',
            'H5PMetadata' => '/lib/h5p/h5p-metadata.class.php',
        );
    }

    if (isset($classmap[$class])) {
        require_once($CFG->dirroot . $classmap[$class]);
    }
}
spl_autoload_register('h5p_autoloader');
