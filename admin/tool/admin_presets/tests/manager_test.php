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

/**
 * Tests for the manager class.
 *
 * @package    tool_admin_presets
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_admin_presets\manager
 */
class manager_test extends \advanced_testcase {
    /**
     * Test the behaviour of protected get_site_settings method.
     *
     * @covers ::get_site_settings
     * @covers ::get_settings
     */
    public function test_manager_get_site_settings(): void {
        global $DB;

        $this->resetAfterTest();

        // Login as admin, to access all the settings.
        $this->setAdminUser();

        $manager = new manager();
        $result = $manager->get_site_settings();

        // Check fullname is set into the none category.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_sitesettext',
                $result['none']['fullname']
        );
        $this->assertEquals('PHPUnit test site', $result['none']['fullname']->get_value());

        // Check some of the config setting is present (they should be stored in the "none" category).
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_configcheckbox',
                $result['none']['enablecompletion']
        );
        $this->assertEquals(1, $result['none']['enablecompletion']->get_value());

        // Check some of the plugin config settings is present.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_configtext',
                $result['folder']['maxsizetodownload']
        );
        $this->assertEquals(0, $result['folder']['maxsizetodownload']->get_value());

        // Set some of these values.
        $sitecourse = new stdClass();
        $sitecourse->id = 1;
        $sitecourse->fullname = 'New site fullname';
        $DB->update_record('course', $sitecourse);

        set_config('enablecompletion', 0);
        set_config('maxsizetodownload', 101, 'folder');

        // Check the new values are returned properly.
        $result = $manager->get_site_settings();
        // Site fullname.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_sitesettext',
                $result['none']['fullname']
        );
        $this->assertEquals($sitecourse->fullname, $result['none']['fullname']->get_value());
        // Config setting.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_configcheckbox',
                $result['none']['enablecompletion']
        );
        $this->assertEquals(0, $result['none']['enablecompletion']->get_value());
        // Plugin config settting.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_configtext',
                $result['folder']['maxsizetodownload']
        );
        $this->assertEquals(101, $result['folder']['maxsizetodownload']->get_value());
    }

    /**
     * Test the behaviour of protected get_setting method.
     *
     * @covers ::get_setting
     * @covers ::get_settings_class
     */
    public function test_manager_get_setting(): void {
        $this->resetAfterTest();

        // Login as admin, to access all the settings.
        $this->setAdminUser();

        $adminroot = admin_get_root();

        // Check the admin_preset_xxxxx class is created properly when it exists.
        $settingpage = $adminroot->locate('optionalsubsystems');
        $settingdata = $settingpage->settings->enablebadges;
        $manager = new manager();
        $result = $manager->get_setting($settingdata, '');
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_admin_setting_configcheckbox', $result);
        $this->assertNotEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));

        // Check the mapped class is returned when no specific class exists and it exists in the mappings array.
        $settingpage = $adminroot->locate('h5psettings');
        $settingdata = $settingpage->settings->h5plibraryhandler;;
        $result = $manager->get_setting($settingdata, '');
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_admin_setting_configselect', $result);
        $this->assertNotEquals(
                'tool_admin_presets\local\setting\admin_preset_admin_settings_h5plib_handler_select',
                get_class($result)
        );

        // Check the mapped class is returned when no specific class exists and it exists in the mappings array.
        $settingpage = $adminroot->locate('modsettingquiz');
        $settingdata = $settingpage->settings->quizbrowsersecurity;;
        $result = $manager->get_setting($settingdata, '');
        $this->assertInstanceOf('\mod_quiz\local\setting\admin_preset_mod_quiz_admin_setting_browsersecurity', $result);
        $this->assertNotEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));

        // Check the admin_preset_setting class is returned when no specific class exists.
        $settingpage = $adminroot->locate('managecustomfields');
        $settingdata = $settingpage->settings->customfieldsui;;
        $result = $manager->get_setting($settingdata, '');
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_setting', $result);
        $this->assertEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));
    }

    /**
     * Test the behaviour of apply_preset() method when the given presetid doesn't exist.
     *
     * @covers ::apply_preset
     */
    public function test_apply_preset_unexisting_preset(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create some presets.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
        $presetid = $generator->create_preset();

        // Unexisting preset identifier.
        $unexistingid = $presetid * 2;

        $manager = new manager();
        $this->expectException(\moodle_exception::class);
        $manager->apply_preset($unexistingid);
    }

    /**
     * Test the behaviour of apply_preset() method.
     *
     * @covers ::apply_preset
     */
    public function test_apply_preset(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a preset.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
        $presetid = $generator->create_preset();

        $currentpresets = $DB->count_records('tool_admin_presets');
        $currentitems = $DB->count_records('tool_admin_presets_it');
        $currentadvitems = $DB->count_records('tool_admin_presets_it_a');
        $currentplugins = $DB->count_records('tool_admin_presets_plug');
        $currentapppresets = $DB->count_records('tool_admin_presets_app');
        $currentappitems = $DB->count_records('tool_admin_presets_app_it');
        $currentappadvitems = $DB->count_records('tool_admin_presets_app_it_a');
        $currentappplugins = $DB->count_records('tool_admin_presets_app_plug');

        // Set the config values (to confirm they change after applying the preset).
        set_config('enablebadges', 1);
        set_config('allowemojipicker', 1);
        set_config('mediawidth', '640', 'mod_lesson');
        set_config('maxanswers', '5', 'mod_lesson');
        set_config('maxanswers_adv', '1', 'mod_lesson');
        set_config('enablecompletion', 1);
        set_config('usecomments', 0);

        // Create the load class and execute it.
        $manager = new manager();
        $manager->apply_preset($presetid);

        // Check the preset applied has been added to database.
        $this->assertCount($currentapppresets + 1, $DB->get_records('tool_admin_presets_app'));
        // Applied items: enablebadges@none, mediawitdh@mod_lesson and maxanswers@@mod_lesson.
        $this->assertCount($currentappitems + 3, $DB->get_records('tool_admin_presets_app_it'));
        // Applied advanced items: maxanswers_adv@mod_lesson.
        $this->assertCount($currentappadvitems + 1, $DB->get_records('tool_admin_presets_app_it_a'));
        // Applied plugins: enrol_guest and mod_glossary.
        $this->assertCount($currentappplugins + 2, $DB->get_records('tool_admin_presets_app_plug'));
        // Check no new preset has been created.
        $this->assertCount($currentpresets, $DB->get_records('tool_admin_presets'));
        $this->assertCount($currentitems, $DB->get_records('tool_admin_presets_it'));
        $this->assertCount($currentadvitems, $DB->get_records('tool_admin_presets_it_a'));
        $this->assertCount($currentplugins, $DB->get_records('tool_admin_presets_plug'));

        // Check the setting values have changed accordingly with the ones defined in the preset.
        $this->assertEquals(0, get_config('core', 'enablebadges'));
        $this->assertEquals(900, get_config('mod_lesson', 'mediawidth'));
        $this->assertEquals(2, get_config('mod_lesson', 'maxanswers'));
        $this->assertEquals(0, get_config('mod_lesson', 'maxanswers_adv'));

        // These settings will never change.
        $this->assertEquals(1, get_config('core', 'allowemojipicker'));
        $this->assertEquals(1, get_config('core', 'enablecompletion'));
        $this->assertEquals(0, get_config('core', 'usecomments'));

        // Check the plugins visibility have changed accordingly with the ones defined in the preset.
        $enabledplugins = \core\plugininfo\enrol::get_enabled_plugins();
        $this->assertArrayNotHasKey('guest', $enabledplugins);
        $this->assertArrayHasKey('manual', $enabledplugins);
        $enabledplugins = \core\plugininfo\mod::get_enabled_plugins();
        $this->assertArrayNotHasKey('glossary', $enabledplugins);
        $this->assertArrayHasKey('assign', $enabledplugins);
        $enabledplugins = \core\plugininfo\qtype::get_enabled_plugins();
        $this->assertArrayHasKey('truefalse', $enabledplugins);
    }
}
