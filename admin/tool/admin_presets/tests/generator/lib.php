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
 * Data generator the admin_presets tool.
 *
 * @package    tool_admin_presets
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_admin_presets_generator extends \component_generator_base {

    /**
     * Create a preset. This preset will have only 3 settings:
     *  - none.enablebadges = 0
     *  - none.allowemojipicker = 1
     *  - mod_lesson.mediawidth = 900
     *  - mod_lesson.maxanswers = 2 with advanced disabled.
     *
     * @param array $data Preset data. Supported values:
     *   - name. To define the preset name.
     *   - comments. To change the comments field.
     *   - author. To set the author.
     *   - applypreset. Whether the preset should be applied too or not.
     * @return int Identifier of the preset created.
     */
    public function create_preset(array $data = []): int {
        global $DB, $USER, $CFG;

        if (!isset($data['name'])) {
            $data['name'] = 'Preset default name';
        }
        if (!isset($data['comments'])) {
            $data['comments'] = 'Preset default comment';
        }
        if (!isset($data['author'])) {
            $data['author'] = 'Default author';
        }

        $preset = [
            'userid' => $USER->id,
            'name' => $data['name'],
            'comments' => $data['comments'],
            'site' => $CFG->wwwroot,
            'author' => $data['author'],
            'moodleversion' => $CFG->version,
            'moodlerelease' => $CFG->release,
            'timecreated' => time(),
            'timeimported' => 0,
        ];

        $presetid = $DB->insert_record('tool_admin_presets', $preset);
        $preset['id'] = $presetid;

        // Setting: enablebadges = 0.
        $this->create_item($presetid, 'enablebadges', '0');

        // Setting: allowemojipicker = 1.
        $this->create_item($presetid, 'allowemojipicker', '1');

        // Setting: mediawidth = 900.
        $this->create_item($presetid, 'mediawidth', '900', 'mod_lesson');

        // Setting: maxanswers = 2 (with advanced disabled).
        $this->create_item($presetid, 'maxanswers', '2', 'mod_lesson', 'maxanswers_adv', 0);

        // Check if the preset should be created as applied preset too, to fill in the rest of the tables.
        $applypreset = isset($data['applypreset']) && $data['applypreset'];
        if ($applypreset) {
            $presetapp = [
                'adminpresetid' => $presetid,
                'userid' => $USER->id,
                'time' => time(),
            ];
            $appid = $DB->insert_record('tool_admin_presets_app', $presetapp);

            $this->apply_setting($appid, 'enablebadges', '1', '0');
            // The allowemojipicker setting shouldn't be applied because the value matches the current one.
            $this->apply_setting($appid, 'mediawidth', '640', '900', 'mod_lesson');
            $this->apply_setting($appid, 'maxanswers', '5', '2', 'mod_lesson');
            $this->apply_setting($appid, 'maxanswers_adv', '1', '0', 'mod_lesson', 'maxanswers');
        }

        return $presetid;
    }

    /**
     * Helper method to create a preset setting item.
     *
     * @param int $presetid Preset identifier where the item will belong.
     * @param string $name Item name.
     * @param string $value Item value.
     * @param string|null $plugin Item plugin.
     * @param string|null $advname If the item is an advanced setting, the name of the advanced setting should be specified here.
     * @param string|null $advvalue If the item is an advanced setting, the value of the advanced setting should be specified here.
     * @return int The item identificator.
     */
    private function create_item(int $presetid, string $name, string $value, ?string $plugin = 'none', ?string $advname = null,
            ?string $advvalue = null): int {
        global $DB;

        $presetitem = [
            'adminpresetid' => $presetid,
            'plugin' => $plugin,
            'name' => $name,
            'value' => $value,
        ];
        $itemid = $DB->insert_record('tool_admin_presets_it', $presetitem);

        if (!empty($advname)) {
            $presetadv = [
                'itemid' => $itemid,
                'name' => $advname,
                'value' => $advvalue,
            ];
            $DB->insert_record('tool_admin_presets_it_a', $presetadv);
        }

        return $itemid;
    }

    /**
     * Helper method to create an applied setting item.
     *
     * @param int $appid The applied preset identifier.
     * @param string $name The setting name.
     * @param string $oldvalue The setting old value.
     * @param string $newvalue The setting new value.
     * @param string|null $plugin The setting plugin (or null if none).
     * @param string|null $itemname Whether it should be treated as advanced item or not.
     */
    private function apply_setting(int $appid, string $name, string $oldvalue, string $newvalue, ?string $plugin = null,
            ?string $itemname = null) {
        global $DB;

        set_config($name, $newvalue, $plugin);
        $configlogid = $this->add_to_config_log($name, $oldvalue, $newvalue, $plugin);
        $presetappitem = [
            'adminpresetapplyid' => $appid,
            'configlogid' => $configlogid,
        ];
        $table = 'tool_admin_presets_app_it';
        if (!is_null($itemname)) {
            $table = 'tool_admin_presets_app_it_a';
            $presetappitem['itemname'] = $itemname;
        }
        $appitemid = $DB->insert_record($table, $presetappitem);

        return $appitemid;

    }

    /**
     * Helper method to add entry in config_log.
     *
     * @param string $name The setting name.
     * @param string $oldvalue The setting old value.
     * @param string $value The setting new value.
     * @param string|null $plugin The setting plugin (or null if the setting doesn't belong to any plugin).
     * @return int The id of the config_log entry created.
     */
    private function add_to_config_log(string $name, string $oldvalue, string $value, ?string $plugin = null): int {
        global $DB, $USER;

        $log = new stdClass();
        $log->userid = $USER->id;
        $log->timemodified = time();
        $log->name = $name;
        $log->oldvalue = $oldvalue;
        $log->value = $value;
        $log->plugin = $plugin;
        $id = $DB->insert_record('config_log', $log);

        return $id;
    }

    /**
     * Helper method to access to a protected property.
     *
     * @param string|object $object The class.
     * @param string $property The private/protected property in $object to access.
     * @return mixed The current value of the property.
     */
    public function access_protected($object, string $property) {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
