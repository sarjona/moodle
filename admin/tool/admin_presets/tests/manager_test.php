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

use ReflectionMethod;
use stdClass;
use tool_admin_presets\manager;

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
     */
    public function test_manager_test_get_site_settings(): void {
        global $DB;

        $this->resetAfterTest();

        // Login as admin, to access all the settings.
        $this->setAdminUser();

        // Set method accessibility.
        $method = new ReflectionMethod(manager::class, 'get_site_settings');
        $method->setAccessible(true);

        $result = $method->invokeArgs(new manager(), []);

        // Check fullname is set into the none category.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_sitesettext',
                $result['none']['fullname']
        );
        $this->assertEquals('PHPUnit test site', $result['none']['fullname']->get_value());

        // Check some of the config setting is present (they should be stored in the "none" category).
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_configcheckbox',
                $result['none']['usetags']
        );
        $this->assertEquals(1, $result['none']['usetags']->get_value());

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

        set_config('usetags', 0);
        set_config('maxsizetodownload', 101, 'folder');

        // Check the new values are returned properly.
        $result = $method->invokeArgs(new manager(), []);
        // Site fullname.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_sitesettext',
                $result['none']['fullname']
        );
        $this->assertEquals($sitecourse->fullname, $result['none']['fullname']->get_value());
        // Config setting.
        $this->assertInstanceOf(
                '\tool_admin_presets\local\setting\admin_preset_admin_setting_configcheckbox',
                $result['none']['usetags']
        );
        $this->assertEquals(0, $result['none']['usetags']->get_value());
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
     */
    public function test_manager_get_setting(): void {
        $this->resetAfterTest();

        // Login as admin, to access all the settings.
        $this->setAdminUser();

        // Set method accessibility.
        $method = new ReflectionMethod(manager::class, 'get_setting');
        $method->setAccessible(true);

        $adminroot = admin_get_root();

        // Check the admin_preset_xxxxx class is created properly when it exists.
        $settingpage = $adminroot->locate('optionalsubsystems');
        $settingdata = $settingpage->settings->enablebadges;
        $result = $method->invokeArgs(new manager(), [$settingdata, '']);
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_admin_setting_configcheckbox', $result);
        $this->assertNotEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));

        // Check the mapped class is returned when no specific class exists and it exists in the mappings array.
        $settingpage = $adminroot->locate('h5psettings');
        $settingdata = $settingpage->settings->h5plibraryhandler;;
        $result = $method->invokeArgs(new manager(), [$settingdata, '']);
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_admin_setting_configselect', $result);
        $this->assertNotEquals(
                'tool_admin_presets\local\setting\admin_preset_admin_settings_h5plib_handler_select',
                get_class($result)
        );

        // Check the mapped class is returned when no specific class exists and it exists in the mappings array.
        $settingpage = $adminroot->locate('modsettingquiz');
        $settingdata = $settingpage->settings->quizbrowsersecurity;;
        $result = $method->invokeArgs(new manager(), [$settingdata, '']);
        $this->assertInstanceOf('\mod_quiz\local\setting\admin_preset_mod_quiz_admin_setting_browsersecurity', $result);
        $this->assertNotEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));

        // Check the admin_preset_setting class is returned when no specific class exists.
        $settingpage = $adminroot->locate('managecustomfields');
        $settingdata = $settingpage->settings->customfieldsui;;
        $result = $method->invokeArgs(new manager(), [$settingdata, '']);
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_setting', $result);
        $this->assertEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));
    }
}
