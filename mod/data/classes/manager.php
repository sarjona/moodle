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

namespace mod_data;

use cm_info;
use context_module;
use core_component;
use stdClass;

/**
 * Class manager for database activity
 *
 * @package    mod_data
 * @copyright  2022 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** Module name. */
    const MODULE = 'data';

    /** Pluginname name. */
    const PLUGIN = 'mod_data';

    /** Template list with their files required to save the information of a preset. */
    const TEMPLATES_LIST = [
        'listtemplate' => 'listtemplate.html',
        'singletemplate' => 'singletemplate.html',
        'asearchtemplate' => 'asearchtemplate.html',
        'addtemplate' => 'addtemplate.html',
        'rsstemplate' => 'rsstemplate.html',
        'csstemplate' => 'csstemplate.css',
        'jstemplate' => 'jstemplate.js',
        'listtemplateheader' => 'listtemplateheader.html',
        'listtemplatefooter' => 'listtemplatefooter.html',
        'rsstitletemplate' => 'rsstitletemplate.html',
    ];

    /** @var stdClass course_module record. */
    private $instance;

    /** @var context_module the current context. */
    private $context;

    /** @var cm_info course_modules record. */
    private $cm;

    /**
     * Class constructor.
     *
     * @param cm_info $cm course module info object
     * @param stdClass $instance activity instance object.
     */
    public function __construct(cm_info $cm, stdClass $instance) {
        $this->cm = $cm;
        $this->instance = $instance;
        $this->context = context_module::instance($cm->id);
        $this->instance->cmidnumber = $cm->idnumber;
    }

    /**
     * Create a manager instance from an instance record.
     *
     * @param stdClass $instance a activity record
     * @return manager
     */
    public static function create_from_instance(stdClass $instance): self {
        $cm = get_coursemodule_from_instance(self::MODULE, $instance->id);
        // Ensure that $this->cm is a cm_info object.
        $cm = cm_info::create($cm);
        return new self($cm, $instance);
    }

    /**
     * Create a manager instance from an course_modules record.
     *
     * @param stdClass|cm_info $cm a activity record
     * @return manager
     */
    public static function create_from_coursemodule($cm): self {
        global $DB;
        // Ensure that $this->cm is a cm_info object.
        $cm = cm_info::create($cm);
        $instance = $DB->get_record(self::MODULE, ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($cm, $instance);
    }

    /**
     * Create a manager instance from a data_record entry.
     *
     * @param stdClass $record the data_record record
     * @return manager
     */
    public static function create_from_data_record($record): self {
        global $DB;
        $instance = $DB->get_record(self::MODULE, ['id' => $record->dataid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(self::MODULE, $instance->id);
        $cm = cm_info::create($cm);
        return new self($cm, $instance);
    }

    /**
     * Return the current context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Return the current instance.
     *
     * @return stdClass the instance record
     */
    public function get_instance(): stdClass {
        return $this->instance;
    }

    /**
     * Return the current cm_info.
     *
     * @return cm_info the course module
     */
    public function get_coursemodule(): cm_info {
        return $this->cm;
    }

    /**
     * Returns an array of all the available presets.
     *
     * @return array A list with the datapreset plugins and the presets saved by users.
     */
    public function get_available_presets(): array {
        // First load the datapreset plugins that exist within the modules preset dir.
        $pluginpresets = static::get_available_plugin_presets();

        // Then find the presets that people have saved.
        $savedpresets = static::get_available_saved_presets();

        return array_merge($pluginpresets, $savedpresets);
    }

    /**
     * Returns an array of all the presets that users have saved to the site.
     *
     * @return array A list with the preset saved by the users.
     */
    public function get_available_saved_presets() {
        global $USER;

        $presets = [];

        $fs = get_file_storage();
        $files = $fs->get_area_files(DATA_PRESET_CONTEXT, DATA_PRESET_COMPONENT, DATA_PRESET_FILEAREA);
        if (empty($files)) {
            return $presets;
        }
        $canviewall = has_capability('mod/data:viewalluserpresets', $this->get_context());
        foreach ($files as $file) {
            $isnotdirectory = ($file->is_directory() && $file->get_filepath() == '/') || !$file->is_directory();
            $userid = $file->get_userid();
            $cannotviewfile = !$canviewall && $userid != $USER->id;
            if ($isnotdirectory || $cannotviewfile) {
                continue;
            }

            $preset = preset::create_from_storedfile($this, $file);
            $presets[] = $preset;
        }

        return $presets;
    }

    /**
     * Returns an array of all the available plugin presets.
     *
     * @return array A list with the datapreset plugins.
     */
    public static function get_available_plugin_presets(): array {
        $presets = [];

        if ($dirs = core_component::get_plugin_list('datapreset')) {
            foreach ($dirs as $dir => $fulldir) {
                if (preset::is_directory_a_preset($fulldir)) {
                    $preset = preset::create_from_plugin(null, $dir, $fulldir);
                    $presets[] = $preset;
                }
            }
        }

        return $presets;
    }

}
