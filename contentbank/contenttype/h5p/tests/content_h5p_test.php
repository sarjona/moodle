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
 * @package    contenttype_h5p
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test for H5P content bank plugin.
 *
 * @package    contenttype_h5p
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \contenttype_h5p\content
 */
class contenttype_h5p_content_plugin_testcase extends advanced_testcase {

    /**
     * Tests for uploaded file.
     *
     * @covers ::get_file
     */
    public function test_upload_file() {
        $this->resetAfterTest();

        // Create content.
        $record = new stdClass();
        $record->name = 'Test content';
        $record->configdata = '';
        $contenttype = new \contenttype_h5p\contenttype(context_system::instance());
        $content = $contenttype->create_content($record);

        // Create a dummy file.
        $filename = 'content.h5p';
        $dummy = array(
            'contextid' => \context_system::instance()->id,
            'component' => 'contentbank',
            'filearea' => 'public',
            'itemid' => $content->get_id(),
            'filepath' => '/',
            'filename' => $filename
        );
        $fs = get_file_storage();
        $fs->create_file_from_string($dummy, 'dummy content');

        $file = $content->get_file();
        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertEquals($filename, $file->get_filename());
    }

    /**
     * Test the behaviour of clean_content().
     */
    public function test_clean_content() {
        global $CFG, $USER, $DB;

        $this->resetAfterTest();
        $systemcontext = context_system::instance();

        // Create users.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign($roleid, $manager->id);
        $this->setUser($manager);

        // Add an H5P file to the content bank.
        $filepath = $CFG->dirroot . '/h5p/tests/fixtures/filltheblanks.h5p';
        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        $records = $generator->generate_contentbank_data('contenttype_h5p', 1, $USER->id, $systemcontext, true, $filepath);
        $record = array_shift($records);

        // Load this H5P file though the player to create the H5P DB entries.
        $h5pplayer = new \core_h5p\player($record->get_file_url(), new \stdClass(), true);
        $h5pplayer->add_assets_to_page();
        $h5pplayer->output();

        // Check the H5P content has been created.
        $this->assertEquals(1, $DB->count_records('h5p'));
        $this->assertEquals(1, $DB->count_records('contentbank_content'));

        // Check only the H5P content is removed after calling the clean_content method.
        $record->clean_content();
        $this->assertEquals(0, $DB->count_records('h5p'));
        $this->assertEquals(1, $DB->count_records('contentbank_content'));
    }
}
