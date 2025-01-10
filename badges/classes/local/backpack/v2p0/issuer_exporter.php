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

namespace core_badges\local\backpack\v2p0;

use moodle_url;
use core_badges\badge;
use core_badges\local\backpack\exporter_base;
use core_badges\local\backpack\issuer_exporter_interface;

/**
 * Class that represents issuer to be exported to a backpack.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer_exporter extends exporter_base implements issuer_exporter_interface {

    /** @var string The issuer name */
    private $name;

    /** @var string The issuer url */
    private $url;

    /** @var string The issuer email */
    private $email;

    /**
     * Constructs with badge identifier.
     *
     * @param int $badgeid Badge identifier.
     */
    public function __construct(
        /** @var int Badge identifier. */
        private ?int $badgeid,
    ) {
        global $CFG, $SITE;

        if (empty($badgeid)) {
            // Get the default issuer for this site.
            $sitebackpack = badges_get_site_primary_backpack();
            $this->name = $CFG->badges_defaultissuername ?: ($SITE->fullname ? $SITE->fullname : $SITE->shortname);
            $this->url = (new moodle_url('/'))->out(false);
            $this->email = $sitebackpack->backpackemail ?: $CFG->badges_defaultissuercontact;
        } else {
            $badge = new badge($badgeid);
            $this->name = $badge->issuername;
            $this->url = $badge->issuerurl;
            $this->email = $badge->issuercontact;
        }
    }

    /**
     * Get issuer.
     *
     * @return array Issuer.
     */
    public function export(
    ): array {
        $params = $this->badgeid ? ['id' => $this->badgeid] : [];
        return [
            'name' => $this->name,
            'url' => $this->url,
            'email' => $this->email,
            '@context' => OPEN_BADGES_V2_CONTEXT,
            'id' => (new moodle_url('/badges/json/issuer.php', $params))->out(false),
            'type' => OPEN_BADGES_V2_TYPE_ISSUER,
        ];
    }

}
