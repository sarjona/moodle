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

namespace core\oauth2\service;

use core\oauth2\issuer;
use core\oauth2\discovery\base_definition;
use core\oauth2\discovery\imsbadgeconnect;

/**
 * Class for IMS Open Badges v2.1 oAuth service, with the specific methods related to it.
 *
 * @package    core
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imsobv2p1 {

    /**
     * Build an OAuth2 issuer, with all the default values for this service.
     *
     * @return issuer The issuer initialised with proper default values.
     */
    public static function init(): issuer {
        $record = (object) [
            'name' => 'IMS OBv2.1',
        ];

        $issuer = new issuer(0, $record);
        return $issuer;
    }

    /**
     * Returns the discovery provider definition used by this service.
     *
     * @return base_definition The discovery definition provider for this service.
     */
    public static function get_discovery_provider(): base_definition {
        return new imsbadgeconnect();
    }

}
