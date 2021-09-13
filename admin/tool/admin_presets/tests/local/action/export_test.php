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
 * Tests for the export class.
 *
 * @package    tool_admin_presets
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_admin_presets\local\action\export
 */
class export_test extends \advanced_testcase {

    /**
     * Test the behaviour of execute() method.
     * @covers ::execute
     * @dataProvider export_execute_provider
     *
     * @param array $params List of settings to export. The value will be excluded because the export method takes it from config.
     * @param bool $excludesensible Whether the sensible settings should be exported too or not.
     * @param int $expectedpreset Number of presets expected to be created.
     * @param int $expecteditems Number of settings expected to be created (associated to the preset).
     * @param int $expectedadvitems Number of advanced settings expected to be created (associated to the preset).
     */
    public function test_export_execute(array $params = [], bool $excludesensible = true, int $expectedpreset = 0,
            int $expecteditems = 0, int $expectedadvitems = 0): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Get current presets and items.
        $currentpresets = $DB->count_records('tool_admin_presets');
        $currentitems = $DB->count_records('tool_admin_presets_it');
        $currentadvitems = $DB->count_records('tool_admin_presets_it_a');

        // Initialise the recaptchapublickey with a value .
        set_config('recaptchapublickey', 'abcde');

        // Get the data we are submitting for the form and mock submitting it.
        $formdata = [
            'name' => 'Export 1',
            'comments' => ['text' => 'This is a presets for testing export'],
            'author' => 'Super-Girl',
            'excludesensiblesettings' => $excludesensible,
            'admin_presets_submit' => 'Save changes',
        ];
        \tool_admin_presets\form\export_form::mock_submit($formdata);

        // Initialise the parameters and create the export class.
        $_POST['action'] = 'export';
        $_POST['mode'] = 'execute';
        $_POST['sesskey'] = sesskey();
        // Initialise the settings to export.
        foreach ($params as $param => $value) {
            $_POST[$param] = $value;
        }

