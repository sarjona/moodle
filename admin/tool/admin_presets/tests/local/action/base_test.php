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

/**
 * Tests for the base class.
 *
 * @package    tool_admin_presets
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_admin_presets\local\action\base
 */
class base_test extends \advanced_testcase {

    /**
     * Test the behaviour of log() method.
     *
     * @covers ::log
     * @dataProvider log_provider
     *
     * @param string $action Action to log.
     * @param string $mode Mode to log.
     * @param string|null $expectedclassname The expected classname or null if no event is expected.
     */
    public function test_base_log(string $action, string $mode, ?string $expectedclassname): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Initialise the parameters and create the class.
        if (!empty($mode)) {
            $_POST['mode'] = $mode;
        }
        if (!empty($action)) {
            $_POST['action'] = $action;
        }
        $base = new base();

        // Redirect events (to capture them) and call to the log method.
        $sink = $this->redirectEvents();
        $base->log();
        $events = $sink->get_events();
        $sink->close();
        $event = reset($events);

        // Validate event data.
        if (is_null($expectedclassname)) {
            $this->assertFalse($event);
        } else {
            $this->assertInstanceOf('\\tool_admin_presets\\event\\' . $expectedclassname, $event);
        }
    }

    /**
     * Data provider for test_base_log().
     *
     * @return array
     */
    public function log_provider(): array {
        return [
            // Action = base.
            'action=base and mode = show' => [
                'action' => 'base',
                'mode' => 'show',
                'expectedclassname' => 'presets_listed',
            ],
            'action=base and mode = execute' => [
                'action' => 'base',
                'mode' => 'execute',
                'expectedclassname' => 'presets_listed',
            ],

            // Action = delete.
            'action=delete and mode = show' => [
                'action' => 'delete',
                'mode' => 'show',
                'expectedclassname' => null,
            ],
            'action=delete and mode = execute' => [
                'action' => 'delete',
                'mode' => 'execute',
                'expectedclassname' => 'preset_deleted',
            ],
            'mode = delete and action = base' => [
                'action' => 'base',
                'mode' => 'delete',
                'expectedclassname' => 'preset_deleted',
            ],

            // Action = export.
            'action=export and mode = show' => [
                'action' => 'export',
                'mode' => 'show',
                'expectedclassname' => null,
            ],
            'action=export and mode = execute' => [
                'action' => 'export',
                'mode' => 'execute',
                'expectedclassname' => 'preset_exported',
            ],
            'mode = export and action = download_xml' => [
                'action' => 'export',
                'mode' => 'download_xml',
                'expectedclassname' => 'preset_downloaded',
            ],

            // Action = load.
            'action=load and mode = show' => [
                'action' => 'load',
                'mode' => 'show',
                'expectedclassname' => 'preset_previewed',
            ],
            'action=load and mode = execute' => [
                'action' => 'load',
                'mode' => 'execute',
                'expectedclassname' => 'preset_loaded',
            ],

            // Unexisting action/method.
            'Unexisting action' => [
                'action' => 'unexisting',
                'mode' => 'show',
                'expectedclassname' => null,
            ],
            'Unexisting mode' => [
                'action' => 'delete',
                'mode' => 'unexisting',
                'expectedclassname' => null,
            ],
        ];
    }

    /**
     * Test the behaviour of protected _get_site_settings method.
     *
     * @covers ::_get_site_settings
     */
    public function test_base_get_site_settings(): void {
        global $DB;

        $this->resetAfterTest();

        // Login as admin, to access all the settings.
        $this->setAdminUser();

        // Set method accessibility.
        $method = new ReflectionMethod(base::class, '_get_site_settings');
        $method->setAccessible(true);

        $result = $method->invokeArgs(new base(), []);

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
        $result = $method->invokeArgs(new base(), []);
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
     * Test the behaviour of protected _get_setting method.
     *
     * @covers ::_get_setting
     */
    public function test_base_get_setting(): void {
        $this->resetAfterTest();

        // Login as admin, to access all the settings.
        $this->setAdminUser();

        // Set method accessibility.
        $method = new ReflectionMethod(base::class, '_get_setting');
        $method->setAccessible(true);

        $adminroot = admin_get_root();

        // Check the admin_preset_xxxxx class is created properly when it exists.
        $settingpage = $adminroot->locate('optionalsubsystems');
        $settingdata = $settingpage->settings->enablebadges;
        $result = $method->invokeArgs(new base(), [$settingdata, '']);
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_admin_setting_configcheckbox', $result);
        $this->assertNotEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));

        // Check the admin_preset_setting class is returned when no specific class exists.
        $settingpage = $adminroot->locate('managecustomfields');
        $settingdata = $settingpage->settings->customfieldsui;;
        $result = $method->invokeArgs(new base(), [$settingdata, '']);
        $this->assertInstanceOf('\tool_admin_presets\local\setting\admin_preset_setting', $result);
        $this->assertEquals('tool_admin_presets\local\setting\admin_preset_setting', get_class($result));
    }
}
