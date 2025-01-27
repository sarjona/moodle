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

use context_system;
use curl;

/**
 * Represent the url for each method and the encoding of the parameters and response.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_session extends mapping_base {

    /** @var string Error string from authentication request. */
    private static $authenticationerror = '';

    /**
     * Get the unique key for the token.
     *
     * @param string $type The type of token.
     * @return string
     */
    public static function get_token_key($type): string {
        return 'badges_backpack_' . $type . '_token';
    }

    /**
     * Remember the error message in a static variable.
     *
     * @param string $msg The message.
     */
    public static function set_authentication_error($msg) {
        self::$authenticationerror = $msg;
    }

    /**
     * Get the last authentication error in this request.
     *
     * @return string
     */
    public static function get_authentication_error(): string {
        return self::$authenticationerror;
    }

    /**
     * Get the user id from a previous user request.
     *
     * @return int
     */
    private function get_auth_user_id(): int {
        global $USER;

        return $USER->id;
    }

    /**
     * Parse the response from an openbadges 2 login.
     *
     * @param string $response The request response data.
     * @param integer $backpackid The id of the backpack.
     * @return mixed
     */
    public function oauth_token_response($response, $backpackid) {
        global $SESSION;

        if (isset($response->access_token) && isset($response->refresh_token)) {
            // Remember the tokens.
            $accesskey = $this->get_token_key(BADGE_ACCESS_TOKEN);
            $refreshkey = $this->get_token_key(BADGE_REFRESH_TOKEN);
            $expireskey = $this->get_token_key(BADGE_EXPIRES_TOKEN);
            $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
            $backpackidkey = $this->get_token_key(BADGE_BACKPACK_ID_TOKEN);
            if (isset($response->expires_in)) {
                $timeout = $response->expires_in;
            } else {
                $timeout = 15 * 60; // 15 minute timeout if none set.
            }
            $expires = $timeout + time();

            $SESSION->$expireskey = $expires;
            $SESSION->$useridkey = $this->get_auth_user_id();
            $SESSION->$accesskey = $response->access_token;
            $SESSION->$refreshkey = $response->refresh_token;
            $SESSION->$backpackidkey = $backpackid;
            return -1;
        } else if (isset($response->error_description)) {
            self::set_authentication_error($response->error_description);
        }
        return $response;
    }

    /**
     * Make an API request and parse the response.
     *
     * @param string $url Request URL
     * @param array|string|null $postdata Data to post
     * @param mixed ...$extra Extra arguments to allow for specific mappings to add more options
     * @return mixed TODO: Replace mixed with more specific type.
     */
    public function curl_request(
        string $url,
        $postdata = null,
        mixed ...$extra,
    ) {
        global $SESSION, $PAGE;

        $curl = new curl();
        if ($this->authrequired) {
            $accesskey = $this->get_token_key(BADGE_ACCESS_TOKEN);
            if (isset($SESSION->$accesskey)) {
                $token = $SESSION->$accesskey;
                $curl->setHeader('Authorization: Bearer ' . $token);
            }
        }
        if ($this->isjson) {
            $curl->setHeader(array('Content-type: application/json'));
        }
        $curl->setHeader(array('Accept: application/json', 'Expect:'));
        $options = $this->get_curl_options();

        if ($this->method == 'get') {
            $response = $curl->get($url, $postdata, $options);
        } else if ($this->method == 'post') {
            $response = $curl->post($url, $postdata, $options);
        } else if ($this->method == 'put') {
            $response = $curl->put($url, $postdata, $options);
        }
        $response = json_decode($response);
        if ((!isset($response->status) || $response->status->success !== false) && isset($response->result)) {
            $response = $response->result;
        }

        return $response;
    }
}
