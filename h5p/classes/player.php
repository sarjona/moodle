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
 * H5P player class.
 *
 * @package    core
 * @subpackage h5p
 * @copyright  2019 Moodle
 * @author     Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

class player {

    /**
     * @var string The local H5P URL containing the .h5p file to display.
     */
    private $url;

    /**
     * @var \H5PCore The H5PCore object.
     */
    private $core;

    private $h5pid;

    private $jsrequires;

    private $cssrequires;

    /**
     * @var string
     */
    private $embedtype;

    /**
     * @var array
     */
    private $settings;

    /**
     * Inits the H5P player for rendering the content.
     *
     * @param string $url Local URL of the H5P file to display.
     */
    public function __construct(string $url) {
        global $CFG;

        $this->url = $url;
        $this->jsrequires  = [];
        $this->cssrequires = [];
        $context = \context_system::instance();
        $this->core = \core_h5p\framework::instance();
        // Get the H5P identifier linked to this URL.
        $this->h5pid = $this->get_h5p_id($url);

        $this->content = $this->core->loadContent($this->h5pid);
        $this->settings = $this->get_core_assets($context);
        $displayoptions = $this->core->getDisplayOptionsForView(0, $this->h5pid);
        // TODO: Remove this hack (it has been added to display the export and embed buttons).
        $displayoptions['export'] = true;
        $displayoptions['embed'] = true;
        $displayoptions['copy'] = true;
        // END
        $this->settings['contents'][ 'cid-' . $this->h5pid ] = [
            'library'         => \H5PCore::libraryToString($this->content['library']),
            'jsonContent'     => $this->get_filtered_parameters(),
            'fullScreen'      => $this->content['library']['fullscreen'],
            'exportUrl'       => $this->get_export_settings($displayoptions[ \H5PCore::DISPLAY_OPTION_DOWNLOAD ]),
            'embedCode'       => "No Embed Code",
            'resizeCode'      => $this->get_resize_code(),
            'title'           => $this->content['slug'],
            'displayOptions'  => $displayoptions,
            'url'             => "{$CFG->wwwroot}/h5p/embed.php?id={$this->h5pid}",
            'contentUrl'      => "{$CFG->wwwroot}/pluginfile.php/{$context->id}/core_h5p/content/{$this->h5pid}",
            'metadata'        => '',
            'contentUserData' => array()
        ];

        // TODO: Use determineEmbedType to update the embed type.
        $this->embedtype = 'iframe';

        $this->files = $this->get_dependency_files();
        $this->generate_assets();
    }

    /**
     * Get the H5P DB instance id for a H5P pluginfile URL.
     *
     * @param string $url H5P pluginfile URL.
     * @return int H5P DB identifier.
     */
    private function get_h5p_id($url) {
        global $DB;

        $hash = $this->get_pluginfile_hash($url);
        // TODO: Check what happens if there is no hash.

        $h5p = $DB->get_record('h5p', ['pathnamehash' => $hash]);
        if (!$h5p) {
            // The H5P content hasn't been deployed previously. It has to be validated and stored before displaying it.
            $fs = get_file_storage();
            $file = $fs->get_file_by_hash($hash);
            if (!$file) {
                // TODO: Throw an exception or move the string to the lang.
                return "File not found";
            } else {
                return $this->save_h5p($file, $hash);
            }
        } else {
            // The H5P content has been deployed previously.

            // TODO: Check if the file has been updated after being deployed and redeploy it if needed.

            return $h5p->id;
        }
    }

    /**
     * Get the pluginfile hash for an H5P internal URL.
     *
     * @param  string $url H5P pluginfile URL
     * @return string hash for pluginfile
     */
    private function get_pluginfile_hash($url) {
        global $CFG;

        // TODO: Validate this method with all the places where the Atto editor can be used.
        // TODO: Take into account $CFG->slasharguments.

        $path = str_replace($CFG->wwwroot, '', $url);
        $parts = array_reverse(explode('/', $path));

        $i = 0;
        $filename = $parts[$i++];
        $filepath = '/';
        if (is_numeric($parts[$i])) {
            $itemid = $parts[$i++];
        } else {
            $itemid = 0;
        }
        $filearea = $parts[$i++];
        $component = $parts[$i++];
        $contextid = $parts[$i++];

        // TODO: Review how to avoid the following dirty hack for getting the correct itemid.
        // Dirty hack for the 'mod_page' because, although the itemid = 0 in DB, there is a /1/ in the URL.
        if ($component == 'mod_page') {
            $itemid = 0;
        }

        $fs = get_file_storage();
        return $fs->get_pathname_hash($contextid, $component, $filearea, $itemid, $filepath, $filename);
    }

