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

namespace core_course\task;

/**
 * Class containing unit tests for the migrate subsection descriptions task.
 *
 * @package   core_course
 * @copyright 2025 Sara Arjona <sara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(migrate_subsection_descriptions_task::class)]
final class migrate_subsection_descriptions_task_test extends \advanced_testcase {
    /**
     * Test migrate_subsection_descriptions task.
     */
    public function test_migrate_subsection_descriptions(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $summarytext = 'Section with description';
        $this->getDataGenerator()->create_module('subsection', ['course' => $course->id, 'section' => 1]);
        // Add forum to the subsection to test the order of the modules is preserved.
        $this->getDataGenerator()->create_module(
            'forum',
            [
                'course' => $course->id,
                'name' => 'Forum in subsection',
                'section' => 2,
            ],
        );
        // Add description to course sections and the subsection.
        $DB->set_field(
            'course_sections',
            'summary',
            $summarytext,
            ['course' => $course->id],
        );
        // Add another subsection without description.
        $this->getDataGenerator()->create_module('subsection', ['course' => $course->id, 'section' => 1]);

        // Check only 2 sections and 1 subsection have description.
        $this->assertEquals(
            3,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            2,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid AND component = \'mod_subsection\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid  AND component = \'mod_subsection\' AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'label',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
        $cms = get_fast_modinfo($course->id)->get_cms();
        // Check that the activities are in the expected initial order.
        $this->assertEquals(
            [
                'Subsection 1',
                'Subsection 2',
                'Forum in subsection',
            ],
            array_values(array_map(fn($cminfo) => $cminfo->name, $cms))
        );

        // Run the task.
        ob_start();
        $task = new migrate_subsection_descriptions_task();
        $task->execute();
        ob_end_clean();

        // Check only 2 sections keep having description after running the task.
        $this->assertEquals(
            2,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        // Check no subsection has description after running the task.
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid  AND component = \'mod_subsection\' AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        // Check text&media module created for the migrated description.
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'label',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            $summarytext,
            $DB->get_field_select(
                'label',
                'intro',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
        $cms = get_fast_modinfo($course->id)->get_cms();
        // Check that the label is in the expected position.
        $this->assertEquals(
            [
                'Subsection 1',
                'Subsection 2',
                'label',
                'Forum in subsection',
            ],
            array_values(array_map(fn($cminfo) => $cminfo->name, $cms))
        );
    }

    /**
     * Test migrate_subsection_descriptions task with attached files.
     */
    public function test_migrate_subsection_descriptions_with_files(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $summarytext = 'Subsection text with <a href="@@PLUGINFILE@@/intro.txt">link</a>';
        $this->getDataGenerator()->create_module('subsection', ['course' => $course->id, 'section' => 1]);
        $subsection = $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => 2],
        );
        // Add description to the subsection.
        $DB->set_field(
            'course_sections',
            'summary',
            $summarytext,
            ['course' => $course->id, 'section' => $subsection->section],
        );
        $filerecord = [
            'component' => 'course',
            'filearea' => 'section',
            'contextid' => \context_course::instance($course->id)->id,
            'itemid' => $subsection->id,
            'filename' => 'intro.txt',
            'filepath' => '/',
        ];
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, 'Test intro file');

        // Check subsection has description with file, and there is no label.
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid  AND component = \'mod_subsection\' AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'files',
                'component = :component  AND filearea = :filearea AND filename = :filename',
                [
                    'component' => 'course',
                    'filearea' => 'section',
                    'filename' => 'intro.txt',
                ],
            ),
        );
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'label',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'files',
                'component = :component  AND filearea = :filearea AND filename = :filename',
                [
                    'component' => 'mod_label',
                    'filearea' => 'intro',
                    'filename' => 'intro.txt',
                ],
            ),
        );

        // Run the task.
        ob_start();
        $task = new migrate_subsection_descriptions_task();
        $task->execute();
        ob_end_clean();

        // Check no subsection has description after running the task.
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid  AND component = \'mod_subsection\' AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        // Check text&media module created for the migrated description.
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'label',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            $summarytext,
            $DB->get_field_select(
                'label',
                'intro',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
        // Check the file has been migrated too.
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'files',
                'component = :component  AND filearea = :filearea AND filename = :filename',
                [
                    'component' => 'mod_label',
                    'filearea' => 'intro',
                    'filename' => 'intro.txt',
                ],
            ),
        );
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'files',
                'component = :component  AND filearea = :filearea AND filename = :filename',
                [
                    'component' => 'course',
                    'filearea' => 'section',
                    'filename' => 'intro.txt',
                ],
            ),
        );
    }

    /**
     * Test migrate_subsection_descriptions task when label or subsection module is not enabled.
     */
    public function test_migrate_subsection_descriptions_modules_not_enabled(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $summarytext = 'Section with description';
        $this->getDataGenerator()->create_module('subsection', ['course' => $course->id, 'section' => 1]);
        // Add description to course sections and the subsection.
        $DB->set_field(
            'course_sections',
            'summary',
            $summarytext,
            ['course' => $course->id],
        );
        // Check only 2 sections and 1 subsection have description.
        $this->assertEquals(
            3,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid  AND component = \'mod_subsection\' AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'label',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
        // Disable label module.
        \core\plugininfo\mod::enable_plugin('label', 0);

        // Run the task.
        ob_start();
        $task = new migrate_subsection_descriptions_task();
        $task->execute();
        ob_end_clean();

        // Check nothing has changed.
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid  AND component = \'mod_subsection\' AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'label',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );

        // Enable label and disable subsection module.
        \core\plugininfo\mod::enable_plugin('label', 1);
        \core\plugininfo\mod::enable_plugin('subsection', 0);

        // Run the task.
        ob_start();
        $task = new migrate_subsection_descriptions_task();
        $task->execute();
        ob_end_clean();

        // Check nothing has changed.
        $this->assertEquals(
            1,
            $DB->count_records_select(
                'course_sections',
                'course = :courseid  AND component = \'mod_subsection\' AND summary != \'\'',
                ['courseid' => $course->id],
            ),
        );
        $this->assertEquals(
            0,
            $DB->count_records_select(
                'label',
                'course = :courseid',
                ['courseid' => $course->id],
            ),
        );
    }
}
