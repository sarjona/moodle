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
     * @param string $requestexporter Name of a class to export parameters for this method.
     * @param string $responseexporter Name of a class to export response for this method.
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
        protected string $requestexporter,
        protected string $responseexporter,
        mixed ...$extra,
    ) {

    }

    /**
     * Get the unique key for the token.
     *
     * @param string $type The type of token.
     * @return string
     */
    private function get_token_key($type): string {
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
     * Parse the post parameters and insert replacements.
     *
     * @param string $email The api username.
     * @param string $password The api password.
     * @param string $param The parameter.
     * @return mixed
     */
    private function get_post_params($email, $password, $param) {
        global $PAGE;

        if ($this->method == 'get') {
            return '';
        }

        $request = $this->postparams;
        if ($request === '[PARAM]') {
            $value = $param;
            foreach ($value as $key => $keyvalue) {
                if (gettype($value[$key]) == 'array') {
                    $newkey = 'related_' . $key;
                    $value[$newkey] = $value[$key];
                    unset($value[$key]);
                }
            }
        } else if (is_array($request)) {
            foreach ($request as $key => $value) {
                if ($value == '[EMAIL]') {
                    $value = $email;
                    $request[$key] = $value;
                } else if ($value == '[PASSWORD]') {
                    $value = $password;
                    $request[$key] = $value;
                } else if ($value == '[PARAM]') {
                    $request[$key] = is_array($param) ? $param[0] : $param;
                }
            }
        }
        $context = context_system::instance();
        $exporter = $this->requestexporter;
        $output = $PAGE->get_renderer('core', 'badges');
        if (!empty($exporter)) {
            $exporterinstance = new $exporter($value, ['context' => $context]);
            $request = $exporterinstance->export($output);
        }
        if ($this->isjson) {
            return json_encode($request);
        }
        return $request;
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
    private function oauth_token_response($response, $backpackid) {
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
     * @param string $apiurl Raw request URL
     * @param mixed ...$extra Extra arguments to allow for specific mappings to add more options
     * @return mixed TODO: Replace mixed with more specific type.
     */
    public function request(
        string $apiurl,
        mixed ...$extra,
    ) {
        global $SESSION, $PAGE;

        // Extract parameters from $extra
        $urlparam1 = $extra[0] ?? '';
        $urlparam2 = $extra[1] ?? '';
        $email = $extra[2] ?? '';
        $password = $extra[3] ?? '';
        $postparam = $extra[4] ?? '';
        $backpackid = $extra[5] ?? '';

        $curl = new curl();

        $url = $this->get_url($apiurl, $urlparam1, $urlparam2);

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

        $post = $this->get_post_params($email, $password, $postparam);

        if ($this->method == 'get') {
            $response = $curl->get($url, $post, $options);
        } else if ($this->method == 'post') {
            $response = $curl->post($url, $post, $options);
        } else if ($this->method == 'put') {
            $response = $curl->put($url, $post, $options);
        }
        $response = json_decode($response);
        if (isset($response->result)) {
            $response = $response->result;
        }
        $context = context_system::instance();
        $exporter = $this->responseexporter;
        if (class_exists($exporter)) {
            $output = $PAGE->get_renderer('core', 'badges');
            if (!$this->multiple) {
                if (count($response)) {
                    $response = $response[0];
                }
                if (empty($response)) {
                    return null;
                }
                $apidata = $exporter::map_external_data($response, $this->backpackapiversion);
                $exporterinstance = new $exporter($apidata, ['context' => $context]);
                $data = $exporterinstance->export($output);
                return $data;
            } else {
                $multiple = [];
                if (empty($response)) {
                    return $multiple;
                }
                foreach ($response as $data) {
                    $apidata = $exporter::map_external_data($data, $this->backpackapiversion);
                    $exporterinstance = new $exporter($apidata, ['context' => $context]);
                    $multiple[] = $exporterinstance->export($output);
                }
                return $multiple;
            }
        } else if (method_exists($this, $exporter)) {
            return $this->$exporter($response, $backpackid);
        }
        return $response;
    }
}
