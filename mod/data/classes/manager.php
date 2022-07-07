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

use context;
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

    /** Filenames required to save the information of a preset. */
    const PRESET_FILENAMES = [
        'addtemplate.html',
        'asearchtemplate.html',
        'csstemplate.css',
        'jstemplate.js',
        'listtemplate.html',
        'listtemplatefooter.html',
        'listtemplateheader.html',
        'preset.xml',
        'rsstemplate.html',
        'rsstitletemplate.html',
        'singletemplate.html',
    ];

    /**
     * Returns an array of all the available presets.
     *
     * @param context $context The context that we are looking from.
     * @return array A list with the datapreset plugins and the presets saved by users.
     */
    public static function get_available_presets(context $context): array {
        // First load the datapreset plugins that exist within the modules preset dir.
        $pluginpresets = static::get_available_plugin_presets();

        // Then find the presets that people have saved.
        $savedpresets = static::get_available_saved_presets($context);

        return array_merge($pluginpresets, $savedpresets);
    }

    /**
     * Returns an array of all the available plugin presets.
     *
     * @return array A list with the datapreset plugins.
     */
    public static function get_available_plugin_presets(): array {
        global $CFG;

        $presets = [];

        if ($dirs = core_component::get_plugin_list('datapreset')) {
            foreach ($dirs as $dir => $fulldir) {
                if (static::is_directory_a_preset($fulldir)) {
                    $preset = new stdClass();
                    $preset->isplugin = true;
                    $preset->path = $fulldir;
                    $preset->userid = 0;
                    $preset->shortname = $dir;
                    $preset->name = static::get_plugin_preset_name($dir);
                    $presets[] = $preset;
                }
            }
        }

        return $presets;
    }

    /**
     * Returns an array of all the presets that users have saved to the site.
     *
     * @param context $context The context that we are looking from.
     * @return array An list with the preset saved by the users.
     */
    public static function get_available_saved_presets(context $context) {
        global $USER;

        $presets = [];

        $fs = get_file_storage();
        $files = $fs->get_area_files(DATA_PRESET_CONTEXT, DATA_PRESET_COMPONENT, DATA_PRESET_FILEAREA);
        if (empty($files)) {
            return $presets;
        }
        $canviewall = has_capability('mod/data:viewalluserpresets', $context);
        foreach ($files as $file) {
            $isnotdirectory = ($file->is_directory() && $file->get_filepath() == '/') || !$file->is_directory();
            $cannotviewfile = !$canviewall && $file->get_userid() != $USER->id;
            if ($isnotdirectory  || $cannotviewfile) {
                continue;
            }

            $preset = new stdClass();
            $preset->isplugin = false;
            $preset->path = $file->get_filepath();
            $preset->name = trim($preset->path, '/');
            $preset->shortname = $preset->name;
            $preset->userid = $file->get_userid();
            $preset->id = $file->get_id();
            $preset->storedfile = $file;
            $presets[] = $preset;
        }

        return $presets;
    }

    /**
     * Checks if a directory contains all the required files to define a preset.
     *
     * @param string $directory The patch to check if it contains the preset files or not.
     * @return bool True if the directory contains all the preset files; false otherwise.
     */
    public static function is_directory_a_preset(string $directory): bool {
        $status = true;
        $directory = rtrim($directory, '/\\') . '/';
        foreach (static::PRESET_FILENAMES as $filename) {
            $status &= file_exists($directory.$filename);
        }

        return $status;
    }

    /**
     * Returns the best name to show for a datapreset plugin.
     *
     * @param string $shortname The preset shortname.
     * @return string The plugin preset name to display.
     */
    public static function get_plugin_preset_name(string $shortname): string {
        if (get_string_manager()->string_exists('modulename', 'datapreset_'.$shortname)) {
            return get_string('modulename', 'datapreset_'.$shortname);
        } else {
            return $shortname;
        }
    }
}
