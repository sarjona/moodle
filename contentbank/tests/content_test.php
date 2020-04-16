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
 * Test for content bank contenttype class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/contentbank/tests/fixtures/testable_contenttype.php');
require_once($CFG->dirroot . '/contentbank/tests/fixtures/testable_content.php');

use stdClass;
use context_system;
use contenttype_testable\contenttype as contenttype;
/**
 * Test for content bank contenttype class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_contentbank\content
 *
 */
class core_contenttype_content_testcase extends \advanced_testcase {

    /**
     * Tests for behaviour of get_name().
     *
     * @covers ::get_name
     */
    public function test_get_name() {
        $this->resetAfterTest();

        // Create content.
        $record = new stdClass();
        $record->name = 'Test content';
        $record->configdata = '';

        $contenttype = new contenttype(context_system::instance());
        $content = $contenttype->create_content($record);
        $this->assertEquals($record->name, $content->get_name());
    }

    /**
     * Tests for behaviour of get_content_type().
     *
     * @covers ::get_content_type
     */
    public function test_get_content_type() {
        $this->resetAfterTest();

        // Create content.
        $record = new stdClass();
        $record->name = 'Test content';
        $record->configdata = '';

        $contenttype = new contenttype(context_system::instance());
        $content = $contenttype->create_content($record);
        $this->assertEquals('contenttype_testable', $content->get_content_type());
    }

    /**
     * Tests for 'configdata' behaviour.
     *
     * @covers ::set_configdata
     */
    public function test_configdata_changes() {
        $this->resetAfterTest();

        $configdata = "{img: 'icon.svg'}";

        // Create content.
        $record = new stdClass();
        $record->configdata = $configdata;

        $contenttype = new contenttype(context_system::instance());
        $content = $contenttype->create_content($record);
        $this->assertEquals($configdata, $content->get_configdata());

        $configdata = "{alt: 'Name'}";
        $content->set_configdata($configdata);
        $this->assertEquals($configdata, $content->get_configdata());
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
        $systemcontext = context_system::instance();

        // Create users.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign($roleid, $manager->id);
        $this->setUser($manager);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        // Add some content to the content bank as manager.
        $records = $generator->generate_contentbank_data('contenttype_testable', 2, $manager->id, $systemcontext, false);
        $record = array_shift($records);

        // Check the content has been created as expected.
        $this->assertEquals(2, $DB->count_records('contentbank_content'));

        // Check the content is deleted as expected.
        $contenttype = new \contenttype_testable\contenttype($systemcontext);
        $deleted = $contenttype->delete_content($record);
        $this->assertTrue($deleted);
        $this->assertEquals(1, $DB->count_records('contentbank_content'));
    }
}
