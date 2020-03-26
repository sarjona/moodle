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
 * H5P editor class.
 *
 * @package    core_h5p
 * @copyright  2020 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

use core_h5p\local\library\autoloader;
use H5PCore;
use H5peditor;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * H5P editor class, for editing local H5P content.
 *
 * @package    core_h5p
 * @copyright  2020 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor {

    /** @var string The H5P Editor form id. */
    public const FORMID   = 'h5peditor-form';

    /**
     * @var core The H5PCore object.
     */
    private $core;

    /**
     * @var H5peditor $h5peditor The H5P Editor object.
     */
    private $h5peditor;

    /**
     * Inits the H5P editor.
     *
     */
    public function __construct() {
        autoloader::register();

        $factory = new factory();
        $this->h5peditor = $factory->get_editor();
        $this->core = $factory->get_core();
    }

    /**
     * Creates or updates an H5P content.
     *
     * @param stdClass $content Object containing all the necessary data.
     *
     * @return int Content id
     */
    public function save_content(stdClass $content): int {

        if (!empty($content->id)) {
            // Load existing content to get old parameters for comparison.
            $oldcontent = $this->core->loadContent($content->id);
            $oldlib = $oldcontent['library'];
            $oldparams = json_decode($oldcontent['params']);
            // Keep the existing display options.
            $content->disable = $oldcontent['disable'];
        }

        $pathnamehash = $oldcontent['pathnamehash'] ?? null;

        // Make params and library available for core to save.
        $content->library = H5PCore::libraryFromString($content->h5plibrary);
        $content->library['libraryId'] = $this->core->h5pF->getLibraryId($content->library['machineName'],
            $content->library['majorVersion'],
            $content->library['minorVersion']);

        $content->id = $this->core->saveContent((array)$content);

        // Prepare current parameters.
        $params = json_decode($content->params);

        // Move any uploaded images or files. Determine content dependencies.
        $this->h5peditor->processParameters($content, $content->library, $params->params, $oldlib ?? null, $oldparams ?? null);

        $this->update_h5p_file($content, $pathnamehash);

        return $content->id;
    }

    /**
     * Creates or updates the H5P file and the related database data.
     *
     * @param stdClass $content
     * @param string|null $pathnamehash
     *
     * @return void
     */
    private function update_h5p_file(stdClass $content, ?string $pathnamehash = null): void {
        global $USER;

        // Keep title before filtering params.
        $title = $content->title;
        $contentarray = $this->core->loadContent($content->id);
        $contentarray['title'] = $title;

        // Generates filtered params and export file.
        $this->core->filterParameters($contentarray);

        $slug = isset($contentarray['slug']) ? $contentarray['slug'] . '-' : '';
        $file = $this->core->fs->get_export_file($slug . $contentarray['id'] . '.h5p');
        $fs = get_file_storage();

        if ($file) {
            $fields['contenthash'] = $file->get_contenthash();

            // Updating content. Rewrite the old H5P file.
            if ($pathnamehash !== null) {
                $oldfile = $fs->get_file_by_hash($pathnamehash);
                if ($oldfile) {
                    $record = [
                        'contextid' => $oldfile->get_contextid(),
                        'component' => $oldfile->get_component(),
                        'filearea' => $oldfile->get_filearea(),
                        'itemid' => $oldfile->get_itemid(),
                        'filepath' => $oldfile->get_filepath(),
                        'filename' => $oldfile->get_filename(),
                        'userid' => $USER->id
                    ];
                    $oldfile->delete();
                    $fs->create_file_from_storedfile($record, $file);
                }
            } else { // New content.
                $record = [
                    'contextid' => \context_user::instance($USER->id)->id,
                    'component' => 'user',
                    'filearea' => 'private',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $contentarray['slug'] . '.h5p',
                    'userid' => $USER->id,
                ];
                $newfile = $fs->create_file_from_storedfile($record, $file);
                $pathnamehash = $newfile->get_pathnamehash();
            }

            // Update hash fields in the h5p table.
            $fields['pathnamehash'] = $pathnamehash;
            $this->core->h5pF->updateContentFields($contentarray['id'], $fields);
        }
    }

    /**
     * Add required assets for displaying the editor.
     *
     * @param int $id Id of the content being edited. null for creating new content.
     * @param string $mformid Id of the Moodle form where the editor is loaded.
     *
     * @return void
     */
    private function add_editor_assets_to_page(?int $id = null, string $mformid = null): void {
        global $PAGE, $CFG;

        $context = \context_system::instance();

        $settings = helper::get_core_assets();

        // Use jQuery and styles from core.
        $assets = array(
            'css' => $settings['core']['styles'],
            'js' => $settings['core']['scripts']
        );

        // Use relative URL to support both http and https.
        $url = autoloader::get_h5p_editor_library_url()->out();
        $url = '/' . preg_replace('/^[^:]+:\/\/[^\/]+\//', '', $url);

        // Make sure files are reloaded for each plugin update.
        $cachebuster = helper::get_cache_buster();

        // Add editor styles.
        foreach (H5peditor::$styles as $style) {
            $assets['css'][] = $url . $style . $cachebuster;
        }

        // Add editor JavaScript.
        foreach (H5peditor::$scripts as $script) {
            // We do not want the creator of the iframe inside the iframe.
            if ($script !== 'scripts/h5peditor-editor.js') {
                $assets['js'][] = $url . $script . $cachebuster;
            }
        }

        // Add JavaScript with library framework integration (editor part).
        $PAGE->requires->js(autoloader::get_h5p_editor_library_url('scripts/h5peditor-editor.js' . $cachebuster), true);
        $PAGE->requires->js(autoloader::get_h5p_editor_library_url('scripts/h5peditor-init.js' . $cachebuster), true);

        // Add translations.
        $language = framework::get_language();
        $languagescript = "language/{$language}.js";

        if (!file_exists("{$CFG->dirroot}" . autoloader::get_h5p_editor_library_base($languagescript))) {
            $languagescript = 'language/en.js';
        }

        // Check if the editor strings have been translated in Moodle.
        // If the strings exist, they will override the existing ones in the JS file.
        require_once("$CFG->libdir/yii/framework/web/helpers/CJSON.php");

        // Get existing strings from current JS language file.
        $langcontent = file_get_contents(autoloader::get_h5p_editor_library_url($languagescript . $cachebuster));

        // Get only the content between { } (for instance, ; at the end of the file has to be removed).
        $langcontent = substr($langcontent, 0, strpos($langcontent, '}', -0) + 1);
        $langcontent = substr($langcontent, strpos($langcontent, '{'));

        // Parse the JS language content and get a PHP array.
        $json = new \CJSON();
        $editorstrings = $json->decode($langcontent);
        foreach ($editorstrings as $key => $value) {
            $stringkey = 'editor:'.strtolower(trim($key));
            $value = helper::get_h5p_string($stringkey, $language);
            if (!empty($value)) {
                $editorstrings[$key] = $value;
            }
        }
        // Load JS language strings.
        $PAGE->requires->data_for_js('H5PEditor.language.core', $editorstrings, false);

        // Add JavaScript settings.
        $root = $CFG->wwwroot;
        $filespathbase = "{$root}/pluginfile.php/{$context->id}/core_h5p/";

        $factory = new factory();
        $contentvalidator = $factory->get_content_validator();

        $editorajaxtoken = H5PCore::createToken(editor_ajax::EDITOR_AJAX_TOKEN);
        $settings['editor'] = array(
            'filesPath' => $filespathbase . 'editor',
            'fileIcon' => array(
                'path' => $url . 'images/binary-file.png',
                'width' => 50,
                'height' => 50,
            ),
            'ajaxPath' => $CFG->wwwroot . '/h5p/' . "ajax.php?contextId={$context->id}&token={$editorajaxtoken}&action=",
            'libraryUrl' => $url,
            'copyrightSemantics' => $contentvalidator->getCopyrightSemantics(),
            'metadataSemantics' => $contentvalidator->getMetadataSemantics(),
            'assets' => $assets,
            'apiVersion' => H5PCore::$coreApi,
            'language' => $language,
            'formId' => $mformid,
        );

        if ($id !== null) {
            $settings['editor']['nodeVersionId'] = $id;

            // Override content URL.
            $contenturl = "{$root}/pluginfile.php/{$context->id}/core_h5p/content/{$id}";
            $settings['contents']['cid-' . $id]['contentUrl'] = $contenturl;
        }

        $PAGE->requires->data_for_js('H5PIntegration', $settings, true);
    }

    /**
     * Preprocess the data sent through the form to the H5P JS Editor Library.
     *
     * @param array $defaultvalues Default values for the editor
     *
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        // If there is a content id, it's an update: load the content data.
        $content = null;
        if (!empty($defaultvalues['id'])) {
            // Load content.
            $content = $this->core->loadContent($defaultvalues['id']);
            if ($content === null) {
                print_error('invalidcontentid');
            }
        }

        // In case both contentid and library have values, content(edition) takes precedence over library(creation).
        if ($content) {
            $defaultvalues['h5plibrary'] = H5PCore::libraryToString($content['library']);
        }

        // Combine params and metadata in one JSON object.
        // H5P JS Editor library expects a JSON object with the parameters and the metadata.
        $params = ($content === null ? '{}' : $this->core->filterParameters($content));

        $maincontentdata = array('params' => json_decode($params));
        if (isset($content['metadata'])) {
            $maincontentdata['metadata'] = $content['metadata'];
        }

        $defaultvalues['h5pparams'] = json_encode($maincontentdata, true);

        // Add to page required editor assets.
        $mformid = self::FORMID;
        $contentid = ($content === null) ? null : $defaultvalues['id'];
        $this->add_editor_assets_to_page($contentid, $mformid);
    }
}