    /**
     * Store a H5P file
     *
     * @param Object $file Moodle file instance
     * @param string $hash
     *
     * @return int|false The H5P identifier or false if it's not a valid H5P package.
     */
    private function save_h5p($file, $hash) {
        global $CFG;

        $path = $this->core->fs->getTmpPath();
        $this->core->h5pF->getUploadedH5pFolderPath($path);
        // Add manually the extension to the file to avoid the validation fails.
        $path .= '.h5p';
        $this->core->h5pF->getUploadedH5pPath($path);

        // Copy the .h5p file to the temporary folder.
        $file->copy_content_to($path);

        // Check if the h5p file is valid before saving it.
        $h5pvalidator = \core_h5p\framework::instance('validator');
        if ($h5pvalidator->isValidPackage(false, false)) {
            $h5pstorage = \core_h5p\framework::instance('storage');
            $h5pstorage->savePackage(null, $hash, false);
            return $h5pstorage->contentId;
        } else {
            $messages = $this->core->h5pF->getMessages('error');
            $errors = array_map(function($error) {
                return $error->message;
            }, $messages);
            throw new \Exception(implode(',', $errors));
        }

        return false;
    }

    /**
     * Export path for settings
     *
     * @param $downloadenabled
     *
     * @return string
     */
    private function get_export_settings($downloadenabled) {
        global $CFG;

        if ( ! $downloadenabled) {
            return '';
        }

        $context        = \context_system::instance();
        //TODO: Get the expected context (not the system one).
        //$modulecontext = \context_module::instance($this->cm->id);
        $slug = $this->content['slug'] ? $this->content['slug'] . '-' : '';
        $url  = \moodle_url::make_pluginfile_url(
            $context->id,
            \core_h5p\file_storage::COMPONENT,
            \core_h5p\file_storage::EXPORT_FILEAREA,
            '',
            '',
            "{$slug}{$this->content['id']}.h5p"
        );

        return $url->out();
    }

    private function get_cache_buster() {
        return '?ver=' . 1;
    }

    private function get_core_assets($context) {
        global $CFG, $PAGE;
        // Get core settings.
        $settings = $this->get_core_settings($context);
        $settings['core'] = [
          'styles' => [],
          'scripts' => []
        ];
        $settings['loadedJs'] = [];
        $settings['loadedCss'] = [];

        // Make sure files are reloaded for each plugin update.
        $cachebuster = $this->get_cache_buster();

        // Use relative URL to support both http and https.
        $liburl = $CFG->wwwroot . '/lib/h5p/';
        $relpath = '/' . preg_replace('/^[^:]+:\/\/[^\/]+\//', '', $liburl);

        // Add core stylesheets.
        foreach (\H5PCore::$styles as $style) {
            $settings['core']['styles'][] = $relpath . $style . $cachebuster;
            //$this->cssrequires[] = new moodle_url($liburl . $style . $cachebuster);
        }
        // Add core JavaScript.
        foreach (\H5PCore::$scripts as $script) {
            $settings['core']['scripts'][] = $relpath . $script . $cachebuster;
            $this->jsrequires[] = new \moodle_url($liburl . $script . $cachebuster);
        }

        return $settings;
    }

    private function get_core_settings($context) {
        global $USER, $CFG;

        $basepath = $CFG->wwwroot . '/';
        $systemcontext = \context_system::instance();
        // Check permissions and generate ajax paths.
        $ajaxpaths = [];
        $ajaxpaths['setFinished'] = '';
        $ajaxpaths['xAPIResult'] = '';
        $ajaxpaths['contentUserData'] = '';

        $settings = array(
            'baseUrl' => $basepath,
            'url' => "{",
            'urlLibraries' => "{$basepath}pluginfile.php/{$systemcontext->id}/core_h5p/libraries",
            'postUserStatistics' => true,
            'ajax' => $ajaxpaths,
            'saveFreq' => false,
            'siteUrl' => $CFG->wwwroot,
            'l10n' => array('H5P' => $this->core->getLocalization()),
            'user' => [],
            'hubIsEnabled' => false,
            'reportingIsEnabled' => true,
            'crossorigin' => null,
            'libraryConfig' => '',
            'pluginCacheBuster' => $this->get_cache_buster(),
            'libraryUrl' => ''
        );

        return $settings;
    }

