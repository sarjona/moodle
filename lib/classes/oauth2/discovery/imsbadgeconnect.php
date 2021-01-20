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

namespace core\oauth2\discovery;

use stdClass;
use core\oauth2\issuer;
use core\oauth2\endpoint;

/**
 * Class for IMS Open Badge Connect API (aka OBv2.1) discovery definition.
 *
 * @package    core
 * @since      Moodle 3.11
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imsbadgeconnect extends base_definition {

    /**
     * Get the URL for the discovery manifest.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @return string The URL of the discovery file, containing the endpoints.
     */
    public function get_discovery_endpoint_url(issuer $issuer): string {
        $url = $issuer->get('baseurl') . '.well-known/badgeconnect.json';

        return $url;
    }

    /**
     * Process the discovery information and create endpoints defined with the expected format.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @param stdClass $info The discovery information, with the endpoints to process and create.
     * @return void
     */
    public function process_configuration_json(issuer $issuer, stdClass $info): void {
        $info = array_pop($info->badgeConnectAPI);
        foreach ($info as $key => $value) {
            if (substr_compare($key, 'Url', - strlen('Url')) === 0 && !empty($value)) {
                $record = new stdClass();
                $record->issuerid = $issuer->get('id');
                // Convert key names from xxxxUrl to xxxx_endpoint, in order to make it compliant with the Moodle oAuth API.
                $record->name = strtolower(substr($key, 0, - strlen('Url'))) . '_endpoint';
                $record->url = $value;

                $endpoint = new endpoint(0, $record);
                $endpoint->create();
            }

            if ($key == 'scopesOffered') {
                $issuer->set('scopessupported', implode(' ', $value));
                $issuer->update();
            }
        }
    }

    /**
     * Process how to map user field information.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @return void
     */
    public function process_userfield_mapping(issuer $issuer): void {
    }
}
