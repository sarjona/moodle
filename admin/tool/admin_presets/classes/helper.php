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

/**
 * Admin tool presets helper class.
 *
 * @package    tool_admin_presets
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Create an empty preset.
     *
     * @param array $data Preset data. Supported values:
     *   - name. To define the preset name.
     *   - comments. To change the comments field.
     * @return int The identifier of the preset created.
     */
    public static function create_preset(array $data): int {
        global $CFG, $USER, $DB;

        $name = array_key_exists('name', $data) ? $data['name'] : '';
        $comments = array_key_exists('comments', $data) ? $data['comments'] : '';
        $iscore = array_key_exists('iscore', $data) ? $data['iscore'] : 0;

        $preset = [
            'userid' => $USER->id,
            'name' => $name,
            'comments' => $comments,
            'site' => $CFG->wwwroot,
            'author' => fullname($USER),
            'moodleversion' => $CFG->version,
            'moodlerelease' => $CFG->release,
            'iscore' => $iscore,
            'timecreated' => time(),
            'timeimported' => 0,
        ];

        $presetid = $DB->insert_record('tool_admin_presets', $preset);
        return $presetid;
    }

    /**
     * Apply a preset.
     *
     * @param int $presetid The preset identifier to apply.
     * @param manager|null $manager The manager helper class (if will be created if null is given).
     * @param bool $simulate Whether this is a simulation or not.
     * @return array List with the admin preset applied id, an array with the applied settings and another with the skipped ones.
     */
    public static function apply_preset(int $presetid, ?manager $manager = null, bool $simulate = false): array {
        if (is_null($manager)) {
            $manager = new manager();
        }

        // Apply preset settings.
        [$appid, $settingsapplied, $settingsskipped] = $manager->apply_settings($presetid, $simulate);

        // Set plugins visibility.
        [$appid, $pluginsapplied, $pluginsskipped] = $manager->apply_plugins($presetid, $simulate, $appid);

        $applied = array_merge($settingsapplied, $pluginsapplied);
        $skipped = array_merge($settingsskipped, $pluginsskipped);

        return [$appid, $applied, $skipped];
    }

    /**
     * Helper method to add a setting item to a preset.
     *
     * @param int $presetid Preset identifier where the item will belong.
     * @param string $name Item name.
     * @param string $value Item value.
     * @param string|null $plugin Item plugin.
     * @param string|null $advname If the item is an advanced setting, the name of the advanced setting should be specified here.
     * @param string|null $advvalue If the item is an advanced setting, the value of the advanced setting should be specified here.
     * @return int The item identificator.
     */
    public static function add_item(int $presetid, string $name, string $value, ?string $plugin = 'none',
            ?string $advname = null, ?string $advvalue = null): int {
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
     * Helper method to add a plugin to a preset.
     *
     * @param int $presetid Preset identifier where the item will belong.
     * @param string $plugin Plugin type.
     * @param string $name Plugin name.
     * @param int $enabled Whether the plugin will be enabled or not.
     * @return int The plugin identificator.
     */
    public static function add_plugin(int $presetid, string $plugin, string $name, int $enabled): int {
        global $DB;

        $pluginentry = [
            'adminpresetid' => $presetid,
            'plugin' => $plugin,
            'name' => $name,
            'enabled' => $enabled,
        ];
        $pluginid = $DB->insert_record('tool_admin_presets_plug', $pluginentry);

        return $pluginid;
    }

    /**
     * Apply default preset, if it's defined in $CFG.
     */
    public static function change_default_preset(): void {
        global $CFG, $DB;

        if (!empty($CFG->defaultadminpreset) && $CFG->defaultadminpreset == 'lite') {
            if ($preset = $DB->get_record('tool_admin_presets', ['name' => get_string('litepreset', 'tool_admin_presets')])) {
                static::apply_preset($preset->id);
            }
        }
    }
}
