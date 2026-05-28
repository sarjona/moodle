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

namespace core_courseformat\output\local\linearnavigation;

/**
 * Tests for the linear navigation sticky footer content renderable.
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2026 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(footer_content::class)]
final class footer_content_test extends \advanced_testcase {
    /**
     * Test export data includes previous and next buttons using routing URLs.
     */
    public function test_export_for_template_contains_previous_and_next_buttons(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $activity = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($user);

        $renderer = \core\di::get(\core\output\renderer_helper::class)->get_core_renderer();
        $renderable = new footer_content($activity->cmid);
        $data = $renderable->export_for_template($renderer);

        $this->assertArrayHasKey('previousurl', $data);
        $this->assertArrayHasKey('nexturl', $data);

        $this->assertStringEndsWith('/course/cms/' . $activity->cmid . '/previous', $data['previousurl']);
        $this->assertStringEndsWith('/course/cms/' . $activity->cmid . '/next', $data['nexturl']);
    }
}
