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

namespace core_badges;

/**
 * Class that represents a backpack.
 * TODO: Review/improve this class because it's not clear how to create backpacks (id's, hash...) and what represents.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backpack {

    /**
     * Constructs with badge details.
     *
     * @param int $badgeid badge ID.
     */
    public function __construct(string $hash) {
    }


    /**
     * Get the user backpack for the currently logged in user OR the provided user
     *
     * @param int|null $userid The user whose backpack you're requesting for. If null, get the logged in user's backpack
     * @return mixed The user's backpack or none.
     */
    public static function get_user_backpack(?int $userid = 0) {
        global $DB;

        if (!$userid) {
            global $USER;
            $userid = $USER->id;
        }

        $sql = "SELECT beb.*, bb.id AS badgebackpack, bb.password, bb.email AS backpackemail
                FROM {badge_external_backpack} beb
                JOIN {badge_backpack} bb ON bb.externalbackpackid = beb.id AND bb.userid=:userid";

        return $DB->get_record_sql($sql, ['userid' => $userid]);
    }

}
