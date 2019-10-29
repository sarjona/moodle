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
 * Testing the H5P helper.
 *
 * @package    core_h5p
 * @category   test
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * Test class covering the H5P helper.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper_testcase extends \advanced_testcase {

    /** @var \core_h5p\factory */
    private $factory;

    /**
     * Set up function for tests.
     */
    public function setUp() {
        $this->factory = new \core_h5p\factory();
    }

    /**
     * Test the behaviour of get_display_options().
     *
     * @dataProvider get_display_options_provider
     * @param  bool   $frame     Whether the frame should be displayed or not
     * @param  bool   $export    Whether the export action button should be displayed or not
     * @param  bool   $embed     Whether the embed action button should be displayed or not
     * @param  bool   $copyright Whether the copyright action button should be displayed or not
     * @param  int    $expected The expectation with the displayoptions value
     */
    public function test_get_display_options(bool $frame, bool $export, bool $embed, bool $copyright, int $expected): void {
        $this->resetAfterTest();

        $core = $this->factory->get_core();
        $config = (object)[
            'frame' => $frame,
            'export' => $export,
            'embed' => $embed,
            'copyright' => $copyright,
        ];
        $displayoptions = helper::get_display_options($core, $config);

        $this->assertEquals($expected, $displayoptions);
    }

    /**
     * Data provider for test_get_display_options().
     *
     * @return array
     */
    public function get_display_options_provider(): array {
        return [
            'All display options disabled' => [
                0,
                0,
                0,
                0,
                15,
            ],
            'All display options enabled' => [
                1,
                1,
                1,
                1,
                0,
            ],
            'Frame disabled and the rest enabled' => [
                0,
                1,
                1,
                1,
                0,
            ],
            'Only export enabled' => [
                0,
                1,
                0,
                0,
                12,
            ],
            'Only embed enabled' => [
                0,
                0,
                1,
                0,
                10,
            ],
            'Only copyright enabled' => [
                0,
                0,
                0,
                1,
                6,
            ],
        ];
    }
}
