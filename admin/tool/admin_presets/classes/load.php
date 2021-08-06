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
* Admin tool presets plugin to load some settings.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko
 * @orignalauthor    David Monllaó <david.monllao@urv.cat>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace admin_tool_presets;

use \StdClass;
use admin_tool_presets\forms\load_form;

use html_table;
use html_writer;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/admin_presets/classes/base.php');
require_once($CFG->dirroot . '/admin/tool/admin_presets/forms/load_form.php');

/**
 * Admin tool presets plugin this class extend base class and handle load function.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko
 * @orignalauthor    David Monllaó <david.monllao@urv.cat>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load extends base {

    /**
     * Executes the settings load into the system
     */
    public function execute(): void {

        global $CFG, $DB, $OUTPUT, $USER;

        confirm_sesskey();

        $url = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=load&mode=execute';
        $this->moodleform = new load_form($url);

        if ($data = $this->moodleform->get_data()) {
            // Standarized format $array['plugin']['settingname'] =  child class.
            $siteavailablesettings = $this->_get_site_settings();

            // Get preset settings.
            if (!$items = $DB->get_records('tool_admin_presets_it', array('adminpresetid' => $this->id))) {
                print_error('errornopreset', 'tool_admin_presets');
            }
            $presetdbsettings = $this->_get_settings_from_db($items);

            // Standarized format $array['plugin']['settingname'] =  child class.
            $presetsettings = $this->_get_settings($presetdbsettings, false, $presetsettings = array());

            // Only for selected items.
            $appliedchanges = array();
            $unnecessarychanges = array();

            foreach ($_POST as $varname => $value) {

                unset($updatesetting);

                if (strstr($varname, '@@') != false) {

                    // [0] => setting [1] => plugin.
                    $name = explode('@@', $varname);

                    // Just to be sure.
                    if (empty($presetsettings[$name[1]][$name[0]])) {
                        continue;
                    }
                    if (empty($siteavailablesettings[$name[1]][$name[0]])) {
                        continue;
                    }

                    // New and old values.
                    $presetsetting = $presetsettings[$name[1]][$name[0]];
                    $sitesetting = $siteavailablesettings[$name[1]][$name[0]];

                    // Wrong setting, set_value() method has previously cleaned the value.
                    if ($presetsetting->get_value() === false) {
                        debugging($presetsetting->get_settingdata()->plugin . '/' .
                            $presetsetting->get_settingdata()->name .
                            ' setting has a wrong value!', DEBUG_DEVELOPER);
                        continue;
                    }

                    // If the new value is different the setting must be updated.
                    if ($presetsetting->get_value() != $sitesetting->get_value()) {
                        $updatesetting = true;
                    }

                    // If one of the setting attributes values is different, setting must also be updated.
                    if ($presetsetting->get_attributes_values()) {

                        $siteattributesvalues = $sitesetting->get_attributes_values();
                        foreach ($presetsetting->get_attributes_values() as $attributename => $attributevalue) {

                            if ($attributevalue !== $siteattributesvalues[$attributename]) {
                                $updatesetting = true;
                            }
                        }
                    }

                    // Saving data.
                    if (!empty($updatesetting)) {

                        // The preset application it's only saved when values differences are found.
                        if (empty($applieditem)) {
                            // Save the preset application and store the preset applied id.
                            $presetapplied = new StdClass();
                            $presetapplied->adminpresetid = $this->id;
                            $presetapplied->userid = $USER->id;
                            $presetapplied->time = time();
                            if (!$adminpresetapplyid = $DB->insert_record('tool_admin_presets_app',
                                $presetapplied)) {
                                print_error('errorinserting', 'tool_admin_presets');
                            }
                        }

                        // Implemented this way because the config_write.
                        // method of admin_setting class does not.
                        // return the config_log inserted id.
                        $applieditem = new StdClass();
                        $applieditem->adminpresetapplyid = $adminpresetapplyid;
                        if ($applieditem->configlogid = $presetsetting->save_value()) {
                            $DB->insert_record('tool_admin_presets_app_it', $applieditem);
                        }

                        // For settings with multiple values.
                        if ($attributeslogids = $presetsetting->save_attributes_values()) {
                            foreach ($attributeslogids as $attributelogid) {
                                $applieditemattr = new StdClass();
                                $applieditemattr->adminpresetapplyid = $applieditem->adminpresetapplyid;
                                $applieditemattr->configlogid = $attributelogid;
                                $applieditemattr->itemname = $presetsetting->get_settingdata()->name;
                                $DB->insert_record('tool_admin_presets_app_it_a', $applieditemattr);
                            }
                        }

                        // Added to changed values.
                        $appliedchanges[$varname] = new StdClass();
                        $appliedchanges[$varname]->plugin = $presetsetting->get_settingdata()->plugin;
                        $appliedchanges[$varname]->visiblename = $presetsetting->get_settingdata()->visiblename;
                        $appliedchanges[$varname]->oldvisiblevalue = $sitesetting->get_visiblevalue();
                        $appliedchanges[$varname]->visiblevalue = $presetsetting->get_visiblevalue();

                        // Unnecessary changes (actual setting value).
                    } else {
                        $unnecessarychanges[$varname] = $presetsetting;
                    }
                }
            }
        }

        // Output applied changes.
        if (!empty($appliedchanges)) {
            $this->outputs .= '<br/>' . $OUTPUT->heading(get_string('settingsapplied',
                    'tool_admin_presets'), 3, 'admin_presets_success');
            $this->_output_applied_changes($appliedchanges);
        } else {
            $this->outputs .= '<br/>' . $OUTPUT->heading(get_string('nothingloaded',
                    'tool_admin_presets'), 3, 'admin_presets_error');
        }

        // Show skipped changes.
        if (!empty($unnecessarychanges)) {

            $skippedtable = new html_table();
            $skippedtable->attributes['class'] = 'generaltable boxaligncenter admin_presets_skipped';
            $skippedtable->head = array(get_string('plugin'),
                get_string('settingname', 'tool_admin_presets'),
                get_string('actualvalue', 'tool_admin_presets')
            );

            $skippedtable->align = array('center', 'center');

            $this->outputs .= '<br/>' . $OUTPUT->heading(get_string('settingsnotapplied',
                    'tool_admin_presets'), 3);

            foreach ($unnecessarychanges as $setting) {
                $skippedtable->data[] = array($setting->get_settingdata()->plugin,
                    $setting->get_settingdata()->visiblename,
                    $setting->get_visiblevalue()
                );
            }

            $this->outputs .= html_writer::table($skippedtable);
        }

        // Don't display the load form.
        $this->moodleform = false;
    }

    /**
     * Lists the preset available settings
     */
    public function preview(): void {
        $this->show(1);
    }

    /**
     * Displays the select preset settings to select what to import
     *
     * Loads the preset data and displays a settings tree
     *
     * It checks the Moodle version, it only allows users
     * to import the preset available settings
     *
     * @param boolean $preview If it's a preview it only lists the preset applicable settings
     */
    public function show($preview = false): void {

        global $CFG, $DB, $OUTPUT;

        $data = new StdClass();
        $data->id = $this->id;

        // Preset data.
        if (!$preset = $DB->get_record('tool_admin_presets', array('id' => $data->id))) {
            print_error('errornopreset', 'tool_admin_presets');
        }

        if (!$items = $DB->get_records('tool_admin_presets_it', array('adminpresetid' => $data->id))) {
            print_error('errornopreset', 'tool_admin_presets');
        }

        // Standarized format $array['pluginname']['settingname'].
        // object('name' => 'settingname', 'value' => 'settingvalue').
        $presetdbsettings = $this->_get_settings_from_db($items);

        // Load site avaible settings to ensure that the settings exists on this release.
        $siteavailablesettings = $this->_get_site_settings();

        $notapplicable = array();
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
        // Standarized format $array['plugin']['settingname'] =  child class.
        $presetsettings = $this->_get_settings($presetdbsettings, false, $presetsettings = array());

        $this->_get_settings_branches($presetsettings);

        // Print preset basic data.
        $this->outputs .= $this->_html_writer_preset_info_table($preset);

        // Display not applicable settings.
        if (!empty($notapplicable)) {

            $this->outputs .= '<br/>' . $OUTPUT->heading(get_string('settingsnotapplicable',
                    'tool_admin_presets'), 3, 'admin_presets_error');

            $table = new html_table();
            $table->attributes['class'] = 'generaltable boxaligncenter';
            $table->head = array(get_string('plugin'),
                get_string('settingname', 'tool_admin_presets'),
                get_string('value', 'tool_admin_presets'));

            $table->align = array('center', 'center');

            foreach ($notapplicable as $setting) {
                $table->data[] = array($setting->plugin, $setting->name, $setting->value);
            }

            $this->outputs .= html_writer::table($table);

        }

        $url = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=load&mode=execute';
        $this->moodleform = new load_form($url, $preview);
        $this->moodleform->set_data($data);

    }
}
