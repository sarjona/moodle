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

namespace core_badges\local\backpack\v2p1;

use moodle_url;
use core_badges\achievement_credential;


/**
 * Class that represents badge assertion to be exported to a backpack.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assertion_exporter {
    /** @var achievement_credential Issued badge */
    private $_assertion;

    /**
     * Constructs with issued badge unique hash.
     *
     * @param string $hash Badge unique hash.
     */
    public function __construct(
        string $hash,
    ) {
        $this->_assertion = new achievement_credential($hash);
    }

    /**
     * Get badge assertion.
     *
     * @param bool $issued Include the nested badge issued information.
     * @param bool $usesalt Hash the identity and include the salt information for the hash.
     * @return array Badge assertion.
     */
    public function export(
        bool $issued = true,
        bool $usesalt = true,
    ): array {
        global $CFG;

        // Required fields.
        $assertionurl = new moodle_url(
            '/badges/assertion.php',
            [
                'b' => $this->_assertion->get_hash(),
                'obversion' => OPEN_BADGES_V2P1,
            ],
        );
        $data = [
            'uid' => $this->_assertion->get_hash(),
            'recipient' => (new recipient_exporter($this->_assertion->get_email()))->export($usesalt),
            'verify' => [
                'type' => 'hosted', // 'Signed' is not implemented yet.
                'url' => $assertionurl->out(false),
                'issuedOn' => $this->_assertion->get_dateissued(),
            ],
        ];

        if ($issued) {
            $classurl = new moodle_url('/badges/badge_json.php', ['id' => $this->_assertion->get_badge_id()]);
            $data['badge'] = $classurl->out(false);

            // Currently issued badge URL.
            $badgeurl = new moodle_url('/badges/badge.php', ['hash' => $this->_assertion->get_hash()]);
            $data['evidence'] = $badgeurl->out(false);
        }

        // Optional fields.
        if ($this->_assertion->get_dateexpire()) {
            $data['expires'] = $this->_assertion->get_dateexpire() ?: null;
        }

        // TODO: Add tags.
        // $tags = $this->get_tags();
        // if (is_array($tags) && count($tags) > 0) {
        //     $assertion['tags'] = $tags;
        // }
        // $this->embed_data_badge_version2($assertion, OPEN_BADGES_V2_TYPE_ASSERTION);

        return $data;
    }

    public function get_json(): string {
        return json_encode($this->export());
    }
}
