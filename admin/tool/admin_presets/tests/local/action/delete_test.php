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
 * Tests for the delete class.
 *
 * @package    tool_admin_presets
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_admin_presets\local\action\delete
 */
class delete_test extends \advanced_testcase {

    /**
     * Test the behaviour of execute() method.
     *
     * @covers ::execute
     */
    public function test_delete_execute(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create some presets.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
        $presetid1 = $generator->create_preset(['name' => 'Preset 1', 'applypreset' => true]);
        $presetid2 = $generator->create_preset(['name' => 'Preset 2']);

        $this->assertCount(2, $DB->get_records('tool_admin_presets'));
        $this->assertCount(8, $DB->get_records('tool_admin_presets_it'));
        $this->assertCount(2, $DB->get_records('tool_admin_presets_it_a'));
        // Only preset1 has been applied.
        $this->assertCount(1, $DB->get_records('tool_admin_presets_app'));
        // Only the preset1 settings that have changed: enablebadges, mediawidth and maxanswers.
        $this->assertCount(3, $DB->get_records('tool_admin_presets_app_it'));
        // Only the preset1 advanced settings that have changed: maxanswers_adv.
        $this->assertCount(1, $DB->get_records('tool_admin_presets_app_it_a'));

        // Initialise the parameters and create the delete class.
        $_POST['action'] = 'delete';
        $_POST['mode'] = 'execute';
        $_POST['id'] = $presetid1;
        $_POST['sesskey'] = sesskey();

        $action = new delete();
        $sink = $this->redirectEvents();
        try {
            $action->execute();
        } catch (\exception $e) {
            // If delete action was successfull, redirect should be called so we will encounter an
            // 'unsupported redirect error' moodle_exception.
            $this->assertInstanceOf(\moodle_exception::class, $e);
        } finally {
            // Check the preset data has been removed.
            $presets = $DB->get_records('tool_admin_presets');
            $this->assertCount(1, $presets);
            $preset = reset($presets);
            $this->assertEquals($presetid2, $preset->id);
            $this->assertCount(4, $DB->get_records('tool_admin_presets_it'));
            $this->assertCount(0, $DB->get_records('tool_admin_presets_it', ['adminpresetid' => $presetid1]));
            $this->assertCount(1, $DB->get_records('tool_admin_presets_it_a'));
            $this->assertCount(0, $DB->get_records('tool_admin_presets_app'));
            $this->assertCount(0, $DB->get_records('tool_admin_presets_app_it'));
            $this->assertCount(0, $DB->get_records('tool_admin_presets_app_it_a'));

            // Check the delete event has been raised.
            $events = $sink->get_events();
            $sink->close();
            $event = reset($events);
            $this->assertInstanceOf('\\tool_admin_presets\\event\\preset_deleted', $event);
        }
    }

    /**
     * Test the behaviour of execute() method when the preset id doesn't exist.
     *
     * @covers ::execute
     */
    public function test_delete_execute_unexisting_preset(): void {

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create some presets.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
        $presetid = $generator->create_preset(['name' => 'Preset 1']);

        // Initialise the parameters and create the delete class.
        $_POST['action'] = 'delete';
        $_POST['mode'] = 'execute';
        $_POST['id'] = $presetid * 2; // Unexisting preset identifier.
        $_POST['sesskey'] = sesskey();

        $action = new delete();
        $this->expectException(\moodle_exception::class);
        $action->execute();
    }

    /**
     * Test the behaviour of show() method when the preset id doesn't exist.
     *
     * @covers ::show
     */
    public function test_delete_show_unexisting_preset(): void {

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create some presets.
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_admin_presets');
        $presetid = $generator->create_preset(['name' => 'Preset 1']);

        // Initialise the parameters and create the delete class.
        $_POST['action'] = 'delete';
        $_POST['mode'] = 'show';
        $_POST['id'] = $presetid * 2; // Unexisting preset identifier.

        $action = new delete();
        $this->expectException(\moodle_exception::class);
        $action->show();
    }
}
