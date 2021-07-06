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

defined('MOODLE_INTERNAL') || die();

use stdClass;
use tool_admin_presets\form\export_form;
use memory_xml_output;
use moodle_exception;
use xml_writer;

global $CFG;
require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/backup/util/xml/xml_writer.class.php');
require_once($CFG->dirroot . '/backup/util/xml/output/xml_output.class.php');
require_once($CFG->dirroot . '/backup/util/xml/output/memory_xml_output.class.php');

/**
 * This class extends base class and handles export function.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export extends base {

    /**
     * Shows the initial form to export/save admin settings
     *
     * Loads the database configuration and prints
     * the settings in a hierical table
     */
    public function show(): void {
        global $CFG;

        $url = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=export&mode=execute';
        $this->moodleform = new export_form($url);
    }

    /**
     * Stores the preset into the DB
     */
    public function execute(): void {
        global $CFG, $USER, $DB;

        confirm_sesskey();

        $url = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=export&mode=execute';
        $this->moodleform = new export_form($url);

        if ($data = $this->moodleform->get_data()) {

            // Admin_preset record.
            $preset = new stdClass();
            $preset->userid = $USER->id;
            $preset->name = $data->name;
            $preset->comments = $data->comments['text'];
            $preset->site = $CFG->wwwroot;
            $preset->author = $data->author;
            $preset->moodleversion = $CFG->version;
            $preset->moodlerelease = $CFG->release;
            $preset->timecreated = time();
            $preset->timemodified = 0;
            if (!$preset->id = $DB->insert_record('tool_admin_presets', $preset)) {
                throw new moodle_exception('errorinserting', 'tool_admin_presets');
            }

            // Store it here for logging and other future id-oriented stuff.
            $this->id = $preset->id;

            // Store settings.
            $settingsfound = false;
            $sitesettings = $this->manager->get_site_settings();
            foreach ($sitesettings as $plugin => $pluginsettings) {
                foreach ($pluginsettings as $settingname => $sitesetting) {
                    // Avoid sensible data.
                    if (empty($data->includesensiblesettings) && !empty($this->sensiblesettings["$settingname@@$plugin"])) {
                        continue;
                    }

                    $setting = new stdClass();
                    $setting->adminpresetid = $preset->id;
                    $setting->plugin = $plugin;
                    $setting->name = $settingname;
                    $setting->value = $sitesetting->get_value();
                    if (!$setting->id = $DB->insert_record('tool_admin_presets_it', $setting)) {
                        throw new moodle_exception('errorinserting', 'tool_admin_presets');
                    }

                    // Setting attributes must also be exported.
                    if ($attributes = $sitesetting->get_attributes_values()) {
                        foreach ($attributes as $attname => $attvalue) {
                            $attr = new stdClass();
                            $attr->itemid = $setting->id;
                            $attr->name = $attname;
                            $attr->value = $attvalue;

                            $DB->insert_record('tool_admin_presets_it_a', $attr);
                        }
                    }
                    $settingsfound = true;
                }
            }

            // Store plugins visibility (enabled/disabled).
            $pluginsfound = false;
            $manager = \core_plugin_manager::instance();
            $types = $manager->get_plugin_types();
            foreach ($types as $plugintype => $notused) {
                $plugins = $manager->get_present_plugins($plugintype);
                $pluginclass = \core_plugin_manager::resolve_plugininfo_class($plugintype);
                if (!empty($plugins)) {
                    foreach ($plugins as $pluginname => $plugin) {
                        $entry = new stdClass();
                        $entry->adminpresetid = $preset->id;
                        $entry->plugin = $plugintype;
                        $entry->name = $pluginname;
                        $entry->enabled = $pluginclass::get_enabled_plugin($pluginname);

                        $DB->insert_record('tool_admin_presets_plug', $entry);
                        $pluginsfound = true;
                    }
                }
            }

            // If there are no settings nor plugins, the admin preset record should be removed.
            if (!$settingsfound && !$pluginsfound) {
                $DB->delete_records('tool_admin_presets', ['id' => $preset->id]);
                redirect(
                    $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=export',
                    get_string('novalidsettingsselected', 'tool_admin_presets')
                );
            }
        }

        // Trigger the as it is usually triggered after execute finishes.
        $this->log();

        redirect($CFG->wwwroot . '/admin/tool/admin_presets/index.php');
    }

    /**
     * To download system presets
     *
     * @return void preset file
     * @throws dml_exception
     * @throws moodle_exception
     * @throws xml_output_exception
     * @throws xml_writer_exception
     */
    public function download_xml(): void {
        global $DB;

        confirm_sesskey();

        if (!$preset = $DB->get_record('tool_admin_presets', ['id' => $this->id])) {
            throw new moodle_exception('errornopreset', 'tool_admin_presets');
        }

        if (!$items = $DB->get_records('tool_admin_presets_it', ['adminpresetid' => $this->id])) {
            throw new moodle_exception('errornopreset', 'tool_admin_presets');
        }

        // Start.
        $xmloutput = new memory_xml_output();
        $xmlwriter = new xml_writer($xmloutput);
        $xmlwriter->start();

        // Preset data.
        $xmlwriter->begin_tag('PRESET');
        foreach ($this->rel as $dbname => $xmlname) {
            $xmlwriter->full_tag($xmlname, $preset->$dbname);
        }

        // We ride through the settings array.
        $allsettings = $this->manager->get_settings_from_db($items);
        if ($allsettings) {

            $xmlwriter->begin_tag('ADMIN_SETTINGS');

            foreach ($allsettings as $plugin => $settings) {

                $tagname = strtoupper($plugin);

                // To aviod xml slash problems.
                if (strstr($tagname, '/') != false) {
                    $tagname = str_replace('/', '__', $tagname);
                }

                $xmlwriter->begin_tag($tagname);

                // One tag for each plugin setting.
                if (!empty($settings)) {

                    $xmlwriter->begin_tag('SETTINGS');

                    foreach ($settings as $setting) {

                        // Unset the tag attributes string.
                        $attributes = [];

                        // Getting setting attributes, if present.
                        $attrs = $DB->get_records('tool_admin_presets_it_a', ['itemid' => $setting->itemid]);
                        if ($attrs) {
                            foreach ($attrs as $attr) {
                                $attributes[$attr->name] = $attr->value;
                            }
                        }

                        $xmlwriter->full_tag(strtoupper($setting->name), $setting->value, $attributes);
                    }

                    $xmlwriter->end_tag('SETTINGS');
                }

                $xmlwriter->end_tag(strtoupper($tagname));
            }

            $xmlwriter->end_tag('ADMIN_SETTINGS');
        }

        // We ride through the plugins array.
        $data = $DB->get_records('tool_admin_presets_plug', ['adminpresetid' => $this->id]);
        if ($data) {

            $plugins = [];
            foreach ($data as $plugin) {
                $plugins[$plugin->plugin][] = $plugin;
            }

            $xmlwriter->begin_tag('PLUGINS');

            foreach ($plugins as $plugintype => $plugintypes) {
                $tagname = strtoupper($plugintype);
                $xmlwriter->begin_tag($tagname);

                foreach ($plugintypes as $plugin) {
                    $xmlwriter->full_tag(strtoupper($plugin->name), $plugin->enabled);
                }

                $xmlwriter->end_tag(strtoupper($tagname));
            }

            $xmlwriter->end_tag('PLUGINS');
        }

        // End.
        $xmlwriter->end_tag('PRESET');
        $xmlwriter->stop();
        $xmlstr = $xmloutput->get_allcontents();

        // Trigger the as it is usually triggered after execute finishes.
        $this->log();

        $filename = addcslashes($preset->name, '"') . '.xml';
        send_file($xmlstr, $filename, 0, 0, true, true);
    }

    protected function get_explanatory_description(): ?string {
        return get_string('exportdescription', 'tool_admin_presets');
    }
}
