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
 * Tests for linear navigation sticky footer content renderable.
 *
 * @package    core_courseformat
 * @copyright  2026 Moodle HQ
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(footer_content::class)]
final class footer_content_test extends \basic_testcase {
    /**
     * Validate the sticky footer context contains previous and next controls.
     */
    public function test_export_for_template_contains_previous_and_next_buttons(): void {
        global $PAGE;

        $PAGE->set_url('/');

        $courseid = 42;
        $cmid = 1337;

        $renderable = new footer_content($courseid, $cmid);
        $renderer = $PAGE->get_renderer('core');
        $data = $renderable->export_for_template($renderer);

        $this->assertSame($courseid, $data['courseid']);
        $this->assertArrayHasKey('previousbutton', $data);
        $this->assertArrayHasKey('nextbutton', $data);

        $this->assertSame(get_string('previous'), $data['previousbutton']['label']);
        $this->assertStringContainsString('/course/cms/' . $cmid . '/previous', $data['previousbutton']['url']);

        $this->assertSame(get_string('next'), $data['nextbutton']['label']);
        $this->assertStringContainsString('/course/cms/' . $cmid . '/next', $data['nextbutton']['url']);
    }
}
