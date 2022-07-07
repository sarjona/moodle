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

namespace mod_data;

use context_module;
use core_component;

/**
 * Manager tests class for mod_data.
 *
 * @package    mod_data
 * @category   test
 * @copyright  2022 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_data\manager
 */
class manager_test extends \advanced_testcase {

    /**
     * Test for get_available_presets().
     *
     * @covers ::get_available_presets
     */
    public function test_get_available_presets() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($user);

        $activity = $this->getDataGenerator()->create_module(manager::MODULE, ['course' => $course]);
        $cm = get_coursemodule_from_id(manager::MODULE, $activity->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Check available presets meet the datapreset plugins when there are no any preset saved by users.
        $datapresetplugins = core_component::get_plugin_list('datapreset');
        $presets = manager::get_available_presets($context);
        $this->assertCount(count($datapresetplugins), $presets);
        // Confirm that, at least, the "Image gallery" is one of them.
        $namepresets = array_map(function($preset) {
            return $preset->name;
        }, $presets);
        $this->assertContains('Image gallery', $namepresets);

        // Login as admin and create some presets saved manually by users.
        $this->setAdminUser();
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_data');
        $savedpresets = [];
        for ($i = 1; $i <= 3; $i++) {
            $preset = (object) [
                'name' => 'Preset name ' . $i,
            ];
            $plugingenerator->create_preset($activity, $preset);
            $savedpresets[] = $preset;
        }
        $savedpresetsnames = array_map(function($preset) {
            return $preset->name;
        }, $savedpresets);
        $this->setUser($user);

        // Check available presets meet the datapreset plugins + presets saved manually by users.
        $presets = manager::get_available_presets($context);
        $this->assertCount(count($datapresetplugins) + count($savedpresets), $presets);
        // Confirm that, apart from the "Image gallery" preset, the ones created manually have been also returned.
        $namepresets = array_map(function($preset) {
            return $preset->name;
        }, $presets);
        $this->assertContains('Image gallery', $namepresets);
        foreach ($savedpresets as $savedpreset) {
            $this->assertContains($savedpreset->name, $namepresets);
        }
        // Check all the presets have the proper value for the isplugin attribute.
        foreach ($presets as $preset) {
            if ($preset->name === 'Image gallery') {
                $this->assertTrue($preset->isplugin);
            } else if (in_array($preset->name, $savedpresetsnames)) {
                $this->assertFalse($preset->isplugin);
            }
        }

        // Unassign the capability to the teacher role and check that only plugin presets are returned (because the saved presets
        // have been created by admin).
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        unassign_capability('mod/data:viewalluserpresets', $teacherrole->id);
        $presets = manager::get_available_presets($context);
        $this->assertCount(count($datapresetplugins), $presets);
        // Confirm that, at least, the "Image gallery" is one of them.
        $namepresets = array_map(function($preset) {
            return $preset->name;
        }, $presets);
        $this->assertContains('Image gallery', $namepresets);
        foreach ($savedpresets as $savedpreset) {
            $this->assertNotContains($savedpreset->name, $namepresets);
        }

        // Create a preset with the current user and check that, although the viewalluserpresets is not assigned to the teacher
        // role, the preset is returned because the teacher is the owner.
        $savedpreset = (object) [
            'name' => 'Preset created by teacher',
        ];
        $plugingenerator->create_preset($activity, $savedpreset);
        $presets = manager::get_available_presets($context);
        // The presets total is all the plugin presets plus the preset created by the teacher.
        $this->assertCount(count($datapresetplugins) + 1, $presets);
        // Confirm that, at least, the "Image gallery" is one of them.
        $namepresets = array_map(function($preset) {
            return $preset->name;
        }, $presets);
        $this->assertContains('Image gallery', $namepresets);
        // Confirm that savedpresets are still not returned.
        foreach ($savedpresets as $savedpreset) {
            $this->assertNotContains($savedpreset->name, $namepresets);
        }
        // Confirm the new preset created by the teacher is returned too.
        $this->assertContains('Preset created by teacher', $namepresets);
    }

    /**
     * Test for get_available_plugin_presets().
     *
     * @covers ::get_available_plugin_presets
     */
    public function get_available_plugin_presets() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module(manager::MODULE, ['course' => $course]);

