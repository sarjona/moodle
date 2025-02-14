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

use coding_exception;
use stdClass;
use core_badges\local\backpack\mapping\mapping_base;

/**
 * Base class for communicating with backpacks.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class api_base {

    /**
     * Base constructor.
     *
     * The $extra parameter has been added here for future-proofing.
     * This allows named parameters to be used and allows classes extending to
     * make use of parameters in newer versions even if they don't exist in older versions.
     *
     * @param stdClass $externalbackpack The external backpack record
     */
    public function __construct(
        /** @var stdClass The external backpack record. */
        protected stdClass $externalbackpack,
    ) {
        if ($this->externalbackpack->apiversion != $this->get_api_version()) {
            throw new coding_exception('Incorrect backpack version');
        }
    }

    /**
     * Return the API version supported by this backpack.
     *
     * @return string The API version.
     */
    abstract protected static function get_api_version(): string;

    /**
     * Disconnect the backpack from current user.
     *
     * @return bool
     */
    abstract public function disconnect_backpack(): bool;

    /**
     * Send an assertion to the backpack.
     *
     * @param string $hash The assertion hash
     * @return array
     */
    abstract public function put_assertions(string $hash): array;

    /**
     * Parse the method URL and insert parameters.
     *
     * @param string $requesturl The request URL.
     * @param string $apiurl The raw API URL.
     * @param array $params Optional parameters.
     * @return string
     */
    protected function get_request_url(string $requesturl, string $apiurl, array $params = []): string {
        $urlscheme = parse_url($apiurl, PHP_URL_SCHEME);
        $urlhost = parse_url($apiurl, PHP_URL_HOST);

        $url = $requesturl;
        $url = str_replace('[SCHEME]', $urlscheme, $url);
        $url = str_replace('[HOST]', $urlhost, $url);
        $url = str_replace('[URL]', $apiurl, $url);
        foreach ($params as $index => $param) {
            $url = str_replace("[PARAM" . ($index + 1) . "]", $param, $url);
        }

        return $url;
    }

    /**
     * Create a new backpackapi instance from an external backpack record.
     *
     * @param \stdClass $externalbackpack The external backpack record
     * @throws \coding_exception
     * @return api_base The new backpackapi instance
     */
    public static function create_from_externalbackpack(
        stdClass $externalbackpack,
    ): self {
        $apiversion = $externalbackpack->apiversion;
        if ($apiversion == OPEN_BADGES_V2) {
            $apiversion = '2p0';
        } else {
            $apiversion = str_replace(".", "p", $externalbackpack->apiversion);
        }

        $classname = __NAMESPACE__ . '\\v' . $apiversion . '\\api';
        if (!class_exists($classname)) {
            throw new coding_exception('Invalid backpack version');
        }

        return new $classname($externalbackpack);
    }

}
