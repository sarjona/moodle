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
 * H5P manager class.
 *
 * @package    core_h5p
 * @copyright  Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to manage H5P files and instances.
 *
 * @package    core_h5p
 * @copyright  2019 Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    protected $fs;

    /**
     * Inits the H5P manager for handling h5p files and instances.
     *
     * @param string $pluginfile Local URL of the H5P file to display.
     */
    public function __construct() {
        $this->fs = get_file_storage();
    }

    /**
     * Get the pluginfile hash for a h5p internal URL.
     *
     * @param  string $url H5P pluginfile string
     * @return string hash for pluginfile
     */
    public function get_pluginfile_hash($url) {
        global $CFG;

        $path = str_replace("$CFG->wwwroot", '', $url);
        $parts = array_reverse(explode('/', $path));

        $filename = $parts[0];
        $filepath = '/';
        $itemid = $parts[1] == 1 ? 0 : $parts[1];
        $filearea = $parts[2];
        $component = $parts[3];
        $contextid = $parts[4];

        return $this->fs->get_pathname_hash($contextid, $component, $filearea, $itemid, $filepath, $filename);
    }

    /**
     * Find H5P instances in Moodle.
     *
     * @param  string $hash H5P pluginfile hash
     * @return object H5P DB record.
     */
    public function find_processed_h5p($hash) {
        global $DB;
        return $DB->get_record('h5p', ['pathnamehash' => $hash]);
    }

    /**
     * Get the H5P DB instance id for a H5P pluginfile url.
     *
     * @param string $url H5P pluginfile url
     * @return int H5P DB id.
     */
    public function get_h5p_id($url) {
        $hash = $this->get_pluginfile_hash($url);
        $h5precord = $this->find_processed_h5p($hash);
        if (!$h5precord) {
            $file = $this->fs->get_file_by_hash($hash);
            if (!$file) {
                return "file not found";
            } else {
                $this->validate_store_h5p($file, $hash);
            }
        } else {
            return $h5precord->id;
        }
    }

    /**
     * Store a H5P file
     *
     * @param Object $file Moodle file instance
     */
    private function validate_store_h5p($file, $hash) {
        global $CFG;

        $interface = \core_h5p\framework::instance('interface');

        $path = $CFG->tempdir . uniqid('/h5p-');

        $interface->getUploadedH5pFolderPath($path);
        $path .= '.h5p';
        $interface->getUploadedH5pPath($path);

        $file->copy_content_to($path);

        $h5pvalidator = \core_h5p\framework::instance('validator');
        if ($h5pvalidator->isValidPackage(false, false)) {
            $h5pstorage = \core_h5p\framework::instance('storage');
            $h5pstorage->savePackage(null, $hash, false);
        }
    }

    /**
     * FOR DEBUGGING PURPOSES ONLY
     *
     * Delete all H5P related DB records and files.
     */
    public function clean_db() {
        global $DB;

        $DB->delete_records('h5p');
        $DB->delete_records('h5p_contents_libraries');
        $DB->delete_records('h5p_libraries');
        $DB->delete_records('h5p_library_dependencies');
        $h5pfilerecords = $DB->get_records('files', ['component' => 'core_h5p']);
        foreach ($h5pfilerecords as $h5pfilerecord) {
            $file = $this->fs->get_file_by_hash($h5pfilerecord->pathnamehash);
            $file->delete();
        }
        $DB->delete_records('files', ['component' => 'core_h5p']);
    }
}
