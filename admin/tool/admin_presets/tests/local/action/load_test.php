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

/**
 * Tests for the load class.
 *
 * @package    tool_admin_presets
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_admin_presets\local\action\load
 */
class load_test extends \advanced_testcase {

    /**
     * Test the behaviour of show() method when the preset id doesn't exist.
     *
     * @covers ::show
     */
    public function test_load_show_unexisting_preset(): void {

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create some presets.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
        $presetid = $generator->create_preset();

        // Initialise the parameters and create the load class.
        $_POST['action'] = 'load';
        $_POST['mode'] = 'view';
        $_POST['id'] = $presetid * 2; // Unexisting preset identifier.

        $action = new load();
        $this->expectException(\moodle_exception::class);
        $action->show();
    }

    /**
     * Test the behaviour of execute() method.
     *
     * @covers ::execute
     * @dataProvider load_execute_provider
     *
     * @param array $params List of the settings to load (it simulates the ones selected using the checkboxes in the form).
     * @param int $expectedapps Number of preset applied entries that will be created after loading the preset.
     * @param int $expectedappitems Number of items applied that will be created after loading the preset.
     * @param int $expectedappadvitems Number of advanced items applied that will be created after loading the preset.
     */
    public function test_load_execute(array $params, int $expectedapps, int $expectedappitems, int $expectedappadvitems): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a preset.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
        $presetid = $generator->create_preset();

        $currentpresets = $DB->count_records('tool_admin_presets');
        $currentitems = $DB->count_records('tool_admin_presets_it');
        $currentadvitems = $DB->count_records('tool_admin_presets_it_a');
        $currentapppresets = $DB->count_records('tool_admin_presets_app');
        $currentappitems = $DB->count_records('tool_admin_presets_app_it');
        $currentappadvitems = $DB->count_records('tool_admin_presets_app_it_a');

        // Set the config values (to confirm they change after applying the preset).
        set_config('enablebadges', 1);
        set_config('allowemojipicker', 1);
        set_config('mediawidth', '640', 'mod_lesson');
        set_config('maxanswers', '5', 'mod_lesson');
        set_config('enablecompletion', 1);
        set_config('usecomments', 0);

        // Get the data we are submitting for the form and mock submitting it.
        $formdata = [
            'id' => $presetid,
            'admin_presets_submit' => 'Load selected settings',
        ];
        \tool_admin_presets\form\load_form::mock_submit($formdata);

        // Initialise the parameters. The list of settings to apply should be included in the $_POST too.
        $_POST['action'] = 'load';
        $_POST['mode'] = 'execute';
        $_POST['id'] = $presetid;
        $_POST['sesskey'] = sesskey();
        foreach ($params as $paramname) {
            $_POST[$paramname] = '';
        }

        // Create the load class and execute it.
        $action = new load();
        $action->execute();

        // Check the preset applied has been added to database and no new preset has been created.
        $this->assertCount($currentapppresets + $expectedapps, $DB->get_records('tool_admin_presets_app'));
        $this->assertCount($currentappitems + $expectedappitems, $DB->get_records('tool_admin_presets_app_it'));
        $this->assertCount($currentappadvitems + $expectedappadvitems, $DB->get_records('tool_admin_presets_app_it_a'));
        $this->assertCount($currentpresets, $DB->get_records('tool_admin_presets'));
        $this->assertCount($currentitems, $DB->get_records('tool_admin_presets_it'));
        $this->assertCount($currentadvitems, $DB->get_records('tool_admin_presets_it_a'));

        // Check the setting values have changed accordingly with the ones defined in the preset.
        if (in_array('enablebadges@@none', $params)) {
            $this->assertEquals(0, get_config('core', 'enablebadges'));
        } else {
            $this->assertEquals(1, get_config('core', 'enablebadges'));
        }
        if (in_array('mediawidth@@mod_lesson', $params)) {
            $this->assertEquals(900, get_config('mod_lesson', 'mediawidth'));
        } else {
            $this->assertEquals(640, get_config('mod_lesson', 'mediawidth'));
        }
        if (in_array('maxanswers@@mod_lesson', $params)) {
            $this->assertEquals(2, get_config('mod_lesson', 'maxanswers'));
        } else {
            $this->assertEquals(5, get_config('mod_lesson', 'maxanswers'));
        }

        // These settings won't change, regardless if they are posted to the form.
        $this->assertEquals(1, get_config('core', 'allowemojipicker'));
        $this->assertEquals(1, get_config('core', 'enablecompletion'));
        $this->assertEquals(0, get_config('core', 'usecomments'));
    }

    /**
     * Data provider for test_load_execute().
     *
     * @return array
     */
    public function load_execute_provider(): array {
        return [
            'Load all the settings included in the preset' => [
                'params' => [
                    'enablebadges@@none',
                    'allowemojipicker@@none',
                    'mediawidth@@mod_lesson',
                    'maxanswers@@mod_lesson',
                    'enablecompletion@@none', // It does not belog to the preset, but it will be posted to confirm it's ignored.
                    'usecomments@@none', // It does not belog to the preset, but it will be posted to confirm it's ignored.
                ],
                'expectedapps' => 1,
                'expectedappitems' => 3,
                'expectedappadvitems' => 1,
            ],
            'Load only one setting (enablebadges)' => [
                'params' => [
                    'enablebadges@@none',
                ],
                'expectedapps' => 1,
                'expectedappitems' => 1,
                'expectedappadvitems' => 0,
            ],
            'Load one setting with advanced attribute' => [
                'params' => [
                    'maxanswers@@mod_lesson',
                ],
                'expectedapps' => 1,
                'expectedappitems' => 1,
                'expectedappadvitems' => 1,
            ],
            'Load only one setting that has not changed from current one (allowemojipicker)' => [
                'params' => [
                    'allowemojipicker@@none',
                ],
                'expectedapps' => 0,
                'expectedappitems' => 0,
                'expectedappadvitems' => 0,
            ],

            // Edge cases: unexisting setting, un-changed setting...
            'Load only settings which are not included in the preset' => [
                'params' => [
                    'enablecompletion@@none',
                    'usecomments@@none',
                ],
                'expectedapps' => 0,
                'expectedappitems' => 0,
                'expectedappadvitems' => 0,
            ],
            'Load only one setting which does not exist' => [
                'params' => [
                    'unexisting@@none',
                ],
                'expectedapps' => 0,
                'expectedappitems' => 0,
                'expectedappadvitems' => 0,
            ],
            'Load only one setting from an unexisting category' => [
                'params' => [
                    'enablebadges@@unexisting',
                ],
                'expectedapps' => 0,
                'expectedappitems' => 0,
                'expectedappadvitems' => 0,
            ],
        ];
    }
}
