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

use core_badges\local\backpack\assertion_exporter_interface;
use core_badges\local\backpack\badge_exporter_interface;
use core_badges\local\backpack\issuer_exporter_interface;

/**
 * Factory class for creating backpack objects based on the OB API version.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backpack_factory {

    /**
     * Create an assertion_exporter object based on the OB API version.
     *
     * @param string $hash Badge unique hash.
     * @param string $apiversion Open Badges API version.
     * @throws \coding_exception
     * @return \core_badges\local\backpack\assertion_exporter_interface
     */
    public static function create_assertion_exporter_from_hash(
        string $hash,
        string $apiversion,
    ): assertion_exporter_interface {

        $classname = self::assertion_exists($hash) ? 'assertion_exporter' : 'revoked_assertion_exporter';
        $classname = '\\core_badges\\local\\backpack\\' . self::convert_apiversion($apiversion) . '\\' . $classname;
        if (!class_exists($classname)) {
            throw new \coding_exception('Invalid Open Badges API version');
        }

        return new $classname($hash);
    }

    public static function create_badge_exporter_from_id(
        int $badgeid,
        string $apiversion,
    ): badge_exporter_interface {
        $classname = '\\core_badges\\local\\backpack\\' . self::convert_apiversion($apiversion) . '\\badge_exporter';

        if (!class_exists($classname)) {
            throw new \coding_exception('Invalid Open Badges API version');
        }

        return new $classname($badgeid);
    }

    public static function create_issuer_exporter_from_id(
        ?int $badgeid,
        string $apiversion,
    ): issuer_exporter_interface {

        $classname = '\\core_badges\\local\\backpack\\' . self::convert_apiversion($apiversion) . '\\issuer_exporter';
        if (!class_exists($classname)) {
            throw new \coding_exception('Invalid Open Badges API version');
        }

        return new $classname($badgeid);
    }

    public static function assertion_exists(
        string $hash,
    ): bool {
        global $DB;

        $column = $DB->sql_compare_text('uniquehash', 255);
        return $DB->record_exists_select(
            'badge_issued',
            $column . ' = ?',
            [$hash],
        );
    }

    public static function badge_available(
        string $id,
    ): bool {
        global $DB;

        return $DB->record_exists_select(
            'badge',
            'id = :id AND status != :status',
            ['id' => $id, 'status' => BADGE_STATUS_INACTIVE],
        );
    }

    public static function convert_apiversion(string $apiversion): string {
        if ($apiversion == OPEN_BADGES_V2) {
            $apiversion = '2p0';
        } else {
            $apiversion = str_replace(".", "p", $apiversion);
        }
        return 'v' . $apiversion;
    }

    public static function revert_apiversion(string $version): string {
        // Remove the leading 'v' and replace 'p' with '.'.
        $converted = str_replace(['v', 'p'], ['', '.'], $version);

        if ($converted == '2.0') {
            return OPEN_BADGES_V2;
        }

        return $converted;
    }

}
