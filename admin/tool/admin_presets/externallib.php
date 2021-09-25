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
 * Define some function for AJAX request
 *
 * @package          tool_admin_presets
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_admin_presets;

use context_system;
use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");

class external extends external_api {

    /**
     * Returns description of get_settings() parameters.
     *
     * @return external_function_parameters
     */
    public static function get_settings_parameters() {
        return new external_function_parameters([
                'action' => new external_value(PARAM_TEXT, 'action of the page'),
                'id' => new external_value(PARAM_INT, 'action of the page')
        ]);
    }

    /*
     * Get all the system settings.
     *
     * @return array $data
     */
    /**
     * @param $action string Action of the page
     * @param $id int Id of the page
     * @return array of system settings
     */
    public static function get_settings(string $action, int $id) {
        global $PAGE, $DB;
        $PAGE->set_context(context_system::instance());
        $manager = new manager();

        if ($action = 'load' and $id > 0) {
            // Preset data.
            if (!$preset = $DB->get_record('tool_admin_presets', ['id' => $id])) {
                throw new moodle_exception('errornopreset', 'tool_admin_presets');
            }

            if (!$items = $DB->get_records('tool_admin_presets_it', ['adminpresetid' => $id])) {
                throw new moodle_exception('errornopreset', 'tool_admin_presets');
            }

            // Standarized format $array['pluginname']['settingname'].
            // object('name' => 'settingname', 'value' => 'settingvalue').
            $presetdbsettings = $manager->get_settings_from_db($items);

            // Load site available settings to ensure that the settings exists on this release.
            $siteavailablesettings = $manager->get_site_settings();

            $notapplicable = [];
            if ($presetdbsettings) {
                foreach ($presetdbsettings as $plugin => $elements) {
                    foreach ($elements as $settingname => $element) {

                        // If the setting doesn't exists in that release skip it.
                        if (empty($siteavailablesettings[$plugin][$settingname])) {
                            // Adding setting plugin.
                            $presetdbsettings[$plugin][$settingname]->plugin = $plugin;

                            $notapplicable[] = $presetdbsettings[$plugin][$settingname];
                        }
                    }
                }
            }
            // Standarized format $array['plugin']['settingname'] = child class.
            $presetsettings = $manager->get_settings($presetdbsettings, false, []);

            return $manager->get_settings_branches($presetsettings);
        } else {
            $settings =  $manager->get_site_settings();
            return $manager->get_settings_branches($settings);
        }
    }

    /**
     * Returns description of get_settings() result value.
     *
     * @return external_single_structure
     */
    public static function get_settings_returns() {
        return null;
    }

}
