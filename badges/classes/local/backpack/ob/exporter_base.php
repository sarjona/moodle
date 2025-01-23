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

namespace core_badges\local\backpack\ob;

use core_badges\achievement_credential;

/**
 * Class exporter_base
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class exporter_base {

    public abstract function export(): array;

    /**
     * Create an assertion_exporter object based on the OB API version.
     *
     * @param string $hash Badge unique hash.
     * @param string $apiversion Open Badges API version.
     * @throws \coding_exception
     * @return \core_badges\local\backpack\ob\assertion_exporter_interface
     */
    public static function create_assertion_exporter_from_hash(
        string $hash,
        string $apiversion,
    ): assertion_exporter_interface {

        $classname = self::assertion_exists($hash) ? 'assertion_exporter' : 'revoked_assertion_exporter';
        $classname = '\\core_badges\\local\\backpack\\ob\\' . $apiversion . '\\' . $classname;
        if (!class_exists($classname)) {
            throw new \coding_exception('Invalid Open Badges API version');
        }

        return new $classname($hash);
    }

    public static function create_badge_exporter_from_id(
        int $badgeid,
        string $apiversion,
    ): badge_exporter_interface {
        $classname = '\\core_badges\\local\\backpack\\ob\\' . $apiversion . '\\badge_exporter';

        if (!class_exists($classname)) {
            throw new \coding_exception('Invalid Open Badges API version');
        }

        return new $classname($badgeid);
    }

    public static function create_issuer_exporter_from_id(
        ?int $badgeid,
        string $apiversion,
    ): issuer_exporter_interface {

        $classname = '\\core_badges\\local\\backpack\\ob\\' . $apiversion . '\\issuer_exporter';
        if (!class_exists($classname)) {
            throw new \coding_exception('Invalid Open Badges API version');
        }

        return new $classname($badgeid);
    }

    public static function create_issuer_exporter_from_hash(
        string $hash,
        string $apiversion,
    ): issuer_exporter_interface {

        $classname = '\\core_badges\\local\\backpack\\ob\\' . $apiversion . '\\issuer_exporter';
        if (!class_exists($classname)) {
            throw new \coding_exception('Invalid Open Badges API version');
        }

        $assertion = new achievement_credential($hash);
        $badgeid = $assertion->get_badge_id();
        return new $classname($badgeid);
    }

    public function get_json(): string {
        return json_encode($this->export());
    }

    protected function get_exporter_class(string $classname): string {
        return (new \ReflectionClass($this))->getNamespaceName() .'\\'. $classname;
    }

    protected function get_version_from_namespace(): string {
        $namespace = (new \ReflectionClass($this))->getNamespaceName();
        preg_match('/v\d+p\d+/', $namespace, $matches);
        return $matches[0] ?? null;
    }

    public static function convert_apiversion(string $apiversion): string {
        if ($apiversion == OPEN_BADGES_V2) {
            $apiversion = '2p0';
        } else {
            $apiversion = str_replace(".", "p", $apiversion);
        }
        return 'v' . $apiversion;
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

}
