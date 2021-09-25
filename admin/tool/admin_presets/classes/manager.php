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

namespace tool_admin_presets;

use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin tool presets manager class.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    private $adminroot;

    /** var array Setting classes mapping, to associated the local/setting class that should be used when there is
     * no specific class. */
    protected static $settingclassesmap = [
            'admin_preset_admin_setting_agedigitalconsentmap' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configcolourpicker' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configdirectory' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configduration_with_advanced' => 'admin_preset_admin_setting_configtext_with_advanced',
            'admin_preset_admin_setting_configduration' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configempty' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configexecutable' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configfile' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_confightmleditor' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configmixedhostiplist' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configmultiselect_modules' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_setting_configpasswordunmask' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configportlist' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configselect_with_lock' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_setting_configtext_trim_lower' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configtext_with_maxlength' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configtextarea' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_configthemepreset' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_setting_countrycodes' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_courselist_frontpage' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_setting_description' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_enablemobileservice' => 'admin_preset_admin_setting_configcheckbox',
            'admin_preset_admin_setting_filetypes' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_forcetimezone' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_setting_grade_profilereport' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_setting_langlist' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_my_grades_report' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_setting_pickroles' => 'admin_preset_admin_setting_configmulticheckbox',
            'admin_preset_admin_setting_question_behaviour' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_setting_regradingcheckbox' => 'admin_preset_admin_setting_configcheckbox',
            'admin_preset_admin_setting_scsscode' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_servertimezone' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_setting_sitesetcheckbox' => 'admin_preset_admin_setting_configcheckbox',
            'admin_preset_admin_setting_sitesetselect' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_setting_special_adminseesall' => 'admin_preset_admin_setting_configcheckbox',
            'admin_preset_admin_setting_special_backup_auto_destination' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_special_coursecontact' => 'admin_preset_admin_setting_configmulticheckbox',
            'admin_preset_admin_setting_special_coursemanager' => 'admin_preset_admin_setting_configmulticheckbox',
            'admin_preset_admin_setting_special_debug' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_setting_special_frontpagedesc' => 'admin_preset_admin_setting_sitesettext',
            'admin_preset_admin_setting_special_gradebookroles' => 'admin_preset_admin_setting_configmulticheckbox',
            'admin_preset_admin_setting_special_gradeexport' => 'admin_preset_admin_setting_configmulticheckbox',
            'admin_preset_admin_setting_special_gradelimiting' => 'admin_preset_admin_setting_configcheckbox',
            'admin_preset_admin_setting_special_grademinmaxtouse' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_setting_special_gradepointdefault' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_special_gradepointmax' => 'admin_preset_admin_setting_configtext',
            'admin_preset_admin_setting_special_registerauth' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_setting_special_selectsetup' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_settings_country_select' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_settings_coursecat_select' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_settings_h5plib_handler_select' => 'admin_preset_admin_setting_configselect',
            'admin_preset_admin_settings_num_course_sections' => 'admin_preset_admin_setting_configmultiselect_with_loader',
            'admin_preset_admin_settings_sitepolicy_handler_select' => 'admin_preset_admin_setting_configselect',
            'admin_preset_antivirus_clamav_pathtounixsocket_setting' => 'admin_preset_admin_setting_configtext',
            'admin_preset_antivirus_clamav_runningmethod_setting' => 'admin_preset_admin_setting_configselect',
            'admin_preset_antivirus_clamav_tcpsockethost_setting' => 'admin_preset_admin_setting_configtext',
            'admin_preset_auth_db_admin_setting_special_auth_configtext' => 'admin_preset_admin_setting_configtext',
            'admin_preset_auth_ldap_admin_setting_special_lowercase_configtext' => 'admin_preset_admin_setting_configtext',
            'admin_preset_auth_ldap_admin_setting_special_ntlm_configtext' => 'admin_preset_admin_setting_configtext',
            'admin_preset_auth_shibboleth_admin_setting_convert_data' => 'admin_preset_admin_setting_configtext',
            'admin_preset_auth_shibboleth_admin_setting_special_idp_configtextarea' => 'admin_preset_admin_setting_configtext',
            'admin_preset_auth_shibboleth_admin_setting_special_wayf_select' => 'admin_preset_admin_setting_configselect',
            'admin_preset_editor_atto_toolbar_setting' => 'admin_preset_admin_setting_configtext',
            'admin_preset_editor_tinymce_json_setting_textarea' => 'admin_preset_admin_setting_configtext',
            'admin_preset_enrol_database_admin_setting_category' => 'admin_preset_admin_setting_configselect',
            'admin_preset_enrol_flatfile_role_setting' => 'admin_preset_admin_setting_configtext',
            'admin_preset_enrol_ldap_admin_setting_category' => 'admin_preset_admin_setting_configselect',
            'admin_preset_format_singleactivity_admin_setting_activitytype' => 'admin_preset_admin_setting_configselect',
            'admin_preset_qtype_multichoice_admin_setting_answernumbering' => 'admin_preset_admin_setting_configselect',
    ];

    /**
     * Gets the system settings
     *
     * Loads the DB $CFG->prefix.'config' values and the
     * $CFG->prefix.'config_plugins' values and redirects
     * the flow through $this->_get_settings()
     *
     * @return array $settings Array format $array['plugin']['settingname'] = settings_types child class
     */
    public function get_site_settings(): array {
        global $DB;

        // Db configs (to avoid multiple queries).
        $dbconfig = $DB->get_records_select('config', '', [], '', 'name, value');

        // Adding site settings in course table.
        $frontpagevalues = $DB->get_record_select('course', 'id = 1',
                [], 'fullname, shortname, summary');
        foreach ($frontpagevalues as $field => $value) {
            $dbconfig[$field] = new stdClass();
            $dbconfig[$field]->name = $field;
            $dbconfig[$field]->value = $value;
        }
        $sitedbsettings['none'] = $dbconfig;

        // Config plugins.
        $configplugins = $DB->get_records('config_plugins');
        foreach ($configplugins as $configplugin) {
            $sitedbsettings[$configplugin->plugin][$configplugin->name] = new stdClass();
            $sitedbsettings[$configplugin->plugin][$configplugin->name]->name = $configplugin->name;
            $sitedbsettings[$configplugin->plugin][$configplugin->name]->value = $configplugin->value;
        }
        // Get an array with the common format.
        return $this->get_settings($sitedbsettings, true, []);
    }

    /**
     * Constructs an array with all the system settings
     *
     * If a setting value can't be found on the DB it considers
     * the default value as the setting value
     *
     * Settings without plugin are marked as 'none' in the plugin field
     *
     * Returns an standarized settings array format, $this->_get_settings_branches
     * will get the html or js to display the settings tree
     *
     * @param array $dbsettings Standarized array,
     * format $array['plugin']['name'] = obj('name'=>'settingname', 'value'=>'settingvalue')
     * @param boolean $sitedbvalues Indicates if $dbsettings comes from the site db or not
     * @param array $settings Array format $array['plugin']['settingname'] = settings_types child class
     * @param bool $children admin_category children
     * @return    array Array format $array['plugin']['settingname'] = settings_types child class
     */
    public function get_settings($dbsettings, $sitedbvalues = false, $settings = [], $children = false): array {
        global $DB;

        // If there are no children, load admin tree and iterate through.
        if (!$children) {
            $this->adminroot = admin_get_root(false, true);
            $children = $this->adminroot->children;
        }

        // Iteates through children.
        foreach ($children as $key => $child) {

            // We must search category children.
            if (is_a($child, 'admin_category')) {

                if ($child->children) {
                    $settings = $this->get_settings($dbsettings, $sitedbvalues, $settings, $child->children);
                }

                // Settings page.
            } else if (is_a($child, 'admin_settingpage')) {

                if (property_exists($child, 'settings')) {

                    foreach ($child->settings as $values) {
                        $settingname = $values->name;

                        unset($settingvalue);

                        // Look for his config value.
                        if ($values->plugin == '') {
                            $values->plugin = 'none';
                        }

                        if (!empty($dbsettings[$values->plugin][$settingname])) {
                            $settingvalue = $dbsettings[$values->plugin][$settingname]->value;
                        }

                        // If no db value found default value.
                        if ($sitedbvalues && !isset($settingvalue)) {
                            // For settings with multiple values.
                            if (is_array($values->defaultsetting)) {

                                if (isset($values->defaultsetting['value'])) {
                                    $settingvalue = $values->defaultsetting['value'];
                                    // Configtime case, does not have a 'value' default setting.
                                } else {
                                    $settingvalue = 0;
                                }
                            } else {
                                $settingvalue = $values->defaultsetting;
                            }
                        }

                        // If there aren't any value loaded, skip that setting.
                        if (!isset($settingvalue)) {
                            continue;
                        }
                        // If there is no setting class defined continue.
                        if (!$setting = $this->get_setting($values, $settingvalue)) {
                            continue;
                        }

                        // Settings_types childs with.
                        // attributes provides an attributes array.
                        if ($attributes = $setting->get_attributes()) {

                            // Look for settings attributes if it is a presets.
                            if (!$sitedbvalues) {
                                $itemid = $dbsettings[$values->plugin][$settingname]->itemid;
                                $attrs = $DB->get_records('tool_admin_presets_it_a',
                                        ['itemid' => $itemid], '', 'name, value');
                            }
                            foreach ($attributes as $defaultvarname => $varname) {

                                unset($attributevalue);

                                // Settings from site.
                                if ($sitedbvalues) {
                                    if (!empty($dbsettings[$values->plugin][$varname])) {
                                        $attributevalue = $dbsettings[$values->plugin][$varname]->value;
                                    }

                                    // Settings from a preset.
                                } else if (!$sitedbvalues && isset($attrs[$varname])) {
                                    $attributevalue = $attrs[$varname]->value;
                                }

                                // If no value found, default value,
                                // But we may not have a default value for the attribute.
                                if (!isset($attributevalue) && !empty($values->defaultsetting[$defaultvarname])) {
                                    $attributevalue = $values->defaultsetting[$defaultvarname];
                                }

                                // If there is no even a default for this setting will be empty.
                                // So we do nothing in this case.
                                if (isset($attributevalue)) {
                                    $setting->set_attribute_value($varname, $attributevalue);
                                }
                            }
                        }

                        // Setting the text.
                        $setting->set_text();

                        // Adding to general settings array.
                        $settings[$values->plugin][$settingname] = $setting;
                    }
                }
            }
        }

        return $settings;
    }

    /**
     * Returns the class type object
     *
     * @param object $settingdata Setting data
     * @param mixed $currentvalue
     * @return mixed
     */
    public function get_setting($settingdata, $currentvalue) {

        $classname = null;

        // Getting the appropiate class to get the correct setting value.
        $settingtype = get_class($settingdata);

        // Check if it is a setting from a plugin.
        $plugindata = explode('_', $settingtype);
        $types = \core_component::get_plugin_types();
        if (array_key_exists($plugindata[0], $types)) {
            $plugins = \core_component::get_plugin_list($plugindata[0]);
            if (array_key_exists($plugindata[1], $plugins)) {
                // Check if there is a specific class for this plugin admin setting.
                $settingname = 'admin_preset_' . $settingtype;
                $classname = "\\$plugindata[0]_$plugindata[1]\\local\\setting\\$settingname";
                if (!class_exists($classname)) {
                    $classname = null;
                }
            }
        } else {
            $settingname = 'admin_preset_' . $settingtype;
            $classname = '\\tool_admin_presets\\local\\setting\\' . $settingname;
            if (!class_exists($classname)) {
                // Check if there is some mapped class that should be used for this setting.
                $classname = self::get_settings_class($settingname);
            }
        }

        if (is_null($classname)) {
            // Return the default setting class if there is no specific class for this setting.
            $classname = '\\tool_admin_presets\\local\\setting\\admin_preset_setting';
        }

        return new $classname($settingdata, $currentvalue);
    }

    /**
     * Returns the settings class mapped to the defined $classname or null if it doesn't exist any associated class.
     *
     * @param string $classname The classname to get the mapped class.
     * @return string|null
     */
    public static function get_settings_class(string $classname): ?string {
        if (array_key_exists($classname, self::$settingclassesmap)) {
            return '\\tool_admin_presets\\local\\setting\\' . self::$settingclassesmap[$classname];
        }

        return null;
    }

    /**
     * Gets the standarized settings array from DB records
     *
     * @param array $dbsettings Array of objects
     * @return   array Standarized array,
     * format $array['plugin']['name'] = obj('name'=>'settingname', 'value'=>'settingvalue')
     */
    public function get_settings_from_db($dbsettings): array {
        $settings = [];

        if (!$dbsettings) {
            return $settings;
        }

        foreach ($dbsettings as $dbsetting) {
            $settings[$dbsetting->plugin][$dbsetting->name] = new stdClass();
            $settings[$dbsetting->plugin][$dbsetting->name]->itemid = $dbsetting->id;
            $settings[$dbsetting->plugin][$dbsetting->name]->name = $dbsetting->name;
            $settings[$dbsetting->plugin][$dbsetting->name]->value = $dbsetting->value;
        }

        return $settings;
    }

    /**
     * Gets the javascript to populate the settings tree
     *
     * @param array $settings Array format $array['plugin']['settingname'] = settings_types child class
     */
    public function get_settings_branches(array $settings): array {

        global $PAGE;

        // Nodes should be added in hierarchical order.
        $nodes = ['categories' => [], 'pages' => [], 'settings' => []];
        $nodes = $this->get_settings_elements($settings, false, false, $nodes);

        $levels = ['categories', 'pages', 'settings'];
        foreach ($levels as $level) {
            foreach ($nodes[$level] as $data) {
                $ids[] = $data[0];
                $nodes[] = $data[1];
                $labels[] = $data[2];
                $descriptions[] = $data[3];
                $parents[] = $data[4];
            }
        }
        return [
            'ids' => $ids,
            'nodes' => $nodes,
            'labels' => $labels,
            'descriptions' => $descriptions,
            'parents' => $parents
        ];
    }

    /**
     * Gets the html code to select the settings to export/import/load
     *
     * @param array $allsettings Array format $array['plugin']['settingname'] = settings_types child class
     * @param object|bool $admintree The admin tree branche object or false if we are in the root
     * @param object|bool $jsparentnode Name of the javascript parent category node
     * @param array $nodes Tree nodes
     * @return array Code to output
     */
    public function get_settings_elements(array $allsettings, $admintree = false, $jsparentnode = false,
            array &$nodes = []): array {

        if (empty($this->adminroot)) {
            $this->adminroot = admin_get_root(false, true);
        }

        // If there are no children, load admin tree and iterate through.
        if (!$admintree) {
            $this->adminroot = admin_get_root(false, true);
            $admintree = $this->adminroot->children;
        }

        // If there are no parentnode specified the parent becomes the tree root.
        if (!$jsparentnode) {
            $jsparentnode = 'root';
        }

        // Iterates through children.
        foreach ($admintree as $key => $child) {
            $pagesettings = [];

            // We must search category children.
            if (is_a($child, 'admin_category')) {
                if ($child->children) {
                    $categorynode = $child->name . 'Node';
                    $nodehtml = '<div class="catnode">' . $child->visiblename . '</div>';
                    $nodes['categories'][$categorynode] = ['category', $categorynode, (string) $nodehtml, '', $jsparentnode];

                    // Not all admin_categories have admin_settingpages.
                    $this->get_settings_elements($allsettings, $child->children, $categorynode, $nodes);
                }

                // Settings page.
            } else if (is_a($child, 'admin_settingpage') || is_a($child, 'admin_externalpage')) {
                // Only if there are settings.
                if (property_exists($child, 'settings')) {

                    // The name of that page tree node.
                    $pagenode = $child->name . 'Node';

                    foreach ($child->settings as $values) {
                        $settingname = $values->name;

                        // IF no plugin was specified mark as 'none'.
                        if (!$plugin = $values->plugin) {
                            $plugin = 'none';
                        }

                        if (empty($allsettings[$plugin][$settingname])) {
                            continue;
                        }

                        // Getting setting data.
                        $setting = $allsettings[$plugin][$settingname];
                        $settingid = $setting->get_id();

                        // String to add the setting to js tree.
                        $pagesettings[$settingid] = [$settingid, $settingid, $setting->get_text(),
                                $setting->get_description(), $pagenode];
                    }

                    // The page node only should be added if it have children.
                    if ($pagesettings) {
                        $nodehtml = '<div class="catnode">' . $child->visiblename . '</div>';
                        $nodes['pages'][$pagenode] = ['page', $pagenode, (string) $nodehtml, '', $jsparentnode];
                        $nodes['settings'] = array_merge($nodes['settings'], $pagesettings);
                    }
                }
            }
        }

        return $nodes;
    }
}