    private function generate_assets() {
        global $CFG;

        if ($this->embedtype === 'div') {
            $context = \context_system::instance();
            $h5ppath = "/pluginfile.php/{$context->id}/core_h5p";

            // Schedule JavaScripts for loading through Moodle.
            foreach ($this->files['scripts'] as $script) {
                $url = $script->path . $script->version;

                // Add URL prefix if not external.
                $isexternal = strpos($script->path, '://');
                if ($isexternal === false) {
                    $url = $h5ppath . $url;
                }
                $this->settings['loadedJs'][] = $url;
                $this->jsrequires[] = new \moodle_url($isexternal ? $url : $CFG->wwwroot . $url);
            }

            // Schedule stylesheets for loading through Moodle.
            foreach ($this->files['styles'] as $style) {
                $url = $style->path . $style->version;

                // Add URL prefix if not external.
                $isexternal = strpos($style->path, '://');
                if ($isexternal === false) {
                    $url = $h5ppath . $url;
                }
                $this->settings['loadedCss'][] = $url;
                $this->cssrequires[] = new \moodle_url($isexternal ? $url : $CFG->wwwroot . $url);
            }

        } else {
            // JavaScripts and stylesheets will be loaded through h5p.js.
            $cid = 'cid-' . $this->h5pid;
            $this->settings['contents'][ $cid ]['scripts'] = $this->core->getAssetsUrls($this->files['scripts']);
            $this->settings['contents'][ $cid ]['styles']  = $this->core->getAssetsUrls($this->files['styles']);
        }
    }

    /**
     * Finds library dependencies of view
     *
     * @return array Files that the view has dependencies to
     */
    private function get_dependency_files() {
        global $PAGE;

        $preloadeddeps = $this->core->loadContentDependencies($this->h5pid, 'preloaded');
        $files         = $this->core->getDependenciesFiles($preloadeddeps);

        return $files;
    }

    private function get_filtered_parameters() {
        global $PAGE;

        $safeparameters = $this->core->filterParameters($this->content);

        $decodedparams  = json_decode($safeparameters);
        $safeparameters = json_encode($decodedparams);

        return $safeparameters;
    }

    /**
     * Resizing script for settings
     *
     * @param $embedenabled
     *
     * @return string
     */
    private function get_resize_code() {
        global $CFG;

        $resizeurl = new \moodle_url($CFG->wwwroot . '/lib/h5p/js/h5p-resizer.js');

        return "<script src=\"{$resizeurl->out()}\" charset=\"UTF-8\"></script>";
    }

    /**
     * Adds js assets to the current page.
     */
    public function add_assets_to_page() {
        global $PAGE, $CFG;

        foreach ($this->jsrequires as $script) {
            $PAGE->requires->js($script, true);
        }

        foreach ($this->cssrequires as $css) {
            $PAGE->requires->css($css);
        }

        // Print JavaScript settings to page.
        $PAGE->requires->data_for_js('H5PIntegration', $this->settings, true);
    }

    /**
     * Outputs an H5P content.
     */
    public function output() {
        if ($this->embedtype === 'div') {
            echo "<div class=\"h5p-content\" data-content-id=\"{$this->h5pid}\"></div>";
        } else {
            echo "<div class=\"h5p-iframe-wrapper\">" .
                 "<iframe id=\"h5p-iframe-{$this->h5pid}\"" .
                 " class=\"h5p-iframe\"" .
                 " data-content-id=\"{$this->h5pid}\"" .
                 " style=\"height:1px; min-width: 100%\"" .
                 " src=\"about:blank\"" .
                 " frameBorder=\"0\"" .
                 " scrolling=\"no\">" .
                 "</iframe>" .
                 "</div>";
        }
    }

    /**
     * FOR DEBUGGING PURPOSES ONLY
     *
     * TODO: Remove before sending to PR.
     *
     * Delete all H5P related DB records and files.
     */
    public static function clean_db() {
        global $DB;

        $DB->delete_records('h5p');
        $DB->delete_records('h5p_contents_libraries');
        $DB->delete_records('h5p_libraries');
        $DB->delete_records('h5p_library_dependencies');
        $fs = get_file_storage();
        $h5pfilerecords = $DB->get_records('files', ['component' => 'core_h5p']);
        foreach ($h5pfilerecords as $h5pfilerecord) {
            $file = $fs->get_file_by_hash($h5pfilerecord->pathnamehash);
            $file->delete();
        }
        $DB->delete_records('files', ['component' => 'core_h5p']);
    }
}
