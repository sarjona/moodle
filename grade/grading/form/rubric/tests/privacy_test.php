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
 * Privacy tests for gradingform_rubric.
 *
 * @package    gradingform_rubric
 * @category   test
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \core_privacy\tests\provider_testcase;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\writer;
use \gradingform_rubric\privacy\provider;

/**
 * Privacy tests for gradingform_rubric.
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_rubric_privacy_testcase extends provider_testcase {

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
     * Test getting the context for the user ID related to this plugin.
     */
    public function test_get_contexts_for_userid() {
        global $DB;

        $this->resetAfterTest();
        $this->gradingform_rubric_setup_test_scenario_data();
        $this->assertCount(2, $DB->get_records('grading_definitions'));

        // User1 has created grading definitions for instance1 and instance2.
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(2, $contextlist);
        $this->assertContains($this->instancecontext1->id, $contextlist->get_contextids());
        $this->assertContains($this->instancecontext2->id, $contextlist->get_contextids());
        $this->assertNotContains($this->instancecontext0->id, $contextlist->get_contextids());

        // User2 has only modified grading definitions for instance2.
        $contextlist = provider::get_contexts_for_userid($this->user2->id);
        $this->assertCount(1, $contextlist);
        $this->assertContains($this->instancecontext2->id, $contextlist->get_contextids());

        // User0 hasn't created or modified any grading definition.
        $contextlist = provider::get_contexts_for_userid($this->user0->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Test that data is exported correctly for this plugin.
     */
    public function test_export_user_data() {
        global $DB;

        $this->resetAfterTest();
        $now = time();
        $defnameprefix = 'fakename';
        $this->gradingform_rubric_setup_test_scenario_data($defnameprefix, $now);
        $this->assertCount(2, $DB->get_records('grading_definitions'));

        // Validate exported data: $assign1 - $user1 has data.
        $this->setUser($this->user1);
        writer::reset();
        $writer = writer::with_context($this->instancecontext1);
        $this->assertFalse($writer->has_any_data());
        $this->export_context_data_for_user($this->user1->id, $this->instancecontext1, 'gradingform_rubric');
        $data = $writer->get_related_data([], 'gradingmethod');
        $this->assertCount(1, $data->definitions);

        $firstkey = reset($data->definitions);
        $this->assertNotEmpty($firstkey->name);
        $this->assertEquals('rubric', $firstkey->method);
        $this->assertEquals(transform::datetime($now), $firstkey->timecreated);
        $this->assertEquals($this->user1->id, $firstkey->usercreated);
        $this->assertEquals($defnameprefix.'1', $firstkey->name);

        // Validate exported data: $assign1 - $user2 has NO data.
        $this->setUser($this->user2);
        writer::reset();
        $writer = writer::with_context($this->instancecontext1);
        $this->assertFalse($writer->has_any_data());
        $this->export_context_data_for_user($this->user2->id, $this->instancecontext1, 'gradingform_rubric');
        $data = $writer->get_related_data([], 'gradingmethod');
        $this->assertEmpty($data);

        // Validate exported data: $assign1 - $user2 has data.
        writer::reset();
        $writer = writer::with_context($this->instancecontext2);
        $this->assertFalse($writer->has_any_data());
        $this->export_context_data_for_user($this->user2->id, $this->instancecontext2, 'gradingform_rubric');
        $data = $writer->get_related_data([], 'gradingmethod');
        $this->assertCount(1, $data->definitions);

        // Validate exported data: $assign2 - $user2 has data.
        $this->setUser($this->user2);
        writer::reset();
        $writer = writer::with_context($this->instancecontext2);
        $this->assertFalse($writer->has_any_data());
        $this->export_context_data_for_user($this->user2->id, $this->instancecontext2, 'gradingform_rubric');
        $data = $writer->get_related_data([], 'gradingmethod');
        $this->assertCount(1, $data->definitions);

        $firstkey = reset($data->definitions);
        $this->assertNotEmpty($firstkey->name);
        $this->assertEquals('rubric', $firstkey->method);
        $this->assertEquals(transform::datetime($now), $firstkey->timecreated);
        $this->assertEquals($this->user1->id, $firstkey->usercreated);
        $this->assertEquals($defnameprefix.'2', $firstkey->name);
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest();
        $this->gradingform_rubric_setup_test_scenario_data();

        // Before deletion, we should have 2 grading_definitions.
        $this->assertCount(2, $DB->get_records('grading_definitions'));

        // Delete data.
        provider::delete_data_for_all_users_in_context($this->instancecontext0);
        provider::delete_data_for_all_users_in_context($this->instancecontext1);
        provider::delete_data_for_all_users_in_context($this->instancecontext2);

        // Before deletion, we should have same grading_definitions (nothing was deleted).
        $this->assertCount(2, $DB->get_records('grading_definitions'));
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->gradingform_rubric_setup_test_scenario_data();

        // Before deletion, we should have 2 grading_definitions.
        $this->assertCount(2, $DB->get_records('grading_definitions'));

        // Delete data for $user0.
        $contextlist = provider::get_contexts_for_userid($this->user0->id);
        $approvedcontextlist = new approved_contextlist(
            $this->user0,
            'gradingform_rubric',
            $contextlist->get_contextids()
        );
        provider::delete_data_for_user($approvedcontextlist);

        // Delete data for $user1.
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $approvedcontextlist = new approved_contextlist(
            $this->user1,
            'gradingform_rubric',
            $contextlist->get_contextids()
        );
        provider::delete_data_for_user($approvedcontextlist);

        // Delete data for $user2.
        $contextlist = provider::get_contexts_for_userid($this->user2->id);
        $approvedcontextlist = new approved_contextlist(
            $this->user2,
            'gradingform_rubric',
            $contextlist->get_contextids()
        );
        provider::delete_data_for_user($approvedcontextlist);

        // Before deletion, we should have same grading_definitions (nothing was deleted).
        $this->assertCount(2, $DB->get_records('grading_definitions'));
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
    protected function gradingform_rubric_setup_test_scenario_data($defnameprefix = null, $now = null) {
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
            'activemethod' => 'rubric'
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
            'method'       => 'rubric',
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
