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
 * Class \core_h5p\editor_ajax
 *
 * @package    core_h5p
 * @copyright  2020 Victor Deniz <victor@moodle.com>, base on code by Joubel AS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

use H5PEditorAjaxInterface;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle's implementation of the H5P Editor Ajax interface.
 *
 * Makes it possible for the editor's core ajax functionality to communicate with the
 * database used by Moodle.
 *
 * @package    core_h5p
 * @copyright  2020 Victor Deniz <victor@moodle.com>, base on code by Joubel AS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_ajax implements H5PEditorAjaxInterface {

    /** The component for H5P. */
    public const EDITOR_AJAX_TOKEN = 'editorajax';

    /**
     * Gets latest library versions that exists locally
     *
     * @return array Latest version of all local libraries
     */
    public function getLatestLibraryVersions() {
        global $DB;

        $maxmajorversionsql = "SELECT hl.machinename, MAX(hl.majorversion) AS majorversion
                                 FROM {h5p_libraries} hl
                                WHERE hl.runnable = 1
                             GROUP BY hl.machinename";

        $maxminorversionsql = "SELECT hl2.machinename, hl2.majorversion, MAX(hl2.minorversion) AS minorversion
                                 FROM ({$maxmajorversionsql}) hl1
                                 JOIN {h5p_libraries} hl2 ON hl1.machinename = hl2.machinename
                                      AND hl1.majorversion = hl2.majorversion
                             GROUP BY hl2.machinename, hl2.majorversion";

        $sql = " SELECT hl4.id, hl4.machinename as machine_name, hl4.title, hl4.majorversion as major_version,
                        hl4.minorversion as minor_version, hl4.patchversion as patch_version, '' as has_icon, 0 as restricted
                   FROM {h5p_libraries} hl4
                   JOIN ({$maxminorversionsql}) hl3 ON hl4.machinename = hl3.machinename
                        AND hl4.majorversion = hl3.majorversion
                        AND hl4.minorversion = hl3.minorversion";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get locally stored Content Type Cache.
     *
     * If machine name is provided it will only get the given content type from the cache.
     *
     * @param null $machinename
     *
     * @return array|mixed|null|object Returns results from querying the database
     */
    public function getContentTypeCache($machinename = null) {
        // This is to be implemented when the Hub client is used.
        return [];
    }

    /**
     * Gets recently used libraries for the current author
     *
     * @return array machine names. The first element in the array is the
     * most recently used.
     */
    public function getAuthorsRecentlyUsedLibraries() {
        // This is to be implemented when the Hub client is used.
        return [];
    }

    /**
     * Checks if the provided token is valid for this endpoint.
     *
     * @param string $token The token that will be validated for.
     *
     * @return bool True if successful validation
     */
    public function validateEditorToken($token) {
        return \H5PCore::validToken(self::EDITOR_AJAX_TOKEN, $token);
    }

    /**
     * Get translations in one language for a list of libraries.
     *
     * @param array $libraries An array of libraries, in the form "<machineName> <majorVersion>.<minorVersion>
     * @param string $languagecode Language code
     *
     * @return array Translations in $languagecode available for libraries $libraries
     */
    public function getTranslations($libraries, $languagecode) {
        global $DB;

        $translations = [];
        $langcache = \cache::make('core', 'h5p_content_type_translations');

        // Get the SQL query for the libraries.
        $librariessql = '';
        $params = [];
        $libstrings = [];
        foreach ($libraries as $libstring) {
            // Check if this library has been saved previously into the cache.
            $librarykey = helper::get_cache_librarykey($libstring);
            $libtranslation = $langcache->get($librarykey);
            if ($libtranslation !== false && array_key_exists($languagecode, $libtranslation)) {
                // The library has this language stored into the cache.
                $translations[$libstring] = $libtranslation[$languagecode];
            } else {
                // This language for the library hasn't been stored previously into the cache, so we need to get it from DB.
                if (!empty($librariessql)) {
                    $librariessql .= ' OR ';
                }
                $librariessql .= '(hl.machinename = ? AND hl.majorversion = ? AND hl.minorversion = ?)';
                $library = \H5PCore::libraryFromString($libstring);
                $machinename = $library['machineName'];
                $majorversion = $library['majorVersion'];
                $minorversion = $library['minorVersion'];
                array_push($params, $machinename, $majorversion, $minorversion);
                // Store the $libstring because it will be used as a key to return the result.
                $libstrings[$machinename][$majorversion][$minorversion] = $libstring;
            }
        }

        if (!empty($librariessql)) {
            // Get all language files for libraries which aren't stored into the cache.
            $component = file_storage::COMPONENT;
            $filearea = file_storage::LIBRARY_FILEAREA;
            $sql = "SELECT hl.id, hl.machinename, hl.majorversion, hl.minorversion, f.contenthash, f.pathnamehash
                      FROM {h5p_libraries} hl
                 LEFT JOIN {files} f
                        ON hl.id = f.itemid AND f.component = '$component' AND f.filearea = '$filearea' AND f.filepath like '%language%'
                     WHERE ($librariessql) AND f.filename = ?";
            $params[] = $languagecode.'.json';
            $results = $DB->get_records_sql($sql, $params);

            // Get the content of all these language files and put them into the translations array.
            $fs = get_file_storage();
            foreach ($results as $result) {
                $libstring = $libstrings[$result->machinename][$result->majorversion][$result->minorversion];
                $file = $fs->get_file_by_hash($result->pathnamehash);
                $translations[$libstring] = $file->get_content();
                // Save translation into the cache for this library.
                $librarykey = helper::get_cache_librarykey($libstring);
                $libtranslation = $langcache->get($librarykey);
                $libtranslation[$languagecode] = $translations[$libstring];
                $langcache->set($librarykey, $libtranslation);
            }
        }

        return $translations;
    }
}
