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

use context_system;
use moodle_url;
use stdClass;
use tool_admin_presets\output\presets_list;
use tool_admin_presets\output\export_import;

global $CFG;
require_once($CFG->libdir . '/adminlib.php');
/**
 * Admin tool presets main controller class.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base {

    protected static $eventsactionsmap = [
        'base' => 'presets_listed',
        'delete' => 'preset_deleted',
        'export' => 'preset_exported',
        'import' => 'preset_imported',
        'preview' => 'preset_previewed',
        'load' => 'preset_loaded',
        'rollback' => 'preset_reverted',
        'download_xml' => 'preset_downloaded'
    ];
    protected $action;
    protected $mode;
    protected $adminroot;
    protected $outputs;
    protected $moodleform;
    protected $rel;

    /**
     * Loads common class attributes and initializes sensible settings and DB - XML relations
     */
    public function __construct() {

        $this->action = optional_param('action', 'base', PARAM_ALPHA);
        $this->mode = optional_param('mode', 'show', PARAM_ALPHAEXT);
        $this->id = optional_param('id', false, PARAM_INT);

        // DB - XML relations.
        $this->rel = array('name' => 'NAME', 'comments' => 'COMMENTS',
            'timecreated' => 'PRESET_DATE', 'site' => 'SITE_URL', 'author' => 'AUTHOR',
            'moodleversion' => 'MOODLE_VERSION', 'moodlerelease' => 'MOODLE_RELEASE');

        // Sensible settings.
        $sensiblesettings = explode(',',
            str_replace(' ', '', get_config('admin_presets', 'sensiblesettings')));
        $this->sensiblesettings = array_combine($sensiblesettings, $sensiblesettings);
    }

    /**
     * Method to list the presets available on the system
     *
     * It allows users to access the different preset
     * actions (preview, load, download, delete and rollback)
     */
    public function show(): void {
        global $DB, $OUTPUT;

        $options = new export_import();
        $this->outputs = $OUTPUT->render($options);

        $presets = $DB->get_records('tool_admin_presets');
        $list = new presets_list($presets, true);
        $this->outputs .= $OUTPUT->render($list);
    }

    /**
     * Main display method
     *
     * Prints the block header and the common block outputs, the
     * selected action outputs, his form and the footer
     *
     * $outputs value depends on $mode and $action selected
     */
    public function display(): void {
        global $OUTPUT;

        $this->_display_header();

        // Other outputs.
        if (!empty($this->outputs)) {
            echo $this->outputs;
        }

        // Form.
        if ($this->moodleform) {
            $this->moodleform->display();
        }

        // Footer.
        echo $OUTPUT->footer();
    }

    /**
     * Displays the header
     */
    protected function _display_header(): void {

        global $CFG, $PAGE, $OUTPUT, $SITE;

        // Strings.
        $actionstr = get_string('action' . $this->action, 'tool_admin_presets');
        $modestr = get_string($this->action . $this->mode, 'tool_admin_presets');
        $titlestr = get_string('pluginname', 'tool_admin_presets');

        // Header.
        $PAGE->set_title($titlestr);
        $PAGE->set_heading($SITE->fullname);

        $PAGE->navbar->add(get_string('pluginname', 'tool_admin_presets'),
            new moodle_url($CFG->wwwroot . '/admin/tool/admin_presets/index.php'));

        $PAGE->navbar->add($actionstr . ': ' . $modestr);

        echo $OUTPUT->header();

        echo $OUTPUT->heading($actionstr . ': ' . $modestr, 1);
    }

    public function log(): void {
        // TODO please, me of the future, fix this ununderstandable code.

        // The only read action we store is list presets.
            $action = $this->action;
            if ($this->mode != 'execute' && $this->mode != 'show') {
                $action = $this->mode;
            }
            if ($this->mode == 'show' && $this->action == 'load') {
                $action = 'preview';
            }

            $eventnamespace = '\\tool_admin_presets\\event\\' . self::$eventsactionsmap[$action];
            $eventdata = array(
                'context' => context_system::instance(),
                'objectid' => $this->id
            );
            $event = $eventnamespace::create($eventdata);
            $event->trigger();
    }

    /**
     * Gets the system settings
     *
     * Loads the DB $CFG->prefix.'config' values and the
     * $CFG->prefix.'config_plugins' values and redirects
     * the flow through $this->_get_settings()
     *
     * @return    array        $settings        Array format $array['plugin']['settingname'] = settings_types child class
     */
    protected function _get_site_settings(): array {

        global $DB;

        // Db configs (to avoid multiple queries).
        $dbconfig = $DB->get_records_select('config', '', array(), '', 'name, value');

        // Adding site settings in course table.
        $frontpagevalues = $DB->get_record_select('course', 'id = 1',
            array(), 'fullname, shortname, summary');
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
        return $this->_get_settings($sitedbsettings, true, $settings = array());
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
    protected function _get_settings($dbsettings, $sitedbvalues = false, $settings, $children = false): array {

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
                    $settings = $this->_get_settings($dbsettings, $sitedbvalues, $settings, $child->children);
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
                        if (!$setting = $this->_get_setting($values, $settingvalue)) {
                            continue;
                        }

                        // Settings_types childs with.
                        // attributes provides an attributes array.
                        if ($attributes = $setting->get_attributes()) {

                            // Look for settings attributes if it is a presets.
                            if (!$sitedbvalues) {
                                $itemid = $dbsettings[$values->plugin][$settingname]->itemid;
                                $attrs = $DB->get_records('tool_admin_presets_it_a',
                                    array('itemid' => $itemid), '', 'name, value');
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
    protected function _get_setting($settingdata, $currentvalue) {

        // Getting the appropiate class to get the correct setting value.
        $settingtype = get_class($settingdata);

        $classname = '\\tool_admin_presets\\local\\setting\\admin_preset_' . $settingtype;

        if (!class_exists($classname)) {
            // Return the default setting class if there is no specific class for this setting.
            $classname = '\\tool_admin_presets\\local\\setting\\admin_preset_setting';
        }

        return new $classname($settingdata, $currentvalue);
    }

    /**
     * Gets the javascript to populate the settings tree
     *
     * @param array $settings Array format $array['plugin']['settingname'] = settings_types child class
     */
    protected function _get_settings_branches($settings): void {

        global $PAGE;

        // Nodes should be added in hierarchical order.
        $nodes = array('categories' => array(), 'pages' => array(), 'settings' => array());
        $nodes = $this->_get_settings_elements($settings, false, false, $nodes);

        $PAGE->requires->js_init_call('M.tool_admin_presets.init', null, true);

        $levels = array('categories', 'pages', 'settings');
        foreach ($levels as $level) {
            foreach ($nodes[$level] as $data) {
                $ids[] = $data[0];
                $nodes[] = $data[1];
                $labels[] = $data[2];
                $descriptions[] = $data[3];
                $parents[] = $data[4];
            }
        }
        $PAGE->requires->js_init_call('M.tool_admin_presets.addNodes',
            array($ids, $nodes, $labels, $descriptions, $parents), true);
        $PAGE->requires->js_init_call('M.tool_admin_presets.render', null, true);
    }

    /**
     * Gets the html code to select the settings to export/import/load
     *
     * @param array $allsettings Array format $array['plugin']['settingname'] = settings_types child class
     * @param bool $admintree The admin tree branche object or false if we are in the root
     * @param bool $jsparentnode Name of the javascript parent category node
     * @param array $nodes Tree nodes
     * @return array Code to output
     */
    protected function _get_settings_elements($allsettings, $admintree = false, $jsparentnode = false, &$nodes): array {

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
            $pagesettings = array();

            // We must search category children.
            if (is_a($child, 'admin_category')) {
                if ($child->children) {
                    $categorynode = $child->name . 'Node';
                    $nodehtml = '<div class="catnode">' . $child->visiblename . '</div>';
                    $nodes['categories'][$categorynode] = array("category",
                        $categorynode, (string) $nodehtml, "", $jsparentnode);

                    // Not all admin_categories have admin_settingpages.
                    $this->_get_settings_elements($allsettings, $child->children, $categorynode, $nodes);
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
                        $pagesettings[$settingid] = array($settingid, $settingid,
                            $setting->get_text(), $setting->get_description(), $pagenode);
                    }

                    // The page node only should be added if it have children.
                    if ($pagesettings) {
                        $nodehtml = '<div class="catnode">' . $child->visiblename . '</div>';
                        $nodes['pages'][$pagenode] = array("page", $pagenode, (string) $nodehtml, "", $jsparentnode);
                        $nodes['settings'] = array_merge($nodes['settings'], $pagesettings);
                    }
                }
            }
        }

        return $nodes;
    }

    /**
     * Gets the standarized settings array from DB records
     *
     * @param array $dbsettings Array of objects
     * @return   array Standarized array,
     * format $array['plugin']['name'] = obj('name'=>'settingname', 'value'=>'settingvalue')
     */
    protected function _get_settings_from_db($dbsettings): array {
        $settings = array();

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
}
