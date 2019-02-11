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

/**
 * External backpack library.
 *
 * @package    core
 * @subpackage badges
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
use core_badges\external\assertion_exporter;
use core_badges\external\collection_exporter;
use core_badges\external\issuer_exporter;
use core_badges\external\badgeclass_exporter;

define('BADGE_ACCESS_TOKEN', 'access');
define('BADGE_USER_ID_TOKEN', 'user_id');
define('BADGE_BACKPACK_ID_TOKEN', 'backpack_id');
define('BADGE_REFRESH_TOKEN', 'refresh');
define('BADGE_EXPIRES_TOKEN', 'expires');

// Adopted from https://github.com/jbkc85/openbadges-class-php.
// Author Jason Cameron <jbkc85@gmail.com>.

class backpack_api_mapping {
    public $action;
    private $url;
    public $params;
    public $requestexporter;
    public $responseexporter;
    public $multiple;
    public $method;
    public $json;
    public $authrequired;
    private $isuserbackpack;

    public function __construct($action, $url, $postparams, $requestexporter, $responseexporter,
                                $multiple, $method, $json, $authrequired, $isuserbackpack, $backpackapiversion) {
        $this->action = $action;
        $this->url = $url;
        $this->postparams = $postparams;
        $this->requestexporter = $requestexporter;
        $this->responseexporter = $responseexporter;
        $this->multiple = $multiple;
        $this->method = $method;
        $this->json = $json;
        $this->authrequired = $authrequired;
        $this->isuserbackpack = $isuserbackpack;
        $this->backpackapiversion = $backpackapiversion;
    }

    private function get_token_key($type) {
        $prefix = 'badges_';
        if ($this->isuserbackpack) {
            $prefix .= 'user_backpack_';
        } else {
            $prefix .= 'site_backpack_';
        }
        $prefix .= $type . '_token';
        return $prefix;
    }

    public function is_match($action) {
        return $this->action == $action;
    }

    private function get_url($apiurl, $param1, $param2) {
        $urlscheme = parse_url($apiurl, PHP_URL_SCHEME);
        $urlhost = parse_url($apiurl, PHP_URL_HOST);

        $url = $this->url;
        $url = str_replace('[SCHEME]', $urlscheme, $url);
        $url = str_replace('[HOST]', $urlhost, $url);
        $url = str_replace('[URL]', $apiurl, $url);
        $url = str_replace('[PARAM1]', $param1, $url);
        $url = str_replace('[PARAM2]', $param2, $url);

        return $url;
    }

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
        if ($this->json) {
            return json_encode($request);
        }
        return $request;
    }

    private function convert_email_response($response, $backpackid) {
        global $SESSION;

        if (isset($response->status) && $response->status == 'okay') {

            // Remember the tokens.
            $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
            $backpackidkey = $this->get_token_key(BADGE_BACKPACK_ID_TOKEN);

            $SESSION->$useridkey = $response->userId;
            $SESSION->$backpackidkey = $backpackid;
            return $response->userId;
        }
    }

    private function get_auth_user_id() {
        global $USER;

        if ($this->isuserbackpack) {
            return $USER->id;
        } else {
            // The access tokens for the system backpack are shared.
            return -1;
        }
    }

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
        }
        return $response;
    }

    private function get_curl_options() {
        return array(
            'FRESH_CONNECT'     => true,
            'RETURNTRANSFER'    => true,
            'FORBID_REUSE'      => true,
            'HEADER'            => 0,
            'CONNECTTIMEOUT'    => 3,
            'CONNECTTIMEOUT'    => 3,
            // Follow redirects with the same type of request when sent 301, or 302 redirects.
            'CURLOPT_POSTREDIR' => 3,
        );
    }

    public function request($apiurl, $urlparam1, $urlparam2, $email, $password, $postparam, $backpackid) {
        global $SESSION, $PAGE;

        $curl = new curl();

        $url = $this->get_url($apiurl, $urlparam1, $urlparam2);

        if ($this->authrequired) {
            $accesskey = $this->get_token_key(BADGE_ACCESS_TOKEN);
            $token = $SESSION->$accesskey;
            $curl->setHeader('Authorization: Bearer ' . $token);
        }
        if ($this->json) {
            $curl->setHeader(array('Content-type: application/json'));
        }
        $curl->setHeader(array('Accept: application/json', 'Expect:'));
        $options = $this->get_curl_options();

        $post = $this->get_post_params($email, $password, $postparam);
        if ($this->method == 'get') {
            $response = $curl->get($url, $post, $options);
        } else if ($this->method == 'post') {
            $response = $curl->post($url, $post, $options);
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
                $apidata = $exporter::map_external_data($response, $this->backpackapiversion);
                $exporterinstance = new $exporter($apidata, ['context' => $context]);
                $data = $exporterinstance->export($output);
                return $data; 
            } else {
                $multiple = [];
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

class OpenBadgesBackpackHandler {
    private $backpack;
    private $email;
    private $backpackapiurl;
    private $backpackapiversion;
    private $password;
    private $isuserbackpack;
    private $backpackid;

    private $mappingsv1site = [];
    private $mappingsv2site = [];
    private $mappingsv1user = [];
    private $mappingsv2user = [];

    /**
     * Create a wrapper to communicate with the backpack.
     *
     * The resulting class can only do either site backpack communication or
     * user backpack communication.
     *
     * @param stdClass $sitebackpack The site backpack record
     * @param mixed $userbackpack Optional - if passed it represents the users backpack.
     */
    public function __construct($sitebackpack, $userbackpack = false) {
        global $CFG;
        $admin = get_admin();
        
        $this->backpackapiurl = $sitebackpack->backpackapiurl;
        $this->backpackapiurl = $sitebackpack->backpackapiurl;
        $this->backpackapiversion = $sitebackpack->apiversion;
        $this->password = $sitebackpack->password;
        $this->email = !empty($CFG->badges_defaultissuercontact) ? $CFG->badges_defaultissuercontact : $admin->email;               
        $this->isuserbackpack = false;
        $this->backpackid = $sitebackpack->id;
        if (!empty($userbackpack)) {
            $this->isuserbackpack = true;
            $this->backpackapiurl = $userbackpack->backpackurl;
            $this->backpackapiversion = $userbackpack->apiversion;
            $this->password = $userbackpack->password;
            $this->email = $userbackpack->email;
        }
        // $this->backpackuid = isset($record->backpackuid) ? $record->backpackuid : 0;

        $this->define_mappings();
    }

    private function define_mappings() {
        if ($this->backpackapiversion == OPEN_BADGES_V2) {
            if ($this->isuserbackpack) {
                $mapping = [];
                $mapping[] = [
                    'collections',                              // Action.
                    '[URL]/backpack/collections',               // URL
                    [],                                         // Post params.
                    '',                                         // Request exporter.
                    'core_badges\external\collection_exporter', // Response exporter.
                    true,                                       // Multiple.
                    'get',                                      // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                $mapping[] = [
                    'user',                                     // Action.
                    '[SCHEME]://[HOST]/o/token',                // URL
                    ['username' => '[EMAIL]', 'password' => '[PASSWORD]'], // Post params.
                    '',                                         // Request exporter.
                    'oauth_token_response',                     // Response exporter.
                    false,                                      // Multiple.
                    'post',                                     // Method.
                    false,                                      // JSON Encoded.
                    false,                                      // Auth required.
                ];
                $mapping[] = [
                    'issuer',                                   // Action.
                    '[URL]/issuers/[PARAM2]',                   // URL
                    [],                                         // Post params.
                    '',                                         // Request exporter.
                    'core_badges\external\issuer_exporter',     // Response exporter.
                    false,                                      // Multiple.
                    'get',                                      // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                $mapping[] = [
                    'badgeclass',                               // Action.
                    '[URL]/badgeclasses/[PARAM2]',              // URL
                    [],                                         // Post params.
                    '',                                         // Request exporter.
                    'core_badges\external\badgeclass_exporter', // Response exporter.
                    false,                                      // Multiple.
                    'get',                                      // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                $mapping[] = [
                    'assertion',                                // Action.
                    '[URL]/backpack/assertions/[PARAM2]',       // URL
                    [],                                         // Post params.
                    '',                                         // Request exporter.
                    'core_badges\external\assertion_exporter',  // Response exporter.
                    false,                                      // Multiple.
                    'get',                                      // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                $mapping[] = [
                    'badges',                                   // Action.
                    '[URL]/backpack/collections/[PARAM1]',      // URL
                    [],                                         // Post params.
                    '',                                         // Request exporter.
                    'core_badges\external\collection_exporter', // Response exporter.
                    true,                                       // Multiple.
                    'get',                                      // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                foreach ($mapping as $map) {
                    $map[] = true; // User api function.
                    $map[] = OPEN_BADGES_V2; // V2 function.
                    $this->mappingsv2user[] = new backpack_api_mapping(...$map);
                }
            } else {
                $mapping = [];
                $mapping[] = [
                    'user',                                     // Action.
                    '[SCHEME]://[HOST]/o/token',                // URL
                    ['username' => '[EMAIL]', 'password' => '[PASSWORD]'], // Post params.
                    '',                                         // Request exporter.
                    'oauth_token_response',                     // Response exporter.
                    false,                                      // Multiple.
                    'post',                                     // Method.
                    false,                                      // JSON Encoded.
                    false                                       // Auth required.
                ];
                $mapping[] = [
                    'issuers',                                  // Action.
                    '[URL]/issuers',                            // URL
                    '[PARAM]',                                  // Post params.
                    'core_badges\external\issuer_exporter',     // Request exporter.
                    'core_badges\external\issuer_exporter',     // Response exporter.
                    false,                                      // Multiple.
                    'post',                                     // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                $mapping[] = [
                    'badgeclasses',                             // Action.
                    '[URL]/issuers/[PARAM2]/badgeclasses',      // URL
                    '[PARAM]',                                  // Post params.
                    'core_badges\external\badgeclass_exporter', // Request exporter.
                    'core_badges\external\badgeclass_exporter', // Response exporter.
                    false,                                      // Multiple.
                    'post',                                     // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                $mapping[] = [
                    'assertions',                               // Action.
                    '[URL]/badgeclasses/[PARAM2]/assertions',    // URL
                    '[PARAM]',                                  // Post params.
                    'core_badges\external\assertion_exporter', // Request exporter.
                    'core_badges\external\assertion_exporter', // Response exporter.
                    false,                                      // Multiple.
                    'post',                                     // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                foreach ($mapping as $map) {
                    $map[] = false; // Site api function.
                    $map[] = OPEN_BADGES_V2; // V2 function.
                    $this->mappingsv2site[] = new backpack_api_mapping(...$map);
                }
            }
        } else {
            if ($this->isuserbackpack) {
                $mapping = [];
                $mapping[] = [
                    'user',                                     // Action.
                    '[URL]/displayer/convert/email',            // URL
                    ['email' => '[EMAIL]'],                     // Post params.
                    '',                                         // Request exporter.
                    'convert_email_response',                   // Response exporter.
                    false,                                      // Multiple.
                    'post',                                     // Method.
                    false,                                      // JSON Encoded.
                    false                                       // Auth required.
                ];
                $mapping[] = [
                    'groups',                                   // Action.
                    '[URL]/displayer/[PARAM1]/groups.json',     // URL
                    [],                                         // Post params.
                    '',                                         // Request exporter.
                    '',                                         // Response exporter.
                    false,                                      // Multiple.
                    'get',                                      // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                $mapping[] = [
                    'badges',                                   // Action.
                    '[URL]/displayer/[PARAM2]/group/[PARAM1].json',     // URL
                    [],                                         // Post params.
                    '',                                         // Request exporter.
                    '',                                         // Response exporter.
                    false,                                      // Multiple.
                    'get',                                      // Method.
                    true,                                       // JSON Encoded.
                    true                                        // Auth required.
                ];
                foreach ($mapping as $map) {
                    $map[] = true; // User api function.
                    $map[] = OPEN_BADGES_V1; // V1 function.
                    $this->mappingsv1user[] = new backpack_api_mapping(...$map);
                }
            } else {
                $mapping = [];
                $mapping[] = [
                    'user',                                     // Action.
                    '[URL]/displayer/convert/email',            // URL
                    ['email' => '[EMAIL]'],                     // Post params.
                    '',                                         // Request exporter.
                    'convert_email_response',                   // Response exporter.
                    false,                                      // Multiple.
                    'post',                                     // Method.
                    false,                                      // JSON Encoded.
                    false                                       // Auth required.
                ];
                foreach ($mapping as $map) {
                    $map[] = false; // Site api function.
                    $map[] = OPEN_BADGES_V1; // V1 function.
                    $this->mappingsv1site[] = new backpack_api_mapping(...$map);
                }
            }
        }
    }

    private function curl_request($action, $collection = null, $entityid = null, $postdata = null) {
        global $CFG, $SESSION;

        $curl = new curl();
        $authrequired = false;
        $mappings = false;
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
            if (isset($SESSION->$useridkey)) {
                if ($collection == null) {
                    $collection = $SESSION->$useridkey;
                } else {
                    $entityid = $SESSION->$useridkey;
                }
            }
            if ($this->isuserbackpack) {
                $mappings = $this->mappingsv1user;
            } else {
                $mappings = $this->mappingsv1site;
            }
        } else {
            if ($this->isuserbackpack) {
                $mappings = $this->mappingsv2user;
            } else {
                $mappings = $this->mappingsv2site;
            }
        }
        foreach ($mappings as $mapping) {
            if ($mapping->is_match($action)) {
                return $mapping->request($this->backpackapiurl, $collection, $entityid, $this->email, $this->password, $postdata, $this->backpackid);
            }
        }

        throw new coding_exception('Unknown request');
            /*
        switch($action) {
            case 'user':
                // BOTH - BAD!
                if ($this->backpackapiversion == OPEN_BADGES_V1) {
                    $url = $this->backpackapiurl . "/displayer/convert/email";
                    $param = array('email' => $this->email);
                } else {
                    $url = rtrim($this->backpackapiurl, '/v2') . "/o/token";
                    $param = array('username' => $this->email, 'password' => $this->password);
                }
                break;
            case 'issuer':
                // V2
                $url = $this->backpackapiurl . "/issuers/" . $entityid;
                $authrequired = true;
                break;
            case 'issuers':
                // V2
                $url = $this->backpackapiurl . "/issuers";
                $param = $collection;
                $authrequired = true;
                break;
            case 'badgeclass':
                // V2
                $url = $this->backpackapiurl . "/badgeclasses/" . $entityid;
                $authrequired = true;
                break;
            case 'badgeclasses':
                // V2
                $url = $this->backpackapiurl . "/issuers/" . $entityid . "/badgeclasses";
                $param = $collection;
                $authrequired = true;
                break;
            case 'assertions':
                // V2
                $url = $this->backpackapiurl . "/badgeclasses/" . $entityid . "/assertions";
                $param = $collection;
                $authrequired = true;
                break;
            case 'assertion':
                // V2
                $url = $this->backpackapiurl . "/backpack/assertions/" . $entityid;
                $authrequired = true;
                break;
            case 'collections':
                // V2
                $url = $this->backpackapiurl . "/backpack/collections";
                $param = $collection;
                $authrequired = true;
                break;
            case 'groups':
                // V1
                $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
                $userid = $SESSION->$useridkey;
                $url = $this->backpackapiurl . '/displayer/' . $userid . '/groups.json';
                break;
            case 'badges':
                $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
                $userid = $SESSION->$useridkey;
                $url = $this->backpackapiurl . '/displayer/' . $userid . '/group/' . $collection . '.json';
                break;
        }

        if ($authrequired) {
            $accesskey = $this->get_token_key(BADGE_ACCESS_TOKEN);
            $token = $SESSION->$accesskey;
            $curl->setHeader('Authorization: Bearer ' . $token);
        }

        $curl->setHeader(array('Accept: application/json', 'Expect:'));
        $options = array(
            'FRESH_CONNECT'     => true,
            'RETURNTRANSFER'    => true,
            'FORBID_REUSE'      => true,
            'HEADER'            => 0,
            'CONNECTTIMEOUT'    => 3,
            'CONNECTTIMEOUT'    => 3,
            // Follow redirects with the same type of request when sent 301, or 302 redirects.
            'CURLOPT_POSTREDIR' => 3,
        );

        if ($action == 'user') {
            // BOTH
            $out = $curl->post($url, $param, $options);
        } else if (!empty($collection) && ($action == 'badgeclasses' || $action == 'badgeclass' || $action == 'issuers' || $action == 'issuer' || $action == 'assertions' || $action == 'assertion')) {
            // V2
            $curl->setHeader(array('Content-type: application/json'));
            $out = $curl->post($url, json_encode($param), $options);
        } else {
            // BOTH
            $out = $curl->get($url, array(), $options);
        }

        return json_decode($out);
            */
    }

    private function get_auth_user_id() {
        global $USER;

        if ($this->isuserbackpack) {
            return $USER->id;
        } else {
            // The access tokens for the system backpack are shared.
            return -1;
        }
    }


    private function get_token_key($type) {
        // This should be removed when everything has a mapping.
        $prefix = 'badges_';
        if ($this->isuserbackpack) {
            $prefix .= 'user_backpack_';
        } else {
            $prefix .= 'site_backpack_';
        }
        $prefix .= $type . '_token';
        return $prefix;
    }


    private function check_status($status) {
        // V1 ONLY
        switch($status) {
            case "missing":
                $response = array(
                    'status'  => $status,
                    'message' => get_string('error:nosuchuser', 'badges')
                );
                return $response;
        }
    }

    public function get_badgeclass_assertions($entityid) {
        die('Cya');
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        return $this->curl_request('assertions', null, $entityid);
    }


    public function get_assertion($entityid) {
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        return $this->curl_request('assertion', null, $entityid);
    }

    public function get_badgeclass($entityid) {
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        return $this->curl_request('badgeclass', null, $entityid);
    }

    public function get_badgeclasses($entityid) {
        die('We can go away');
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        $result = $this->curl_request('badgeclasses', null, $entityid);
        if ($result->result) {
            return $result->result;
        }
        return false;
    }

    public function put_badgeclass_assertion($entityid, $data) {
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }

        return $this->curl_request('assertions', null, $entityid, $data);
    }

    public function set_backpack_collections($backpackid, $collections) {
        global $DB, $USER;

        // Delete any previously selected collections.
        $sqlparams = array('backpack' => $backpackid);
        $select = 'backpackid = :backpack ';
        $DB->delete_records_select('badge_external', $select, $sqlparams);
        $badgescache = cache::make('core', 'externalbadges');

        // Insert selected collections if they are not in database yet.
        foreach ($collections as $collection) {
            $obj = new stdClass();
            $obj->backpackid = $backpackid;
            if ($this->backpackapiversion == OPEN_BADGES_V1) {
                $obj->collectionid = (int) $collection;
            } else {
                $obj->entityid = $collection;
                $obj->collectionid = -1;
            }
            if (!$DB->record_exists('badge_external', (array) $obj)) {
                $DB->insert_record('badge_external', $obj);
            }
        }
        $badgescache->delete($USER->id);
    }

    public function put_badgeclass($entityid, $data) {
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        return $this->curl_request('badgeclasses', null, $entityid, $data);
    }

    public function get_issuers() {
        // I think we can blow this up!
        die('Goodbye');
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        $result = $this->curl_request('issuers');
        if ($result->result) {
            return $result->result;
        }
        return false;
    }

    public function put_issuer($data) {
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        return $this->curl_request('issuers', null, null, $data);
    }

    public function get_issuer($entityid) {
        // V2 Only
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            throw new coding_exception('Not supported in this backpack API');
        }
        
        $result = $this->curl_request('issuer', null, $entityid);
        return $result;
    }

    /**
     * Authenticate using the stored email and password and save the valid access tokens.
     * @return mixed Success - 
     */
    public function authenticate() {
        global $SESSION;

        $backpackidkey = $this->get_token_key(BADGE_BACKPACK_ID_TOKEN);
        $backpackid = isset($SESSION->$backpackidkey) ? $SESSION->$backpackidkey : 0;
        // If the backpack is changed we need to expire sessions.
        if ($backpackid == $this->backpackid) {
            if ($this->backpackapiversion == OPEN_BADGES_V2) {
                $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
                $authuserid = isset($SESSION->$useridkey) ? $SESSION->$useridkey : 0;
                if ($authuserid == $this->get_auth_user_id()) {
                    $expireskey = $this->get_token_key(BADGE_EXPIRES_TOKEN);
                    if (isset($SESSION->$expireskey)) {
                        $expires = $SESSION->$expireskey;
                        if ($expires > time()) {
                            // We have a current access token for this user
                            // that has not expired.
                            return -1;
                        }
                    }
                }
            } else {
                $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
                $authuserid = isset($SESSION->$useridkey) ? $SESSION->$useridkey : 0;
                if (!empty($authuserid)) {
                    return $authuserid;
                }
            }
        }
        return $this->curl_request('user', $this->email);
    }

    public function get_collections() {
        global $PAGE;

        if ($this->authenticate()) {
            if ($this->backpackapiversion == OPEN_BADGES_V1) {
                $result = $this->curl_request('groups');
                if (isset($result->groups)) {
                    $result = $result->groups;
                }
            } else {
                $result = $this->curl_request('collections');
            }
            if ($result) {
                return $result;
            }
        }
        return [];
    }

    public function get_collection_record($collectionid) {
        global $DB;

        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            return $DB->get_fieldset_select('badge_external', 'collectionid', 'backpackid = :bid', array('bid' => $collectionid));
        } else {
            return $DB->get_fieldset_select('badge_external', 'entityid', 'backpackid = :bid', array('bid' => $collectionid));
        }
    }

    public function disconnect_backpack($userid, $backpackid) {
        global $DB, $USER;

        if (\core\session\manager::is_loggedinas() || $userid != $USER->id) {
            // Can't change someone elses backpack settings.
            return false;
        }

        $badgescache = cache::make('core', 'externalbadges');

        $DB->delete_records('badge_external', array('backpackid' => $backpackid));
        $DB->delete_records('badge_backpack', array('userid' => $userid));
        $badgescache->delete($userid);
        return true;
    }

    public function get_collection_id_from_response($data) {
        if ($this->backpackapiversion == OPEN_BADGES_V1) {
            return $data->groupId;
        } else {
            return $data->entityId;
        }
    }

    public function get_badges($collection, $expanded = false) {
        if ($this->authenticate()) {
            if ($this->backpackapiversion == OPEN_BADGES_V1) {
                if (empty($collection->collectionid)) {
                    return [];
                }
                $result = $this->curl_request('badges', $collection->collectionid);
                return $result->badges;
            } else {
                if (empty($collection->entityid)) {
                    return [];
                }
                // Now we can make requests.
                $badges = $this->curl_request('badges', $collection->entityid);
                if (count($badges) == 0) {
                    return [];
                }
                $badges = $badges[0];
                if ($expanded) {
                    $publicassertions = [];
                    foreach ($badges->assertions as $assertion) {
                        $remoteassertion = $this->get_assertion($assertion);
                        $remotebadge = $this->get_badgeclass($remoteassertion->badgeclass);
                        $remoteissuer = $this->get_issuer($remotebadge->issuer);

                        $badgeclone = clone $remotebadge;
                        $badgeclone->issuer = $remoteissuer;
                        $remoteassertion->badge = $badgeclone;
                        $remotebadge->assertion = $remoteassertion;
                                    
                        $publicassertions[] = $remotebadge;
                    }
                    $badges = $publicassertions;
                }
                return $badges;
            }
        }
    }

    public function get_url() {
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        var_dump(__FILE__ . ' : ' . __LINE__);
        var_dump('I dont think this is a value that should be retrieved from the backpack.');
        return $this->backpackapiurl;
    }

    public function get_apiversion() {
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        var_dump(__FILE__ . ' : ' . __LINE__);
        var_dump('This is definitely not a value that should be retrieved from the backpack.');
        return $this->backpackapiversion;
    }
}
