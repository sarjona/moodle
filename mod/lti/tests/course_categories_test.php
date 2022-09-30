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
 * Unit tests for mod_lti edit_form
 *
 * @package    mod_lti
 * @copyright  2022 Jackson D'Souza <jackson.dsouza@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.1
 */
namespace mod_lti;

/**
 * Unit tests for mod_lti edit_form
 *
 * @package    mod_lti
 * @copyright  2022 Jackson D'Souza <jackson.dsouza@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.1
 */
class course_categories_test extends \advanced_testcase {

    /*
     * Setup course categories.
     *
     * @return array
     */
    protected function setup_course_categories() : array {
        global $DB;

        $topcatdbrecord = $DB->get_record('course_categories', ['parent' => 0]);

        $subcata = $this->getDataGenerator()->create_category(['parent' => $topcatdbrecord->id, 'name' => 'cata']);
        $subcatadbrecord = $DB->get_record('course_categories', ['id' => $subcata->id]);

        $subcatca = $this->getDataGenerator()->create_category(['parent' => $subcata->id, 'name' => 'catca']);
        $subcatcadbrecord = $DB->get_record('course_categories', ['id' => $subcatca->id]);

        $subcatb = $this->getDataGenerator()->create_category(['parent' => $topcatdbrecord->id, 'name' => 'catb']);
        $subcatbdbrecord = $DB->get_record('course_categories', ['id' => $subcatb->id]);

        $subcatcb = $this->getDataGenerator()->create_category(['parent' => $subcatb->id, 'name' => 'catcb']);
        $subcatcbdbrecord = $DB->get_record('course_categories', ['id' => $subcatcb->id]);

        return [
            'topcat' => $topcatdbrecord,
            'subcata' => $subcatadbrecord,
            'subcatca' => $subcatcadbrecord,
            'subcatb' => $subcatb,
            'subcatcb' => $subcatcbdbrecord
        ];
    }

    /**
     * Tests the nested course categories JSON returned by public method lti_build_category_tree().
     *
     * @covers ::lti_build_category_tree
     */
    public function test_set_nested_categories() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/lti/tests/fixtures/test_edit_form.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $ltiform = new test_edit_form(null);
        $ltiform->definition_after_data();

        // Setup fixture.
        $coursecategories = $this->setup_course_categories();

        $categoryarray[] = [
            "id" => $coursecategories['topcat']->id,
            "parent" => $coursecategories['topcat']->parent,
            "name" => $coursecategories['topcat']->name,
            "nodes" => [
                            [
                                "id" => $coursecategories['subcata']->id,
                                "parent" => $coursecategories['topcat']->id,
                                "name" => $coursecategories['subcata']->name,
                                "nodes" => [
                                    [
                                        "id" => $coursecategories['subcatca']->id,
                                        "parent" => $coursecategories['subcata']->id,
                                        "name" => $coursecategories['subcatca']->name,
                                        "nodes" => "",
                                        "haschildren" => ""
                                    ]
                                ],
                                "haschildren" => "1"
                            ],
                            [
                                "id" => $coursecategories['subcatb']->id,
                                "parent" => $coursecategories['topcat']->id,
                                "name" => $coursecategories['subcatb']->name,
                                "nodes" => [
                                    [
                                        "id" => $coursecategories['subcatcb']->id,
                                        "parent" => $coursecategories['subcatb']->id,
                                        "name" => $coursecategories['subcatcb']->name,
                                        "nodes" => "",
                                        "haschildren" => ""
                                    ]
                                ],
                                "haschildren" => "1"
                            ]
                    ],
            "haschildren" => "1"
        ];

        $records = $DB->get_records('course_categories', [], 'sortorder, id', 'id, parent, name');
        $allcategories = json_decode(json_encode($records), true);
        $coursecategoriesarray = $ltiform->lti_build_category_tree($allcategories);

        $this->assertEquals($categoryarray, $coursecategoriesarray);
    }

    /**
     * Tests the LTI tool restricted course categories backup and restore in a course on the same site.
     * On Course restore, it should create LTI tool and assign the restricted course categories to LTI module.
     *
     * @covers \restore_lti_activity_structure_step::process_ltitype
     */
    public function test_backup_restore_restricted_categories() {
        global $CFG, $DB;

        // Include the necessary files to perform backup and restore.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $admin = get_admin();
        $time = time();

        // Setup fixture.
        $coursecategories = $this->setup_course_categories();

        // Create a course to backup.
        $course1 = $this->getDataGenerator()->create_course(['category' => $coursecategories['subcata']->id]);

        // Create a course to restore above course backup in a different sub category.
        $course2 = $this->getDataGenerator()->create_course(['category' => $coursecategories['subcatca']->id]);

        // Restrict LTI to course categories.
        $restrictcoursecategories = $coursecategories['subcata']->id . ','
                                        . $coursecategories['subcatca']->id . ','
                                        . $coursecategories['subcatcb']->id;

        // Create LTI tool.
        $course1toolrecord = (object) [
            'name' => 'Course created tool which is available in the activity chooser',
            'baseurl' => 'http://example3.com',
            'createdby' => $admin->id,
            'course' => $course1->id,
            'coursecategories' => $restrictcoursecategories,
            'ltiversion' => 'LTI-1p0',
            'timecreated' => $time,
            'timemodified' => $time,
            'state' => LTI_TOOL_STATE_CONFIGURED,
            'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER
        ];
        $tool1id = $DB->insert_record('lti_types', $course1toolrecord);

        // Add LTI tool to course.
        $lti = $this->getDataGenerator()->create_module(
            'lti',
            ['course' => $course1->id, 'typeid' => $tool1id]
        );

        // Backup the course.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course1->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, 2);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/test-restore-course';
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        // Now restore the course.
        $rc = new \restore_controller('test-restore-course', $course2->id, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, 2, \backup::TARGET_NEW_COURSE);
        $rc->execute_precheck();
        $rc->execute_plan();

        $ltirecords = $DB->get_records('lti_types');

        // There should only be two LTI tool records.
        $this->assertEquals(2, count($ltirecords));

        $originallti = array_shift($ltirecords);
        $restoredlti = array_shift($ltirecords);

        // Restored LTI should belong to Course 2.
        $this->assertEquals($course2->id, $restoredlti->course);

        // Course category restriction should match.
        $this->assertEquals($originallti->coursecategories, $restoredlti->coursecategories);
    }
}
