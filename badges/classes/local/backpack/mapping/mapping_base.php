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
     * @param mixed $postparams List of parameters for this method.
     * @param bool $multiple This method returns an array of responses.
     * @param string $method get or post methods.
     * @param bool $isjson json decode the response.
     * @param bool $authrequired Authentication is required for this request.
     * @param int $backpackapiversion OpenBadges version.
     * @param mixed ...$extra Extra arguments to allow for future versions to add more options
     */
    public function __construct(
        /** @var string $action The action of this method. */
        protected string $action,
        /** @var string $url The base URL of this backpack. */
        protected string $url,
        /** @var mixed $postparams List of parameters for this method. */
        protected mixed $postparams,
        /** @var bool $multiple This method returns an array of responses. */
        protected bool $multiple,
        /** @var string $method GET or POST method. */
        protected string $method,
        /** @var bool $json JSON decode the response. */
        protected bool $isjson,
        /** @var bool $authrequired Authentication is required for this request. */
        protected bool $authrequired,
        /** @var int OpenBadges version. */
        protected $backpackapiversion,
        mixed ...$extra,
    ) {

    }

    /**
     * Make an API request and parse the response.
     *
     * @param string $apiurl Raw request URL
     * @param mixed ...$extra Extra arguments to allow for specific mappings to add more options
     * @return mixed
     */
    abstract public function request(
        string $apiurl,
        mixed ...$extra,
    );

    /**
     * Does the action match this mapping?
     *
     * @param string $action The action.
     * @return bool
     */
    public function is_match($action): bool {
        return $this->action == $action;
    }

    /**
     * Parse the method URL and insert parameters.
     *
     * @param string $apiurl The raw API URL.
     * @param string ...$params Optional parameters.
     * @return string
     */
    protected function get_url(string $apiurl, string ...$params): string {
        $urlscheme = parse_url($apiurl, PHP_URL_SCHEME);
        $urlhost = parse_url($apiurl, PHP_URL_HOST);

        $url = $this->url;
        $url = str_replace('[SCHEME]', $urlscheme, $url);
        $url = str_replace('[HOST]', $urlhost, $url);
        $url = str_replace('[URL]', $apiurl, $url);
        foreach ($params as $index => $param) {
            $url = str_replace("[PARAM" . ($index + 1) . "]", $param, $url);
        }

        return $url;
    }

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
