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
 * Class \core_h5p\file_storage.
 *
 * @package    core_h5p
 * @copyright  2019 Victor Deniz <victor@moodle.com>, base on code by Joubel AS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/h5p/h5p-file-storage.interface.php');

/**
 * Class to handle storage and export of H5P Content.
 *
 * @package    core_h5p
 * @copyright  2019 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_storage implements \H5PFileStorage {

    private $component   = 'h5p';
    private $libfilearea = 'libraries';
    private $contentfilearea = 'content';

    /**
     * Stores a H5P library in the Moodle filesystem.
     *
     * @param array $library
     */
    public function saveLibrary($library) {
        // Libraries are stored in a system context.
        $context = \context_system::instance();

        $options = array(
            'contextid' => $context->id,
            'component' => self::$component,
            'filearea' => self::$libfilearea,
            'filepath' => '/' . \H5PCore::libraryToString($library, true) . '/',
            'itemid' => 0
        );

        // Easiest approach: delete the existing library version and copy the new one.
        self::delete_directory($options);
        self::copy_directory($library['uploadDirectory'], $options);
    }

    /**
     * Store the given stream into the given file.
     *
     * @param string $path
     * @param string $file
     * @param resource $stream
     * @return bool
     */
    public function saveFileFromZip($path, $file, $stream) {
        $filepath = $path . '/' . $file;

        // Make sure the directory exists first.
        $matches = array();
        preg_match('/(.+)\/[^\/]*$/', $filepath, $matches);
        // Recursively make directories
        if (!file_exists($matches[1])) {
            mkdir($matches[1], 0777, true);
        }

        // Store in local storage folder.
        return file_put_contents($filepath, $stream);
    }

    /**
     * Store the content folder.
     *
     * @param string $source
     *  Path on file system to content directory.
     * @param array $content
     *  Content properties
     */
    public function saveContent($source, $content) {
        // Contents are stored in a course context.
        // TODO: we are planning to use another context.
        $context = \context_module::instance($content['coursemodule']);
        $options = array(
                'contextid' => $context->id,
                'component' => self::$component,
                'filearea' => self::$contentfilearea,
                'itemid' => $content['id'],
                'filepath' => '/',
        );

        self::delete_directory($options);
        // Copy content directory into Moodle filesystem.
        self::copy_directory($source, $options);
    }

    /**
     * Remove content folder.
     *
     * @param array $content
     *  Content properties
     */
    public function deleteContent($content) {
        // TODO: we are planning to use another context.
        $context = \context_module::instance($content['coursemodule']);

        $options = array(
                'contextid' => $context->id,
                'component' => self::$component,
                'filearea' => self::$contentfilearea,
                'itemid' => $content['id'],
                'filepath' => '/',
        );

        self::delete_directory($options);
    }

    /**
     * Creates a stored copy of the content folder.
     *
     * @param string $id
     *  Identifier of content to clone.
     * @param int $newId
     *  The cloned content's identifier
     */
    public function cloneContent($id, $newId) {
        // TODO: Implement cloneContent() method.
        // Not implemented in Moodle.
    }

    /**
     * Get path to a new unique tmp folder.
     *
     * @return string Path
     */
    public function getTmpPath() {
        global $CFG;

        return $CFG->tempdir . uniqid('/hvp-');
    }

    /**
     * Fetch content folder and save in target directory.
     *
     * @param int $id
     *  Content identifier
     * @param string $target
     *  Where the content folder will be saved
     */
    public function exportContent($id, $target) {
        // TODO Context won't be the course module
        $cm = \get_coursemodule_from_instance('hvp', $id);
        $context = \context_module::instance($cm->id);
        self::exportFileTree($target, $context->id, self::$contentfilearea, '/', $id);
    }

    /**
     * Fetch library folder and save in target directory.
     *
     * @param array $library
     *  Library properties
     * @param string $target
     *  Where the library folder will be saved
     */
    public function exportLibrary($library, $target) {
        $folder = \H5PCore::libraryToString($library, true);
        $context = \context_system::instance();
        self::exportFileTree("{$target}/{$folder}", $context->id, self::libfilearea, "/{$folder}/");
    }

    /**
     * Save export in file system
     *
     * @param string $source
     *  Path on file system to temporary export file.
     * @param string $filename
     *  Name of export file.
     */
    public function saveExport($source, $filename) {
        // TODO: Implement saveExport() method.
    }

    /**
     * Removes given export file
     *
     * @param string $filename
     */
    public function deleteExport($filename) {
        // TODO: Implement deleteExport() method.
    }

    /**
     * Check if the given export file exists
     *
     * @param string $filename
     * @return boolean
     */
    public function hasExport($filename) {
        // TODO: Implement hasExport() method.
    }

    /**
     * Will concatenate all JavaScrips and Stylesheets into two files in order
     * to improve page performance.
     *
     * @param array $files
     *  A set of all the assets required for content to display
     * @param string $key
     *  Hashed key for cached asset
     */
    public function cacheAssets(&$files, $key) {
        // TODO: Implement cacheAssets() method.
    }

    /**
     * Will check if there are cache assets available for content.
     *
     * @param string $key
     *  Hashed key for cached asset
     * @return array
     */
    public function getCachedAssets($key) {
        // TODO: Implement getCachedAssets() method.
    }

    /**
     * Remove the aggregated cache files.
     *
     * @param array $keys
     *   The hash keys of removed files
     */
    public function deleteCachedAssets($keys) {
        // TODO: Implement deleteCachedAssets() method.
    }

    /**
     * Read file content of given file and then return it.
     *
     * @param string $file_path
     * @return string contents
     */
    public function getContent($file_path) {
        // TODO: Implement getContent() method.
    }

    /**
     * Save files uploaded through the editor.
     * The files must be marked as temporary until the content form is saved.
     *
     * @param \H5peditorFile $file
     * @param int $contentId
     */
    public function saveFile($file, $contentId) {
        // TODO: Implement saveFile() method.
    }

    /**
     * Copy a file from another content or editor tmp dir.
     * Used when copy pasting content in H5P.
     *
     * @param string $file path + name
     * @param string|int $fromId Content ID or 'editor' string
     * @param int $toId Target Content ID
     */
    public function cloneContentFile($file, $fromId, $toId) {
        // TODO: Implement cloneContentFile() method.
    }

    /**
     * Copy a content from one directory to another. Defaults to cloning
     * content from the current temporary upload folder to the editor path.
     *
     * @param string $source path to source directory
     * @param string $contentId Id of content
     *
     * @return object Object containing h5p json and content json data
     */
    public function moveContentDirectory($source, $contentId = null) {
        // TODO: Implement moveContentDirectory() method.
    }

    /**
     * Checks to see if content has the given file.
     * Used when saving content.
     *
     * @param string $file path + name
     * @param int $contentId
     * @return string|int File ID or NULL if not found
     */
    public function getContentFile($file, $contentId) {
        // TODO: Implement getContentFile() method.
    }

    /**
     * Remove content files that are no longer used.
     * Used when saving content.
     *
     * @param string $file path + name
     * @param int $contentId
     */
    public function removeContentFile($file, $contentId) {
        // TODO: Implement removeContentFile() method.
    }

    /**
     * Check if server setup has write permission to
     * the required folders
     *
     * @return bool True if server has the proper write access
     */
    public function hasWriteAccess() {
        // TODO: Implement hasWriteAccess() method.
    }

    /**
     * Check if the library has a presave.js in the root folder
     *
     * @param string $libraryName
     * @param string $developmentPath
     * @return bool
     */
    public function hasPresave($libraryName, $developmentPath = null) {
        // TODO: Implement hasPresave() method.
    }

    /**
     * Check if upgrades script exist for library.
     *
     * @param string $machineName
     * @param int $majorVersion
     * @param int $minorVersion
     * @return string Relative path
     */
    public function getUpgradeScript($machineName, $majorVersion, $minorVersion) {
        // TODO: Implement getUpgradeScript() method.
    }

    /**
     * Remove an H5P directory from Moodle filesystem.
     *
     * @param int   $contextid  context ID
     * @param string $filepath  directory path
     */
    private static function delete_directory($options) {
        list($contextid, $component, $filepath, $filearea, $itemid) = $options;
        $fs = get_file_storage();

        // Look up files in the library folder and remove.
        $files = $fs->get_directory_files($contextid, $component, $filearea, $itemid, $filepath, true);
        foreach ($files as $file) {
            $file->delete();
        }

        // Remove library folder.
        $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, '.');
        if ($file) {
            $file->delete();
        }
    }

    /**
     * Copy an H5P directory from the temporary directory into the Moodle file system.
     *
     * @param string $libtemptpath Library path in the temp area
     * @param $contextid The context id which the library belongs to
     * @param $filepath Final path of the library
     */
    private static function copy_directory($source, $options) {
        $it =
                new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source,
                        \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST);

        $fs = get_file_storage();
        $root = $options['filepath'];

        $it->rewind();
        while($it->valid()) {
            $item = $it->current();
            $subpath = $it->getSubPath();
            if (!$item->isDir()) {
                $options['filename'] = $it->getFilename();
                if (!$subpath == '') {
                    $options['filepath'] = $root.$subpath.'/';
                } else {
                    $options['filepath'] = $root;
                }

                $fs->create_file_from_pathname($options, $item->getPathName());
            }
            $it->next();
        }
    }

    /**
     * Copies files from Moodle storage to temporary folder.
     *
     * @param string $target
     *  Path to temporary folder
     * @param int $contextid
     *  Moodle context where the files are found
     * @param string $filearea
     *  Moodle file area
     * @param string $filepath
     *  Moodle file path
     * @param int $itemid
     *  Optional Moodle item ID
     */
    private static function exportFileTree($target, $contextid, $filearea, $filepath, $itemid = 0) {
        // Make sure target folder exists.
        if (!file_exists($target)) {
            mkdir($target, 0777, true);
        }

        // Read source files.
        $fs = get_file_storage();
        $files = $fs->get_directory_files($contextid, self::$component, $filearea, $itemid, $filepath, true);

        foreach ($files as $file) {
            // Correct target path for file.
            $path = $target . str_replace($filepath, '/', $file->get_filepath());

            if ($file->is_directory()) {
                // Create directory.
                $path = rtrim($path, '/');
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
            } else {
                // Copy file.
                $file->copy_content_to($path . $file->get_filename());
            }
        }
    }
}