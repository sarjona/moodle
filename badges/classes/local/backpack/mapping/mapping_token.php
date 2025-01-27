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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

use curl;

/**
 * Represent the url for each method and the encoding of the parameters and response.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_token extends mapping_base {

    /**
     * Make an API request and parse the response.
     *
     * @param string $apiurl Raw request URL
     * @param mixed ...$extra Extra arguments to allow for specific mappings to add more options
     * @return mixed TODO: Replace mixed with more specific type.
     */
    public function request(
        string $apiurl,
        mixed ...$extra,
    ) {
        // Extract parameters from $extra
        $tokenkey = $extra[0] ?? '';
        $post = $extra[1] ?? [];

        $curl = new curl();
        $url = $this->get_url($apiurl);
        if ($tokenkey) {
            $curl->setHeader('Authorization: Bearer ' . $tokenkey);
        }

        if ($this->isjson) {
            $curl->setHeader(array('Content-type: application/json'));
            if ($this->method == 'post') {
                $post = json_encode($post);
            }
        }

        $curl->setHeader(array('Accept: application/json', 'Expect:'));
        $options = $this->get_curl_options();
        if ($this->method == 'get') {
            $response = $curl->get($url, $post, $options);
        } else if ($this->method == 'post') {
            $response = $curl->post($url, $post, $options);
        }
        $response = json_decode($response);
        if (isset($response->result)) {
            $response = $response->result;
        }

        return $response;
    }
}
