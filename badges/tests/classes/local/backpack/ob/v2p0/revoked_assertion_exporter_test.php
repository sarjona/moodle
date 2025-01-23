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

namespace core_badges\local\backpack\ob\v2p0;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/badgeslib.php');

use core_badges\local\backpack\helper;
use core_badges\local\backpack\ob_factory;

/**
 * Tests for revoked achievement credential (or assertion) exporter class in the Open Badges v2.0 backpack integration.
 *
 * @package    core_badges
 * @category   test
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_badges\local\backpack\ob\v2p0\badge_exporter
 */
class revoked_assertion_exporter_test extends \advanced_testcase {

    /**
     * The Open Badges version to use in the test.
     * That way, OBv2.1 can override this method and reuse the same tests.
     *
     * @return string The converted Open Badges API version.
     */
    protected function get_obversion(): string {
        return helper::convert_apiversion(OPEN_BADGES_V2);
    }

    /**
     * Test export method.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Export badges.
        $exporter = ob_factory::create_assertion_exporter_from_hash('non-existing-hash', $this->get_obversion());
        $data = $exporter->export();

        // Check the data structure.
        $this->assertArrayNotHasKey('@context', $data);
        $this->assertArrayNotHasKey('type', $data);
        $this->assertEquals($exporter->get_json_url()->out(false), $data['id']);
        $this->assertTrue($data['revoked']);
        // Revoked badge.
        $this->assertTrue($exporter->is_revoked());
    }
}
