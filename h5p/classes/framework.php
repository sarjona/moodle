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

global $CFG;

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/adminlib.php');

/**
 * Moodle's implementation of the H5P framework interface.
 *
 * @package    core_h5p
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework implements \H5PFrameworkInterface {

   /**
    * Returns info for the current platform
    * Implements getPlatformInfo
    *
    * @return array An associative array containing:
    *               - name: The name of the platform, for instance "Moodle"
    *               - version: The version of the platform, for instance "3.8"
    *               - h5pVersion: The version of the H5P component
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

            // Get the extension of the remote file.
            $parsedurl = parse_url($url);
            $ext = pathinfo($parsedurl['path'], PATHINFO_EXTENSION);

            // Generate local tmp file path.
            $localfolder = $CFG->tempdir . uniqid('/h5p-');
            $stream = $localfolder;

            // Add the remote file's extension to the temp file.
            if ($ext) {
                $stream .= '.' . $ext;
            }

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
        // Tutorial url is currently not being used or stored in libraries.
    }

    /**
     * Show the user an error message.
     * Implements setErrorMessage
     *
     * @param string $message The error message
     * @param string $code An optional code
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
     * @param string $message The info message
     */
    public function setInfoMessage($message) {
        if ($message !== null) {
            self::messages('info', $message);
        }
    }

    /**
     * Return messages.
     * Implements getMessages
     *
     * @param string $type The message type, e.g. 'info' or 'error'
     * @return string[] Array of messages
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
        // TODO: Review this code (A temporary return has been added until this method will be implemented properly).
        return $message;
    }

    /**
     * Get URL to file in the specific library.
     * Implements getLibraryFileUrl
     *
     * @param string $libraryfoldername The name of the library's folder
     * @param string $filename The file name
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
     * @param string $setpath The path to the folder of the last uploaded h5p
     * @return string Path to the folder where the last uploaded h5p for this session is located
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
     * @param string $setpath The path to the last uploaded h5p
     * @return string Path to the last uploaded h5p
     */
    public function getUploadedH5pPath($setpath = null) {
        static $path;

        if ($setpath !== null) {
            $path = $setpath;
        }

        if (!isset($path)) {
            throw new \coding_exception('Using getUploadedH5pPath() before path is set');
        }

        return $path;
    }

    /**
     * Load addon libraries
     * Implements loadAddons
     *
     * @return array The array containing the addon libraries
     */
    public function loadAddons() {
        global $DB;

        $addons = array();

        $records = $DB->get_records_sql(
                "SELECT l1.id AS library_id,
                            l1.machinename AS machine_name,
                            l1.majorversion AS major_version,
                            l1.minorversion AS minor_version,
                            l1.patchversion AS patch_version,
                            l1.addto AS add_to,
                            l1.preloadedjs AS preloaded_js,
                            l1.preloadedcss AS preloaded_css
                       FROM {h5p_libraries} l1
                  LEFT JOIN {h5p_libraries} l2
                         ON l1.machinename = l2.machinename
                        AND (l1.majorversion < l2.majorversion
                             OR (l1.majorversion = l2.majorversion
                                 AND l1.minorversion < l2.minorversion))
                      WHERE l1.addto IS NOT NULL
                        AND l2.machinename IS NULL");

        // NOTE: These are treated as library objects but are missing the following properties:
        // title, droplibrarycss, fullscreen, runnable, semantics.

        // Extract num from records.
        foreach ($records as $addon) {
            $addons[] = \H5PCore::snakeToCamel($addon);
        }

        return $addons;
    }

    /**
     * Load config for libraries
     * Implements getLibraryConfig
     *
     * @param array $libraries
     */
    public function getLibraryConfig($libraries = null) {
        // Currently, a library config is not present.
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
                'machinename AS machine_name, majorversion AS major_version, minorversion AS minor_version,
                patchversion AS patch_version');

        $libraries = array();
        foreach ($results as $library) {
            $libraries[$library->machine_name][] = $library;
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
     * @param string $machinename The librarys machine name
     * @param string $majorversion Major version number for library (optional)
     * @param string $minorversion Minor version number for library (optional)
     * @return int Identifier, or false if non-existent
     */
    public function getLibraryId($machinename, $majorversion = null, $minorversion = null) {
        global $DB;

        // Look for specific library.
        $sqlwhere = 'WHERE machinename = :machinename';
        $sqlargs = array(
            'machinename' => $machinename
        );

        if ($majorversion !== null) {
            // Look for major version.
            $sqlwhere .= ' AND majorversion = :majorversion';
            $sqlargs['majorversion'] = $majorversion;
            if ($minorversion !== null) {
                // Look for minor version.
                $sqlwhere .= ' AND minorversion = :minorversion';
                $sqlargs['minorversion'] = $minorversion;
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
     * Is the library a patched version of an existing library?
     * Implements isPatchedLibrary
     *
     * @param array $library An associative array containing:
     *                       - machineName: The library machine name
     *                       - majorVersion: The librarys major version
     *                       - minorVersion: The librarys minor version
     *                       - patchVersion: The librarys patch version
     * @return boolean TRUE if the library is a patched version of an existing library FALSE otherwise
     */
    public function isPatchedLibrary($library) {
        global $DB;

        $operator = $this->isInDevMode() ? '<=' : '<';
        $sql = "SELECT id
                  FROM {h5p_libraries}
                 WHERE machinename = :machinename
                       AND majorversion = :majorversion
                       AND minorversion = :minorversion
                       AND patchversion {$operator} :patchversion";

        $library = $DB->get_records_sql(
            $sql,
            array(
                'machinename' => $library['machineName'],
                'majorversion' => $library['majorVersion'],
                'minorversion' => $library['minorVersion'],
                'patchversion' => $library['patchVersion']
            ),
            0,
            1
        );

        return !empty($library);
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
    public function mayUpdateLibraries() {
        return true;
    }

    /**
     * Store data about a library
     * Implements saveLibraryData
     *
     * Also fills in the libraryId in the libraryData object if the object is new
     *
     * @param array $librarydata Associative array containing:
     *                           - libraryId: The id of the library if it is an existing library.
     *                           - title: The library's name
     *                           - machineName: The library machineName
     *                           - majorVersion: The library's majorVersion
     *                           - minorVersion: The library's minorVersion
     *                           - patchVersion: The library's patchVersion
     *                           - runnable: 1 if the library is a content type, 0 otherwise
     *                           - fullscreen(optional): 1 if the library supports fullscreen, 0 otherwise
     *                           - preloadedJs(optional): list of associative arrays containing:
     *                             - path: path to a js file relative to the library root folder
     *                           - preloadedCss(optional): list of associative arrays containing:
     *                             - path: path to css file relative to the library root folder
     *                           - dropLibraryCss(optional): list of associative arrays containing:
     *                             - machineName: machine name for the librarys that are to drop their css
     *                           - semantics(optional): Json describing the content structure for the library
     * @param bool $new Whether it is a new or existing library.
     * @return
     */
    public function saveLibraryData(&$librarydata, $new = true) {
        global $DB;

        // Some special properties needs some checking and converting before they can be saved.
        $preloadedjs = $this->library_parameter_values_to_csv($librarydata, 'preloadedJs', 'path');
        $preloadedcss = $this->library_parameter_values_to_csv($librarydata, 'preloadedCss', 'path');
        $droplibrarycss = $this->library_parameter_values_to_csv($librarydata, 'dropLibraryCss', 'machineName');

        if (!isset($librarydata['semantics'])) {
            $librarydata['semantics'] = '';
        }
        if (!isset($librarydata['fullscreen'])) {
            $librarydata['fullscreen'] = 0;
        }

        $library = (object) array(
            'title' => $librarydata['title'],
            'machinename' => $librarydata['machineName'],
            'majorversion' => $librarydata['majorVersion'],
            'minorversion' => $librarydata['minorVersion'],
            'patchversion' => $librarydata['patchVersion'],
            'runnable' => $librarydata['runnable'],
            'fullscreen' => $librarydata['fullscreen'],
            'preloadedjs' => $preloadedjs,
            'preloadedcss' => $preloadedcss,
            'droplibrarycss' => $droplibrarycss,
            'semantics' => $librarydata['semantics'],
            'addto' => isset($librarydata['addTo']) ? json_encode($librarydata['addTo']) : null,
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
     * Insert new content.
     * Implements insertContent
     *
     * @param array $content An associative array containing:
     *                       - id: The content id
     *                       - params: The content in json format
     *                       - library: An associative array containing:
     *                         - libraryId: The id of the main library for this content
     * @param int $contentmainid Main id for the content if this is a system that supports versions
     * @return int int The ID of the newly inserted content
     */
    public function insertContent($content, $contentmainid = null) {
        return $this->updateContent($content);
    }

    /**
     * Update old content or insert new content.
     * Implements updateContent
     *
     * @param array $content An associative array containing:
     *                       - id: The content id
     *                       - params: The content in json format
     *                       - library: An associative array containing:
     *                         - libraryId: The id of the main library for this content
     *                       - pathnamehash: The hash linking the record with the entry in the mdl_files table.
     * @param int $contentmainid Main id for the content if this is a system that supports versions
     * @return int The ID of the newly inserted or updated content
     */
    public function updateContent($content, $contentmainid = null) {
        global $DB;

        if (!isset($content['pathnamehash'])) {
            $content['pathnamehash'] = '';
        }

        $data = array(
            'jsoncontent' => $content['params'],
            'embedtype' => 'div',
            'mainlibraryid' => $content['library']['libraryId'],
            'pathnamehash' => $content['pathnamehash'],
            'timemodified' => time(),
        );

        if (!isset($content['id'])) {
            $data['timecreated'] = $data['timemodified'];
            $id = $DB->insert_record('h5p', $data);
        } else {
            $id = $data['id'] = $content['id'];
            $DB->update_record('h5p', $data);
        }

        return $id;
    }

    /**
     * Resets marked user data for the given content.
     * Implements resetContentUserData
     *
     * @param int $contentid The h5p content id
     */
    public function resetContentUserData($contentid) {
        // Currently, we do not store user data for a content.
    }

    /**
     * Save what libraries a library is depending on
     * Implements saveLibraryDependencies
     *
     * @param int $libraryid Library Id for the library we're saving dependencies for
     * @param array $dependencies List of dependencies as associative arrays containing:
     *                            - machineName: The library machineName
     *                            - majorVersion: The library's majorVersion
     *                            - minorVersion: The library's minorVersion
     * @param string $dependencytype The type of dependency
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
     * Give an H5P the same library dependencies as a given H5P.
     * Implements copyLibraryUsage
     *
     * @param int $contentid Id identifying the content
     * @param int $copyfromid Id identifying the content to be copied
     * @param int $contentmainid Main id for the content, typically used in frameworks
     */
    public function copyLibraryUsage($contentid, $copyfromid, $contentmainid = null) {
        // Currently not being called.
    }

    /**
     * Deletes content data
     * Implements deleteContentData
     *
     * @param int $contentid Id identifying the content
     */
    public function deleteContentData($contentid) {
        global $DB;

        // Remove content.
        $DB->delete_records('h5p', array('id' => $contentid));

        // Remove content library dependencies.
        $this->deleteLibraryUsage($contentid);
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
                $droplibrarycsslist = array_merge($droplibrarycsslist,
                        explode(', ', $dependency['library']['dropLibraryCss']));
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
     * Get number of content/nodes using a library, and the number of dependencies to other libraries.
     * Implements getLibraryUsage
     *
     * @param int $id Library identifier.
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
                      JOIN {h5p_contents_libraries} cl
                           ON l.id = cl.libraryid
                      JOIN {h5p} c
                           ON cl.h5pid = c.id
                     WHERE l.id = :libraryid";

            $sqlargs = array(
                'libraryid' => $id
            );

            $content = $DB->count_records_sql($sql, $sqlargs);
        }

        $libraries = $DB->count_records('h5p_library_dependencies', ['requiredlibraryid' => $id]);

        return array(
            'content' => $content,
            'libraries' => $libraries,
        );
    }

    /**
     * Loads a library.
     * Implements loadLibrary
     *
     * @param string $machinename The library's machine name
     * @param int $majorversion The library's major version
     * @param int $minorversion The library's minor version
     * @return array|FALSE Returns FALSE if the library does not exist.
     *                     Otherwise an associative array containing:
     *                     - libraryId: The id of the library if it is an existing library.
     *                     - title: The library's name
     *                     - machineName: The library machineName
     *                     - majorVersion: The library's majorVersion
     *                     - minorVersion: The library's minorVersion
     *                     - patchVersion: The library's patchVersion
     *                     - runnable: 1 if the library is a content type, 0 otherwise
     *                     - fullscreen: 1 if the library supports fullscreen, 0 otherwise
     *                     - embedTypes: list of supported embed types
     *                     - preloadedJs: comma separated string with js file paths
     *                     - preloadedCss: comma separated sting with css file paths
     *                     - dropLibraryCss: list of associative arrays containing:
     *                       - machineName: machine name for the librarys that are to drop their css
     *                     - semantics: Json describing the content structure for the library
     *                     - preloadedDependencies(optional): list of associative arrays containing:
     *                       - machineName: Machine name for a library this library is depending on
     *                       - majorVersion: Major version for a library this library is depending on
     *                       - minorVersion: Minor for a library this library is depending on
     *                     - dynamicDependencies(optional): list of associative arrays containing:
     *                       - machineName: Machine name for a library this library is depending on
     *                       - majorVersion: Major version for a library this library is depending on
     *                       - minorVersion: Minor for a library this library is depending on
     */
    public function loadLibrary($machinename, $majorversion, $minorversion) {
        global $DB;

        $library = $DB->get_record('h5p_libraries', array(
            'machinename' => $machinename,
            'majorversion' => $majorversion,
            'minorversion' => $minorversion
        ));

        if (!$library) {
            return false;
        }

        $librarydata = array(
            'libraryId' => $library->id,
            'title' => $library->title,
            'machineName' => $library->machinename,
            'majorVersion' => $library->majorversion,
            'minorVersion' => $library->minorversion,
            'patchVersion' => $library->patchversion,
            'runnable' => $library->runnable,
            'fullscreen' => $library->fullscreen,
            'embedTypes' => '',
            'preloadedJs' => $library->preloadedjs,
            'preloadedCss' => $library->preloadedcss,
            'dropLibraryCss' => $library->droplibrarycss,
            'semantics'     => $library->semantics
        );

        $sql = 'SELECT hl.id, hl.machinename, hl.majorversion, hl.minorversion, hll.dependencytype
                  FROM {h5p_library_dependencies} hll
                  JOIN {h5p_libraries} hl
                       ON hll.requiredlibraryid = hl.id
                 WHERE hll.libraryid = :libraryid';

        $sqlargs = array(
            'libraryid' => $library->id
        );

        $dependencies = $DB->get_records_sql($sql, $sqlargs);

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
     * Loads library semantics.
     * Implements loadLibrarySemantics
     *
     * @param string $name Machine name for the library
     * @param int $majorversion The library's major version
     * @param int $minorversion The library's minor version
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
        global $DB;

        $library = $DB->get_record('h5p_libraries',
            array(
                'machinename' => $name,
                'majorversion' => $majorversion,
                'minorversion' => $minorversion,
            )
        );

        if ($library) {
            $library->semantics = $semantics;
            $DB->update_record('h5p_libraries', $library);
        }
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

        $fs = new \core_h5p\file_storage();
        // Delete the library from the file system.;
        $fs->delete_library(array('libraryId' => $library->id));

        // Remove library data from database.
        $DB->delete_records('h5p_library_dependencies', array('libraryid' => $library->id));
        $DB->delete_records('h5p_libraries', array('id' => $library->id));
    }

    /**
     * Load content.
     * Implements loadContent
     *
     * @param int $id Content identifier
     * @return array Associative array containing:
     *               - id: Identifier for the content
     *               - params: json content as string
     *               - embedType: csv of embed types
     *               - libraryId: Id for the main library
     *               - libraryName: The library machine name
     *               - libraryMajorVersion: The library's majorVersion
     *               - libraryMinorVersion: The library's minorVersion
     *               - libraryEmbedTypes: CSV of the main library's embed types
     *               - libraryFullscreen: 1 if fullscreen is supported. 0 otherwise.
     *               - metadata: The content's metadata.
     */
    public function loadContent($id) {
        global $DB;

        $sql = "SELECT hc.id, hc.jsoncontent, hc.embedtype, hl.id AS libraryid,
                       hl.machinename, hl.majorversion, hl.minorversion,
                       hl.fullscreen, hl.semantics
                  FROM {h5p} hc
                  JOIN {h5p_libraries} hl
                       ON hl.id = hc.mainlibraryid
                 WHERE hc.id = :h5pid";

        $sqlargs = array(
            'h5pid' => $id
        );

        $data = $DB->get_record_sql($sql, $sqlargs);

        // Return null if not found.
        if ($data === false) {
            return null;
        }

        // Some databases do not support camelCase, so we need to manually
        // map the values to the camelCase names used by the H5P core.
        $content = array(
            'id' => $data->id,
            'params' => $data->jsoncontent,
            'embedType' => $data->embedtype,
            'libraryId' => $data->libraryid,
            'libraryName' => $data->machinename,
            'libraryMajorVersion' => $data->majorversion,
            'libraryMinorVersion' => $data->minorversion,
            'libraryEmbedTypes' => '',
            'libraryFullscreen' => $data->fullscreen,
            'metadata' => ''
        );

        return $content;
    }

    /**
     * Load dependencies for the given content of the given type.
     * Implements loadContentDependencies
     *
     * @param int $id Content identifier
     * @param int $type The dependency type
     * @return array List of associative arrays containing:
     *               - libraryId: The id of the library if it is an existing library.
     *               - machineName: The library machineName
     *               - majorVersion: The library's majorVersion
     *               - minorVersion: The library's minorVersion
     *               - patchVersion: The library's patchVersion
     *               - preloadedJs(optional): comma separated string with js file paths
     *               - preloadedCss(optional): comma separated sting with css file paths
     *               - dropCss(optional): csv of machine names
     *               - dependencyType: The dependency type
     */
    public function loadContentDependencies($id, $type = null) {
        global $DB;

        $query = "SELECT hcl.id AS unidepid, hl.id AS library_id, hl.machinename AS machine_name,
                         hl.majorversion AS major_version, hl.minorversion AS minor_version,
                         hl.patchversion AS patch_version, hl.preloadedcss AS preloaded_css,
                         hl.preloadedjs AS preloaded_js, hcl.dropcss AS drop_css,
                         hcl.dependencytype as dependency_type
                    FROM {h5p_contents_libraries} hcl
                    JOIN {h5p_libraries} hl ON hcl.libraryid = hl.id
                   WHERE hcl.h5pid = :h5pid";
        $queryargs = array(
            'h5pid' => $id
        );

        if ($type !== null) {
            $query .= " AND hcl.dependencytype = :dependencytype";
            $queryargs['dependencytype'] = $type;
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
     * @return mixed Return always CONTROLLED_BY_PERMISSIONS to base on content's settings
     */
    public function getOption($name, $default = false) {
        return \H5PDisplayOptionBehaviour::CONTROLLED_BY_PERMISSIONS;
    }

    /**
     * Stores the given setting.
     * Implements setOption
     *
     * @param string $name Identifier for the setting
     * @param mixed $value Data Whatever we want to store as the setting
     */
    public function setOption($name, $value) {
        // Currently not storing settings.
    }

    /**
     * This will update selected fields on the given content.
     * Implements updateContentFields().
     *
     * @param int $id Content identifier
     * @param array $fields Content fields, e.g. filtered or slug.
     */
    public function updateContentFields($id, $fields) {
        global $DB;

        $content = new \stdClass();
        $content->id = $id;

        foreach ($fields as $name => $value) {
            $content->$name = $value;
        }

        $DB->update_record('h5p', $content);
    }

    /**
     * Will clear filtered params for all the content that uses the specified
     * libraries. This means that the content dependencies will have to be rebuilt,
     * and the parameters re-filtered.
     * Implements clearFilteredParameters().
     *
     * @param array $libraryids array of library ids
     */
    public function clearFilteredParameters($libraryids) {
        // Currently, we do not store filtered parameters.
    }

    /**
     * Get number of contents that has to get their content dependencies rebuilt
     * and parameters re-filtered.
     * Implements getNumNotFiltered().
     *
     * @return int The number of contents that has to get their content dependencies rebuilt
     *             and parameters re-filtered.
     */
    public function getNumNotFiltered() {
        // Currently, we do not store filtered parameters.
    }

    /**
     * Get number of contents using library as main library.
     * Implements getNumContent().
     *
     * @param int $libraryId The library ID
     * @param array $skip The array of h5p content ID's that should be ignored
     * @return int The number of contents using library as main library
     */
    public function getNumContent($libraryid, $skip = NULL) {
        global $DB;

        $skipquery = empty($skip) ? '' : ' AND id NOT IN (' . implode(",", $skip) .')';
        $sql = "SELECT COUNT(id) FROM {h5p} WHERE mainlibraryid = :libraryid {$skipquery}";
        $sqlparams = array(
            'libraryid' => $libraryid
        );
        $contentcount = $DB->count_records_sql($sql, $sqlparams);

        return $contentcount;
    }

    /**
     * Determines if content slug is used.
     * Implements isContentSlugAvailable
     *
     * @param string $slug The content slug
     * @return boolean Whether the content slug is used
     */
    public function isContentSlugAvailable($slug) {
        // Content slug is not being stored.
        return false;
    }

    /**
     * Generates statistics from the event log per library
     * Implements getLibraryStats
     *
     * @param string $type Type of event to generate stats for
     * @return array Number values indexed by library name and version
     */
    public function getLibraryStats($type) {
        // Event logs are not being stored.
    }

    /**
     * Aggregate the current number of H5P authors
     * Implements getNumAuthors
     *
     * @return int The current number of H5P authors
     */
    public function getNumAuthors() {
        // Currently, H5P authors are not being stored.
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
       global $DB;

       foreach ($libraries as $library) {
           $cachedasset = new \stdClass();
           $cachedasset->libraryid = $library['id'];
           $cachedasset->hash = $key;

           $DB->insert_record('h5p_libraries_cachedassets', $cachedasset);
       }
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
       global $DB;

        // Get all the keys so we can remove the files.
        $results = $DB->get_records('h5p_libraries_cachedassets', ['libraryid' => $libraryid]);

        $hashes = array_map(function($result) {
            return $result->hash;
        }, $results);

        list($sql, $params) = $DB->get_in_or_equal($hashes, SQL_PARAMS_NAMED);
        // Remove all invalid keys.
        $DB->delete_records_select('h5p_libraries_cachedassets', 'hash ' . $sql, $params);

        return $hashes;
    }

    /**
     * Get the amount of content items associated to a library
     * Implements getLibraryContentCount
     *
     * return array The number of content items associated to a library
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
                               count(id) AS count
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
     * Will trigger after the export file is created.
     * Implements afterExportCreated
     *
     * @param $content The content
     * @param $filename The file name
     */
    public function afterExportCreated($content, $filename) {
        // Not being used.
    }

    /**
     * Check if user has permissions to an action
     * Implements hasPermission
     *
     * @param  \H5PPermission $permission The action
     * @param  int $cmid context module id
     */
    public function hasPermission($permission, $cmid = null) {
        // H5P capabilities have not been introduced.
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
     * Checks if the given library has a higher version.
     * Implements libraryHasUpgrade
     *
     * @param array $library An associative array containing:
     *                       - machineName: The library machineName
     *                       - majorVersion: The library's majorVersion
     *                       - minorVersion: The library's minorVersion
     * @return boolean Whether the library has a higher version
     */
    public function libraryHasUpgrade($library) {
        global $DB;

        $sql = "SELECT id
                  FROM {h5p_libraries}
                 WHERE machinename = :machinename
                       AND (majorversion > :majorversion1
                           OR (majorversion = :majorversion2 AND minorversion > :minorversion))";

        $results = $DB->get_records_sql(
            $sql,
            array(
                'machinename' => $library['machineName'],
                'majorversion1' => $library['majorVersion'],
                'majorversion2' => $library['majorVersion'],
                'minorversion' => $library['minorVersion']
            ),
            0,
            1
        );

        return !empty($results);
    }

    /**
     * Get type of h5p instance
     *
     * @param string|null $type Type of h5p instance to get
     * @return \H5PContentValidator|\H5PCore|\H5PStorage|\H5PValidator|\core_h5p\framework|\H5peditor
     */
    public static function instance($type = null) {
        global $CFG;
        static $interface, $core;

        if (!isset($interface)) {
            $interface = new \core_h5p\framework();
            $fs = new \core_h5p\file_storage();
            //$fs = new \stdClass();
            $language = self::get_language();

            $context = \context_system::instance();
            $url = "{$CFG->wwwroot}/pluginfile.php/{$context->id}/core_h5p";

            $core = new \H5PCore($interface, $fs, $url, $language, true);
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
     * Store messages until they can be printed to the current user
     *
     * @param string $type Type of messages, e.g. 'info' or 'error'
     * @param string $newmessage The message
     * @param string $code The message code
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
     * Convert list of library parameter values to csv.
     *
     * @param array $librarydata Library data as found in library.json files
     * @param string $key Key that should be found in $librarydata
     * @param string $searchparam The library parameter (Default: 'path')
     * @return string Library parameter values separated by ', '
     */
    private function library_parameter_values_to_csv(array $librarydata, string $key, string $searchparam = 'path') : string {
        if (isset($librarydata[$key])) {
            $parametervalues = array();
            foreach ($librarydata[$key] as $key => $value) {
                if ($key === $searchparam) {
                    $parametervalues[] = $value;
                }
            }
            return implode(', ', $parametervalues);
        }
        return '';
    }
}
