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

/**
 * Class for provider discovery definition, to allow services easily discover and process information.
 * This abstract class is called from core\oauth2\api when discovery points need to be updated.
 *
 * @package    core
 * @since      Moodle 3.11
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_definition {

    /**
     * Get the URL for the discovery manifest.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @return string The URL of the discovery file, containing the endpoints.
     */
    public abstract function get_discovery_endpoint_url(issuer $issuer): string;

    /**
     * Process the discovery information and create endpoints defined with the expected format.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @param stdClass $info The discovery information, with the endpoints to process and create.
     * @return void
     */
    public abstract function process_configuration_json(issuer $issuer, stdClass $info): void;

    /**
     * Process how to map user field information.
     *
     * @param issuer $issuer The OAuth issuer the endpoints should be discovered for.
     * @return void
     */
    public abstract function process_userfield_mapping(issuer $issuer): void;

    /**
     * Self-register the issuer.
     *
     * @param issuer $issuer The OAuth issuer to register.
     * @return void
     */
    public abstract function register(issuer $issuer): void;

}
