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
 * Test for Content bank contenttype class.
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

use stdClass;
use contenttype_testable\contenttype as testable;
/**
 * Test for Content bank contenttype class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_contentbank\contenttype
 *
 */
class core_contenttype_contenttype_testcase extends \advanced_testcase {

    /**
     * Test create_content() with empty data.
     *
     * @covers ::create_content
     */
    public function test_create_empty_content() {
        $this->resetAfterTest();

        // Create empty content.
        $record = new stdClass();

        $content = testable::create_content($record);
        $this->assertEquals(testable::COMPONENT, $content->get_content_type());
        $this->assertInstanceOf('\\contenttype_testable\\contenttype', $content);
    }

    /**
     * Test create_content() from 'contenttype' class.
     *
     * @covers ::create_content
     */
    public function test_create_content_using_contenttype() {
        $this->resetAfterTest();

        // Create empty content.
        $record = new stdClass();

        // This should throw an exception. create_content() should be called using plugins, no using 'base' class.
        $this->expectExceptionMessage("Cannot call abstract method");
        $content = contenttype::create_content($record);
    }

    /**
     * Tests for behaviour of create_content() and getter functions.
     *
     * @covers ::create_content
     */
    public function test_create_content() {
        $this->resetAfterTest();

        // Create content.
        $record = new stdClass();
        $record->name = 'Test content';
        $record->contenttype = testable::COMPONENT;
        $record->contextid = \context_system::instance()->id;
        $record->configdata = '';

        $content = testable::create_content($record);
        $this->assertEquals($record->name, $content->get_name());
        $this->assertEquals($record->contenttype, $content->get_content_type());
        $this->assertEquals($record->configdata, $content->get_configdata());
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

        $content = testable::create_content($record);
        $this->assertEquals($configdata, $content->get_configdata());

        $configdata = "{alt: 'Name'}";
        $content->set_configdata($configdata);
        $this->assertEquals($configdata, $content->get_configdata());
    }

    /**
     * Tests can_upload behavior with no implemented upload feature.
     *
     * @covers ::can_upload
     */
    public function test_no_implemented_feature() {
        $this->resetAfterTest();

        $systemcontext = \context_system::instance();

        // Admins can upload.
        $this->setAdminUser();
        $this->assertEmpty(testable::get_implemented_features());
        $this->assertFalse(testable::is_feature_supported(testable::CAN_UPLOAD));
        $this->assertFalse(testable::can_upload($systemcontext));
    }
}
