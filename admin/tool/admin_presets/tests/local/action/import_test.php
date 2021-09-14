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
 * Tests for the import class.
 *
 * @package    tool_admin_presets
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_admin_presets\local\action\import
 */
class import_test extends \advanced_testcase {

    /**
     * Test the behaviour of execute() method.
     *
     * @dataProvider import_execute_provider
     * @covers ::execute
     *
     * @param string $filecontents File content to import.
     * @param bool $expectedpreset Whether the preset should be created or not.
     * @param bool $expecteddebugging Whether debugging message will be thrown or not.
     * @param string|null $expectedexception Expected exception class (if that's the case).
     */
    public function test_import_execute(string $filecontents, bool $expectedpreset, bool $expecteddebugging = false,
            ?string $expectedexception = null): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $currentpresets = $DB->count_records('tool_admin_presets');
        $currentitems = $DB->count_records('tool_admin_presets_it');
        $currentadvitems = $DB->count_records('tool_admin_presets_it_a');

        // Create draft file to import.
        $draftid = file_get_unused_draft_itemid();
        $filerecord = [
            'component' => 'user',
            'filearea' => 'draft',
            'contextid' => \context_user::instance($USER->id)->id, 'itemid' => $draftid,
            'filename' => 'export.xml', 'filepath' => '/'
        ];
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, $filecontents);
        // Get the data we are submitting for the form and mock submitting it.
        $formdata = [
            'xmlfile' => $draftid,
            'name' => '',
            'admin_presets_submit' => 'Save changes',
            'sesskey' => sesskey(),
        ];
        \tool_admin_presets\form\import_form::mock_submit($formdata);

        // Initialise the parameters and create the import class.
        $_POST['action'] = 'import';
        $_POST['mode'] = 'execute';

        $action = new import();
        $sink = $this->redirectEvents();
        try {
            $action->execute();
        } catch (\exception $e) {
            // If import action was successfull, redirect should be called so we will encounter an
            // 'unsupported redirect error' moodle_exception.
            if ($expectedexception) {
                $this->assertInstanceOf($expectedexception, $e);
            } else {
                $this->assertInstanceOf(\moodle_exception::class, $e);
            }
        } finally {
            if ($expecteddebugging) {
                $this->assertDebuggingCalled();
            }

            if ($expectedpreset) {
                // Check the preset record has been created.
                $presets = $DB->get_records('tool_admin_presets');
                $this->assertCount($currentpresets + 1, $presets);
                $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
                $presetid = $generator->access_protected($action, 'id');
                $this->assertArrayHasKey($presetid, $presets);
                $preset = $presets[$presetid];
                $this->assertEquals('Exported preset', $preset->name);
                $this->assertEquals('http://demo.moodle', $preset->site);
                $this->assertEquals('Ada Lovelace', $preset->author);
                // Check the items have been created.
                $items = $DB->get_records('tool_admin_presets_it');
                $this->assertCount($currentitems + 4, $items);
                $presetitems = [
                    'none' => [
                        'enablebadges' => 0,
                        'allowemojipicker' => 1,
                    ],
                    'mod_lesson' => [
                        'mediawidth' => 900,
                        'maxanswers' => 2,
                    ],
                ];
                foreach ($items as $item) {
                    $this->assertArrayHasKey($item->name, $presetitems[$item->plugin]);
                    $this->assertEquals($presetitems[$item->plugin][$item->name], $item->value);
                }

                // Check the advanced attributes have been created.
                $advitems = $DB->get_records('tool_admin_presets_it_a');
                $this->assertCount($currentadvitems + 1, $advitems);
                $advitemfound = false;
                foreach ($advitems as $advitem) {
                    if ($advitem->name == 'maxanswers_adv') {
                        $this->assertEmpty($advitem->value);
                        $advitemfound = true;
                    }
                }
                $this->assertTrue($advitemfound);
            } else {
                // Check the preset nor the items are not created.
                $this->assertCount($currentpresets, $DB->get_records('tool_admin_presets'));
                $this->assertCount($currentitems, $DB->get_records('tool_admin_presets_it'));
                $this->assertCount($currentadvitems, $DB->get_records('tool_admin_presets_it_a'));
            }

            // Check the export event has been raised.
            $events = $sink->get_events();
            $sink->close();
            $event = reset($events);
            if ($expectedpreset) {
                // If preset has been created, an event should be raised.
                $this->assertInstanceOf('\\tool_admin_presets\\event\\preset_imported', $event);
            } else {
                $this->assertFalse($event);
            }
        }
    }

    /**
     * Data provider for test_import_execute().
     *
     * @return array
     */
    public function import_execute_provider(): array {
        return [
            'Import settings from an empty file' => [
                'filecontents' => '',
                'expectedpreset' => false,
            ],
            'Import settings from a valid XML file' => [
                'filecontents' => file_get_contents(__DIR__ . '/../../fixtures/import_settings.xml'),
                'expectedpreset' => true,
            ],
            'Import settings from an invalid XML file' => [
                'filecontents' => file_get_contents(__DIR__ . '/../../fixtures/invalid_xml_file.xml'),
                'expectedpreset' => false,
                'expecteddebugging' => false,
                'expectedexception' => \Exception::class,
            ],
            'Import unexisting category' => [
                'filecontents' => file_get_contents(__DIR__ . '/../../fixtures/unexisting_category.xml'),
                'expectedpreset' => false,
            ],
            'Import unexisting setting' => [
                'filecontents' => file_get_contents(__DIR__ . '/../../fixtures/unexisting_setting.xml'),
                'expectedpreset' => false,
                'expecteddebugging' => true,
            ],
            'Import valid settings with one unexisting setting too' => [
                'filecontents' => file_get_contents(__DIR__ . '/../../fixtures/import_settings_with_unexisting_setting.xml'),
                'expectedpreset' => true,
                'expecteddebugging' => true,
            ],
        ];
    }
}
