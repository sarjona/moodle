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
 * Test for H5P content bank plugin.
 *
 * @package    contentbank_h5p
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test for H5P content bank plugin.
 *
 * @package    contentbank_h5p
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contentbank_h5p_content_plugin_testcase extends advanced_testcase {

    /**
     * Tests can_upload behavior.
     */
    public function test_can_upload() {
        global $DB;

        $this->resetAfterTest();

        $roleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign($roleid, $manager->id);

        $this->setUser($manager);
        $this->assertTrue(contentbank_h5p\plugin::can_upload());

        unassign_capability('contentbank/h5p:additem', $roleid);
        $this->assertFalse(contentbank_h5p\plugin::can_upload());
    }

    /**
     * Test the behaviour of clean_content().
     */
    public function test_clean_content() {
        global $CFG, $USER, $DB;

        $this->resetAfterTest();

        // Add an H5P file to the content bank.
        $filepath = $CFG->dirroot . '/h5p/tests/fixtures/filltheblanks.h5p';
        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        $records = $generator->generate_contentbank_data(\contentbank_h5p\plugin::COMPONENT, 1, $USER->id, true, $filepath);
        $record = array_shift($records);
        $record->get_view_content();

        // Check the H5P content has been created.
        $this->assertEquals(1, $DB->count_records('h5p'));
        $this->assertEquals(1, $DB->count_records('contentbank_content'));

        // Check only the H5P content is removed after calling the clean_content method.
        $record->clean_content();
        $this->assertEquals(0, $DB->count_records('h5p'));
        $this->assertEquals(1, $DB->count_records('contentbank_content'));
    }
}
