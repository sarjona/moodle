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

use moodle_url;

/**
 * Class that represents revoked badge assertion to be exported to a backpack.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class revoked_assertion_exporter extends assertion_exporter {

    /**
     * Constructs with issued badge unique hash.
     *
     * @param string $hash Badge unique hash.
     */
    public function __construct(
        /** @var string $hash Badge unique hash. */
        private string $hash,
    ) {
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
            '/badges/json/assertion.php',
            [
                'b' => $this->hash,
                'obversion' => $this->get_version_from_namespace(),
            ],
        );
        $data = [
            'id' => $assertionurl->out(false),
            'revoked' => true,
        ];

        return $data;
    }

    public function is_revoked(): bool {
        return true;
    }

}
