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

use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/badges/renderer.php');

/**
 * Class that represents badge assertion, also known as achievement credential from OBv3.0 onwards.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievement_credential {
    /** @var object Issued badge information from database */
    private $_data;

    /** @var moodle_url Issued badge url */
    private $_url;

    /**
     * Constructs with issued badge unique hash.
     *
     * @param string $hash Badge unique hash from badge_issued table.
     */
    public function __construct(string $hash) {
        global $DB;

        $this->_data = $DB->get_record_sql('
            SELECT
                bi.dateissued,
                bi.dateexpire,
                bi.uniquehash,
                u.email,
                u.id as userid,
                b.*,
                bb.email as backpackemail
            FROM
                {badge} b
                JOIN {badge_issued} bi
                    ON b.id = bi.badgeid
                JOIN {user} u
                    ON u.id = bi.userid
                LEFT JOIN {badge_backpack} bb
                    ON bb.userid = bi.userid
            WHERE ' . $DB->sql_compare_text('bi.uniquehash', 40) . ' = ' . $DB->sql_compare_text(':hash', 40),
            ['hash' => $hash], IGNORE_MISSING);

        if (!$this->_data) {
            throw new \moodle_exception('invalidbadgehash', 'badges', '', $hash);
        }

        $this->_url = new moodle_url('/badges/badge.php', ['hash' => $this->_data->uniquehash]);
    }

    /**
     * Get the local id for this badge.
     *
     * @return int
     */
    public function get_badge_id(): int {
        if ($this->_data) {
            return $this->_data->id;
        }
        return 0;
    }

    /**
     * Get the local id for this badge assertion.
     *
     * @return string
     */
    public function get_hash() {
        $hash = '';
        if ($this->_data) {
            $hash = $this->_data->uniquehash;
        }
        return $hash;
    }

    public function get_email() {
        return $this->_data->backpackemail ?: $this->_data->email;
    }

    public function get_dateissued() {
        return $this->_data->dateissued;
    }

    public function get_dateexpire() {
        return $this->_data->dateexpire;
    }

    public function get_tags(): array {
        return array_values(\core_tag_tag::get_item_tags_array('core_badges', 'badge', $this->get_badge_id()));
    }


}
