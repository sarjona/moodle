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

namespace mod_scorm;

/**
 * Genarator tests class for mod_scorm.
 *
 * @package    mod_scorm
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator_test extends \advanced_testcase {

    public function test_create_instance(): void {
        global $DB, $CFG, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('scorm', array('course' => $course->id)));
        $scorm = $this->getDataGenerator()->create_module('scorm', array('course' => $course));
        $records = $DB->get_records('scorm', array('course' => $course->id), 'id');
        $this->assertEquals(1, count($records));
        $this->assertTrue(array_key_exists($scorm->id, $records));

        $params = array('course' => $course->id, 'name' => 'Another scorm');
        $scorm = $this->getDataGenerator()->create_module('scorm', $params);
        $records = $DB->get_records('scorm', array('course' => $course->id), 'id');
        $this->assertEquals(2, count($records));
        $this->assertEquals('Another scorm', $records[$scorm->id]->name);

        // Examples of specifying the package file (do not validate anything, just check for exceptions).
        // 1. As path to the file in filesystem.
        $params = array(
            'course' => $course->id,
            'packagefilepath' => $CFG->dirroot.'/mod/scorm/tests/packages/singlescobasic.zip'
        );
        $scorm = $this->getDataGenerator()->create_module('scorm', $params);

        // 2. As file draft area id.
        $fs = get_file_storage();
        $params = array(
            'course' => $course->id,
            'packagefile' => file_get_unused_draft_itemid()
        );
        $usercontext = \context_user::instance($USER->id);
        $filerecord = array('component' => 'user', 'filearea' => 'draft',
                'contextid' => $usercontext->id, 'itemid' => $params['packagefile'],
                'filename' => 'singlescobasic.zip', 'filepath' => '/');
        $fs->create_file_from_pathname($filerecord, $CFG->dirroot.'/mod/scorm/tests/packages/singlescobasic.zip');
        $scorm = $this->getDataGenerator()->create_module('scorm', $params);
    }


    /**
     * Test creating a SCORM attempt.
     *
     * @param array $attemptdata Data for the attempt to create.
     * @param array $expected Expected results.
     * @dataProvider get_create_attempt_data
     * @covers \mod_scorm_generator::create_attempt
     */
    public function test_create_attempt(array $attemptdata, array $expected): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $user = $dg->create_user();
        $scorm = $dg->create_module('scorm', ['course' => $course]);
        $scormid = $scorm->id;
        $userid = $user->id;
        if (!empty($attemptdata['scormid'])) {
            $scormid = $attemptdata['scormid'];
        }
        if (!empty($attemptdata['userid'] === null)) {
            $userid = $attemptdata['userid'];
        }
        if ($expected['exception'] !== null) {
            $this->expectException($expected['exception']);
        }
        $scormgenerator = $dg->get_plugin_generator('mod_scorm');
        $scormgenerator->create_attempt(['scormid' => $scormid, 'userid' => $userid]);
        $records = $DB->get_records('scorm_attempt', ['scormid' => $scormid], 'id');
        $this->assertEquals($expected['attemptscount'], count($records));
    }

    /**
     * Data provider for test_create_attempt.
     *
     * @return array
     */
    public static function get_create_attempt_data(): array {
        return [
            'default' => [
                'attemptdata' => [
                    'scormid' => null, // The created scorm.
                    'userid' => null, // The created user.
                ],
                'expected' => [
                    'exception' => null,
                    'attemptscount' => 1,
                ],
            ],
            'with wrong scormid' => [
                'attemptdata' => [
                    'scormid' => 3,
                    'userid' => null, // The created user.
                ],
                'expected' => [
                    'exception' => \dml_missing_record_exception::class,
                ],
            ],
        ];
    }

    /**
     * Test creating several SCORM attempts.
     *
     * @covers \mod_scorm_generator::create_attempt
     */
    public function test_create_attempts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $user = $dg->create_user();
        $scorm = $dg->create_module('scorm', ['course' => $course]);
        $scormgenerator = $dg->get_plugin_generator('mod_scorm');
        $scormgenerator->create_attempt(['scormid' => $scorm->id, 'userid' => $user->id]);
        $records = $DB->get_records('scorm_attempt', ['scormid' => $scorm->id], 'id');
        $this->assertEquals(1, count($records));
        $scormgenerator->create_attempt(['scormid' => $scorm->id, 'userid' => $user->id]);
        $records = $DB->get_records('scorm_attempt', ['scormid' => $scorm->id], 'id');
        $this->assertEquals(2, count($records));
    }

    /**
     * Test creating a SCORM attempt.
     *
     * @covers \mod_scorm_generator::create_attempt
     */
    public function test_create_instance_timeopen_timeclose(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $clock = $this->mock_clock_with_frozen();
        $timeopen = $clock->time();
        $timeclose = $clock->time() + 10;
        $scorm = $dg->create_module(
            'scorm',
            [
                'course' => $course,
                'timeopen' => $timeopen,
                'timeclose' => $timeclose,
            ]
        );
        $record = $DB->get_record('scorm', ['id' => $scorm->id]);
        $this->assertEquals($timeopen, $record->timeopen);
        $this->assertEquals($timeclose, $record->timeclose);
    }
}
