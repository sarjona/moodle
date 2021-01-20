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

use core\oauth2\endpoint;
use core\oauth2\issuer;
use stdClass;

/**
 * Class for provider definition, to allow... [TODO complete doc]
 *
 * @package    core
 * @since      Moodle 3.11
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imsbadgeconnect_definition extends provider_definition {

    public function get_discovery_endpoint_url(issuer $issuer) {
        $url = $issuer->get('baseurl') . '.well-known/badgeconnect.json';

        return $url;
    }

    public function process_configuration_json(issuer $issuer, stdClass $info) {
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

    public function process_userfield_mapping(issuer $issuer) {
    }
}
