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
 * Callbacks.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

 /**
 * Serve the files from the core_h5p file areas.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the newmodule's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 *
 * @return bool Returns false if we don't find a file.
 */
function core_h5p_pluginfile($course, $cm, $context, string $filearea, array $args, bool $forcedownload, array $options = []) : bool {
    switch ($filearea) {
        case 'libraries':
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                return false; // Invalid context because the libraries are loaded always in the context system.
            }
            $itemid = 0;
            break;

        case 'content':
            // TODO: Check if user has capability to access to this context.
            $itemid = array_shift($args);
            break;

        default:
            return false; // Invalid file area.
    }

    $filename = array_pop($args);
    $filepath = (!$args ? '/' : '/' .implode('/', $args) . '/');

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'core_h5p', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // No such file.
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
