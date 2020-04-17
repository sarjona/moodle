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
require_once($CFG->dirroot . '/contentbank/tests/fixtures/testable_contenttype.php');
require_once($CFG->dirroot . '/contentbank/tests/fixtures/testable_content.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_contentbank\external\external;
use core_contentbank\external\rename_content;
use external_api;

/**
 * Core content bank external functions tests.
 *
 * @package    core_contentbank
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_contentbank\external
 */
class core_contentbank_external_testcase extends \externallib_advanced_testcase {

    /**
     * Data provider for test_rename_content.
     *
     * @return  array
     */
    public function rename_content_provider() {
        return [
            'Standard name' => ['New name', 'New name'],
            'Name with digits' => ['Today is 17/04/2017', 'Today is 17/04/2017'],
            'Name with symbols' => ['Follow us: @moodle', 'Follow us: @moodle'],
            'Name with tags' => ['This is <b>bold</b>', 'This is bold'],
            'Long name' => [str_repeat('a', 100), str_repeat('a', 100)],
            'Too long name' => [str_repeat('a', 300), str_repeat('a', 255)]
        ];
    }

    /**
     * Test the behaviour of rename_content().

     * @dataProvider    rename_content_provider
     * @param   string  $newname    The name to set
     * @param   string   $expected   The name result
     *
     * @covers ::execute
     */
    public function test_rename_content(string $newname, string $expected) {
        global $DB;
        $this->resetAfterTest();

        // Create users.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->role_assign($roleid, $teacher->id);
        $this->setUser($teacher);

        // Add some content to the content bank as manager.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_contentbank');
        $records = $generator->generate_contentbank_data('contenttype_testable', 1, $teacher->id, null, false);
        $record = array_shift($records);

        $oldname = $record->name;

        // Call the WS and check the content is renamed as expected.
        $result = rename_content::execute($record->id, $newname);
        $result = external_api::clean_returnvalue(rename_content::execute_returns(), $result);
        $this->assertTrue($result);
        $record = $DB->get_record('contentbank_content', ['id' => $record->id]);
        $this->assertNotEquals($oldname, $record->name);
        $this->assertEquals($expected, $record->name);

        // Call the WS using an unexisting contentid and check an error is thrown.
        $this->expectException(\invalid_response_exception::class);
        $result = rename_content::execute_returns($record->id + 1, $oldname);
        $result = external_api::clean_returnvalue(rename_content::execute_returns(), $result);
        $this->assertFalse($result);
    }
}