        // Check available plugin presets meet the datapreset plugins.
        $datapresetplugins = core_component::get_plugin_list('datapreset');
        $presets = manager::get_available_plugin_presets();
        $this->assertCount(count($datapresetplugins), $presets);
        // Confirm that, at least, the "Image gallery" is one of them.
        $namepresets = array_map(function($preset) {
            return $preset->name;
        }, $presets);
        $this->assertContains('Image gallery', $namepresets);

        // Create a preset saved manually by users.
        $savedpreset = (object) [
            'name' => 'Preset name 1',
        ];
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_data');
        $plugingenerator->create_preset($activity, $savedpreset);

        // Check available plugin presets don't contain the preset saved manually.
        $presets = manager::get_available_plugin_presets();
        $this->assertCount(count($datapresetplugins), $presets);
        // Confirm that, at least, the "Image gallery" is one of them.
        $namepresets = array_map(function($preset) {
            return $preset->name;
        }, $presets);
        $this->assertContains('Image gallery', $namepresets);
        // Confirm that the preset saved manually hasn't been returned.
        $this->assertNotContains($savedpreset->name, $namepresets);
        // Check all the presets have the proper value for the isplugin attribute.
        foreach ($presets as $preset) {
            $this->assertTrue($preset->isplugin);
        }
    }

    /**
     * Test for get_available_saved_presets().
     *
     * @covers ::get_available_saved_presets
     */
    public function get_available_saved_presets() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($user);

        $activity = $this->getDataGenerator()->create_module(manager::MODULE, ['course' => $course]);
        $cm = get_coursemodule_from_id(manager::MODULE, $activity->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Check available saved presets is empty (because, for now, no user preset has been created).
        $presets = manager::get_available_saved_presets($context);
        $this->assertCount(0, $presets);

        // Create some presets saved manually by users.
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_data');
        $savedpresets = [];
        for ($i = 1; $i <= 3; $i++) {
            $preset = (object) [
                'name' => 'Preset name ' . $i,
            ];
            $plugingenerator->create_preset($activity, $preset);
            $savedpresets[] = $preset;
        }
        $savedpresetsnames = array_map(function($preset) {
            return $preset->name;
        }, $savedpresets);

        // Check available saved presets only contain presets saved manually by users.
        $presets = manager::get_available_saved_presets($context);
        $this->assertCount(count($savedpresets), $presets);
        // Confirm that it contains only the presets created manually.
        foreach ($presets as $preset) {
            $this->assertContains($preset->name, $savedpresetsnames);
            $this->assertFalse($preset->isplugin);
        }

        // Unassign the mod/data:viewalluserpresets capability to the teacher role and check that saved presets are not returned.
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        unassign_capability('mod/data:viewalluserpresets', $teacherrole->id);
        $presets = manager::get_available_saved_presets($context);
        $this->assertCount(0, $presets);
    }

    /**
     * Test for is_directory_a_preset().
     *
     * @dataProvider is_directory_a_preset_provider
     * @covers ::is_directory_a_preset
     * @param string $directory
     * @param bool $expected
     */
    public function test_is_directory_a_preset(string $directory, bool $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = manager::is_directory_a_preset($directory);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for test_is_directory_a_preset().
     *
     * @return array
     */
    public function is_directory_a_preset_provider(): array {
        global $CFG;

        return [
            'Valid preset directory' => [
                'directory' => $CFG->dirroot . '/mod/data/preset/imagegallery',
                'expected' => true,
            ],
            'Invalid preset directory' => [
                'directory' => $CFG->dirroot . '/mod/data/field/checkbox',
                'expected' => false,
            ],
            'Unexisting preset directory' => [
                'directory' => $CFG->dirroot . 'unexistingdirectory',
                'expected' => false,
            ],
        ];
    }

    /**
     * Test for get_plugin_preset_name().
     *
     * @covers ::get_plugin_preset_name
     */
    public function test_get_plugin_preset_name() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // The expected name for plugins with modulename in lang is this value.
        $name = manager::get_plugin_preset_name('imagegallery');
        $this->assertEquals('Image gallery', $name);

        // However, if the plugin doesn't exist or the modulename is not defined, the preset shortname will be returned.
        $presetshortname = 'nonexistingpreset';
        $name = manager::get_plugin_preset_name($presetshortname);
        $this->assertEquals($presetshortname, $name);
    }

}
