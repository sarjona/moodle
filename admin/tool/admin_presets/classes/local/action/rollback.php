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

/**
 * This class extends base class and handles rollback function.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rollback extends base {

    /**
     * Displays the different previous applications of the preset
     */
    public function show(): void {

        global $DB, $OUTPUT;

        // Preset data.
        $preset = $DB->get_record('tool_admin_presets', ['id' => $this->id]);

        // Applications data.
        $context = new stdClass();
        $applications = $DB->get_records('tool_admin_presets_app', ['adminpresetid' => $this->id], 'time DESC');
        $context->noapplications = !empty($applications);
        $context->applications = [];
        foreach ($applications as $application) {
            $format = get_string('strftimedatetime', 'langconfig');
            $user = $DB->get_record('user', ['id' => $application->userid]);
            $rollbacklink = new \moodle_url(
                '/admin/tool/admin_presets/index.php',
                ['action' => 'rollback', 'mode' => 'execute', 'id' => $application->id, 'sesskey' => sesskey()]
            );

            $context->applications[] = [
                'timeapplied' => strftime($format, $application->time),
                'user' => fullname($user),
                'action' => $rollbacklink->out(false),
            ];
        }

        $this->outputs .= '<br/>' . $OUTPUT->heading(get_string('presetname', 'tool_admin_presets') . ': ' . $preset->name, 3);
        $this->outputs = $OUTPUT->render_from_template('tool_admin_presets/preset_applications_list', $context);

        $url = new \moodle_url('/admin/tool/admin_presets/index.php');
        $this->moodleform = new continue_form($url);
    }

    /**
     * Executes the application rollback
     *
     * Each setting value is checked against the config_log->value
     */
    public function execute(): void {

        global $DB, $OUTPUT;

        confirm_sesskey();

        // Actual settings.
        $sitesettings = $this->manager->get_site_settings();

        // To store rollback results.
        $rollback = [];
        $failures = [];

        if (!$DB->get_record('tool_admin_presets_app', ['id' => $this->id])) {
            throw new moodle_exception('wrongid', 'tool_admin_presets');
        }

        // Items.
        $itemsql = "SELECT cl.id, cl.plugin, cl.name, cl.value, cl.oldvalue, ap.adminpresetapplyid
                      FROM {tool_admin_presets_app_it} ap
                      JOIN {config_log} cl ON cl.id = ap.configlogid
                     WHERE ap.adminpresetapplyid = {$this->id}";
        $itemchanges = $DB->get_records_sql($itemsql);
        if ($itemchanges) {

            foreach ($itemchanges as $change) {

                if ($change->plugin == '') {
                    $change->plugin = 'none';
                }

                // Admin setting.
                if (!empty($sitesettings[$change->plugin][$change->name])) {
                    $actualsetting = $sitesettings[$change->plugin][$change->name];
                    $oldsetting = $this->manager->get_setting($actualsetting->get_settingdata(), $change->oldvalue);
                    $oldsetting->set_text();

                    $visiblepluginname = $oldsetting->get_settingdata()->plugin;
                    if ($visiblepluginname == 'none') {
                        $visiblepluginname = 'core';
                    }
                    $contextdata = [
                        'plugin' => $visiblepluginname,
                        'visiblename' => $oldsetting->get_settingdata()->visiblename,
                        'oldvisiblevalue' => $actualsetting->get_visiblevalue(),
                        'visiblevalue' => $oldsetting->get_visiblevalue()
                    ];

                    // Check if the actual value is the same set by the preset.
                    if ($change->value == $actualsetting->get_value()) {

                        $oldsetting->save_value();

                        // Output table.
                        $rollback[] = $contextdata;

                        // Deleting the admin_preset_apply_item instance.
                        $deletewhere = [
                            'adminpresetapplyid' => $change->adminpresetapplyid,
                            'configlogid' => $change->id,
                        ];
                        $DB->delete_records('tool_admin_presets_app_it', $deletewhere);

                    } else {
                        $failures[] = $contextdata;
                    }
                }
            }
        }

        // Attributes.
        $attrsql = "SELECT cl.id, cl.plugin, cl.name, cl.value, cl.oldvalue, ap.itemname, ap.adminpresetapplyid
                      FROM {tool_admin_presets_app_it_a} ap
                      JOIN {config_log} cl ON cl.id = ap.configlogid
                     WHERE ap.adminpresetapplyid = {$this->id}";
        $attrchanges = $DB->get_records_sql($attrsql);
        if ($attrchanges) {

            foreach ($attrchanges as $change) {

                if ($change->plugin == '') {
                    $change->plugin = 'none';
                }

                // Admin setting of the attribute item.
                if (!empty($sitesettings[$change->plugin][$change->itemname])) {

                    // Getting the attribute item.
                    $actualsetting = $sitesettings[$change->plugin][$change->itemname];

                    $oldsetting = $this->manager->get_setting($actualsetting->get_settingdata(), $actualsetting->get_value());
                    $oldsetting->set_attribute_value($change->name, $change->oldvalue);
                    $oldsetting->set_text();

                    $varname = $change->plugin . '_' . $change->name;

                    // Check if the actual value is the same set by the preset.
                    $actualattributes = $actualsetting->get_attributes_values();
                    if ($change->value == $actualattributes[$change->name]) {
                        $oldsetting->save_attributes_values();

                        // Output table.
                        $visiblepluginname = $oldsetting->get_settingdata()->plugin;
                        if ($visiblepluginname == 'none') {
                            $visiblepluginname = 'core';
                        }
                        $rollback[] = [
                            'plugin' => $visiblepluginname,
                            'visiblename' => $oldsetting->get_settingdata()->visiblename,
                            'oldvisiblevalue' => $actualsetting->get_visiblevalue(),
                            'visiblevalue' => $oldsetting->get_visiblevalue()
                        ];

                        // Deleting the admin_preset_apply_item_attr instance.
                        $deletewhere = [
                            'adminpresetapplyid' => $change->adminpresetapplyid,
                            'configlogid' => $change->id,
                        ];
                        $DB->delete_records('tool_admin_presets_app_it_a', $deletewhere);

                    } else {
                        $visiblepluginname = $oldsetting->get_settingdata()->plugin;
                        if ($visiblepluginname == 'none') {
                            $visiblepluginname = 'core';
                        }
                        $failures[] = [
                            'plugin' => $visiblepluginname,
                            'visiblename' => $oldsetting->get_settingdata()->visiblename,
                            'oldvisiblevalue' => $actualsetting->get_visiblevalue(),
                            'visiblevalue' => $oldsetting->get_visiblevalue()
                        ];
                    }
                }
            }
        }

        // Plugins.
        $plugins = $DB->get_records('tool_admin_presets_app_plug', ['adminpresetapplyid' => $this->id]);
        if ($plugins) {
            foreach ($plugins as $plugin) {
                $pluginclass = \core_plugin_manager::resolve_plugininfo_class($plugin->plugin);
                $pluginclass::enable_plugin($plugin->name, (int) $plugin->oldvalue);

                $visiblename = $plugin->plugin . '_' . $plugin->name;
                if (get_string_manager()->string_exists('pluginname', $plugin->plugin . '_' . $plugin->name)) {
                    $visiblename = get_string('pluginname', $plugin->plugin . '_' . $plugin->name);
                }

                // Output table.
                $rollback[] = [
                    'plugin' => $plugin->plugin,
                    'visiblename' => $visiblename,
                    'oldvisiblevalue' => $plugin->value,
                    'visiblevalue' => $plugin->oldvalue,
                ];
            }
            $plugins = $DB->delete_records('tool_admin_presets_app_plug', ['adminpresetapplyid' => $this->id]);
        }

        // Delete application if no items nor attributes nor plugins of the application remains.
        if (!$DB->get_records('tool_admin_presets_app_it', ['adminpresetapplyid' => $this->id]) &&
                !$DB->get_records('tool_admin_presets_app_it_a', ['adminpresetapplyid' => $this->id]) &&
                !$DB->get_records('tool_admin_presets_app_plug', ['adminpresetapplyid' => $this->id])) {

            $presetapp = $DB->get_record('tool_admin_presets_app', ['id' => $this->id]);
            $DB->delete_records('tool_admin_presets_app', ['id' => $this->id]);
            // Change $this->id to point to the preset.
            $this->id = $presetapp->adminpresetid;
        }

        $appliedchanges = new stdClass();
        $appliedchanges->show = !empty($rollback);
        $appliedchanges->caption = get_string('rollbackresults', 'tool_admin_presets');
        $appliedchanges->settings = $rollback;

        $skippedchanges = new stdClass();
        $skippedchanges->show = !empty($failures);
        $skippedchanges->caption = get_string('rollbackfailures', 'tool_admin_presets');
        $skippedchanges->settings = $failures;

        $context = new stdClass();
        $context->appliedchanges = $appliedchanges;
        $context->skippedchanges = $skippedchanges;
        $context->beforeapplying = true;
        $this->outputs = $OUTPUT->render_from_template('tool_admin_presets/settings_rollback', $context);

        $url = new \moodle_url('/admin/tool/admin_presets/index.php');
        $this->moodleform = new continue_form($url);
    }

    protected function get_title(): string {
        global $DB;

        $title = '';
        if ($preset = $DB->get_record('tool_admin_presets', ['id' => $this->id])) {
            $title = get_string($this->action . $this->mode, 'tool_admin_presets', $preset->name);
        }

        return $title;
    }

    protected function get_explanatory_description(): ?string {
        $text = null;
        if ($this->mode == 'show') {
            $text = get_string('rollbackdescription', 'tool_admin_presets');
        }

        return $text;
    }
}
