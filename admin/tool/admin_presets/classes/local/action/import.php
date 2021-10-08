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

namespace tool_admin_presets\local\action;

use stdClass;
use context_user;
use moodle_exception;
use tool_admin_presets\form\import_form;

/**
 * This class extends base class and handles import function.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import extends base {

    /**
     * Displays the import moodleform
     */
    public function show(): void {

        global $CFG;

        $url = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=import&mode=execute';
        $this->moodleform = new import_form($url);
    }

    /**
     * Imports the xmlfile into DB
     */
    public function execute(): void {
        global $CFG, $USER, $DB;

        confirm_sesskey();

        $url = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=import&mode=execute';
        $this->moodleform = new import_form($url);

        if ($data = $this->moodleform->get_data()) {

            $sitesettings = $this->manager->get_site_settings();

            // Getting the file.
            $xmlcontent = $this->moodleform->get_file_content('xmlfile');
            $xml = simplexml_load_string($xmlcontent);
            if (!$xml) {
                $redirecturl = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=import';
                redirect($redirecturl, get_string('wrongfile', 'tool_admin_presets'));
            }

            // Preset info.
            $preset = new stdClass();
            foreach ($this->rel as $dbname => $xmlname) {
                $preset->$dbname = (String) $xml->$xmlname;
            }
            $preset->userid = $USER->id;
            $preset->timeimported = time();

            // Overwrite preset name.
            if ($data->name != '') {
                $preset->name = $data->name;
            }

            // Inserting preset.
            if (!$preset->id = $DB->insert_record('tool_admin_presets', $preset)) {
                throw new moodle_exception('errorinserting', 'tool_admin_presets');
            }

            // Store it here for logging and other future id-oriented stuff.
            $this->id = $preset->id;

            // Settings.
            $xmladminsettings = $xml->ADMIN_SETTINGS[0];
            foreach ($xmladminsettings as $plugin => $settings) {

                $plugin = strtolower($plugin);

                if (strstr($plugin, '__') != false) {
                    $plugin = str_replace('__', '/', $plugin);
                }

                $pluginsettings = $settings->SETTINGS[0];

                if ($pluginsettings) {
                    foreach ($pluginsettings->children() as $name => $setting) {

                        $name = strtolower($name);

                        // Default to ''.
                        if ($setting->__toString() === false) {
                            $value = '';
                        } else {
                            $value = $setting->__toString();
                        }

                        if (empty($sitesettings[$plugin][$name])) {
                            debugging('Setting ' . $plugin . '/' . $name .
                                    ' not supported by this Moodle version', DEBUG_DEVELOPER);
                            continue;
                        }

                        // Cleaning the setting value.
                        if (!$presetsetting = $this->manager->get_setting($sitesettings[$plugin][$name]->get_settingdata(),
                            $value)) {
                            debugging('Setting ' . $plugin . '/' . $name . ' not implemented', DEBUG_DEVELOPER);
                            continue;
                        }

                        $settingsfound = true;

                        // New item.
                        $item = new stdClass();
                        $item->adminpresetid = $preset->id;
                        $item->plugin = $plugin;
                        $item->name = $name;
                        $item->value = $presetsetting->get_value();

                        // Inserting items.
                        if (!$item->id = $DB->insert_record('tool_admin_presets_it', $item)) {
                            throw new moodle_exception('errorinserting', 'tool_admin_presets');
                        }

                        // Adding settings attributes.
                        if ($setting->attributes() && ($itemattributes = $presetsetting->get_attributes())) {

                            foreach ($setting->attributes() as $attrname => $attrvalue) {

                                $itemattributenames = array_flip($itemattributes);

                                // Check the attribute existence.
                                if (!isset($itemattributenames[$attrname])) {
                                    debugging('The ' . $plugin . '/' . $name . ' attribute ' . $attrname .
                                            ' is not supported by this Moodle version', DEBUG_DEVELOPER);
                                    continue;
                                }

                                $attr = new stdClass();
                                $attr->itemid = $item->id;
                                $attr->name = $attrname;
                                $attr->value = $attrvalue->__toString();
                                $DB->insert_record('tool_admin_presets_it_a', $attr);
                            }
                        }
                    }
                }
            }

            // Plugins.
            if ($xml->PLUGINS) {
                $xmlplugins = $xml->PLUGINS[0];
                foreach ($xmlplugins as $plugin => $plugins) {
                    $pluginname = strtolower($plugin);
                    foreach ($plugins->children() as $name => $plugin) {
                        $pluginsfound = true;

                        // New plugin.
                        $entry = new stdClass();
                        $entry->adminpresetid = $preset->id;
                        $entry->plugin = $pluginname;
                        $entry->name = strtolower($name);
                        $entry->enabled = $plugin->__toString();

                        // Inserting plugin.
                        if (!$item->id = $DB->insert_record('tool_admin_presets_plug', $entry)) {
                            throw new moodle_exception('errorinserting', 'tool_admin_presets');
                        }
                    }
                }
            }

            // If there are no valid or selected settings we should delete the admin preset record.
            if (empty($settingsfound) && empty($pluginsfound)) {
                $DB->delete_records('tool_admin_presets', ['id' => $preset->id]);
                redirect($CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=import',
                        get_string('novalidsettings', 'tool_admin_presets'));
            }

            // Trigger the as it is usually triggered after execute finishes.
            $this->log();

            redirect($CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=load&id=' . $preset->id);
        }
    }
}
