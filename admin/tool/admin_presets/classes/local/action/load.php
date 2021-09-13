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

use moodle_exception;
use stdClass;
use tool_admin_presets\form\load_form;
use tool_admin_presets\output\presets_list;

/**
 * This class extends base class and handles load function.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load extends base {

    /**
     * Executes the settings load into the system
     */
    public function execute(): void {

        global $DB, $OUTPUT, $USER;

        confirm_sesskey();

        $url = new \moodle_url('/admin/tool/admin_presets/index.php', ['action' => 'load', 'mode' => 'execute']);
        $this->moodleform = new load_form($url);

        if ($this->moodleform->is_cancelled()) {
            redirect(new \moodle_url('/admin/tool/admin_presets/index.php?action=base'));
        }

        if ($this->moodleform->is_submitted() && $this->moodleform->is_validated() && ($data = $this->moodleform->get_data())) {
            // Standarized format: $array['plugin']['settingname'] = child class.
            $siteavailablesettings = $this->_get_site_settings();

            // Get preset settings.
            if (!$items = $DB->get_records('tool_admin_presets_it', ['adminpresetid' => $this->id])) {
                throw new moodle_exception('errornopreset', 'tool_admin_presets');
            }

            $presetdbsettings = $this->_get_settings_from_db($items);
            // Standarized format $array['plugin']['settingname'] = child class.
            $presetsettings = $this->_get_settings($presetdbsettings, false, []);

            // Only for selected items.
            $applied = [];
            $skipped = [];

            foreach ($_POST as $varname => $ignored) {

                unset($updatesetting);

                if (strstr($varname, '@@') != false) {

                    // Format: [0] => setting [1] => plugin.
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

                    $data = [
                        'plugin' => $presetsetting->get_settingdata()->plugin,
                        'visiblename' => $presetsetting->get_settingdata()->visiblename,
                        'visiblevalue' => $presetsetting->get_visiblevalue(),
                    ];

                    // Saving data.
                    if (!empty($updatesetting)) {

                        // The preset application it's only saved when values differences are found.
                        if (empty($applieditem)) {
                            // Save the preset application and store the preset applied id.
                            $presetapplied = new stdClass();
                            $presetapplied->adminpresetid = $this->id;
                            $presetapplied->userid = $USER->id;
                            $presetapplied->time = time();
                            if (!$adminpresetapplyid = $DB->insert_record('tool_admin_presets_app', $presetapplied)) {
                                throw new moodle_exception('errorinserting', 'tool_admin_presets');
                            }
                        }

                        // Implemented this way because the config_write method of admin_setting class does not return the
                        // config_log inserted id.
                        $applieditem = new stdClass();
                        $applieditem->adminpresetapplyid = $adminpresetapplyid;
                        if ($applieditem->configlogid = $presetsetting->save_value()) {
                            $DB->insert_record('tool_admin_presets_app_it', $applieditem);
                        }

                        // For settings with multiple values.
                        if ($attributeslogids = $presetsetting->save_attributes_values()) {
                            foreach ($attributeslogids as $attributelogid) {
                                $applieditemattr = new stdClass();
                                $applieditemattr->adminpresetapplyid = $applieditem->adminpresetapplyid;
                                $applieditemattr->configlogid = $attributelogid;
                                $applieditemattr->itemname = $presetsetting->get_settingdata()->name;
                                $DB->insert_record('tool_admin_presets_app_it_a', $applieditemattr);
                            }
                        }

                        // Added to changed values.
                        $data['oldvisiblevalue'] = $sitesetting->get_visiblevalue();
                        $applied[] = $data;

                        // Unnecessary changes (actual setting value).
                    } else {
                        $skipped[] = $data;
                    }
                }
            }

            $application = new stdClass();

            $applieddata = new stdClass();
            $applieddata->show = !empty($applied);
            $applieddata->caption = get_string('settingsnotapplicable', 'tool_admin_presets');
            $applieddata->settings = $applied;
            $application->appliedchanges = $applieddata;

            $skippeddata = new stdClass();
            $skippeddata->show = !empty($skipped);
            $skippeddata->caption = get_string('settingsnotapplicable', 'tool_admin_presets');
            $skippeddata->settings = $skipped;
            $application->skippedchanges = $skippeddata;

            $this->outputs = $OUTPUT->render_from_template('tool_admin_presets/settings_application', $application);

            // Don't display the load form.
            $this->moodleform = false;
        }
    }

    /**
     * Displays the select preset settings to select what to import.
     * Loads the preset data and displays a settings tree.
     *
     * It checks the Moodle version and it only allows users to import
     * the preset available settings.
     */
    public function show(): void {
        global $DB, $OUTPUT;

        $data = new stdClass();
        $data->id = $this->id;

        // Preset data.
        if (!$preset = $DB->get_record('tool_admin_presets', ['id' => $data->id])) {
            throw new moodle_exception('errornopreset', 'tool_admin_presets');
        }

        if (!$items = $DB->get_records('tool_admin_presets_it', ['adminpresetid' => $data->id])) {
            throw new moodle_exception('errornopreset', 'tool_admin_presets');
        }

        // Standarized format $array['pluginname']['settingname'].
        // object('name' => 'settingname', 'value' => 'settingvalue').
        $presetdbsettings = $this->_get_settings_from_db($items);

        // Load site available settings to ensure that the settings exists on this release.
        $siteavailablesettings = $this->_get_site_settings();

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
        $presetsettings = $this->_get_settings($presetdbsettings, false, []);

        $this->_get_settings_branches($presetsettings);

        // Print preset basic data.
        $list = new presets_list([$preset]);
        $this->outputs = $OUTPUT->render($list);

        // Display not applicable settings.
        $skipped = [];
        if (!empty($notapplicable)) {
            foreach ($notapplicable as $setting) {
                $skipped[] = [
                    'plugin' => $setting->plugin,
                    'visiblename' => $setting->name,
                    'visiblevalue' => $setting->value
                ];
            }

            $application = new stdClass();
            $application->skippedchanges = [];

            $contextdata = new stdClass();
            $contextdata->show = !empty($skipped);
            $contextdata->caption = get_string('settingsnotapplicable', 'tool_admin_presets');
            $contextdata->settings = $skipped;
            $application->skippedchanges[] = $contextdata;

            $this->outputs .= $OUTPUT->render_from_template('tool_admin_presets/not_applicable_settings', $application);
        }
        $url = new \moodle_url('/admin/tool/admin_presets/index.php', ['action' => 'load', 'mode' => 'execute']);
        $this->moodleform = new load_form($url);
        $this->moodleform->set_data($data);
    }
}
