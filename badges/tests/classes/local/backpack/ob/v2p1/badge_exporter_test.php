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

namespace core_badges\local\backpack\ob\v2p1;

use core_badges\local\backpack\helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/badges/tests/classes/local/backpack/ob/v2p0/badge_exporter_test.php');

use core_badges\local\backpack\ob\v2p0\badge_exporter_test as badge_exporter_v2p0_test;

/**
 * Tests for badge exporter class in the Open Badges v2.1 backpack integration.
 *
 * @package    core_badges
 * @category   test
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_badges\local\backpack\ob\v2p1\badge_exporter
 */
final class badge_exporter_test extends badge_exporter_v2p0_test {

    #[\Override]
    protected function get_obversion(): string {
        return helper::convert_apiversion(OPEN_BADGES_V2P1);
    }
}
