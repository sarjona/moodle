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
        $dummy = [
            'contextid' => \context_system::instance()->id,
            'component' => 'contentbank',
            'filearea' => 'public',
            'itemid' => $content->get_id(),
            'filepath' => '/',
            'filename' => $filename
        ];
        $fs = get_file_storage();
        $fs->create_file_from_string($dummy, 'dummy content');

        $file = $content->get_file();
        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertEquals($filename, $file->get_filename());
    }


    /**
     * Data provider for test_set_name.
     *
     * @return  array
     */
    public function set_name_provider() {
        return [
            'Standard name' => ['New name', 'New name'],
            'Name with tags' => ['This is <b>bold</b>', 'This is bold'],
            'Too long name' => [str_repeat('a', 300), str_repeat('a', 255)],
        ];
    }

    /**
     * Tests for 'set_name' behaviour.
     *
     * @dataProvider  set_name_provider
     * @param  string $newname         The name to set.
     * @param  string $expected        The name result.
     *
     * @covers ::set_name
     */
    public function test_set_name(string $newname, string $expected) {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $oldname = 'Geography';
        $context = context_system::instance();

        // Add an H5P file to the content bank.
        $filepath = $CFG->dirroot . '/h5p/tests/fixtures/filltheblanks.h5p';
        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        $contents = $generator->generate_contentbank_data('contenttype_h5p', 1, 0, $context, true, $filepath, $oldname);
        $content = array_shift($contents);

        // Deploy the content through the player to create the H5P DB entries.
        $h5pplayer = new \core_h5p\player($content->get_file_url(), new \stdClass(), true);
        $h5pplayer->add_assets_to_page();
        $h5pplayer->output();

        // Check the content title has the expected value.
        $this->assertEquals($oldname, $content->get_name());
        $h5p = \core_h5p\api::get_content_from_pathnamehash($content->get_file()->get_pathnamehash());
        $jsoncontent = json_decode($h5p->jsoncontent);
        $this->assertEquals($oldname, $jsoncontent->title);

        // Check when $shouldbeupdated is set to false, DB is not updated.
        $content->set_name($newname, false);
        $this->assertEquals($expected, $content->get_name());
        $record = $DB->get_record('contentbank_content', ['id' => $content->get_id()]);
        $h5p = \core_h5p\api::get_content_from_pathnamehash($content->get_file()->get_pathnamehash());
        $jsoncontent = json_decode($h5p->jsoncontent);
        $this->assertEquals($oldname, $record->name);
        $this->assertEquals($oldname, $jsoncontent->title);

        // Check when $shouldbeupdated is empty (so default true value used), DB is updated properly.
        $content->set_name($newname);
        $this->assertEquals($expected, $content->get_name());
        $record = $DB->get_record('contentbank_content', ['id' => $content->get_id()]);
        $h5p = \core_h5p\api::get_content_from_pathnamehash($content->get_file()->get_pathnamehash());
        $jsoncontent = json_decode($h5p->jsoncontent);
        $this->assertEquals($expected, $record->name);
        $this->assertEquals($expected, $jsoncontent->title);
    }
}
