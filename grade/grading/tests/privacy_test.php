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
 * Privacy tests for core_grading.
 *
 * @package    core_grading
 * @category   test
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \core_privacy\tests\provider_testcase;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\writer;
use \core_grading\privacy\provider;

/**
 * Privacy tests for core_grading.
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_grading_privacy_testcase extends provider_testcase {

    /** @var stdClass User without data. */
    protected $user0;

    /** @var stdClass User with data. */
    protected $user1;

    /** @var stdClass User with data. */
    protected $user2;

    /** @var context context_module of an activity without grading definitions. */
    protected $instancecontext0;

    /** @var context context_module of the activity where the grading definitions are. */
    protected $instancecontext1;

    /** @var context context_module of the activity where the grading definitions are. */
    protected $instancecontext2;

    /**
     * Export for a user with no grading definitions created or modified will not have any data exported.
     */
    public function test_export_definitions_no_content() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_system::instance();

        provider::export_definitions($context, [], 'test_method');

        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    /**
     * Export for a user with a grading definition against a method.
     */
    public function test_export_definitions() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $defnameprefix = 'fakename';
        $this->grading_setup_test_scenario_data($defnameprefix, $now);

        // Export the definitions for the assign1 context.
        $subcontext = [];
        provider::export_definitions($this->instancecontext1, $subcontext, 'test_method');
        $writer = writer::with_context($this->instancecontext1);

        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_related_data($subcontext, 'gradingmethod');
        $this->assertCount(1, $exported->definitions);

        $firstkey = reset($exported->definitions);
        $this->assertNotEmpty($firstkey->name);
        $this->assertEquals('test_method', $firstkey->method);
        $this->assertEquals(transform::datetime($now), $firstkey->timecreated);
        $this->assertEquals($this->user1->id, $firstkey->usercreated);
        $this->assertEquals($defnameprefix.'1', $firstkey->name);

        // Export the definitions for the assign2 context.
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext2, $subcontext, 'test_method');
        $writer = writer::with_context($this->instancecontext2);

        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_related_data($subcontext, 'gradingmethod');
        $this->assertCount(1, $exported->definitions);

        // Export the definitions for the assign3 context (should be empty).
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext0, $subcontext, 'test_method');
        $writer = writer::with_context($this->instancecontext0);

        $this->assertFalse($writer->has_any_data());

        // Export the definitions for $assign1 - $user1 (has created and modified it).
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext1, $subcontext, 'test_method', $this->user1->id);
        $writer = writer::with_context($this->instancecontext1);

        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_related_data($subcontext, 'gradingmethod');
        $this->assertCount(1, $exported->definitions);

        // Export the definitions for $assign1 - $user2 (nothing has to be exported).
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext1, $subcontext, 'test_method', $this->user2->id);
        $writer = writer::with_context($this->instancecontext1);

        $this->assertFalse($writer->has_any_data());

        // Export the definitions for $assign1 - $user0 (nothing has to be exported).
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext1, $subcontext, 'test_method', $this->user0->id);
        $writer = writer::with_context($this->instancecontext1);

        $this->assertFalse($writer->has_any_data());

        // Export the definitions for $assign2 - $user1 (has created it).
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext2, $subcontext, 'test_method', $this->user1->id);
        $writer = writer::with_context($this->instancecontext2);

        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_related_data($subcontext, 'gradingmethod');
        $this->assertCount(1, $exported->definitions);

        // Export the definitions for $assign2 - $user2 (has modified it).
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext2, $subcontext, 'test_method', $this->user2->id);
        $writer = writer::with_context($this->instancecontext2);

        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_related_data($subcontext, 'gradingmethod');
        $this->assertCount(1, $exported->definitions);

        // Export the definitions for $assign2 - $user0 (nothing has to be exported).
        writer::reset();
        $subcontext = [];
        provider::export_definitions($this->instancecontext2, $subcontext, 'test_method', $this->user0->id);
        $writer = writer::with_context($this->instancecontext2);

        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Delete all grading definitions of a method.
     */
    public function test_delete_definitions() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->grading_setup_test_scenario_data();
        $this->assertCount(2, $DB->get_records('grading_definitions'));

        // Delete all grading definitions for a method.
        provider::delete_definitions('test_method');
        $this->assertCount(0, $DB->get_records('grading_definitions'));
    }

    /**
     * Delete grading definitions of a method for a user.
     */
    public function test_delete_definitions_by_userid() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->grading_setup_test_scenario_data();
        $this->assertCount(2, $DB->get_records('grading_definitions'));

        // Delete grading definitions for $user0 (nothing to remove).
        provider::delete_definitions('test_method', $this->user0->id);
        $this->assertCount(2, $DB->get_records('grading_definitions'));

        // Delete grading definitions for $user2.
        provider::delete_definitions('test_method', $this->user2->id);
        $this->assertCount(1, $DB->get_records('grading_definitions'));

        // Delete grading definitions for $user1.
        provider::delete_definitions('test_method', $this->user1->id);
        $this->assertCount(0, $DB->get_records('grading_definitions'));
    }

    /**
     * Helper function to setup 3 users, 1 course, 2 assignments and 2 grading definitions:
     * - $user0 hasn't any data.
     * - $user1 has created both grading definition and modified $gradingdef1.
     * - $user2 has modified $gradingdef2.
     *
     * @param string $defnameprefix
     * @param timestamp $now
     */
    protected function grading_setup_test_scenario_data($defnameprefix = null, $now = null) {
        global $DB;

        $this->user0 = $this->getDataGenerator()->create_user();
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);

        // Create some assignment instances.
        $params = (object)array(
            'course' => $course->id,
            'name'   => 'Testing instance'
        );
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance1 = $generator->create_instance($params);
        $cm1 = get_coursemodule_from_instance('assign', $instance1->id);
        $this->instancecontext1 = context_module::instance($cm1->id);
        $instance2 = $generator->create_instance($params);
        $cm2 = get_coursemodule_from_instance('assign', $instance2->id);
        $this->instancecontext2 = context_module::instance($cm2->id);
        $instance3 = $generator->create_instance($params);
        $cm3 = get_coursemodule_from_instance('assign', $instance3->id);
        $this->instancecontext0 = context_module::instance($cm3->id);

        // Create fake grading areas.
        $fakearea1 = (object)array(
            'contextid'    => $this->instancecontext1->id,
            'component'    => 'mod_assign',
            'areaname'     => 'submissions',
            'activemethod' => 'test_method'
        );
        $fakeareaid1 = $DB->insert_record('grading_areas', $fakearea1);
        $fakearea2 = clone($fakearea1);
        $fakearea2->contextid = $this->instancecontext2->id;
        $fakeareaid2 = $DB->insert_record('grading_areas', $fakearea2);

        // Create fake grading definitions.
        if (empty($now)) {
            $now = time();
        }
        if (empty($defnameprefix)) {
            $defnameprefix = 'fakename';
        }
        $fakedefinition1 = (object)array(
            'areaid'       => $fakeareaid1,
            'method'       => 'test_method',
            'name'         => $defnameprefix.'1',
            'status'       => 0,
            'timecreated'  => $now,
            'usercreated'  => $this->user1->id,
            'timemodified' => $now + 1,
            'usermodified' => $this->user1->id,
        );
        $fakedefid1 = $DB->insert_record('grading_definitions', $fakedefinition1);
        $fakedefinition2 = clone($fakedefinition1);
        $fakedefinition2->areaid = $fakeareaid2;
        $fakedefinition2->name = $defnameprefix.'2';
        $fakedefinition2->usermodified = $this->user2->id;
        $fakedefid2 = $DB->insert_record('grading_definitions', $fakedefinition2);
    }
}
