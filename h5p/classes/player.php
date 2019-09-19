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

    /**
     * @var int H5P DB id.
     */
    private $h5pid;

    /**
     * @var array JavaScript requirements for this H5P.
     */
    private $jsrequires;

    /**
     * @var array CSS requirements for this H5P.
     */
    private $cssrequires;

    /**
     * @var string Type of embed object, div or iframe.
     */
    private $embedtype;

    /**
     * @var array Main H5P configuration.
     */
    private $settings;

    /**
     * Inits the H5P player for rendering the content.
     *
     * @param string $url Local URL of the H5P file to display.
     * @param object $config Configuration for H5P buttons.
     */
    public function __construct(string $url, object $config) {
        $this->url = $url;
        $this->jsrequires  = [];
        $this->cssrequires = [];
        $context = \context_system::instance();
        $this->core = \core_h5p\framework::instance();
        // Get the H5P identifier linked to this URL.
        $this->h5pid = $this->get_h5p_id($url, $config);

        $this->content = $this->core->loadContent($this->h5pid);
        $this->settings = $this->get_core_assets($context);

        // TODO: The display options for view will always return null for embed and export
        // this needs to be changed in the framework
        $displayoptions = $this->core->getDisplayOptionsForView($this->content['disable'], $this->h5pid);
        $displayoptions['embed'] = true;
        $displayoptions['export'] = true;
        $embedurl = new \moodle_url('/h5p/embed.php', ['id' => $this->h5pid]);
        $contenturl = new \moodle_url("/pluginfile.php/{$context->id}/core_h5p/content/{$this->h5pid}");

        $this->settings['contents'][ 'cid-' . $this->h5pid ] = [
            'library'         => \H5PCore::libraryToString($this->content['library']),
            'jsonContent'     => $this->get_filtered_parameters(),
            'fullScreen'      => $this->content['library']['fullscreen'],
            'exportUrl'       => $this->get_export_settings($displayoptions[ \H5PCore::DISPLAY_OPTION_DOWNLOAD ]),
            'embedCode'       => $this->get_embed_code($displayoptions[ \H5PCore::DISPLAY_OPTION_EMBED ]),
            'resizeCode'      => $this->get_resize_code(),
            'title'           => $this->content['slug'],
            'displayOptions'  => $displayoptions,
            'url'             => $embedurl->out(),
            'contentUrl'      => $contenturl->out(),
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
     * @param object $config Configuration for H5P buttons.
     * @return int H5P DB identifier.
     */
    private function get_h5p_id($url, $config) {
        global $DB;

        $fs = get_file_storage();

        $pathnamehash = $this->get_pluginfile_hash($url);
        $file = $fs->get_file_by_hash($pathnamehash);
        $contenthash = $file->get_contenthash();
        $hashes = $pathnamehash . '/' . $contenthash;

        if (!$file) {
            throw new \moodle_exception('h5pfilenotfound', 'core_h5p');
        }

        $h5p = $DB->get_record('h5p', ['pathnamehash' => $pathnamehash, 'contenthash' => $contenthash]);

        if (!$h5p) {
            // The H5P content hasn't been deployed previously. It has to be validated and stored before displaying it.
            return $this->save_h5p($file, $hashes, $config);
        } else {
            // The H5P content has been deployed previously.
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
        $path = str_replace($CFG->wwwroot, '', $url);
        if (!$CFG->slasharguments) {
            $param = explode("=", $path);
            if ($param[1]) {
                $path = $param[1];
            } else {
                throw new \moodle_exception('invalidurl', 'core_h5p');
            }
        }
        $parts = array_reverse(explode('/', $path));

        $i = 0;
        $filename = $parts[$i++];
        $filepath = '/';
        if (is_numeric($parts[$i])) {
            $itemid = $parts[$i++];
        } else {
            $itemid = 0;
            $i++;
        }
        $filearea = $parts[$i++];
        $component = $parts[$i++];
        $contextid = $parts[$i++];

        // TODO: Review how to avoid the following dirty hack for getting the correct itemid.
        // Dirty hack for the 'mod_page' because, although the itemid = 0 in DB, there is a /1/ in the URL.
        if ($component == 'mod_page' || $component == 'mod_resource') {
            $itemid = 0;
        }

        $fs = get_file_storage();
        return $fs->get_pathname_hash($contextid, $component, $filearea, $itemid, $filepath, $filename);
    }

    /**
     * Store a H5P file
     *
     * @param Object $file Moodle file instance
     * @param string $hash pathnamehash.
     * @param Object $config Button options config.
     *
     * @return int|false The H5P identifier or false if it's not a valid H5P package.
     */
    private function save_h5p($file, $hashes, $config) {
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

            $disableoptions = [
                \H5PCore::DISPLAY_OPTION_FRAME     => isset($config->frame) ? $config->frame : 0,
                \H5PCore::DISPLAY_OPTION_DOWNLOAD  => isset($config->export) ? $config->export : 0,
                \H5PCore::DISPLAY_OPTION_EMBED     => isset($config->embed) ? $config->embed : 0,
                \H5PCore::DISPLAY_OPTION_COPYRIGHT => isset($config->copyright) ? $config->copyright : 0,
            ];

            $options = ['disable' => $this->core->getStorableDisplayOptions($disableoptions, 0)];

            $h5pstorage->savePackage(null, $hashes, false, $options);
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

        if ( ! $downloadenabled) {
            return '';
        }

        $context = \context_system::instance();
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

    /**
     * Get a query string with the theme revision number to include at the end
     * of URLs. This is used to force the browser to reload the asset when the
     * theme caches are cleared.
     *
     * @return string
     */
    private function get_cache_buster() {
        global $CFG;
        return '?ver=' . $CFG->themerev;
    }

    /**
     * Get the core H5p assets, including all core H5P JavaScript and CSS.
     *
     * @return Array core H5P assets.
     */
    private function get_core_assets() {
        global $CFG, $PAGE;
        // Get core settings.
        $settings = $this->get_core_settings();
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
            $this->cssrequires[] = new \moodle_url($liburl . $style . $cachebuster);
        }
        // Add core JavaScript.
        foreach (\H5PCore::$scripts as $script) {
            $settings['core']['scripts'][] = $relpath . $script . $cachebuster;
            $this->jsrequires[] = new \moodle_url($liburl . $script . $cachebuster);
        }

        return $settings;
    }

    private function get_core_settings() {
        global $USER, $CFG;

        $basepath = $CFG->wwwroot . '/';
        $systemcontext = \context_system::instance();
        // Check permissions and generate ajax paths.
        $ajaxpaths = [];
        $ajaxpaths['xAPIResult'] = '';
        $ajaxpaths['contentUserData'] = '';

        $settings = array(
            'baseUrl' => $basepath,
            'url' => "{$basepath}pluginfile.php/{$systemcontext->instanceid}/core_h5p",
            'urlLibraries' => "{$basepath}pluginfile.php/{$systemcontext->id}/core_h5p/libraries",
            'postUserStatistics' => false,
            'ajax' => $ajaxpaths,
            'saveFreq' => false,
            'siteUrl' => $CFG->wwwroot,
            'l10n' => array('H5P' => $this->core->getLocalization()),
            'user' => [],
            'hubIsEnabled' => false,
            'reportingIsEnabled' => false,
            'crossorigin' => null,
            'libraryConfig' => null,
            'pluginCacheBuster' => $this->get_cache_buster(),
            'libraryUrl' => ''
        );

        return $settings;
    }

    /**
     * Generate the assets arrays for the H5P settings object and the JavaScript and CSS requirements.
     *
     */
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

    /**
     * Filtered and potentially altered parameters
     *
     * @return Object|string
     */
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
        global $CFG, $OUTPUT;

        $template = new \stdClass();
        $template->resizeurl = new \moodle_url('/lib/h5p/js/h5p-resizer.js');
        return $OUTPUT->render_from_template('core_h5p/h5presize', $template);
    }

    /**
     * Embed code for settings
     *
     * @param $embedenabled
     *
     * @return string
     */
    private function get_embed_code($embedenabled) {
        global $CFG, $OUTPUT;

        if ( ! $embedenabled) {
            return '';
        }

        $template = new \stdClass();
        $template->embedurl = new \moodle_url("/h5p/embed.php", ["url" => $this->url]);
        return $OUTPUT->render_from_template('core_h5p/h5pembed', $template);
    }

    /**
     * Create the H5PIntegration variable that will be included in the page. This variable is used as the
     * main H5P config variable.
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
     * Outputs H5P wrapper HTML.
     */
    public function output() {
        global $OUTPUT;

        $template = new \stdClass();
        $template->h5pid = $this->h5pid;
        if ($this->embedtype === 'div') {
            return $OUTPUT->render_from_template('core_h5p/h5pdiv', $template);
        } else {
            return $OUTPUT->render_from_template('core_h5p/h5piframe', $template);
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
