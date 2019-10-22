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
 * H5P Wrapper for Moodle.
 *
 * This works by including the H5P classes, which are not in any namespace, into a new `H5P` namespace, and then
 * overriding the relevant functions onto that namespace.
 *
 * @package    H5P
 * @copyright  2019 Andrew Nicols <andrew@nicols.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types = 1);

namespace H5P;

static $loaded = false;

if (class_exists('H5PCore')) {
    // H5PCore has already been loaded - this is typically by this loader.
    if (!$loaded) {
        throw new \coding_exception("Incorrect loading of the H5P Library without using this loader");
    }
    return;
}

$loaded = true;
// Load the H5P file list.
foreach (get_h5p_file_list() as $file) {
    require_once($file);
}

/**
 * Get the list of H5P files which contains classes, interfaces, and traits.
 *
 * @return array
 */
function get_h5p_file_list(): array {
    return [
        __DIR__ . '/h5p.classes.php',
        __DIR__ . '/h5p-development.class.php',
        __DIR__ . '/h5p-file-storage.interface.php',
        __DIR__ . '/h5p-metadata.class.php',
    ];
}
