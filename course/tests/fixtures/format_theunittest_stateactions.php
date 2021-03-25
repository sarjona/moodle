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

namespace format_theunittest;

use core_course\stateupdates;
use stdClass;

/**
 * Fixture for fake course stateactions testing.
 *
 * @package    core_course
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateactions extends \core_course\stateactions {

    /**
     * Hide a course module. For testing purposes, this method will show course module (instead of hiding it).
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id (not used in hide action)
     * @param int $targetcmid optional target cm id (not used in hide action)
     */
    public function cm_hide(stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {

        $this->set_cm_visibility(1, $updates, $course, $ids, $targetsectionid, $targetcmid);
    }

    /**
     * Show a course module. For testing purposes, this method will hide course module (instead of showing it).
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id (not used in show action)
     * @param int $targetcmid optional target cm id (not used in show action)
     */
    public function cm_show(stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {

        $this->set_cm_visibility(0, $updates, $course, $ids, $targetsectionid, $targetcmid);
    }

}
