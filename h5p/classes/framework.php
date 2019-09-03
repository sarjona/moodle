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
 * \core_h5p\framework class
 *
 * @package    core_h5p
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../autoloader.php');

/**
 * Moodle's implementation of the H5P framework interface.
 *
 * @package    core_h5p
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework implements \H5PFrameworkInterface {

    /**
     * Get type of h5p instance
     *
     * @param string $type Type of hvp instance to get
     * @return \H5PContentValidator|\H5PCore|\H5PStorage|\H5PValidator|\core_h5p\framework|\H5peditor
     */
    public static function instance($type = null) {
        global $CFG;
        static $interface, $core;

        if (!isset($interface)) {
            $interface = new \core_h5p\framework();
            $fs = new \core_h5p\file_storage();
            $language = self::get_language();

            $context = \context_system::instance();
            $url = "{$CFG->wwwroot}/pluginfile.php/{$context->id}/core_h5p";

            $core = new \H5PCore($interface, $fs, $url, $language);
            $core->aggregateAssets = !(isset($CFG->core_h5p_aggregate_assets) && $CFG->core_h5p_aggregate_assets === '0');
        }

        switch ($type) {
            case 'validator':
                return new \H5PValidator($interface, $core);
            case 'storage':
                return new \H5PStorage($interface, $core);
            case 'contentvalidator':
                return new \H5PContentValidator($interface, $core);
            case 'interface':
                return $interface;
            case 'core':
            default:
                return $core;
        }
    }

    /**
     * Get current H5P language code.
     *
     * @return string Language Code
     */
    public static function get_language() {
        static $map;

        if (empty($map)) {
            // Create mapping for "converting" language codes.
            $map = array(
                'no' => 'nb'
            );
        }

        // Get current language in Moodle.
        $language = str_replace('_', '-', strtolower(\current_language()));

        // Try to map.
        return $map[$language] ?? $language;
    }

   /**
    * Returns info for the current platform
    * Implements getPlatformInfo
    *
    * @return array
    */
    public function getPlatformInfo() {
        global $CFG;

        return array(
            'name' => 'Moodle',
            'version' => $CFG->version,
            'h5pVersion' => get_component_version('core_h5p'),
        );
    }

    /**
     * Fetches a file from a remote server using HTTP GET
     * Implements fetchExternalData
     *
     * @param string $url Where you want to get or send data.
     * @param array $data Data to post to the URL.
     * @param bool $blocking Set to 'FALSE' to instantly time out (fire and forget).
     * @param string $stream Path to where the file should be saved.
     * @return string The content (response body). NULL if something went wrong
     */
    public function fetchExternalData($url, $data = null, $blocking = true, $stream = null) {
        global $CFG;

        if ($stream !== null) {
            // Download file.
            @set_time_limit(0);

            // Generate local tmp file path.
            $localfolder = $CFG->tempdir . uniqid('/h5p-');
            $stream = $localfolder . '.h5p';

            // Add folder and file paths to H5P Core.
            $interface = self::instance('interface');
            $interface->getUploadedH5pFolderPath($localfolder);
            $interface->getUploadedH5pPath($stream);
        }

        $response = download_file_content($url, null, $data, true, 300, 20,
                false, $stream);

        if (empty($response->error)) {
            return $response->results;
        } else {
            $this->setErrorMessage($response->error, 'failed-fetching-external-data');
        }
    }

    /**
     * Set the tutorial URL for a library. All versions of the library is set.
     * Implements setLibraryTutorialUrl
     *
     * @param string $libraryname
     * @param string $url
     */
    public function setLibraryTutorialUrl($libraryname, $url) {
    }

    /**
     * Show the user an error message.
     * Implements setErrorMessage
     *
     * @param string $message translated error message
     * @param string $code
     */
    public function setErrorMessage($message, $code = null) {
        if ($message !== null) {
            self::messages('error', $message, $code);
        }
    }

    /**
     * Show the user an information message.
     * Implements setInfoMessage
     *
     * @param string $message
     */
    public function setInfoMessage($message) {
        if ($message !== null) {
            self::messages('info', $message);
        }
    }

    /**
     * Store messages until they can be printed to the current user
     *
     * @param string $type Type of messages, e.g. 'info' or 'error'
     * @param string $newmessage Optional
     * @param string $code
     * @return array Array of stored messages
     */
    public static function messages(string $type, string $newmessage = null, string $code = null) : array {
        global $SESSION;

        if ($newmessage === null) {
            // Return and reset messages.
            $messages = $SESSION->core_h5p_messages[$type] ?? array();
            unset($SESSION->core_h5p_messages[$type]);
            if (empty($SESSION->core_h5p_messages)) {
                unset($SESSION->core_h5p_messages);
            }
            return $messages;
        }

        // We expect to get out an array of strings when getting info
        // and an array of objects when getting errors for consistency across platforms.
        // This implementation should be improved for consistency across the data type returned here.
        if ($type === 'error') {
            $SESSION->core_h5p_messages[$type][] = (object) array(
                'code' => $code,
                'message' => $newmessage
            );
        } else {
            $SESSION->core_h5p_messages[$type][] = $newmessage;
        }

        return $SESSION->core_h5p_messages[$type];
    }

    /**
     * Simple print of given messages.
     *
     * @param string $type One of error|info
     * @param array $messages
     */
    public static function print_messages(string $type, array $messages) {
        global $OUTPUT;

        foreach ($messages as $message) {
            $out = $type === 'error' ? $message->message : $message;
            print $OUTPUT->notification($out, ($type === 'error' ? 'notifyproblem' : 'notifymessage'));
        }
    }

    /**
     * Return messages.
     * Implements getMessages
     *
     * @param string $type 'info' or 'error'
     * @return string[]
     */
    public function getMessages($type) {
        return self::messages($type);
    }

    /**
     * Translation function.
     * Implements t
     *
     * @param string $message The english string to be translated.
     * @param array $replacements An associative array of replacements to make after translation.
     * @return string Translated string
     */
    public function t($message, $replacements = array()) {
    }

    /**
     * Return the path to the folder where the h5p files are stored.
     *
     * @return string Path to the folder where all h5p files are stored
     */
    private function get_h5p_path() : string {
        global $CFG;
        // TODO: Add the correct path.
        return $CFG->dirroot . '/h5p/files';
    }

    /**
     * Get URL to file in the specific library.
     * Implements getLibraryFileUrl
     *
     * @param string $libraryfoldername
     * @param string $filename
     * @return string URL to file
     */
    public function getLibraryFileUrl($libraryfoldername, $filename) {
        global $CFG;
        $context  = \context_system::instance();
        return "{$CFG->wwwroot}/pluginfile.php/{$context->id}/core_h5p/libraries/{$libraryfoldername}/{$filename}";
    }

    /**
     * Get the Path to the last uploaded h5p.
     * Implements getUploadedH5PFolderPath
     *
     * @param string $setpath
     * @return string
     */
    public function getUploadedH5pFolderPath($setpath = null) {
        static $path;

        if ($setpath !== null) {
            $path = $setpath;
        }

        if (!isset($path)) {
            throw new \coding_exception('Using getUploadedH5pFolderPath() before path is set');
        }

        return $path;
    }

    /**
     * Get the path to the last uploaded h5p file.
     * Implements getUploadedH5PPath
     *
     * @param string $setpath
     * @return string Path to the last uploaded h5p
     */
    public function getUploadedH5pPath($setpath = null) {
        static $path;

        if ($setpath !== null) {
            $path = $setpath;
        }

        return $path;
    }

    /**
     * Get a list of the current installed libraries.
     * Implements getAdminUrl
     *
     * @return array Associative array containing one entry per machine name.
     */
    public function loadLibraries() {
        global $DB;

        $results = $DB->get_records('h5p_libraries', [], 'title ASC, majorversion ASC, minorversion ASC',
                'id, machinename, title, majorversion, minorversion, patchversion, runnable');

        $libraries = array();
        foreach ($results as $library) {
            $libraries[$library->machinename][] = $library;
        }

        return $libraries;
    }

    /**
     * Returns the URL to the library admin page.
     * Implements getAdminUrl
     *
     * @return string URL to admin page
     */
    public function getAdminUrl() {
        // Not supported.
    }

    /**
     * Return the library's ID.
     * Implements getLibraryId
     *
     * @param string $machinename
     * @param string $majorversion
     * @param string $minorversion
     * @return int Identifier, or false if non-existent
     */
    public function getLibraryId($machinename, $majorversion = null, $minorversion = null) {
        global $DB;

        // Look for specific library.
        $sqlwhere = 'WHERE machinename = ?';
        $sqlargs = array($machinename);

        if ($majorversion !== null) {
            // Look for major version.
            $sqlwhere .= ' AND majorversion = ?';
            $sqlargs[] = $majorversion;
            if ($minorversion !== null) {
                // Look for minor version.
                $sqlwhere .= ' AND minorversion = ?';
                $sqlargs[] = $minorversion;
            }
        }

        $sql = "SELECT id
                  FROM {h5p_libraries}
                       {$sqlwhere}
              ORDER BY majorversion DESC,
                       minorversion DESC,
                       patchversion DESC";

        // Get the latest version which matches the input parameters.
        $libraries = $DB->get_records_sql($sql, $sqlargs, 0, 1);
        if ($libraries) {
            $library = reset($libraries);
            return $library->id ?? false;
        }

        return false;
    }

    /**
     * Is the library a patched version of an existing library?
     * Implements isPatchedLibrary
     *
     * @param array $library
     * @return boolean
     */
    public function isPatchedLibrary($library) {
        global $DB, $CFG;

        if (isset($CFG->core_h5p_dev) && $CFG->core_h5p_dev) {
            // Makes sure libraries are updated, patch version does not matter.
            return true;
        }
        $operator = $this->isInDevMode() ? '<=' : '<';
        $sql = "SELECT id
                  FROM {h5p_libraries}
                 WHERE machinename = ?
                       AND majorversion = ?
                       AND minorversion = ?
                       AND patchversion {$operator} ?";

        $library = $DB->get_record_sql($sql,
            array(
                $library['machineName'],
                $library['majorVersion'],
                $library['minorVersion'],
                $library['patchVersion']
            )
        );

        return $library ? true : false;
    }

    /**
     * Is H5P in development mode?
     * Implements isPatchedLibrary
     *
     * @return boolean TRUE if H5P development mode is active FALSE otherwise
     */
    public function isInDevMode() {
        return false; // Not supported (Files in moodle not editable).
    }

    /**
     * Is the current user allowed to update libraries?
     * Implements mayUpdateLibraries
     *
     * @return boolean TRUE if the user is allowed to update libraries
     *                 FALSE if the user is not allowed to update libraries
     */
    public function mayUpdateLibraries($allow = false) {
        static $override;

        // Allow overriding the permission check. Needed when installing.
        // since caps hasn't been set.
        if ($allow) {
            $override = true;
        }
        if ($override) {
            return true;
        }

//        // Check permissions.
//        $context = \context_system::instance();
//        if (!has_capability('core/h5p:updatelibraries', $context)) {
//            return false;
//        }

        return true;
    }

    /**
     * Get number of content/nodes using a library, and the number of
     * dependencies to other libraries.
     * Implements getLibraryUsage
     *
     * @param int $id
     * @param boolean $skipcontent Optional. Set as true to get number of content instances for library.
     * @return array The array contains two elements, keyed by 'content' and 'libraries'.
     *               Each element contains a number.
     */
    public function getLibraryUsage($id, $skipcontent = false) {
        global $DB;

        if ($skipcontent) {
            $content = -1;
        } else {
            $sql = "SELECT COUNT(distinct c.id)
                      FROM {h5p_libraries} l
                      JOIN {hvp_contents_libraries} cl
                           ON l.id = cl.libraryid
                      JOIN {h5p} c
                           ON cl.h5pid = c.id
                     WHERE l.id = ?";
            $content = $DB->count_records_sql($sql, array($id));
        }

        $libraries = $DB->count_records('h5p_library_dependencies', ['requiredlibraryid' => $id]);

        return array(
            'content' => $content,
            'libraries' => $libraries,
        );
    }

    /**
     * Get the amount of content items associated to a library
     * Implements getLibraryContentCount
     *
     * return int
     */
    public function getLibraryContentCount() {
        global $DB;

        $contentcount = array();

        $sql = "SELECT c.mainlibraryid,
                       l.machinename,
                       l.majorversion,
                       l.minorversion,
                       c.count
                  FROM (SELECT mainlibraryid,
                               count(id) as count
                          FROM {h5p}
                      GROUP BY mainlibraryid) c, {h5p_libraries} l
                  WHERE c.mainlibraryid = l.id";

        // Count content using the same content type.
        $res = $DB->get_records_sql($sql);

        // Extract results.
        foreach ($res as $lib) {
            $contentcount["{$lib->machinename} {$lib->majorversion}.{$lib->minorversion}"] = $lib->count;
        }

        return $contentcount;
    }

    /**
     * Store data about a library
     * Implements saveLibraryData
     *
     * Also fills in the libraryId in the libraryData object if the object is new
     *
     * @param array $librarydata
     * @param bool $new
     * @return
     */
    public function saveLibraryData(&$librarydata, $new = true) {
        global $DB;

        // Some special properties needs some checking and converting before they can be saved.
        $preloadedjs = $this->paths_to_csv($librarydata, 'preloadedJs');
        $preloadedcss = $this->paths_to_csv($librarydata, 'preloadedCss');
        $droplibrarycss = '';

        if (isset($librarydata['dropLibraryCss'])) {
            $libs = array();
            foreach ($librarydata['dropLibraryCss'] as $lib) {
                $libs[] = $lib['machineName'];
            }
            $droplibrarycss = implode(', ', $libs);
        }

        $embedtypes = '';
        if (isset($librarydata['embedTypes'])) {
            $embedtypes = implode(', ', $librarydata['embedTypes']);
        }
        if (!isset($librarydata['semantics'])) {
            $librarydata['semantics'] = '';
        }
        if (!isset($librarydata['fullscreen'])) {
            $librarydata['fullscreen'] = 0;
        }
        if (!isset($librarydata['hasIcon'])) {
            $librarydata['hasIcon'] = 0;
        }
        // TODO: Can we move the above code to H5PCore? It's the same for multiple
        // implementations. Perhaps core can update the data objects before calling
        // this function?
        // I think maybe it's best to do this when classes are created for
        // library, content, etc.

        $library = (object) array(
            'title' => $librarydata['title'],
            'machinename' => $librarydata['machineName'],
            'majorversion' => $librarydata['majorVersion'],
            'minorversion' => $librarydata['minorVersion'],
            'patchversion' => $librarydata['patchVersion'],
            'runnable' => $librarydata['runnable'],
            'fullscreen' => $librarydata['fullscreen'],
            'embedtypes' => $embedtypes,
            'preloaded_js' => $preloadedjs,
            'preloaded_css' => $preloadedcss,
            'droplibrarycss' => $droplibrarycss,
            'semantics' => $librarydata['semantics']
        );

        if ($new) {
            // Create new library and keep track of id.
            $library->id = $DB->insert_record('h5p_libraries', $library);
            $librarydata['libraryId'] = $library->id;
        } else {
            // Update library data.
            $library->id = $librarydata['libraryId'];
            // Save library data.
            $DB->update_record('h5p_libraries', $library);
            // Remove old dependencies.
            $this->deleteLibraryDependencies($librarydata['libraryId']);
        }
    }

    /**
     * Convert list of file paths to csv.
     *
     * @param array $librarydata Library data as found in library.json files
     * @param string $key Key that should be found in $librarydata
     * @return string File paths separated by ', '
     */
    private function paths_to_csv(array $librarydata, string $key) : string {
        if (isset($librarydata[$key])) {
            $paths = array();
            foreach ($librarydata[$key] as $file) {
                $paths[] = $file['path'];
            }
            return implode(', ', $paths);
        }
        return '';
    }


    /**
     * Start an atomic operation against the dependency storage
     * Implements lockDependencyStorage
     */
    public function lockDependencyStorage() {
        // Library development mode not supported.
    }

    /**
     * Start an atomic operation against the dependency storage
     * Implements unlockDependencyStorage
     */
    public function unlockDependencyStorage() {
        // Library development mode not supported.
    }

    /**
     * Delete a library from database and file system
     * Implements deleteLibrary
     *
     * @param stdClass $library Library object with id, name, major version and minor version.
     */
    public function deleteLibrary($library) {
        global $DB;

        // Delete library files.
        // TODO: Add the correct path.
        $librarybase = $this->get_h5p_path() . '/libraries/';
        $libname = "{$library->name}-{$library->majorversion}.{$library->minorversion}";
        \H5PCore::deleteFileTree("{$librarybase}{$libname}");

        // Remove library data from database.
        $DB->delete_records('h5p_library_dependencies', array('libraryid' => $library->id));
        $DB->delete_records('h5p_libraries', array('id' => $library->id));
    }

    /**
     * Save what libraries a library is depending on
     * Implements saveLibraryDependencies
     *
     * @param int $libraryid Library Id for the library we're saving dependencies for
     * @param array $dependencies
     * @param string $dependencytype
     */
    public function saveLibraryDependencies($libraryid, $dependencies, $dependencytype) {
        global $DB;

        foreach ($dependencies as $dependency) {
            // Find dependency library.
            $dependencylibrary = $DB->get_record('h5p_libraries',
                array(
                    'machinename' => $dependency['machineName'],
                    'majorversion' => $dependency['majorVersion'],
                    'minorversion' => $dependency['minorVersion']
                )
            );

            // Create relation.
            $DB->insert_record('h5p_library_dependencies', array(
                'libraryid' => $libraryid,
                'requiredlibraryid' => $dependencylibrary->id,
                'dependencytype' => $dependencytype
            ));
        }
    }

    /**
     * Update old content.
     * Implements updateContent
     *
     * @param array $content
     * @param int $contentmainid Main id for the content if this is a system that supports versions
     * @return int
     */
    public function updateContent($content, $contentmainid = null) {
        global $DB;

        if (!isset($content['disable'])) {
            $content['disable'] = \H5PCore::DISABLE_NONE;
        }

        $data = array(
            'jsoncontent' => $content['params'],
            'embedtype' => 'div',
            'mainlibraryid' => $content['library']['libraryId'],
            'timemodified' => time(),
        );

        if (!isset($content['id'])) {
            $data['slug'] = '';
            $data['timecreated'] = $data['timemodified'];
            $id = $DB->insert_record('h5p', $data);
        } else {
            $id = $data['id'] = $content['id'];
            $DB->update_record('h5p', $data);
        }

        return $id;
    }

    /**
     * Insert new content.
     * Implements insertContent
     *
     * @param array $content
     * @param int $contentmainid Main id for the content if this is a system that supports versions
     * @return int
     */
    public function insertContent($content, $contentmainid = null) {
        return $this->updateContent($content);
    }

    /**
     * Resets marked user data for the given content.
     * Implements resetContentUserData
     *
     * @param int $contentid
     */
    public function resetContentUserData($contentid) {
//        global $DB;
//
//        $userdata = $DB->get_record('h5p_content_user_data', [
//            'h5pid' => $contentid,
//            'delete_on_content_change' => 1
//        ]);
//        $userdata->data = 'RESET';
//        $DB->update_record('h5p_content_user_data', $userdata);
    }

    /**
     * Get file extension whitelist.
     * Implements getWhitelist
     *
     * The default extension list is part of h5p, but admins should be allowed to modify it
     *
     * @param boolean $islibrary TRUE if this is the whitelist for a library. FALSE if it is the whitelist
     *                           for the content folder we are getting
     * @param string $defaultcontentwhitelist A string of file extensions separated by whitespace
     * @param string $defaultlibrarywhitelist A string of file extensions separated by whitespace
     */
    public function getWhitelist($islibrary, $defaultcontentwhitelist, $defaultlibrarywhitelist) {
        return $defaultcontentwhitelist . ($islibrary ? ' ' . $defaultlibrarywhitelist : '');
    }

    /**
     * Give an H5P the same library dependencies as a given H5P.
     * Implements copyLibraryUsage
     *
     * @param int $contentid Id identifying the content
     * @param int $copyfromid Id identifying the content to be copied
     * @param int $contentmainid Main id for the content, typically used in frameworks
     */
    public function copyLibraryUsage($contentid, $copyfromid, $contentmainid = null) {
        global $DB;

        $libraryusage = $DB->get_record('h5p_contents_libraries', array('id' => $copyfromid));

        $libraryusage->id = $contentid;
        $DB->insert_record_raw('h5p_contents_libraries', (array) $libraryusage, false, false, true);
    }

    /**
     * Loads library semantics.
     * Implements loadLibrarySemantics
     *
     * @param string $machineName Machine name for the library
     * @param int $majorVersion The library's major version
     * @param int $minorVersion The library's minor version
     * @return string The library's semantics as json
     */
    public function loadLibrarySemantics($name, $majorversion, $minorversion) {
        global $DB;

        $semantics = $DB->get_field('h5p_libraries', 'semantics',
            array(
                'machinename' => $name,
                'majorversion' => $majorversion,
                'minorversion' => $minorversion
            )
        );

        return ($semantics === false ? null : $semantics);
    }

  /**
   * Makes it possible to alter the semantics, adding custom fields, etc.
   * Implements alterLibrarySemantics
   *
   * @param array $semantics Associative array representing the semantics
   * @param string $name The library's machine name
   * @param int $majorversion The library's major version
   * @param int $minorversion The library's minor version
   */
    public function alterLibrarySemantics(&$semantics, $name, $majorversion, $minorversion) {
    }

    /**
     * Load content.
     * Implements loadContent
     *
     * @param int $id Content identifier
     * @return array
     */
    public function loadContent($id) {
        global $DB;

        $sql = "SELECT hc.id, hc.jsoncontent, hc.embedtype, hl.id AS libraryid,
                       hl.machinename, hl.majorversion, hl.minorversion,
                       hl.fullscreen, hl.semantics
                  FROM {h5p} hc
                  JOIN {h5p_libraries} hl
                       ON hl.id = hc.mainlibraryid
                 WHERE hc.id = ?";

        $data = $DB->get_record_sql($sql, array($id));

        // Return null if not found.
        if ($data === false) {
            return null;
        }

        // Some databases do not support camelCase, so we need to manually
        // map the values to the camelCase names used by the H5P core.
        $content = array(
            'id' => $data->id,
            'params' => $data->jsoncontent,
            'title' => 'h5p-title-' . $data->id,
            'filtered' => '',
            'slug' => 'h5p-test-' . $data->id,
            'embedType' => $data->embedtype,
            'disable' => 'false',
            'libraryId' => $data->libraryid,
            'libraryName' => $data->machinename,
            'libraryMajorVersion' => $data->majorversion,
            'libraryMinorVersion' => $data->minorversion,
            'libraryEmbedTypes' => $data->embedtype,
            'libraryFullscreen' => $data->fullscreen,
        );

        $content['metadata'] = '';

        return $content;
    }

    /**
     * Load dependencies for the given content of the given type.
     * Implements loadContentDependencies
     *
     * @param int $id Content identifier
     * @param int $type
     * @return array List of associative arrays containing:
     */
    public function loadContentDependencies($id, $type = null) {
        global $DB;

        $query = "SELECT hcl.id AS unidepid, hl.id, hl.machinename AS machine_name,
                         hl.majorversion AS major_version, hl.minorversion AS minor_version,
                         hl.patchversion AS patch_version, hl.preloaded_css, hl.preloaded_js,
                         hcl.dropcss, hcl.dependencytype
                    FROM {h5p_contents_libraries} hcl
                    JOIN {h5p_libraries} hl ON hcl.libraryid = hl.id
                   WHERE hcl.h5pid = ?";
        $queryargs = array($id);

        if ($type !== null) {
            $query .= " AND hcl.dependencytype = ?";
            $queryargs[] = $type;
        }

        $query .= " ORDER BY hcl.weight";
        $data = $DB->get_records_sql($query, $queryargs);

        $dependencies = array();
        foreach ($data as $dependency) {
            unset($dependency->unidepid);
            $dependencies[$dependency->machine_name] = \H5PCore::snakeToCamel($dependency);
        }

        return $dependencies;
    }

    /**
     * Get stored setting.
     * Implements getOption
     *
     * @param string $name Identifier for the setting
     * @param string $default Optional default value if settings is not set
     * @return mixed Whatever has been stored as the setting
     */
    public function getOption($name, $default = false) {
        $value = get_config('core_h5p', $name);
        if ($value === false) {
            return $default;
        }
        return $value;
    }

    /**
     * Stores the given setting.
     * Implements setOption
     *
     * @param string $name Identifier for the setting
     * @param mixed $value Data Whatever we want to store as the setting
     */
    public function setOption($name, $value) {
        set_config($name, $value, 'core_h5p');
    }

    /**
     * This will update selected fields on the given content.
     * Implements updateContentFields().
     *
     * @param int $id Content identifier
     * @param array $fields Content fields, e.g. filtered or slug.
     */
    public function updateContentFields($id, $fields) {
//        global $DB;
//
//        $content = new \stdClass();
//        $content->id = $id;
//
//        foreach ($fields as $name => $value) {
//            $content->$name = $value;
//        }
//
//        $DB->update_record('h5p', $content);
    }

    /**
     * Delete all dependencies belonging to given library
     * Implements deleteLibraryDependencies
     *
     * @param int $libraryId Library identifier
     */
    public function deleteLibraryDependencies($libraryid) {
        global $DB;

        $DB->delete_records('h5p_library_dependencies', array('libraryid' => $libraryid));
    }

    /**
     * Deletes content data
     * Implements deleteContentData
     *
     * @param int $contentid Id identifying the content
     */
    public function deleteContentData($contentid) {
//        global $DB;
//
//        // Remove content.
//        $DB->delete_records('h5p', array('id' => $contentid));
//
//        // Remove content library dependencies.
//        $this->deleteLibraryUsage($contentid);
//
//        // Remove user data for content.
//        $DB->delete_records('h5p_content_user_data', array('h5pid' => $contentid));
    }

    /**
     * Delete what libraries a content item is using
     * Implements deleteLibraryUsage
     *
     * @param int $contentid Content Id of the content we'll be deleting library usage for
     */
    public function deleteLibraryUsage($contentid) {
        global $DB;

        $DB->delete_records('h5p_contents_libraries', array('h5pid' => $contentid));
    }

    /**
     * Saves what libraries the content uses
     * Implements saveLibraryUsage
     *
     * @param int $contentid Id identifying the content
     * @param array $librariesinuse List of libraries the content uses.
     */
    public function saveLibraryUsage($contentid, $librariesinuse) {
        global $DB;

        $droplibrarycsslist = array();
        foreach ($librariesinuse as $dependency) {
            if (!empty($dependency['library']['dropLibraryCss'])) {
                $droplibrarycsslist = array_merge($droplibrarycsslist, explode(', ', $dependency['library']['dropLibraryCss']));
            }
        }

        foreach ($librariesinuse as $dependency) {
            $dropcss = in_array($dependency['library']['machineName'], $droplibrarycsslist) ? 1 : 0;
            $DB->insert_record('h5p_contents_libraries', array(
                'h5pid' => $contentid,
                'libraryid' => $dependency['library']['libraryId'],
                'dependencytype' => $dependency['type'],
                'dropcss' => $dropcss,
                'weight' => $dependency['weight']
            ));
        }
    }

    /**
     * Loads a library.
     * Implements loadLibrary
     *
     * @param string $machinename The library's machine name
     * @param int $majorversion The library's major version
     * @param int $minorversion The library's minor version
     * @return array|FALSE
     */
    public function loadLibrary($machinename, $majorversion, $minorversion) : array {
        global $DB;

        $library = $DB->get_record('h5p_libraries', array(
            'machinename' => $machinename,
            'majorversion' => $majorversion,
            'minorversion' => $minorversion
        ));

        $librarydata = array(
            'libraryId' => $library->id,
            'machineName' => $library->machinename,
            'title' => $library->title,
            'majorVersion' => $library->majorversion,
            'minorVersion' => $library->minorversion,
            'patchVersion' => '',
            'runnable' => $library->runnable,
            'fullscreen' => $library->fullscreen,
            'embedTypes' => '',
            'preloadedJs' => $library->preloaded_js,
            'preloadedCss' => $library->preloaded_css,
            'dropLibraryCss' => $library->droplibrarycss,
            'semantics'     => $library->semantics
        );

        $sql = 'SELECT hl.id, hl.machinename, hl.majorversion, hl.minorversion, hll.dependencytype
                  FROM {h5p_library_dependencies} hll
                  JOIN {h5p_libraries} hl
                       ON hll.requiredlibraryid = hl.id
                 WHERE hll.libraryid = ?';

        $dependencies = $DB->get_records_sql($sql, array($library->id));

        foreach ($dependencies as $dependency) {
            $librarydata[$dependency->dependencytype . 'Dependencies'][] = array(
                'machineName' => $dependency->machinename,
                'majorVersion' => $dependency->majorversion,
                'minorVersion' => $dependency->minorversion
            );
        }

        return $librarydata;
    }

    /**
     * Will clear filtered params for all the content that uses the specified
     * libraries. This means that the content dependencies will have to be rebuilt,
     * and the parameters re-filtered.
     * Implements clearFilteredParameters().
     *
     * @param array $libraryids array of library ids
     *
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public function clearFilteredParameters($libraryids) {
//        global $DB;
//
//        if (empty($libraryids)) {
//            return;
//        }
//
//        list($insql, $inparams) = $DB->get_in_or_equal($libraryids);
//
//        $h5p = $DB->get_records_select('h5p', "mainlibraryid {$insql}", $inparams);
//        $h5p->filtered = null;
//        $DB->update_record('h5p', $h5p);
    }

    /**
     * Get number of contents that has to get their content dependencies rebuilt
     * and parameters re-filtered.
     * Implements getNumNotFiltered().
     *
     * @return int
     */
    public function getNumNotFiltered() {
    }

    /**
     * Get number of contents using library as main library.
     * Implements getNumContent().
     *
     * @param int $libraryId
     * @param array $skip
     * @return int
     */
    public function getNumContent($libraryid, $skip = NULL) {
        global $DB;

        $skipquery = empty($skip) ? '' : " AND id NOT IN ($skip)";
        $sql = "SELECT COUNT(id) FROM {h5p} WHERE mainlibraryid = ? {$skipquery}";
        $contentcount = $DB->count_records_sql($sql, array($libraryid));

        return $contentcount;
    }

    /**
     * Determines if content slug is used.
     * Implements isContentSlugAvailable
     *
     * @param string $slug
     * @return boolean
     */
    public function isContentSlugAvailable($slug) {
    }

    /**
     * Stores hash keys for cached assets, aggregated JavaScripts and
     * stylesheets, and connects it to libraries so that we know which cache file
     * to delete when a library is updated.
     * Implements saveCachedAssets
     *
     * @param string $key Hash key for the given libraries
     * @param array $libraries List of dependencies(libraries) used to create the key
     */
    public function saveCachedAssets($key, $libraries) {
//        global $DB;
//
//        foreach ($libraries as $library) {
//            $cachedasset = new \stdClass();
//            $cachedasset->libraryid = $library['id'];
//            $cachedasset->hash = $key;
//
//            $DB->insert_record('h5p_libraries_cachedassets', $cachedasset);
//        }
    }

    /**
     * Locate hash keys for given library and delete them.
     * Used when cache file are deleted.
     * Implements deleteCachedAssets
     *
     * @param int $libraryid Library identifier
     * @return array List of hash keys removed
     */
    public function deleteCachedAssets($libraryid) {
//        global $DB;
//
//        $sql = "SELECT hash
//                  FROM {h5p_libraries_cachedassets}
//                 WHERE libraryid = ?";
//
//        // Get all the keys so we can remove the files.
//        $results = $DB->get_records_sql($sql, array($libraryid));
//
//        // Remove all invalid keys.
//        $hashes = array();
//        foreach ($results as $key) {
//            $hashes[] = $key->hash;
//            $DB->delete_records('h5p_libraries_cachedassets', array('hash' => $key->hash));
//        }
//
//        return $hashes;
    }

    /**
     * Generates statistics from the event log per library
     * Implements getLibraryStats
     *
     * @param string $type Type of event to generate stats for
     * @return array Number values indexed by library name and version
     */
    public function getLibraryStats($type) {
    }

    /**
     * Aggregate the current number of H5P authors
     * Implements getNumAuthors
     *
     * @return int
     */
    public function getNumAuthors() {
    }

    /**
     * Will trigger after the export file is created.
     * Implements afterExportCreated
     *
     * @param $content
     * @param $filename
     */
    public function afterExportCreated($content, $filename) {
    }

    /**
     * Check if user has permissions to an action
     * Implements hasPermission
     *
     * @param  \H5PPermission $permission
     * @param  int $cmid context module id
     * @return boolean
     */
    public function hasPermission($permission, $cmid = null) {
//        switch ($permission) {
//            case \H5PPermission::DOWNLOAD_H5P:
//            case \H5PPermission::COPY_H5P:
//                $cmcontext = \context_module::instance($cmid);
//                return has_capability('core/h5p:getexport', $cmcontext);
//            case \H5PPermission::CREATE_RESTRICTED:
//                return has_capability('core/h5p:userestrictedlibraries', $this->getajaxcoursecontext());
//            case \H5PPermission::UPDATE_LIBRARIES:
//                $context = \context_system::instance();
//                return has_capability('core/h5p:updatelibraries', $context);
//            case \H5PPermission::INSTALL_RECOMMENDED:
//                return has_capability('core/h5p:installrecommendedh5plibraries', $this->getajaxcoursecontext());
//            case \H5PPermission::EMBED_H5P:
//                $cmcontext = \context_module::instance($cmid);
//                return has_capability('core/h5p:getembedcode', $cmcontext);
//        }
//        return false;
    }

    /**
     * Gets course context in AJAX
     * Implements getajaxcoursecontext
     *
     * @return bool|\context|\context_course
     */
    private function getajaxcoursecontext() {
    }

    /**
     * Replaces existing content type cache with the one passed in
     * Implements replaceContentTypeCache
     *
     * @param object $contenttypecache Json with an array called 'libraries'
     * containing the new content type cache that should replace the old one.
     */
    public function replaceContentTypeCache($contenttypecache) {
    }

    /**
     * Load addon libraries
     * Implements loadAddons
     *
     * @return array
     */
    public function loadAddons() {
        return array();
    }

    /**
     * Load config for libraries
     * Implements getLibraryConfig
     *
     * @param array $libraries
     * @return array
     */
    public function getLibraryConfig($libraries = null) {
        global $CFG;

        return $CFG->core_h5p_library_config ?? null;
    }

    /**
     * Checks if the given library has a higher version.
     * Implements libraryHasUpgrade
     *
     * @param array $library
     * @return boolean
     */
    public function libraryHasUpgrade($library) {
        global $DB;

        $sql = "SELECT id
                  FROM {h5p_libraries}
                 WHERE machinename = ?
                       AND (majorversion > ?
                           OR (majorversion = ? AND minor_version > ?))";

        $results = $DB->get_records_sql(
            $sql,
            array(
                $library['machineName'],
                $library['majorVersion'],
                $library['majorVersion'],
                $library['minorVersion']
            ),
            0,
            1
        );

        return !empty($results);
    }
}
