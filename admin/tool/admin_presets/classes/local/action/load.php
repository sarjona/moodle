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
use tool_admin_presets\form\continue_form;
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
        global $OUTPUT;

        confirm_sesskey();

        $url = new \moodle_url('/admin/tool/admin_presets/index.php', ['action' => 'load', 'mode' => 'execute']);
        $this->moodleform = new load_form($url);

        if ($this->moodleform->is_cancelled()) {
            redirect(new \moodle_url('/admin/tool/admin_presets/index.php?action=base'));
        }

        if ($this->moodleform->is_submitted() && $this->moodleform->is_validated() && ($this->moodleform->get_data())) {
            // Apply preset settings.
            [$adminpresetapplyid, $settingsapplied, $settingsskipped] = $this->apply_settings();

            // Set plugins visibility.
            [$adminpresetapplyid, $pluginsapplied, $pluginsskipped] = $this->apply_plugins(false, $adminpresetapplyid);

            $applied = array_merge($settingsapplied, $pluginsapplied);
            $skipped = array_merge($settingsskipped, $pluginsskipped);

            if (empty($applied)) {
                $message = [
                    'message' => get_string('nothingloaded', 'tool_admin_presets'),
                    'closebutton' => true,
                    'announce' => true,
                ];
            } else {
                $message = [
                    'message' => get_string('settingsappliednotification', 'tool_admin_presets'),
                    'closebutton' => true,
                    'announce' => true,
                ];
            }
            $application = new stdClass();
            $applieddata = new stdClass();
            $applieddata->show = !empty($applied);
            $applieddata->message = $message;
            $applieddata->heading = get_string('settingsapplied', 'tool_admin_presets');
            $applieddata->caption = get_string('settingsapplied', 'tool_admin_presets');
            $applieddata->settings = $applied;
            $application->appliedchanges = $applieddata;

            $skippeddata = new stdClass();
            $skippeddata->show = !empty($skipped);
            $skippeddata->heading = get_string('settingsnotapplied', 'tool_admin_presets');
            $skippeddata->caption = get_string('settingsnotapplicable', 'tool_admin_presets');
            $skippeddata->settings = $skipped;
            $application->skippedchanges = $skippeddata;

            $this->outputs = $OUTPUT->render_from_template('tool_admin_presets/settings_application', $application);
            $url = new \moodle_url('/admin/tool/admin_presets/index.php');
            $this->moodleform = new continue_form($url);
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

        // Print preset basic data.
        $list = new presets_list([$preset]);
        $this->outputs = $OUTPUT->render($list);

        // Display settings and plugins that will change.
        [$adminpresetapplyid, $settingsapplied] = $this->apply_settings(true);
        [$adminpresetapplyid, $pluginsapplied] = $this->apply_plugins(true, $adminpresetapplyid);

        $applied = array_merge($settingsapplied, $pluginsapplied);

        // Order the applied array by the visiblename column.
        if (!empty($applied)) {
            $visiblenamecolumn = array_column($applied, 'visiblename');
            array_multisort($visiblenamecolumn, SORT_ASC, $applied);
        }

        $application = new stdClass();
        $applieddata = new stdClass();
        $applieddata->show = !empty($applied);
        $applieddata->heading = get_string('settingstobeapplied', 'tool_admin_presets');
        $applieddata->caption = get_string('settingsapplied', 'tool_admin_presets');
        $applieddata->settings = $applied;
        $applieddata->beforeapplying = true;
        $application->appliedchanges = $applieddata;
        if (empty($applied)) {
            // Display a warning when no settings will be applied.
            $applieddata->message = get_string('nosettingswillbeapplied', 'tool_admin_presets');

            // Only display the Continue button.
            $url = new \moodle_url('/admin/tool/admin_presets/index.php');
            $this->moodleform = new continue_form($url);
        } else {
            // Display the form to apply the preset.
            $url = new \moodle_url('/admin/tool/admin_presets/index.php', ['action' => 'load', 'mode' => 'execute']);
            $this->moodleform = new load_form($url);
            $this->moodleform->set_data($data);
        }

        $this->outputs .= $OUTPUT->render_from_template('tool_admin_presets/settings_application', $application);

    }

    protected function apply_settings(bool $simulate = false, ?int $adminpresetapplyid = null): array {
        global $DB, $USER;

        if (!$items = $DB->get_records('tool_admin_presets_it', ['adminpresetid' => $this->id])) {
            throw new moodle_exception('errornopreset', 'tool_admin_presets');
        }

        $presetdbsettings = $this->manager->get_settings_from_db($items);
        // Standarized format: $array['plugin']['settingname'] = child class.
        $presetsettings = $this->manager->get_settings($presetdbsettings, false, []);

        // Standarized format: $array['plugin']['settingname'] = child class.
        $siteavailablesettings = $this->manager->get_site_settings();

        $applied = [];
        $skipped = [];

        // Set settings values.
        foreach ($presetsettings as $plugin => $pluginsettings) {
            foreach ($pluginsettings as $settingname => $presetsetting) {
                unset($updatesetting);

                // Current value (which will become old value if the setting is legit to be applied).
                $sitesetting = $siteavailablesettings[$plugin][$settingname];

                // Wrong setting, set_value() method has previously cleaned the value.
                if ($sitesetting->get_value() === false) {
                    debugging($presetsetting->get_settingdata()->plugin . '/' . $presetsetting->get_settingdata()->name .
                            ' setting has a wrong value!', DEBUG_DEVELOPER);
                    continue;
                }

                // If the new value is different the setting must be updated.
                if ($presetsetting->get_value() != $sitesetting->get_value()) {
                    $updatesetting = true;
                }

                // If one of the setting attributes values is different, setting must also be updated.
                if ($presetsetting->get_attributes_values()) {

                    $siteattributesvalues = $presetsetting->get_attributes_values();
                    foreach ($presetsetting->get_attributes_values() as $attributename => $attributevalue) {

                        if ($attributevalue !== $siteattributesvalues[$attributename]) {
                            $updatesetting = true;
                        }
                    }
                }

                $visiblepluginname = $presetsetting->get_settingdata()->plugin;
                if ($visiblepluginname == 'none') {
                    $visiblepluginname = 'core';
                }
                $data = [
                    'plugin' => $visiblepluginname,
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
                        if (!$simulate && !$adminpresetapplyid = $DB->insert_record('tool_admin_presets_app', $presetapplied)) {
                            throw new moodle_exception('errorinserting', 'tool_admin_presets');
                        }
                    }

                    // Implemented this way because the config_write method of admin_setting class does not return the
                    // config_log inserted id.
                    $applieditem = new stdClass();
                    $applieditem->adminpresetapplyid = $adminpresetapplyid;
                    if (!$simulate && $applieditem->configlogid = $presetsetting->save_value()) {
                        $DB->insert_record('tool_admin_presets_app_it', $applieditem);
                    }

                    // For settings with multiple values.
                    if (!$simulate && $attributeslogids = $presetsetting->save_attributes_values()) {
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
                } else {
                    // Unnecessary changes (actual setting value).
                    $skipped[] = $data;
                }
            }
        }
        return [$adminpresetapplyid, $applied, $skipped];
    }

    protected function apply_plugins(bool $simulate = false, ?int $adminpresetapplyid = null): array {
        global $DB, $USER;

        $applied = [];
        $skipped = [];

        $strenabled = get_string('enabled', 'tool_admin_presets');
        $strdisabled = get_string('disabled', 'tool_admin_presets');

        $plugins = $DB->get_records('tool_admin_presets_plug', ['adminpresetid' => $this->id]);
        foreach ($plugins as $plugin) {
            $pluginclass = \core_plugin_manager::resolve_plugininfo_class($plugin->plugin);
            $oldvalue = $pluginclass::get_enabled_plugin($plugin->name);

            $visiblename = $plugin->plugin . '_' . $plugin->name;
            if (get_string_manager()->string_exists('pluginname', $plugin->plugin . '_' . $plugin->name)) {
                $visiblename = get_string('pluginname', $plugin->plugin . '_' . $plugin->name);
            }
            if ($plugin->enabled > 0) {
                $visiblevalue = $strenabled;
            } else if ($plugin->enabled == 0) {
                $visiblevalue = $strdisabled;
            } else {
                $visiblevalue = get_string('disabledwithvalue', 'tool_admin_presets', $plugin->enabled);
            }

            $data = [
                'plugin' => $plugin->plugin,
                'visiblename' => $visiblename,
                'visiblevalue' => $visiblevalue,
            ];

            if ($pluginclass == '\core\plugininfo\orphaned') {
                $skipped[] = $data;
                continue;
            }

            // Only change the plugin visibility if it's different to current value.
            if (($plugin->enabled != $oldvalue) && (($plugin->enabled > 0 && !$oldvalue) || ($plugin->enabled < 1 && $oldvalue))) {
                try {
                    if (!$simulate) {
                        $pluginclass::enable_plugin($plugin->name, $plugin->enabled);

                        // The preset application it's only saved when values differences are found.
                        if (empty($adminpresetapplyid)) {
                            // Save the preset application and store the preset applied id.
                            $presetapplied = new stdClass();
                            $presetapplied->adminpresetid = $this->id;
                            $presetapplied->userid = $USER->id;
                            $presetapplied->time = time();
                            if (!$adminpresetapplyid = $DB->insert_record('tool_admin_presets_app', $presetapplied)) {
                                throw new moodle_exception('errorinserting', 'tool_admin_presets');
                            }
                        }

                        // Add plugin to aplied plugins table (for being able to restore in the future if required).
                        $appliedplug = new stdClass();
                        $appliedplug->adminpresetapplyid = $adminpresetapplyid;
                        $appliedplug->plugin = $plugin->plugin;
                        $appliedplug->name = $plugin->name;
                        $appliedplug->value = $plugin->enabled;
                        $appliedplug->oldvalue = $oldvalue;
                        $DB->insert_record('tool_admin_presets_app_plug', $appliedplug);
                    }

                    if ($oldvalue > 0) {
                        $oldvisiblevalue = $strenabled;
                    } else if ($oldvalue == 0) {
                        $oldvisiblevalue = $strdisabled;
                    } else {
                        $oldvisiblevalue = get_string('disabledwithvalue', 'tool_admin_presets', $oldvalue);
                    }
                    $data['oldvisiblevalue'] = $oldvisiblevalue;
                    $applied[] = $data;
                } catch (\exception $e) {
                    $skipped[] = $data;
                }
            } else {
                $skipped[] = $data;
            }
        }

        return [$adminpresetapplyid, $applied, $skipped];
    }

    protected function get_explanatory_description(): ?string {
        $text = null;
        if ($this->mode == 'show') {
            $text = get_string('loaddescription', 'tool_admin_presets');
        }

        return $text;
    }
}
