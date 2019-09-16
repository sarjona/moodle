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

    /** The component for H5P. */
    const COMPONENT   = 'core_h5p';
    /** The library file area. */
    const LIBRARY_FILEAREA = 'libraries';
    /** The content file area */
    const CONTENT_FILEAREA = 'content';
    /** The cached assest file area. */
    const CACHED_ASSETS_FILEAREA = 'cachedassets';
    /** The export file area */
    const EXPORT_FILEAREA = 'export';

    /**
     * Stores a H5P library in the Moodle filesystem.
     *
     * @param array $library
     */
    public function saveLibrary($library) {
        // Libraries are stored in a system context.
        $context = \context_system::instance();

        $options = [
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea' => self::LIBRARY_FILEAREA,
            'filepath' => DIRECTORY_SEPARATOR . \H5PCore::libraryToString($library, true) . DIRECTORY_SEPARATOR,
            'itemid' => $library['libraryId']
        ];

        // Easiest approach: delete the existing library version and copy the new one.
        $this->delete_directory($options);
        $this->copy_directory($library['uploadDirectory'], $options);
    }

    /**
     * Store the content folder.
     *
     * @param string $source Path on file system to content directory.
     * @param array $content Content properties
     */
    public function saveContent($source, $content) {
        // Contents are stored in a course context.
        // TODO: we are planning to use another context.
        $context = \context_system::instance();
        $options = [
                'contextid' => $context->id,
                'component' => self::COMPONENT,
                'filearea' => self::CONTENT_FILEAREA,
                'itemid' => $content['id'],
                'filepath' => '/',
        ];

        $this->delete_directory($options);
        // Copy content directory into Moodle filesystem.
        $this->copy_directory($source, $options);
    }

    /**
     * Remove content folder.
     *
     * @param array $content Content properties
     */
    public function deleteContent($content) {
        // TODO: we are planning to use another context.
        $context = \context_system::instance();

        $options = [
                'contextid' => $context->id,
                'component' => self::COMPONENT,
                'filearea' => self::CONTENT_FILEAREA,
                'itemid' => $content['id'],
                'filepath' => '/',
        ];

        $this->delete_directory($options);
    }

    /**
     * Creates a stored copy of the content folder.
     *
     * @param string $id Identifier of content to clone.
     * @param int $newid The cloned content's identifier
     */
    public function cloneContent($id, $newid) {
        // Not implemented in Moodle.
    }

    /**
     * Get path to a new unique tmp folder.
     * Please note this needs to not be a directory.
     *
     * @return string Path
     */
    public function getTmpPath() : string {
        global $CFG;
        return $CFG->tempdir . DIRECTORY_SEPARATOR . uniqid('h5p-');
    }

    /**
     * Fetch content folder and save in target directory.
     *
     * @param int $id Content identifier
     * @param string $target Where the content folder will be saved
     */
    public function exportContent($id, $target) {
        $context = \context_system::instance();
        $this->export_file_tree($target, $context->id, self::CONTENT_FILEAREA, '/', $id);
    }

    /**
     * Fetch library folder and save in target directory.
     *
     * @param array $library Library properties
     * @param string $target Where the library folder will be saved
     */
    public function exportLibrary($library, $target) {
        $folder = \H5PCore::libraryToString($library, true);
        $context = \context_system::instance();
        $this->export_file_tree($target . DIRECTORY_SEPARATOR . $folder, $context->id, self::LIBRARY_FILEAREA, DIRECTORY_SEPARATOR
                . $folder . DIRECTORY_SEPARATOR, $library['libraryId']);
    }

    /**
     * Save export in file system
     *
     * @param string $source Path on file system to temporary export file.
     * @param string $filename Name of export file.
     */
    public function saveExport($source, $filename) {
        $context = \context_system::instance();
        $filerecord = [
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea' => self::EXPORT_FILEAREA,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename
        ];
        $fs = get_file_storage();
        $fs->create_file_from_pathname($filerecord, $source);
    }

    /**
     * Removes given export file
     *
     * @param string $filename
     */
    public function deleteExport($filename) {
        $file = $this->get_export_file($filename);
        if ($file) {
            $file->delete();
        }
    }

    /**
     * Check if the given export file exists
     *
     * @param string $filename
     * @return boolean
     */
    public function hasExport($filename) {
        return !!$this->get_export_file($filename);
    }

    /**
     * Will concatenate all JavaScrips and Stylesheets into two files in order
     * to improve page performance.
     *
     * @param array $files A set of all the assets required for content to display
     * @param string $key Hashed key for cached asset
     */
    public function cacheAssets(&$files, $key) {

        $context = \context_system::instance();
        $fs = get_file_storage();

        foreach ($files as $type => $assets) {
            if (empty($assets)) {
                continue;
            }

            $content = '';
            $content .= $this->concatenate_files($assets, $type, $context);

            // Create new file for cached assets.
            $ext = ($type === 'scripts' ? 'js' : 'css');
            $filename = $key . '.' . $ext;
            $fileinfo = [
                'contextid' => $context->id,
                'component' => self::COMPONENT,
                'filearea' => self::CACHED_ASSETS_FILEAREA,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename
            ];

            // Store concatenated content.
            $fs->create_file_from_string($fileinfo, $content);
            $files[$type] = [
                (object) [
                    'path' => DIRECTORY_SEPARATOR . self::CACHED_ASSETS_FILEAREA . DIRECTORY_SEPARATOR . $filename,
                    'version' => ''
                ]
            ];
        }
    }

    /**
     * Will check if there are cache assets available for content.
     *
     * @param string $key Hashed key for cached asset
     * @return array
     */
    public function getCachedAssets($key) {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $files = [];

        $js = $fs->get_file($context->id, self::COMPONENT, self::CACHED_ASSETS_FILEAREA, 0, '/', "{$key}.js");
        if ($js && $js->get_filesize() > 0) {
            $files['scripts'] = [
                (object) [
                    'path' => DIRECTORY_SEPARATOR . self::CACHED_ASSETS_FILEAREA . DIRECTORY_SEPARATOR . "{$key}.js",
                    'version' => ''
                ]
            ];
        }

        $css = $fs->get_file($context->id, self::COMPONENT, self::CACHED_ASSETS_FILEAREA, 0, '/', "{$key}.css");
        if ($css && $css->get_filesize() > 0) {
            $files['styles'] = [
                (object) [
                    'path' => DIRECTORY_SEPARATOR . self::CACHED_ASSETS_FILEAREA . DIRECTORY_SEPARATOR . "{$key}.css",
                    'version' => ''
                ]
            ];
        }

        return empty($files) ? null : $files;
    }

    /**
     * Remove the aggregated cache files.
     *
     * @param array $keys The hash keys of removed files
     */
    public function deleteCachedAssets($keys) {

        if (empty($keys)) {
            return;
        }

        $context = \context_system::instance();
        $fs = get_file_storage();

        foreach ($keys as $hash) {
            foreach (['js', 'css'] as $type) {
                $cachedasset = $fs->get_file($context->id, self::COMPONENT, self::CACHED_ASSETS_FILEAREA, 0, '/',
                        "{$hash}.{$type}");
                if ($cachedasset) {
                    $cachedasset->delete();
                }
            }
        }
    }

    /**
     * Read file content of given file and then return it.
     *
     * @param string $filepath
     * @return string contents
     */
    public function getContent($filepath) {
        // Grab context and file storage.
        $context = \context_system::instance();
        $fs = get_file_storage();

        list('filearea' => $filearea, 'filepath' => $filepath, 'filename' => $filename) =
                    $this->get_file_elements_from_filepath($filepath);

        $itemid = $this->get_itemid_for_file($filearea, $filepath, $filename);
        if (!$itemid) {
            throw new \file_serving_exception('Could not retrieve the requested file, check your file permissions.');
        }

        // Locate file.
        $file = $fs->get_file($context->id, self::COMPONENT, $filearea, $itemid, $filepath, $filename);

        // Return content.
        return $file->get_content();
    }

    /**
     * Save files uploaded through the editor.
     * The files must be marked as temporary until the content form is saved.
     *
     * @param \H5peditorFile $file
     * @param int $contentid
     */
    public function saveFile($file, $contentid) {
        // This is to be implemented when the h5p editor is introduced / created.
    }

    /**
     * Copy a file from another content or editor tmp dir.
     * Used when copy pasting content in H5P.
     *
     * @param string $file path + name
     * @param string|int $fromid Content ID or 'editor' string
     * @param int $toid Target Content ID
     */
    public function cloneContentFile($file, $fromid, $toid) {
        // This is to be implemented when the h5p editor is introduced / created.
    }

    /**
     * Copy content from one directory to another. Defaults to cloning
     * content from the current temporary upload folder to the editor path.
     *
     * @param string $source path to source directory
     * @param string $contentid Id of content
     *
     * @return object Object containing h5p json and content json data
     */
    public function moveContentDirectory($source, $contentid = null) {
        // This is to be implemented when the h5p editor is introduced / created.
    }

    /**
     * Checks to see if content has the given file.
     * Used when saving content.
     *
     * @param string $file path + name
     * @param int $contentid
     * @return string|int File ID or NULL if not found
     */
    public function getContentFile($file, $contentid) {
        // This is to be implemented when the h5p editor is introduced / created.
    }

    /**
     * Remove content files that are no longer used.
     * Used when saving content.
     *
     * @param string $file path + name
     * @param int $contentid
     */
    public function removeContentFile($file, $contentid) {
        // This is to be implemented when the h5p editor is introduced / created.
    }

    /**
     * Check if server setup has write permission to
     * the required folders
     *
     * @return bool True if server has the proper write access
     */
    public function hasWriteAccess() {
        // Moodle has access to the files table which is where all of the folders are stored.
        return true;
    }

    /**
     * Check if the library has a presave.js in the root folder
     *
     * @param string $libraryname
     * @param string $developmentpath
     * @return bool
     */
    public function hasPresave($libraryname, $developmentpath = null) {
        return false;
    }

    /**
     * Check if upgrades script exist for library.
     *
     * @param string $machinename
     * @param int $majorversion
     * @param int $minorversion
     * @return string Relative path
     */
    public function getUpgradeScript($machinename, $majorversion, $minorversion) {
        $context = \context_system::instance();
        $fs = get_file_storage();
        $path = DIRECTORY_SEPARATOR . "{$machinename}-{$majorversion}.{$minorversion}" . DIRECTORY_SEPARATOR;
        $file = 'upgrade.js';
        if ($fs->get_file($context->id, self::COMPONENT, self::LIBRARY_FILEAREA, 0, $path, $file)) {
            return DIRECTORY_SEPARATOR . self::LIBRARY_FILEAREA . $path . $file;
        } else {
            return null;
        }
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
        global $CFG;

        $filepath = $path . DIRECTORY_SEPARATOR . $file;

        $fileitems = explode(DIRECTORY_SEPARATOR, $file);
        array_pop($fileitems);
        $newfilestring = implode(DIRECTORY_SEPARATOR, $fileitems);
        $directory = $path . DIRECTORY_SEPARATOR . $newfilestring;

        if (!file_exists($directory)) {
            mkdir($directory, $CFG->directorypermissions, true);
        }

        // Store in local storage folder.
        return (file_put_contents($filepath, $stream));
    }

    /**
     * Remove an H5P directory from the filesystem.
     *
     * @param array $options File system information.
     */
    private function delete_directory(array $options) {
        list('contextid' => $contextid,
            'component' => $component,
            'filepath' => $filepath,
            'filearea' => $filearea,
            'itemid' => $itemid) = $options;
        $fs = get_file_storage();

        $sql = '= :itemid AND filepath = :filepath';
        $params = ['itemid' => $itemid, 'filepath' => $filepath];
        $fs->delete_area_files_select($contextid, $component, $filearea, $sql, $params);
    }

    /**
     * Copy an H5P directory from the temporary directory into the file system.
     *
     * @param  string $source  Temporary location for files.
     * @param  array  $options File system information.
     */
    private function copy_directory(string $source, array $options) {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST);

        $fs = get_file_storage();
        $root = $options['filepath'];

        $it->rewind();
        while ($it->valid()) {
            $item = $it->current();
            $subpath = $it->getSubPath();
            if (!$item->isDir()) {
                $options['filename'] = $it->getFilename();
                if (!$subpath == '') {
                    $options['filepath'] = $root.$subpath . DIRECTORY_SEPARATOR;
                } else {
                    $options['filepath'] = $root;
                }

                $fs->create_file_from_pathname($options, $item->getPathName());
            }
            $it->next();
        }
    }

    /**
     * Copies files from storage to temporary folder.
     *
     * @param string $target Path to temporary folder
     * @param int $contextid context where the files are found
     * @param string $filearea file area
     * @param string $filepath file path
     * @param int $itemid Optional item ID
     */
    private function export_file_tree(string $target, int $contextid, string $filearea, string $filepath, int $itemid = 0) {
        global $CFG;
        // Make sure target folder exists.
        if (!file_exists($target)) {
            mkdir($target, $CFG->directorypermissions, true);
        }

        // Read source files.
        $fs = get_file_storage();
        $files = $fs->get_directory_files($contextid, self::COMPONENT, $filearea, $itemid, $filepath, true);

        foreach ($files as $file) {
            // Correct target path for file.
            $path = $target . str_replace($filepath, DIRECTORY_SEPARATOR, $file->get_filepath());

            if ($file->is_directory()) {
                // Create directory.
                $path = rtrim($path, DIRECTORY_SEPARATOR);
                if (!file_exists($path)) {
                    mkdir($path, $CFG->directorypermissions, true);
                }
            } else {
                // Copy file.
                $file->copy_content_to($path . $file->get_filename());
            }
        }
    }

    /**
     * Adds all files of a type into one file.
     *
     * @param  array    $assets  A list of files.
     * @param  string   $type    The type of files in assets. Either 'scripts' or 'styles'
     * @param  \context $context Context
     * @return string All of the file content in one string.
     */
    private function concatenate_files(array $assets, string $type, \context $context) :string {
        $fs = get_file_storage();
        $content = '';
        foreach ($assets as $asset) {
            // Find location of asset.
            //
            list('filearea' => $filearea, 'filepath' => $filepath, 'filename' => $filename) =
                    $this->get_file_elements_from_filepath($asset->path);

            $fileid = $this->get_itemid_for_file($filearea, $filepath, $filename);
            if ($fileid === false) {
                continue;
            }

            // Locate file.
            $file = $fs->get_file($context->id, self::COMPONENT, $filearea, $fileid, $filepath, $filename);

            // Get file content and concatenate.
            if ($type === 'scripts') {
                $content .= $file->get_content() . ";\n";
            } else {
                // Rewrite relative URLs used inside stylesheets.
                $content .= preg_replace_callback(
                        '/url\([\'"]?([^"\')]+)[\'"]?\)/i',
                        function ($matches) use ($filearea, $filepath) {
                            if (preg_match("/^(data:|([a-z0-9]+:)?\/)/i", $matches[1]) === 1) {
                                return $matches[0]; // Not relative, skip.
                            }
                            return 'url("../' . $filearea . $filepath . $matches[1] . '")';
                        },
                        $file->get_content()) . "\n";
            }
        }
        return $content;
    }

    /**
     * Get files ready for export.
     *
     * @param  string $filename File name to retrieve.
     * @return \stored_file The file for export.
     */
    private function get_export_file(string $filename) {
        $context = \context_system::instance();
        $fs = get_file_storage();
        return $fs->get_file($context->id, self::COMPONENT, self::EXPORT_FILEAREA, 0, '/', $filename);
    }

    /**
     * Returns necessary file information from a given filepath.
     *
     * @param  string $filepath The filepath to get information from.
     * @return array File information.
     */
    private function get_file_elements_from_filepath(string $filepath) : array {
        $sections = explode(DIRECTORY_SEPARATOR, $filepath);
        $filename = array_pop($sections);
        if (empty($sections[0])) {
            array_shift($sections);
        }
        $filearea = array_shift($sections);
        $filepath = implode(DIRECTORY_SEPARATOR, $sections);
        $filepath = DIRECTORY_SEPARATOR . $filepath . DIRECTORY_SEPARATOR;

        return ['filearea' => $filearea, 'filepath' => $filepath, 'filename' => $filename];
    }

    /**
     * Returns the item id given the other necessary variables.
     *
     * @param  string $filearea The file area.
     * @param  string $filepath The file path.
     * @param  string $filename The file name.
     * @return mixed the specified value false if not found.
     */
    private function get_itemid_for_file(string $filearea, string $filepath, string $filename) {
        global $DB;
        return $DB->get_field('files', 'itemid', ['component' => self::COMPONENT, 'filearea' => $filearea, 'filepath' => $filepath,
                'filename' => $filename]);
    }
}