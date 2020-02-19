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
 * Test for Content bank base class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use core_contentbank\base;
use contentbank_h5p\plugin as h5pplugin;
/**
 * Test for Content bank base class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_contentbank_content_base_testcase extends \advanced_testcase {

    /**
     * Test create_content() with empty data.
     */
    public function test_create_empty_content() {
        $this->resetAfterTest();

        // Create empty content.
        $record = new stdClass();

        $content = h5pplugin::create_content($record);
        $this->assertEquals(h5pplugin::COMPONENT, $content->get_content_type());
        $this->assertInstanceOf('\\contentbank_h5p\\plugin', $content);
    }

    /**
     * Test create_content() from 'base' class.
     */
    public function test_create_content_using_base() {
        $this->resetAfterTest();

        // Create empty content.
        $record = new stdClass();

        // This should throw an exception. create_content() should be called using plugins, no using 'base' class.
        $this->expectException('coding_exception');
        $content = base::create_content($record);
    }

    /**
     * Tests for behaviour of create_content() and getter functions.
     */
    public function test_create_content() {
        $this->resetAfterTest();

        // Create content.
        $record = new stdClass();
        $record->name = 'Test content';
        $record->contenttype = h5pplugin::COMPONENT;
        $record->contextid = \context_system::instance()->id;
        $record->configdata = '';

        $content = h5pplugin::create_content($record);
        $this->assertEquals($record->name, $content->get_name());
        $this->assertEquals($record->contenttype, $content->get_content_type());
        $this->assertEquals($record->configdata, $content->get_configdata());
    }

    /**
     * Tests for 'configdata' behaviour.
     */
    public function test_configdata_changes() {
        $this->resetAfterTest();

        $configdata = "{img: 'icon.svg'}";

        // Create content.
        $record = new stdClass();
        $record->configdata = $configdata;

        $content = h5pplugin::create_content($record);
        $this->assertEquals($configdata, $content->get_configdata());

        $configdata = "{alt: 'Name'}";
        $content->set_configdata($configdata);
        $this->assertEquals($configdata, $content->get_configdata());
    }

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
        $this->assertTrue(base::can_upload());

        unassign_capability('moodle/contentbank:upload', $roleid);
        $this->assertFalse(base::can_upload());
    }

    /**
     * Tests for uploaded file.
     */
    public function test_upload_file() {
        $this->resetAfterTest();

        // Create content.
        $record = new stdClass();
        $record->name = 'Test content';
        $record->contenttype = h5pplugin::COMPONENT;
        $record->contextid = \context_system::instance()->id;
        $record->configdata = '';
        $content = h5pplugin::create_content($record);

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
     * Test the behaviour of can_delete().
     */
    public function test_can_delete() {
        global $DB;

        $this->resetAfterTest();

        // Create users.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $manager = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign($roleid, $manager->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        // Add some content to the content bank as manager.
        $recordsbymanager = $generator->generate_contentbank_data(null, 3, $manager->id);
        $recordbymanager = array_shift($recordsbymanager);
        // Add some content to the content bank as user.
        $recordsbyuser = $generator->generate_contentbank_data(null, 1, $user->id);
        $recordbyuser = array_shift($recordsbyuser);
        // Check the content has been created as expected.
        $records = $DB->count_records('contentbank_content');
        $this->assertEquals(4, $records);

        // Check user can only delete records created by her.
        $this->setUser($user);
        $this->assertFalse($recordbymanager->can_delete());
        $this->assertTrue($recordbyuser->can_delete());
        // Check manager can delete records all the records created.
        $this->setUser($manager);
        $this->assertTrue($recordbymanager->can_delete());
        $this->assertTrue($recordbyuser->can_delete());
        // Unassign capability to manager role and check not can only delete their own records.
        unassign_capability('moodle/contentbank:deleteanycontent', $roleid);
        $this->assertTrue($recordbymanager->can_delete());
        $this->assertFalse($recordbyuser->can_delete());
    }

    /**
     * Test the behaviour of delete_content().
     */
    public function test_delete_content() {
        global $DB;

        $this->resetAfterTest();

        // Create users.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign($roleid, $manager->id);
        $this->setUser($manager);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        // Add some content to the content bank as manager.
        $records = $generator->generate_contentbank_data(\contentbank_testable\plugin::COMPONENT, 2, $manager->id, false);
        $record = array_shift($records);

        // Check the content has been created as expected.
        $this->assertEquals(2, $DB->count_records('contentbank_content'));

        // Check the content is deleted as expected.
        $deleted = \contentbank_testable\plugin::delete_content($record);
        $this->assertTrue($deleted);
        $this->assertEquals(1, $DB->count_records('contentbank_content'));
    }
}
