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

namespace core_badges\local\backpack\mapping;

/**
 * Base class for mapping backpack API requests.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mapping_base {

    /**
     * Base constructor.
     *
     * The $extra parameter has been added here for future-proofing.
     * This allows named parameters to be used and allows classes extending to
     * make use of parameters in newer versions even if they don't exist in older versions.
     *
     * @param string $action The action of this method.
     * @param string $url The base url of this backpack.
     * @param string $method get or post methods.
     * @param bool $isjson json decode the response.
     * @param bool $authrequired Authentication is required for this request.
     * @param int $backpackapiversion OpenBadges version.
     */
    public function __construct(
        /** @var string $action The action of this method. */
        protected string $action,
        /** @var string $url The base URL of this backpack. */
        public string $url,
        /** @var string $method GET or POST method. */
        protected string $method,
        /** @var bool $json JSON decode the response. */
        protected bool $isjson,
        /** @var bool $authrequired Authentication is required for this request. */
        protected bool $authrequired,
        /** @var int OpenBadges version. */
        protected $backpackapiversion,
    ) {

    }

    /**
     * Make an API request and parse the response.
     *
     * @param string $apiurl Raw request URL
     * @param array|string|null $postdata Data to post
     * @param mixed ...$extra Extra arguments to allow for specific mappings to add more options
     * @return mixed
     */
    abstract public function curl_request(
        string $apiurl,
        $postdata = null,
        mixed ...$extra,
    );

    /**
     * Standard options used for all curl requests.
     *
     * @return array
     */
    protected function get_curl_options() {
        return array(
            'FRESH_CONNECT'     => true,
            'RETURNTRANSFER'    => true,
            'FORBID_REUSE'      => true,
            'HEADER'            => 0,
            'CONNECTTIMEOUT'    => 3,
            // Follow redirects with the same type of request when sent 301, or 302 redirects.
            'CURLOPT_POSTREDIR' => 3,
        );
    }
}
