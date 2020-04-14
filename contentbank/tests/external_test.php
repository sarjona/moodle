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
 * Core content bank external functions tests.
 *
 * @package    core_contentbank
 * @category   external
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.9
 */

namespace core_contentbank;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use external_api;

/**
 * Core content bank external functions tests.
 *
 * @package    core_contentbank
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_contentbank_external_testcase extends \externallib_advanced_testcase {

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

        // Add some content to the content bank as manager.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        $records = $generator->generate_contentbank_data(\contentbank_testable\plugin::COMPONENT, 2, $manager->id, false);
        $record = array_shift($records);

        // Check the content has been created as expected.
        $this->assertEquals(2, $DB->count_records('contentbank_content'));

        // Call the WS and check the content is deleted as expected.
        $result = external::delete_content($record->id);
        $result = external_api::clean_returnvalue(external::delete_content_returns(), $result);
        $this->assertTrue($result);
        $this->assertEquals(1, $DB->count_records('contentbank_content'));

        // Call the WS using an unexisting contentid and check the content is deleted as expected.
        $record = array_shift($records);
        $this->expectException(\dml_missing_record_exception::class);
        $result = external::delete_content($record->id + 1);
        $result = external_api::clean_returnvalue(external::delete_content_returns(), $result);
        $this->assertFalse($result);
        $this->assertEquals(1, $DB->count_records('contentbank_content'));
    }
}
