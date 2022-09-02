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

namespace mod_data\importer;

use mod_data\manager;
use mod_data\preset;
use stdClass;

/**
 * Abstract class used for data preset importers
 *
 * @package    mod_data
 * @copyright  2022 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class preset_importer {

    /** @var manager manager instance. */
    private $manager;

    /**
     * Constructor
     *
     * @param manager $manager
     * @param string $directory
     */
    public function __construct(manager $manager, string $directory) {
        $this->manager = $manager;
        $this->directory = $directory;
    }

    /**
     * Returns the name of the directory the preset is located in
     *
     * @return string
     */
    public function get_directory(): string {
        return basename($this->directory);
    }

    /**
     * Retreive the contents of a file. That file may either be in a conventional directory of the Moodle file storage
     *
     * @param \file_storage|null $filestorage . Should be null if using a conventional directory
     * @param \stored_file|null $fileobj the directory to look in. null if using a conventional directory
     * @param string|null $dir the directory to look in. null if using the Moodle file storage
     * @param string $filename the name of the file we want
     * @return string|null the contents of the file or null if the file doesn't exist.
     */
    public function data_preset_get_file_contents(
        ?\file_storage &$filestorage,
        ?\stored_file &$fileobj,
        ?string $dir,
        string $filename
    ): ?string {
        if (empty($filestorage) || empty($fileobj)) {
            if (substr($dir, -1) != '/') {
                $dir .= '/';
            }
            if (file_exists($dir.$filename)) {
                return file_get_contents($dir.$filename);
            } else {
                return null;
            }
        } else {
            if ($filestorage->file_exists(
                DATA_PRESET_CONTEXT,
                DATA_PRESET_COMPONENT,
                DATA_PRESET_FILEAREA,
                0,
                $fileobj->get_filepath(),
                $filename)
            ) {
                $file = $filestorage->get_file(
                    DATA_PRESET_CONTEXT,
                    DATA_PRESET_COMPONENT,
                    DATA_PRESET_FILEAREA,
                    0,
                    $fileobj->get_filepath(),
                    $filename
                );
                return $file->get_content();
            } else {
                return null;
            }
        }
    }

    /**
     * Gets the preset settings
     *
     * @return stdClass
     */
    public function get_preset_settings(): stdClass {
        global $DB, $CFG;
        require_once($CFG->libdir.'/xmlize.php');

        $fs = $fileobj = null;
        if (!preset::is_directory_a_preset($this->directory)) {
            // Maybe the user requested a preset stored in the Moodle file storage.

            $fs = get_file_storage();
            $files = $fs->get_area_files(DATA_PRESET_CONTEXT, DATA_PRESET_COMPONENT, DATA_PRESET_FILEAREA);

            // Preset name to find will be the final element of the directory.
            $explodeddirectory = explode('/', $this->directory);
            $presettofind = end($explodeddirectory);

            // Now go through the available files available and see if we can find it.
            foreach ($files as $file) {
                if (($file->is_directory() && $file->get_filepath() == '/') || !$file->is_directory()) {
                    continue;
                }
                $presetname = trim($file->get_filepath(), '/');
                if ($presetname == $presettofind) {
                    $this->directory = $presetname;
                    $fileobj = $file;
                }
            }

            if (empty($fileobj)) {
                throw new \moodle_exception('invalidpreset', 'data', '', $this->directory);
            }
        }

        $allowedsettings = [
            'intro',
            'comments',
            'requiredentries',
            'requiredentriestoview',
            'maxentries',
            'rssarticles',
            'approval',
            'defaultsortdir',
            'defaultsort'];

        $module = $this->manager->get_instance();
        $result = new stdClass;
        $result->settings = new stdClass;
        $result->importfields = [];
        $result->currentfields = $DB->get_records('data_fields', ['dataid' => $module->id]);
        if (!$result->currentfields) {
            $result->currentfields = [];
        }

        // Grab XML.
        $presetxml = $this->data_preset_get_file_contents($fs, $fileobj, $this->directory, 'preset.xml');
        $parsedxml = xmlize($presetxml, 0);

        // First, do settings. Put in user friendly array.
        $settingsarray = $parsedxml['preset']['#']['settings'][0]['#'];
        $result->settings = new StdClass();
        foreach ($settingsarray as $setting => $value) {
            if (!is_array($value) || !in_array($setting, $allowedsettings)) {
                // Unsupported setting.
                continue;
            }
            $result->settings->$setting = $value[0]['#'];
        }

        // Now work out fields to user friendly array.
        $fieldsarray = $parsedxml['preset']['#']['field'];
        foreach ($fieldsarray as $field) {
            if (!is_array($field)) {
                continue;
            }
            $f = new StdClass();
            foreach ($field['#'] as $param => $value) {
                if (!is_array($value)) {
                    continue;
                }
                $f->$param = $value[0]['#'];
            }
            $f->dataid = $module->id;
            $f->type = clean_param($f->type, PARAM_ALPHA);
            $result->importfields[] = $f;
        }
        // Now add the HTML templates to the settings array so we can update d.
        $result->settings->singletemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "singletemplate.html"
        );
        $result->settings->listtemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "listtemplate.html"
        );
        $result->settings->listtemplateheader = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "listtemplateheader.html"
        );
        $result->settings->listtemplatefooter = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "listtemplatefooter.html"
        );
        $result->settings->addtemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "addtemplate.html"
        );
        $result->settings->rsstemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "rsstemplate.html"
        );
        $result->settings->rsstitletemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "rsstitletemplate.html"
        );
        $result->settings->csstemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "csstemplate.css"
        );
        $result->settings->jstemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "jstemplate.js"
        );
        $result->settings->asearchtemplate = $this->data_preset_get_file_contents(
            $fs,
            $fileobj,
            $this->directory,
            "asearchtemplate.html"
            );

        $result->settings->instance = $module->id;
        return $result;
    }

    /**
     * Import the preset into the given database module
     *
     * @param bool $overwritesettings Whether to overwrite activity settings or not.
     * @return bool
     */
    public function import($overwritesettings) {
        global $DB;

        $params = $this->get_preset_settings();
        $settings = $params->settings;
        $newfields = $params->importfields;
        $currentfields = $params->currentfields;
        $preservedfields = [];
        $module = $this->manager->get_instance();

        // Maps fields and makes new ones.
        if (!empty($newfields)) {
            // We require an injective mapping, and need to know what to protect.
            foreach ($newfields as $nid => $newfield) {
                $cid = optional_param("field_$nid", -1, PARAM_INT);
                if ($cid == -1) {
                    continue;
                }
                if (array_key_exists($cid, $preservedfields)) {
                    throw new \moodle_exception('notinjectivemap', 'data');
                } else {
                    $preservedfields[$cid] = true;
                }
            }

            foreach ($newfields as $nid => $newfield) {
                $cid = optional_param("field_$nid", -1, PARAM_INT);

                /* A mapping. Just need to change field params. Data kept. */
                if ($cid != -1 && isset($currentfields[$cid])) {
                    $fieldobject = data_get_field_from_id($currentfields[$cid]->id, $module);
                    foreach ($newfield as $param => $value) {
                        if ($param != "id") {
                            $fieldobject->field->$param = $value;
                        }
                    }
                    unset($fieldobject->field->similarfield);
                    $fieldobject->update_field();
                    unset($fieldobject);
                } else {
                    /* Make a new field */
                    include_once("field/$newfield->type/field.class.php");

                    if (!isset($newfield->description)) {
                        $newfield->description = '';
                    }
                    $classname = 'data_field_'.$newfield->type;
                    $fieldclass = new $classname($newfield, $module);
                    $fieldclass->insert_field();
                    unset($fieldclass);
                }
            }
        }

        // Get rid of all old unused data.
        if (!empty($preservedfields)) {
            foreach ($currentfields as $cid => $currentfield) {
                if (!array_key_exists($cid, $preservedfields)) {
                    // Data not used anymore so wipe!
                    print "Deleting field $currentfield->name<br />";

                    $id = $currentfield->id;
                    // Why delete existing data records and related comments/ratings??
                    $DB->delete_records('data_content', ['fieldid' => $id]);
                    $DB->delete_records('data_fields', ['id' => $id]);
                }
            }
        }

        // Handle special settings here.
        if (!empty($settings->defaultsort)) {
            if (is_numeric($settings->defaultsort)) {
                // Old broken value.
                $settings->defaultsort = 0;
            } else {
                $settings->defaultsort = (int)$DB->get_field(
                    'data_fields',
                    'id',
                    ['dataid' => $module->id, 'name' => $settings->defaultsort]
                );
            }
        } else {
            $settings->defaultsort = 0;
        }

        // Do we want to overwrite all current database settings?
        if ($overwritesettings) {
            // All supported settings.
            $overwrite = array_keys((array)$settings);
        } else {
            // Only templates and sorting.
            $overwrite = ['singletemplate', 'listtemplate', 'listtemplateheader', 'listtemplatefooter',
                'addtemplate', 'rsstemplate', 'rsstitletemplate', 'csstemplate', 'jstemplate',
                'asearchtemplate', 'defaultsortdir', 'defaultsort'];
        }

        // Now overwrite current data settings.
        foreach ($module as $prop => $unused) {
            if (in_array($prop, $overwrite)) {
                $module->$prop = $settings->$prop;
            }
        }

        data_update_instance($module);

        return $this->cleanup();
    }

    /**
     * Any clean up routines should go here
     *
     * @return bool
     */
    public function cleanup() {
        return true;
    }

    /**
     * Check if the importing process needs fields mapping.
     *
     * @return bool True if the current database needs to map the fields imported.
     */
    public function needs_mapping() {
        global $DB;

        // Return false for empty database activity with no fields.
        $module = $this->manager->get_instance();
        return $DB->record_exists('data_fields', ['dataid' => $module->id]);
    }
}
