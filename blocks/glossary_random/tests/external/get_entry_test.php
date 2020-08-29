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
 * External function test for get_entry.
 *
 * @package    block_glossary_random
 * @category   external
 * @since      Moodle 3.10
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_glossary_random\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use external_api;
use externallib_advanced_testcase;
use \block_glossary_random\helper;
use stdClass;

/**
 * External function test for get_attempts.
 *
 * @package    block_glossary_random
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_entry_testcase extends externallib_advanced_testcase {
    /**
     * Test the behaviour of get_entry.
     *
     * @dataProvider execute_data
     * @param  string       $user              Current user: editingteacher, student, other or noenrolled.
     * @param  string       $glossary          Glossary to display in the block: content, hidden or empty.
     * @param  string       $type              Glossary random configuration: BGR_LASTMODIFIED, BGR_NEXTONE or BGR_NEXTALPHA.
     * @param  string       $expectedentry     Expected glossary entry to display into the block.
     * @param  string|null  $nextexpectedentry Next expected glossary entry (if the refresh button, for instance, is clicked).
     * @param  bool|boolean $exception         Is an exception expected?
     */
    public function test_execute(string $user, string $glossary, string $type, ?string $expectedentry,
            ?string $nextexpectedentry = null, bool $exception = false): void {
        $this->resetAfterTest();

        // Generate all the things.
        $generator = $this->getDataGenerator();
        $glossarygenerator = $generator->get_plugin_generator('mod_glossary');
        $c1 = $generator->create_course();

        // Prepare glossaries: 1 displayed, 1 hidden and 1 empty.
        $g1 = $generator->create_module('glossary', array('course' => $c1->id));
        $g2 = $generator->create_module('glossary', array('course' => $c1->id, 'visible' => false));
        $g3 = $generator->create_module('glossary', array('course' => $c1->id));
        $glossaries = [
            'content' => $g1,
            'hidden' => $g2,
            'empty' => $g3,
        ];

        // Prepare users: 1 teacher, 2 students, 1 unenroled user.
        $users = [
            'editingteacher' => $generator->create_and_enrol($c1, 'editingteacher'),
            'student' => $generator->create_and_enrol($c1, 'student'),
            'other' => $generator->create_and_enrol($c1, 'student'),
            'noenrolled' => $generator->create_user(),
        ];
        // Enrol users in the course.
        $generator->enrol_user($users['editingteacher']->id, $c1->id);
        $generator->enrol_user($users['student']->id, $c1->id);
        $generator->enrol_user($users['other']->id, $c1->id);

        // Prepare glossary entries: 3 for glossary1 and 1 for glossary2.
        $g1e1 = $glossarygenerator->create_content($g1, array('approved' => 1));
        $g1e2 = $glossarygenerator->create_content($g1, array('approved' => 1, 'userid' => $users['student']->id));
        $g1e3 = $glossarygenerator->create_content($g1, array('approved' => 1, 'userid' => -1));
        $g2e1 = $glossarygenerator->create_content($g2, array('approved' => 1));
        $g2e2 = $glossarygenerator->create_content($g2, array('approved' => 1));
        $entries = [
            'g1e1' => $g1e1,
            'g1e2' => $g1e2,
            'g1e3' => $g1e3,
            'g2e1' => $g2e1,
            'g2e2' => $g2e2,
            'null' => null,
        ];

        // Create course and add Random glossary block.
        $block = $this->create_block($c1);

        $currentuser = $users[$user];
        $this->setUser($currentuser);

        // Change block $settings to add some data.
        $itemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $usercontext = \context_user::instance($currentuser->id);
        $fs->create_file_from_string(['component' => 'user', 'filearea' => 'draft',
                'contextid' => $usercontext->id, 'itemid' => $itemid, 'filepath' => '/',
                'filename' => 'file.txt'], 'File content');
        $currentglossaryid = '';
        if (array_key_exists($glossary, $glossaries)) {
            $currentglossaryid = $glossaries[$glossary]->id;
        }
        $data = (object)[
            'title' => 'Block title',
            'glossary' => $currentglossaryid,
            'refresh' => 0,
            'type' => $type,
            'showconcept' => '1',
        ];
        $block->instance_config_save($data);

        // Execute external method.
        if ($exception) {
            $this->expectException(\require_login_exception::class);
        }
        $result = get_entry::execute($block->instance->id);
        $result = external_api::clean_returnvalue(
            get_entry::execute_returns(),
            $result
        );
        $this->assert_glossary_entry($entries[$expectedentry], $data, $result);

        if ($nextexpectedentry) {
            // Execute external method.
            $result = get_entry::execute($block->instance->id);
            $result = external_api::clean_returnvalue(
                get_entry::execute_returns(),
                $result
            );
            $this->assert_glossary_entry($entries[$nextexpectedentry], $data, $result);
        }
    }

    /**
     * Data provider for the test_execute tests.
     *
     * @return  array
     */
    public function execute_data(): array {
        return [
            // Check the behaviour for editing teacher.
            'Glossary with content: Last modified (as teacher)' => ['editingteacher', 'content', '1', 'g1e3', 'g1e3'],
            'Glossary with content: Next one (as teacher)' => ['editingteacher', 'content', '2', 'g1e3'],
            'Glossary with content: Alphabetical (as teacher)' => ['editingteacher', 'content', '3', 'g1e1', 'g1e2'],

            'Empty glossary: Last modified (as teacher)' => ['editingteacher', 'empty', '1', 'null'],
            'Empty glossary: Next one (as teacher)' => ['editingteacher', 'empty', '1', 'null'],
            'Empty glossary: Alphabetical (as teacher)' => ['editingteacher', 'empty', '3', 'null'],

            'Hidden glossary: Last modified (as teacher)' => ['editingteacher', 'hidden', '1', 'g2e2', 'g2e2'],
            'Hidden glossary: Next one (as teacher)' => ['editingteacher', 'hidden', '2', 'g2e2'],
            'Hidden glossary: Alphabetical (as teacher)' => ['editingteacher', 'hidden', '3', 'g2e1', 'g2e2'],

            // Check the behaviour for student.
            'Glossary with content: Last modified (as student)' => ['student', 'content', '1', 'g1e3', 'g1e3'],
            'Glossary with content: Next one (as student)' => ['student', 'content', '2', 'g1e3'],
            'Glossary with content: Alphabetical (as student)' => ['student', 'content', '3', 'g1e1', 'g1e2'],

            'Empty glossary: Last modified (as student)' => ['student', 'empty', '1', 'null'],
            'Empty glossary: Next one (as student)' => ['student', 'empty', '1', 'null'],
            'Empty glossary: Alphabetical (as student)' => ['student', 'empty', '3', 'null'],

            'Hidden glossary: Last modified (as student)' => ['student', 'hidden', '1', 'null'],
            'Hidden glossary: Next one (as student)' => ['student', 'hidden', '2', 'null'],
            'Hidden glossary: Alphabetical (as student)' => ['student', 'hidden', '3', 'null'],

            // Check the behaviour for non-enrolled user.
            'Glossary with content: Last modified (as noenrolled)' => ['noenrolled', 'content', '1', 'null', null, true],
            'Glossary with content: Next one (as noenrolled)' => ['noenrolled', 'content', '2', 'null', null, true],
            'Glossary with content: Alphabetical (as noenrolled)' => ['noenrolled', 'content', '3', 'null', null, true],

            'Empty glossary: Last modified (as noenrolled)' => ['noenrolled', 'empty', '1', 'null', null, true],
            'Empty glossary: Next one (as noenrolled)' => ['noenrolled', 'empty', '1', 'null', null, true],
            'Empty glossary: Alphabetical (as noenrolled)' => ['noenrolled', 'empty', '3', 'null', null, true],

            'Hidden glossary: Last modified (as noenrolled)' => ['noenrolled', 'hidden', '1', 'null', null, true],
            'Hidden glossary: Next one (as noenrolled)' => ['noenrolled', 'hidden', '2', 'null', null, true],
            'Hidden glossary: Alphabetical (as noenrolled)' => ['noenrolled', 'hidden', '3', 'null', null, true],
        ];
    }

    /**
     * Check if a glossary entry is equal to expected.
     *
     * @param  stdClass $expected [description]
     * @param  stdClass $data     [description]
     * @param  array $result   [description]
     */
    protected function assert_glossary_entry(?stdClass $expected, stdClass $data, array $result): void {
        if (empty($expected)) {
            $this->assertArrayNotHasKey('data', $result);
        } else {
            $this->assertEquals($expected->id, $result['data']['id']);
            $this->assertEquals($expected->concept, $result['data']['concept']);
            $this->assertContains($expected->definition, $result['data']['definition']);
            $this->assertEquals($data->showconcept, $result['data']['showconcept']);
        }
    }

    /**
     * Creates a Glossary random block on a course.
     *
     * @param \stdClass $course Course object
     * @return \block_glossary_random Block instance object
     */
    protected function create_block(stdClass $course): \block_glossary_random {
        $page = self::construct_page($course);
        $page->blocks->add_block_at_end_of_default_region('glossary_random');

        // Load the block.
        $page = self::construct_page($course);
        $page->blocks->load_blocks();
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        $block = end($blocks);
        return $block;
    }

    /**
     * Constructs a page object for the test course.
     *
     * @param \stdClass $course Moodle course object
     * @return \moodle_page Page object representing course view
     */
    protected static function construct_page(stdClass $course): \moodle_page {
        $context = \context_course::instance($course->id);
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->blocks->load_blocks();
        return $page;
    }
}
