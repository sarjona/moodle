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

namespace core_course\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the course_edit class.
 *
 * @package    core_course
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_course\external\course_edit
 */
class course_edit_test extends \externallib_advanced_testcase {

    /** @var array Sections in the testing course. */
    private $sections = [];

    /** @var array Activities in the testing course. */
    private $activities = [];

    /**
     * Setup to ensure that fixtures are loaded.
     */
    public static function setupBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/course/tests/fixtures/format_theunittest.php');
        require_once($CFG->dirroot . '/course/tests/fixtures/format_theunittest_output_course_format_state.php');
        require_once($CFG->dirroot . '/course/tests/fixtures/format_theunittest_stateactions.php');
    }

    /**
     * Test the behaviour of course_edit::execute() for course modules visibility updates, such as cm_hide or cm_show.
     *
     * @dataProvider cm_update_visibility_provider
     * @covers ::execute
     *
     * @param string $action Action to execute (cm_hide, cm_show...).
     * @param string $format The course will be created with this course format.
     * @param string $role The role of the user that will execute the method.
     * @param array $expectedresults List of the course module names expected after calling the method.
     * @param bool $expectedvisibility The expected visibility after running the given action.
     * @param string|null $expectedexception If this call will raise an exception, this is its name.
     * @param bool $unexistingactivities Whether activities should exist or not.
     */
    public function test_cm_update_visibility(string $action, string $format, string $role, array $expectedresults,
            bool $expectedvisibility, ?string $expectedexception = null, bool $unexistingactivities = false): void {
        global $USER;

        $this->resetAfterTest();

        // Create a course with 5 sections, 1 of them hidden.
        $course = $this->getDataGenerator()->create_course(['format' => $format]);
        $hiddensections = [2];
        foreach ($hiddensections as $section) {
            set_section_visible($course->id, $section, 0);
        }

        // Create and enrol user using given role.
        $isadmin = ($role == 'admin');
        $canedit = $isadmin || ($role == 'editingteacher' || $role == 'manager');
        if ($isadmin) {
            $this->setAdminUser();
        } else {
            $user = $this->getDataGenerator()->create_user();
            if ($role != 'unenroled') {
                $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
            }
            $this->setUser($user);
        }

        // Add some activities to the course (2 to the section 1, which is visible and 2 more to section 2 which is hidden).
        $this->create_activity($course->id, 'assign', 1, true, $canedit);
        $this->create_activity($course->id, 'book', 1, false, $canedit);
        $this->create_activity($course->id, 'glossary', 2, true, $canedit);
        $this->create_activity($course->id, 'page', 2, false, $canedit);

        if ($unexistingactivities) {
            // Increasing id we can guarantee that one of the activities won't exist.
            $activities = array_map(function ($value) {
                return $value + 1;
            }, array_keys($this->activities));
        } else {
            $activities = array_keys($this->activities);
        }

        try {
            // Execute course action.
            $results = json_decode(course_edit::execute($action, $course->id, $activities));

            // Check state returned after executing given action.
            $expectedname = 'cm';
            $expectedaction = 'update';
            $this->assertCount(count($expectedresults), $results);
            foreach ($results as $result) {
                $this->assertEquals($expectedname, $result->name);
                $this->assertEquals($expectedaction, $result->action);
                $this->assertEquals($expectedvisibility, $result->fields->visible);
                $this->assertTrue(in_array($result->fields->name, $expectedresults));
            }
        } catch (\Exception $e) {
            // Check the exception is the expected.
            $this->assertTrue(is_a($e, $expectedexception));

            // Check none of the course modules have changed.
            $modinfo = \course_modinfo::instance($course);
            foreach ($activities as $cmid) {
                if (array_key_exists($cmid, $this->activities)) {
                    $cm = $modinfo->get_cm($cmid);
                    $this->assertEquals($this->activities[$cmid]->visible, $cm->visible);
                }
            }
        } finally {
            if ($format == 'social' || $format == 'theunittest') {
                // The course format hasn't the renderer file, so a debugging message will be displayed.
                $this->assertDebuggingCalled();
            }
        }
    }

    /**
     * Data provider for test_cm_update_visibility().
     *
     * @return array
     */
    public function cm_update_visibility_provider(): array {
        return array_merge(
            $this->cm_update_visibility_provider_generator('cm_hide', false),
            $this->cm_update_visibility_provider_generator('cm_show', true),
        );
    }

    /**
     * Helper method to generate data provider for test_cm_update_visibility.
     *
     * @param string $action Action to execute
     * @param bool $expectedvisibility Expected visibility once the given action will be run.
     * @return array
     */
    private function cm_update_visibility_provider_generator(string $action, bool $expectedvisibility): array {
        $activities = [
            0 => ['Assignment 1', 'Glossary 1'],
            1 => ['Book 1', 'Page 1'],
        ];

        return [
            // ROLES. Testing behaviour depending on the user role calling the method.
            "Course module action as admin should work ($action)" => [
                'action' => $action,
                'format' => 'topics',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Course module action as editingteacher should work ($action)" => [
                'action' => $action,
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Course module action as student should raise an exception ($action)" => [
                'action' => $action,
                'format' => 'topics',
                'role' => 'student',
                'expectedresults' => $activities[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'required_capability_exception',
            ],
            "Course module action as unenroled user should raise an exception ($action)" => [
                'action' => $action,
                'format' => 'topics',
                'role' => 'unenroled',
                'expectedresults' => $activities[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],

            // COURSEFORMAT. Test behaviour depending on course formats.
            "Weeks format should work ($action)" => [
                'action' => $action,
                'format' => 'weeks',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Social format should work ($action)" => [
                'action' => $action,
                'format' => 'social',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Single activity format should work ($action)" => [
                'action' => $action,
                'format' => 'singleactivity',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Theunittest format should work ($action)" => [
                'action' => $action,
                'format' => 'theunittest',
                'role' => 'admin',
                'expectedresults' => $activities[(int) !$expectedvisibility], // This course format overwrites visibility methods.
                'expectedvisibility' => !$expectedvisibility, // This course format overwrites visibility methods.
            ],

            // UNEXISTING. Test with unexisting data (action, course modules...).
            "Unexisting course module ($action)" => [
                'action' => $action,
                'format' => 'topics',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
                'unexistingactivities' => true,
            ],
            "Unexisting action ($action)" => [
                'action' => 'cm_unexisting',
                'format' => 'topics',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
        ];
    }

    /**
     * Test the behaviour of course_edit::execute() for section visibility updates, such as section_hide or section_show.
     *
     * @dataProvider section_update_visibility_provider
     * @covers ::execute
     *
     * @param string $action Action to execute (section_hide, section_show...).
     * @param array $sectionnums Sections to execute the given action.
     * @param string $format The course will be created with this course format.
     * @param string $role The role of the user that will execute the method.
     * @param array $expectedsections List of the section numbers changed after calling the method.
     * @param array $expectedcms List of the course module names changed after calling the method.
     * @param bool $expectedvisibility The expected visibility after running the given action.
     * @param string|null $expectedexception If this call will raise an exception, this is its name.
     */
    public function test_section_update_visibility(string $action, array $sectionnums, string $format, string $role,
            array $expectedsections, array $expectedcms, bool $expectedvisibility, ?string $expectedexception = null): void {
        global $USER;

        $this->resetAfterTest();

        // Create a course with 4 sections, 2 of them hidden.
        $numsections = 4;
        $course = $this->getDataGenerator()->create_course(['numsections' => $numsections, 'format' => $format]);
        $hiddensections = [2, 4];
        foreach ($hiddensections as $section) {
            set_section_visible($course->id, $section, 0);
        }

        // Create and enrol user using given role.
        $isadmin = ($role == 'admin');
        $canedit = $isadmin || ($role == 'editingteacher' || $role == 'manager');
        if ($isadmin) {
            $this->setAdminUser();
        } else {
            $user = $this->getDataGenerator()->create_user();
            if ($role != 'unenroled') {
                $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
            }
            $this->setUser($user);
        }

        // Add some activities to the course (2 to the section 1, which is visible and 2 more to section 2 which is hidden).
        $this->create_activity($course->id, 'assign', 1, true, $canedit);
        $this->create_activity($course->id, 'book', 1, false, $canedit);
        $this->create_activity($course->id, 'glossary', 2, true, $canedit);
        $this->create_activity($course->id, 'page', 2, false, $canedit);

        try {
            $results = json_decode(course_edit::execute($action, $course->id, $sectionnums));

            // Calculate sections indexed by id to make easier to compare results.
            $sectionsbyid = [];
            $modinfo = get_fast_modinfo($course);
            $allsections = $modinfo->get_section_info_all();
            foreach ($allsections as $section) {
                $sectionsbyid[$section->id] = $section;
            }
            // Check state returned after executing given action.
            $this->assertCount(count($expectedsections) + count($expectedcms), $results);
            foreach ($results as $result) {
                $this->assertEquals('update', $result->action);
                $this->assertEquals($expectedvisibility, $result->fields->visible);
                if ($result->name == 'section') {
                    // Check section information.
                    $sectionnum = $sectionsbyid[$result->fields->id]->section;
                    $this->assertTrue(in_array($sectionnum, $expectedsections));
                    foreach ($result->fields->cmlist as $cmid) {
                        $this->assertTrue(in_array($cmid, $this->sections[$sectionnum]));
                    }
                } else if ($result->name == 'cm') {
                    // Check course module information.
                    $this->assertEquals($expectedvisibility, $result->fields->visible);
                    $this->assertTrue(in_array($result->fields->name, $expectedcms));
                }
            }
        } catch (\Exception $e) {
            // Check the exception is the expected.
            $this->assertTrue(is_a($e, $expectedexception));

            // Check none of the sections have changed.
            $modinfo = \course_modinfo::instance($course);
            $sections = $modinfo->get_section_info_all();
            foreach ($sectionnums as $sectionnum) {
                if (in_array($sectionnum, $hiddensections)) {
                    $this->assertFalse(filter_var($sections[$sectionnum]->visible, FILTER_VALIDATE_BOOLEAN));
                } else if (array_key_exists($sectionnum, $sections)) {
                    $this->assertTrue(filter_var($sections[$sectionnum]->visible, FILTER_VALIDATE_BOOLEAN));
                }
            }
        } finally {
            if ($format == 'social' || $format == 'theunittest') {
                // The course format hasn't the renderer file, so a debugging message will be displayed.
                $this->assertDebuggingCalled();
            }
        }
    }

    /**
     * Data provider for test_section_update_visibility().
     *
     * @return array
     */
    public function section_update_visibility_provider(): array {
        return array_merge(
            $this->section_update_visibility_provider_generator('section_hide', false, [1], ['Assignment 1'], [], []),
            $this->section_update_visibility_provider_generator('section_show', true, [], [], [2], []),
        );
    }

    /**
     * Helper method to generate data provider for test_section_update_visibility.
     *
     * @param string $action Action to execute
     * @param bool $expectedvisibility Expected visibility once the given action will be run.
     * @param array $expectedsectionsforvisible List of section numbers changed after the call when 1 visible section is given.
     * @param array $expectedcmsforvisible List of course modules changed after the call when 1 visible section is given.
     * @param array $expectedsectionsforhidden List of section numbers changed after the call when 1 hidden section is given.
     * @param array $expectedcmsforhidden List of course modules changed after the call when 1 hidden section is given.
     * @return array
     */
    private function section_update_visibility_provider_generator(string $action, bool $expectedvisibility,
            array $expectedsectionsforvisible, array $expectedcmsforvisible, array $expectedsectionsforhidden,
            array $expectedcmsforhidden): array {
        $defaultexpectedsections = [
            0 => [1, 3],
            1 => [2, 4],
        ];
        $defaultexpectedcms = [
            0 => ['Assignment 1'],
            1 => [],
        ];

        return [
            // ROLES. Testing behaviour depending on the user role calling the method.
            "Section action as admin should work ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility],
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Section action as editingteacher should work ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility],
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Section action as student should raise an exception ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'student',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Section action as unenroled user should raise an exception ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'unenroled',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],

            // TOPICS. Testing behaviour when topic 0 is included as $ids parameter.
            "Section action passing topic 0 should raise an exception ($action)" => [
                'action' => $action,
                'sections' => [0],
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Section action passing topic 0 should raise an exception ($action)" => [
                'action' => $action,
                'sections' => [0, 1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Section action passing only 1 visible existing topic should work ($action)" => [
                'action' => $action,
                'sections' => [1], // Visible section.
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $expectedsectionsforvisible,
                'expectedcms' => $expectedcmsforvisible,
                'expectedvisibility' => $expectedvisibility,
            ],
            "Section action passing only 1 hidden existing topic should work ($action)" => [
                'action' => $action,
                'sections' => [2], // Hidden section.
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $expectedsectionsforhidden,
                'expectedcms' => $expectedcmsforhidden,
                'expectedvisibility' => $expectedvisibility,
            ],

            // COURSEFORMAT. Test behaviour depending on course formats.
            "Weeks format should work ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'weeks',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility],
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Social format should raise an exception because it does not uses sections ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'social',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility,  // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Single activity format should raise an exception because it does not uses sections ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'singleactivity',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility,  // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Theunittest format should raise an exception because it does not uses sections ($action)" => [
                'action' => $action,
                'sections' => [1, 2, 3, 4],
                'format' => 'theunittest',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility,  // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],

            // UNEXISTING. Test with unexisting data (action, sections...).
            "Unexisting section (5) ($action)" => [
                'action' => $action,
                'sections' => [4, 5],
                'format' => 'topics',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',

            ],
            "Unexisting action ($action)" => [
                'action' => 'section_unexisting',
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
        ];
    }

    /**
     * Helper method to create an activity into a section and add it to the $sections and $activities arrays.
     * When $canedit is false, only visible activities will be added to the activities and sections arrays.
     *
     * @param int $courseid Course identifier where the activity will be added.
     * @param string $type Activity type ('forum', 'assign', ...).
     * @param int $section Section number where the activity will be added.
     * @param bool $visible Whether the activity will be visible or not.
     * @param bool $canedit Whether the activity will be accessed later by a user with editing capabilities.
     */
    private function create_activity(int $courseid, string $type, int $section, bool $visible = true, bool $canedit = true): void {
        $activity = $this->getDataGenerator()->create_module($type,
            ['course' => $courseid], ['section' => $section, 'visible' => $visible]);
        list(, $activitycm) = get_course_and_cm_from_instance($activity->id, $type);
        if ($visible || $canedit) {
            $this->activities[$activitycm->id] = $activitycm;
            $this->sections[$section][] = $activitycm->id;
        }
    }
}
