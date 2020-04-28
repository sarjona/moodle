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
 * Class \core_h5p\editor_framework
 *
 * @package    core_h5p
 * @copyright  2020 Victor Deniz <victor@moodle.com>, base on code by Joubel AS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

use H5peditorStorage;
use stdClass;

/**
 * Moodle's implementation of the H5P Editor storage interface.
 *
 * Makes it possible for the editor's core library to communicate with the
 * database used by Moodle.
 *
 * @package    core_h5p
 * @copyright  2020 Victor Deniz <victor@moodle.com>, base on code by Joubel AS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_framework implements H5peditorStorage {

    /** The cache key for the available languages. */
    public const AVAILABLE_LANGS   = 'availablelangs';

    /**
     * Load language file(JSON).
     * Used to translate the editor fields(title, description etc.)
     *
     * @param string $name The machine readable name of the library(content type)
     * @param int $major Major part of version number
     * @param int $minor Minor part of version number
     * @param string $lang Language code
     *
     * @return string|boolean Translation in JSON format if available, false otherwise
     */
    public function getLanguage($name, $major, $minor, $lang) {
        global $DB;

        $translation = false;

        // Check if this information has been saved previously into the cache.
        $langcache = \cache::make('core', 'h5p_content_type_translations');
        $libraryarray = [
            'machineName' => $name,
            'majorVersion' => $major,
            'minorVersion' => $minor,
        ];
        $librarykey = helper::get_cache_librarykey(\H5PCore::libraryToString($libraryarray));
        $libcachedata = $langcache->get($librarykey);
        if ($libcachedata !== false && array_key_exists($lang, $libcachedata)) {
            return $libcachedata[$lang];
        }

        // Get the language file for this library.
        $component = file_storage::COMPONENT;
        $filearea = file_storage::LIBRARY_FILEAREA;
        $sql = "SELECT hl.id, f.pathnamehash
                  FROM {h5p_libraries} hl
             LEFT JOIN {files} f
                    ON hl.id = f.itemid AND f.component = '$component' AND f.filearea = '$filearea' AND f.filepath like '%language%'
                 WHERE ((hl.machinename = :machinename AND hl.majorversion = :majorversion AND hl.minorversion = :minorversion)
                   AND f.filename = :filename)
              ORDER BY hl.patchversion DESC";
        $params = [
            'machinename' => $name,
            'majorversion' => $major,
            'minorversion' => $minor,
            'filename' => $lang.'.json'
        ];

        $result = $DB->get_record_sql($sql, $params);

        if (!empty($result)) {
            // If the JS language file exists, its content should be returned.
            $fs = get_file_storage();
            $file = $fs->get_file_by_hash($result->pathnamehash);
            $translation = $file->get_content();
        }

        // Save translation into the cache (even if there is no translation for this language).
        $libcachedata[$lang] = $translation;
        $langcache->set($librarykey, $libcachedata);

        return $translation;
    }

    /**
     * Load a list of available language codes.
     *
     * Until translations is implemented, only returns the "en" language.
     *
     * @param string $machinename The machine readable name of the library(content type)
     * @param int $major Major part of version number
     * @param int $minor Minor part of version number
     *
     * @return array List of possible language codes
     */
    public function getAvailableLanguages($machinename, $major, $minor): array {
        global $DB;

        $defaultcode = 'en';
        $codes = [];

        // Check if this information has been saved previously into the cache.
        $langcache = \cache::make('core', 'h5p_content_type_translations');
        $libraryarray = [
            'machineName' => $machinename,
            'majorVersion' => $major,
            'minorVersion' => $minor,
        ];
        $librarykey = helper::get_cache_librarykey(\H5PCore::libraryToString($libraryarray));
        $libcachedata = $langcache->get($librarykey);
        if ($libcachedata !== false && array_key_exists(self::AVAILABLE_LANGS, $libcachedata)) {
            return $libcachedata[self::AVAILABLE_LANGS];
        }

        // Get the language files for this library.
        $component = file_storage::COMPONENT;
        $filearea = file_storage::LIBRARY_FILEAREA;
        $sql = "SELECT DISTINCT f.filename
                           FROM {h5p_libraries} hl
                      LEFT JOIN {files} f
                             ON hl.id = f.itemid AND f.component = '$component' AND f.filearea = '$filearea'
                            AND f.filepath like '%language%' AND f.filename like '%.json'
                          WHERE hl.machinename = :machinename AND hl.majorversion = :majorversion
                            AND hl.minorversion = :minorversion";
        $params = [
            'machinename' => $machinename,
            'majorversion' => $major,
            'minorversion' => $minor,
        ];
        $results = $DB->get_records_sql($sql, $params);

        if ($results) {
            // Extract the code language from the JS language files.
            foreach ($results as $result) {
                if (!empty($result->filename)) {
                    $lang = substr($result->filename, 0, -5);
                    $codes[$lang] = $lang;
                }
            }
            // Semantics is 'en' by default. It has to be added always.
            if (!array_key_exists($defaultcode, $codes)) {
                $codes = array_keys($codes);
                array_unshift($codes, $defaultcode);
            }
        } else {
            if ($DB->record_exists('h5p_libraries', $params)) {
                // If the library exists (but it doesn't contain any language file), at least defaultcode should be returned.
                $codes[] = $defaultcode;
            }
        }

        // Save available languages into the cache.
        if (empty($libcachedata)) {
            $libcachedata = [];
        }
        $libcachedata[self::AVAILABLE_LANGS] = $codes;
        $langcache->set($librarykey, $libcachedata);

        return $codes;
    }

    /**
     * "Callback" for mark the given file as a permanent file.
     *
     * Used when saving content that has new uploaded files.
     *
     * @param int $fileid
     */
    public function keepFile($fileid): void {
        // Temporal files will be removed on a task when they are in the "editor" file area and and are at least one day older.
    }

    /**
     * Return libraries details.
     *
     * Two use cases:
     * 1. No input, will list all the available content types.
     * 2. Libraries supported are specified, load additional data and verify
     * that the content types are available. Used by e.g. the Presentation Tool
     * Editor that already knows which content types are supported in its
     * slides.
     *
     * @param array $libraries List of library names + version to load info for.
     *
     * @return array List of all libraries loaded.
     */
    public function getLibraries($libraries = null): ?array {

        if ($libraries !== null) {
            // Get details for the specified libraries.
            $librariesin = [];
            $fields = 'title, runnable';

            foreach ($libraries as $library) {
                $params = [
                    'machinename' => $library->name,
                    'majorversion' => $library->majorVersion,
                    'minorversion' => $library->minorVersion
                ];

                $details = api::get_library_details($params, true, $fields);

                if ($details) {
                    $library->title = $details->title;
                    $library->runnable = $details->runnable;
                    $librariesin[] = $library;
                }
            }
        } else {
            $fields = 'id, machinename as name, title, majorversion, minorversion';
            $librariesin = api::get_contenttype_libraries($fields);
        }

        return $librariesin;
    }

    /**
     * Allow for other plugins to decide which styles and scripts are attached.
     *
     * This is useful for adding and/or modifying the functionality and look of
     * the content types.
     *
     * @param array $files List of files as objects with path and version as properties.
     * @param array $libraries List of libraries indexed by machineName with objects as values. The objects have majorVersion and
     *     minorVersion as properties.
     */
    public function alterLibraryFiles(&$files, $libraries): void {
        // This is to be implemented when the renderer is used.
    }

    /**
     * Saves a file or moves it temporarily.
     *
     * This is often necessary in order to validate and store uploaded or fetched H5Ps.
     *
     * @param string $data Uri of data that should be saved as a temporary file.
     * @param bool $movefile Can be set to TRUE to move the data instead of saving it.
     *
     * @return bool|object Returns false if saving failed or an object with path
     * of the directory and file that is temporarily saved.
     */
    public static function saveFileTemporarily($data, $movefile = false) {
        // This is to be implemented when the Hub client is used to upload libraries.
        return false;
    }

    /**
     * Marks a file for later cleanup.
     *
     * Useful when files are not instantly cleaned up. E.g. for files that are uploaded through the editor.
     *
     * @param int $file Id of file that should be cleaned up
     * @param int|null $contentid Content id of file
     */
    public static function markFileForCleanup($file, $contentid = null): ?int {
        // Temporal files will be removed on a task when they are in the "editor" file area and and are at least one day older.
        return null;
    }

    /**
     * Clean up temporary files
     *
     * @param string $filepath Path to file or directory
     */
    public static function removeTemporarilySavedFiles($filepath): void {
        // This is to be implemented when the Hub client is used to upload libraries.
    }
}
