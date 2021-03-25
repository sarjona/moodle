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

namespace core_course;

/**
 * Tests for the stateactions class.
 *
 * @package    core_course
 * @category   test
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_course\stateactions
 */
class stateactions_test extends \advanced_testcase {

    /** @var array Sections in the testing course. */
    private $sections = [];

    /** @var array Activities in the testing course. */
    private $activities = [];

    /**
     * Test the behaviour of stateactions::set_section_visibility().
     *
     * @dataProvider set_section_visibility_provider
     * @covers ::set_section_visibility
     *
     * @param string $method Method name to call.
     * @param array $sectionnums Sections to execute the given action.
     * @param string $format The course will be created with this course format.
     * @param string $role The role of the user that will execute the method.
     * @param array $expectedsections List of the section numbers changed after calling the method.
     * @param array $expectedcms List of the course module names changed after calling the method.
     * @param bool $expectedvisibility The expected visibility after running the given action.
     * @param string|null $expectedexception If this call will raise an exception, this is its name.
     */
    public function test_set_section_visibility(string $method, array $sectionnums, string $format, string $role,
            array $expectedsections, array $expectedcms, bool $expectedvisibility, ?string $expectedexception = null): void {

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
            // Initialise stateupdates.
            $courseformat = course_get_format($course->id);
            $updates = new stateupdates($courseformat);
            // Execute given method.
            $actions = new stateactions();
            $actions->$method($updates, $course, $sectionnums);

            // Check state returned after executing given action.
            $results = $updates->jsonSerialize();

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
            if ($format == 'social') {
                // The course format hasn't the renderer file, so a debugging message will be displayed.
                $this->assertDebuggingCalled();
            }
        }
    }

    /**
     * Data provider for test_set_section_visibility().
     *
     * @return array
     */
    public function set_section_visibility_provider(): array {
        return array_merge(
            $this->set_section_visibility_provider_generator('section_hide', false, [1], ['Assignment 1'], [], []),
            $this->set_section_visibility_provider_generator('section_show', true, [], [], [2], []),
        );
    }

    /**
     * Helper method to generate data provider for test_set_section_visibility.
     *
     * @param string $method Method to execute.
     * @param bool $expectedvisibility Expected visibility once the given action will be run.
     * @param array $expectedsectionsforvisible List of section numbers changed after the call when 1 visible section is given.
     * @param array $expectedcmsforvisible List of course modules changed after the call when 1 visible section is given.
     * @param array $expectedsectionsforhidden List of section numbers changed after the call when 1 hidden section is given.
     * @param array $expectedcmsforhidden List of course modules changed after the call when 1 hidden section is given.
     * @return array
     */
    private function set_section_visibility_provider_generator(string $method, bool $expectedvisibility,
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
            "Section action as admin should work ($method)" => [
                'action' => $method,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility],
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Section action as editingteacher should work ($method)" => [
                'action' => $method,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility],
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Section action as student should raise an exception ($method)" => [
                'action' => $method,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'student',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Section action as unenroled user should raise an exception ($method)" => [
                'action' => $method,
                'sections' => [1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'unenroled',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],

            // TOPICS. Testing behaviour when topic 0 is included as $ids parameter.
            "Section action passing topic 0 should raise an exception ($method)" => [
                'action' => $method,
                'sections' => [0],
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Section action passing topic 0 should raise an exception ($method)" => [
                'action' => $method,
                'sections' => [0, 1, 2, 3, 4],
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Section action passing only 1 visible existing topic should work ($method)" => [
                'action' => $method,
                'sections' => [1], // Visible section.
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $expectedsectionsforvisible,
                'expectedcms' => $expectedcmsforvisible,
                'expectedvisibility' => $expectedvisibility,
            ],
            "Section action passing only 1 hidden existing topic should work ($method)" => [
                'action' => $method,
                'sections' => [2], // Hidden section.
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedsections' => $expectedsectionsforhidden,
                'expectedcms' => $expectedcmsforhidden,
                'expectedvisibility' => $expectedvisibility,
            ],

            // COURSEFORMAT. Test behaviour depending on course formats.
            "Weeks format should work ($method)" => [
                'action' => $method,
                'sections' => [1, 2, 3, 4],
                'format' => 'weeks',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility],
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Social format should raise an exception because it does not uses sections ($method)" => [
                'action' => $method,
                'sections' => [1, 2, 3, 4],
                'format' => 'social',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility,  // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],
            "Single activity format should raise an exception because it does not uses sections ($method)" => [
                'action' => $method,
                'sections' => [1, 2, 3, 4],
                'format' => 'singleactivity',
                'role' => 'admin',
                'expectedsections' => $defaultexpectedsections[(int) $expectedvisibility], // Ignored because exception is raised.
                'expectedcms' => $defaultexpectedcms[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility,  // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],

            // UNEXISTING. Test with unexisting data (action, sections...).
            "Unexisting section (5) ($method)" => [
                'action' => $method,
                'sections' => [4, 5],
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
     * Test the behaviour of stateactions::set_section_visibility().
     *
     * @dataProvider set_cm_visibility_provider
     * @covers ::cm_hide
     * @covers ::cm_show
     *
     * @param string $method Method name to call.
     * @param string $format The course will be created with this course format.
     * @param string $role The role of the user that will execute the method.
     * @param array $expectedresults List of the course module names expected after calling the method.
     * @param bool $expectedvisibility The expected visibility after running the given action.
     * @param string|null $expectedexception If this call will raise an exception, this is its name.
     * @param bool $unexistingactivities Whether activities should exist or not.
     */
    public function test_set_cm_visibility(string $method, string $format, string $role, array $expectedresults,
            bool $expectedvisibility, ?string $expectedexception = null, bool $unexistingactivities = false): void {

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
            // Initialise stateupdates.
            $courseformat = course_get_format($course->id);
            $updates = new stateupdates($courseformat);
            // Execute given method.
            $actions = new stateactions();
            $actions->$method($updates, $course, $activities);

            // Check state returned after executing given action.
            $results = $updates->jsonSerialize();
            $this->assertCount(count($expectedresults), $results);
            foreach ($results as $result) {
                $this->assertEquals('cm', $result->name);
                $this->assertEquals('update', $result->action);
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
            if ($format == 'social') {
                // The course format hasn't the renderer file, so a debugging message will be displayed.
                $this->assertDebuggingCalled();
            }
        }
    }

    /**
     * Data provider for test_set_cm_visibility().
     *
     * @return array
     */
    public function set_cm_visibility_provider(): array {
        return array_merge(
            $this->set_cm_visibility_provider_generator('cm_hide', false),
            $this->set_cm_visibility_provider_generator('cm_show', true),
        );
    }

    /**
     * Helper method to generate data provider for set_cm_visibility_provider.
     *
     * @param string $method Method to execute
     * @param bool $expectedvisibility Expected visibility once the given method will be executed.
     * @return array
     */
    private function set_cm_visibility_provider_generator(string $method, bool $expectedvisibility): array {
        $activities = [
            0 => ['Assignment 1', 'Glossary 1'],
            1 => ['Book 1', 'Page 1'],
        ];
        return [
            // ROLES. Testing behaviour depending on the user role calling the method.
            "Course module action as admin should work ($method)" => [
                'method' => $method,
                'format' => 'topics',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Course module action as editingteacher should work ($method)" => [
                'method' => $method,
                'format' => 'topics',
                'role' => 'editingteacher',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Course module action as student should raise an exception ($method)" => [
                'method' => $method,
                'format' => 'topics',
                'role' => 'student',
                'expectedresults' => $activities[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'required_capability_exception',
            ],
            "Course module action as unenroled user should raise an exception ($method)" => [
                'method' => $method,
                'format' => 'topics',
                'role' => 'unenroled',
                'expectedresults' => $activities[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
            ],

            // COURSEFORMAT. Test behaviour depending on course formats.
            "Weeks format should work ($method)" => [
                'method' => $method,
                'format' => 'weeks',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Social format should work ($method)" => [
                'method' => $method,
                'format' => 'social',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],
            "Single activity format should work ($method)" => [
                'method' => $method,
                'format' => 'singleactivity',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility],
                'expectedvisibility' => $expectedvisibility,
            ],

            // UNEXISTING. Test with unexisting data.
            "Unexisting course module ($method)" => [
                'method' => $method,
                'format' => 'topics',
                'role' => 'admin',
                'expectedresults' => $activities[(int) $expectedvisibility], // Ignored because an exception is raised.
                'expectedvisibility' => $expectedvisibility, // Ignored because an exception is raised.
                'expectedexception' => 'moodle_exception',
                'unexistingactivities' => true,
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