        $action = new export();
        $sink = $this->redirectEvents();
        try {
            $action->execute();
        } catch (\exception $e) {
            // If export action was successfull, redirect should be called so we will encounter an
            // 'unsupported redirect error' moodle_exception.
            $this->assertInstanceOf(\moodle_exception::class, $e);
        } finally {
            // Check the preset record has been created.
            $presets = $DB->get_records('tool_admin_presets');
            $this->assertCount($currentpresets + $expectedpreset, $presets);
            if ($expectedpreset > 0) {
                $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
                $presetid = $generator->access_protected($action, 'id');
                $this->assertArrayHasKey($presetid, $presets);
            }
            // Check the items have been created.
            $items = $DB->get_records('tool_admin_presets_it');
            $this->assertCount($currentitems + $expecteditems, $items);
            if ($expecteditems > 0) {
                $paramnames = [];
                foreach ($params as $name => $ignored) {
                    $name = explode('@@', $name);
                    $plugin = ($name[1] === 'none') ? 'core' : $name[1];
                    // Format: $paramnames[plugin][settingname] = settingvalue.
                    $paramnames[$name[1]][$name[0]] = get_config($plugin, $name[0]);
                }
                foreach ($items as $item) {
                    $this->assertArrayHasKey($item->name, $paramnames[$item->plugin]);
                    $this->assertEquals($paramnames[$item->plugin][$item->name], $item->value);
                }
            }

            // Check the advanced attributes have been created.
            $this->assertCount($currentadvitems + $expectedadvitems, $DB->get_records('tool_admin_presets_it_a'));

            // Check the export event has been raised.
            $events = $sink->get_events();
            $sink->close();
            $event = reset($events);
            if ($expectedpreset > 0) {
                // If preset has been created, an event should be raised.
                $this->assertInstanceOf('\\tool_admin_presets\\event\\preset_exported', $event);
            } else {
                $this->assertFalse($event);
            }
        }
    }

    /**
     * Data provider for test_export_execute().
     *
     * @return array
     */
    public function export_execute_provider(): array {
        return [
            'No settings to export. No preset should be created' => [
                'params' => [],
                'excludesensible' => true,
                'expectedpreset' => 0,
                'expecteditems' => 0,
                'expectedadvitems' => 0,
            ],

            // Expoting settings, excluding sensible.
            'Exporting 1 non-sensible setting' => [
                'params' => [
                    'enablebadges@@none' => 1,
                ],
                'excludesensible' => true,
                'expectedpreset' => 1,
                'expecteditems' => 1,
                'expectedadvitems' => 0,
            ],
            'Exporting 2 non-sensible setting' => [
                'params' => [
                    'enablebadges@@none' => 1,
                    'mediawidth@@mod_lesson' => 5,
                ],
                'excludesensible' => true,
                'expectedpreset' => 1,
                'expecteditems' => 2,
                'expectedadvitems' => 0,
            ],
            'Exporting 2 non-sensible setting, one of them with advanced attribute' => [
                'params' => [
                    'enablebadges@@none' => 1,
                    'maxanswers@@mod_lesson' => 5,
                ],
                'excludesensible' => true,
                'expectedpreset' => 1,
                'expecteditems' => 2,
                'expectedadvitems' => 1,
            ],
            'Exporting 1 empty sensible setting. No preset is expected because sensible settings are excluded' => [
                'params' => [
                    'cronremotepassword@@none' => '',
                ],
                'excludesensible' => true,
                'expectedpreset' => 0,
                'expecteditems' => 0,
                'expectedadvitems' => 0,
            ],
            'Exporting 1 non-empty sensible setting. No preset is expected because sensible settings are excluded' => [
                'params' => [
                    'recaptchapublickey@@none' => 'abcde',
                ],
                'excludesensible' => true,
                'expectedpreset' => 0,
                'expecteditems' => 0,
                'expectedadvitems' => 0,
            ],
            'Exporting 2 sensible settings + 3 more settings. Sensible settings will not be exported' => [
                'params' => [
                    'cronremotepassword@@none' => '',
                    'recaptchapublickey@@none' => 'abcde',
                    'enablebadges@@none' => 1,
                    'mediawidth@@mod_lesson' => 640,
                    'maxanswers@@mod_lesson' => 5,
                ],
                'excludesensible' => true,
                'expectedpreset' => 1,
                'expecteditems' => 3,
                'expectedadvitems' => 1,
            ],

            // Expoting settings, including sensible.
            'Exporting 1 empty sensible setting' => [
                'params' => [
                    'cronremotepassword@@none' => '',
                ],
                'excludesensible' => false,
                'expectedpreset' => 1,
                'expecteditems' => 1,
                'expectedadvitems' => 0,
            ],
            'Exporting 1 non-empty sensible setting' => [
                'params' => [
                    'recaptchapublickey@@none' => 'abcde',
                ],
                'excludesensible' => false,
                'expectedpreset' => 1,
                'expecteditems' => 1,
                'expectedadvitems' => 0,
            ],
            'Exporting 2 sensible settings + 3 more settings' => [
                'params' => [
                    'cronremotepassword@@none' => '',
                    'recaptchapublickey@@none' => 'abcde',
                    'enablebadges@@none' => 1,
                    'mediawidth@@mod_lesson' => 640,
                    'maxanswers@@mod_lesson' => 5,
                ],
                'excludesensible' => false,
                'expectedpreset' => 1,
                'expecteditems' => 5,
                'expectedadvitems' => 1,
            ],

            // Edge cases: plugin/setting does not exist.
            'Unexisting plugin' => [
                'params' => [
                    'enablebadges@@unexisting' => '',
                ],
                'excludesensible' => false,
                'expectedpreset' => 0,
                'expecteditems' => 0,
                'expectedadvitems' => 0,
            ],
            'Unexisting setting' => [
                'params' => [
                    'unexisting@@none' => '',
                ],
                'excludesensible' => false,
                'expectedpreset' => 0,
                'expecteditems' => 0,
                'expectedadvitems' => 0,
            ],
        ];
    }
}
