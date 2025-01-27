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

namespace core_badges\local\backpack\ob\v2p1;

use coding_exception;
use moodle_url;
use stdClass;
use core_badges\local\backpack\mapping\mapping_token;
use core_badges\local\backpack\ob\api_base;
use core_badges\oauth2\client;
use core_badges\oauth2\badge_backpack_oauth2;
use core\oauth2\issuer;
use core\oauth2\endpoint;
use core\oauth2\discovery\imsbadgeconnect;

/**
 * To process badges with backpack and control api request and this class using for Open Badge API v2.1 methods.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api extends api_base {

    /** @var false|null|stdClass|api to */
    private $tokendata;

    /** @var null clienid. */
    private $clientid = null;

    /** @var issuer The OAuth2 Issuer for this backpack */
    protected issuer $issuer;

    /** @var endpoint The apiBase endpoint */
    protected endpoint $apibase;

    /**
     * Create a wrapper to communicate with the backpack.
     *
     * The resulting class can only do either site backpack communication or
     * user backpack communication.
     *
     * @param stdClass $externalbackpack The external backpack record
     * @param mixed ...$extra Extra arguments to allow for future versions to add more options
     */
    public function __construct(
        /** @var stdClass The external backpack record. */
        protected stdClass $externalbackpack,
        mixed ...$extra,
    ) {
        parent::__construct(externalbackpack: $externalbackpack);

        $this->get_clientid($externalbackpack->oauth2_issuerid);
        if (!$this->tokendata = $this->get_stored_token($externalbackpack->id)) {
            throw new coding_exception('Backpack incorrect');
        }

    }

    protected function get_api_version(): string {
        return OPEN_BADGES_V2P1;
    }

    protected function get_mapping_class(): string {
        return mapping_token::class;
    }

    /**
     * Get the mappings supported by this usage and api version.
     *
     * @return array The mappings.
     */
    protected function get_mappings(): array {
        $mapping[] = [
            'post.assertions',          // Action.
            '[URL]/assertions',         // URL
            '[PARAM]',                  // Post params.
            false,                      // Multiple.
            'post',                     // Method.
            true,                       // JSON Encoded.
            true,                       // Auth required.
            $this->get_api_version(),   // Backpack version.
        ];

        $mapping[] = [
            'get.assertions',           // Action.
            '[URL]/assertions',         // URL
            '[PARAM]',                  // Post params.
            false,                      // Multiple.
            'get',                      // Method.
            true,                       // JSON Encoded.
            true,                       // Auth required.
            $this->get_api_version(),   // Backpack version.
        ];

        return $mapping;
    }

    /**
     * Initialises or returns the OAuth2 issuer associated to this backpack.
     *
     * @return issuer
     */
    protected function get_issuer(): issuer {
        if (!isset($this->issuer)) {
            $this->issuer = new issuer($this->externalbackpack->oauth2_issuerid);
        }
        return $this->issuer;
    }

    /**
     * Gets the apiBase url associated to this backpack.
     *
     * @return string
     */
    protected function get_api_base_url(): string {
        if (!isset($this->apibase)) {
            $apibase = endpoint::get_record([
                'issuerid' => $this->externalbackpack->oauth2_issuerid,
                'name' => 'apiBase',
            ]);

            if (empty($apibase)) {
                imsbadgeconnect::create_endpoints($this->get_issuer());
                $apibase = endpoint::get_record([
                    'issuerid' => $this->externalbackpack->oauth2_issuerid,
                    'name' => 'apiBase',
                ]);
            }

            $this->apibase = $apibase;
        }

        return $this->apibase->get('url');
    }


    /**
     * Disconnect the backpack from current user.
     *
     * @return bool
     * @throws \dml_exception
     */
    public function disconnect_backpack(): bool {
        global $USER, $DB;

        $badgebackpack = $this->externalbackpack->badgebackpack;
        $DB->delete_records_select('badge_external', 'backpackid = :backpack', ['backpack' => $badgebackpack]);
        $DB->delete_records('badge_backpack', ['id' => $badgebackpack]);
        $DB->delete_records('badge_backpack_oauth2', ['externalbackpackid' => $badgebackpack, 'userid' => $USER->id]);

        return true;
    }

    /**
     * Make an api request.
     *
     * @param string $action The api function.
     * @param string $postdata The body of the api request.
     * @return mixed
     */
    public function curl_request($action, $postdata = null) {
        $tokenkey = $this->tokendata->token;
        foreach ($this->mappings as $mapping) {
            if ($mapping->is_match($action)) {
                return $mapping->request(
                    $this->get_api_base_url(),
                    $tokenkey,
                    $postdata
                );
            }
        }

        throw new coding_exception('Unknown request');
    }

    /**
     * Get token.
     *
     * @param int $externalbackpackid ID of external backpack.
     * @return badge_backpack_oauth2|false|stdClass|null
     */
    protected function get_stored_token($externalbackpackid) {
        global $USER;

        $token = badge_backpack_oauth2::get_record(
            ['externalbackpackid' => $externalbackpackid, 'userid' => $USER->id]);
        if ($token !== false) {
            $token = $token->to_record();
            return $token;
        }
        return null;
    }

    /**
     * Get client id.
     *
     * @param int $issuerid id of Oauth2 service.
     * @throws coding_exception
     */
    private function get_clientid($issuerid) {
        $issuer = \core\oauth2\api::get_issuer($issuerid);
        if (!empty($issuer)) {
            $this->issuer = $issuer;
            $this->clientid = $issuer->get('clientid');
        }
    }

    /**
     * Export a badge to the backpack site.
     *
     * @param string $hash of badge issued.
     * @return array
     * @throws \moodle_exception
     * @throws coding_exception
     */
    public function put_assertions($hash) {
        $data = [];
        if (!$hash) {
            return false;
        }

        $issuer = $this->get_issuer();
        $client = new client($issuer, new moodle_url('/badges/mybadges.php'), '', $this->externalbackpack);
        if (!$client->is_logged_in()) {
            $redirecturl = new moodle_url('/badges/mybadges.php', ['error' => 'backpackexporterror']);
            redirect($redirecturl);
        }

        $this->tokendata = $this->get_stored_token($this->externalbackpack->id);

        $assertion = new \core_badges_assertion($hash, OPEN_BADGES_V2);
        $data['assertion'] = $assertion->get_badge_assertion();
        $response = $this->curl_request('post.assertions', $data);
        if ($response && isset($response->status->statusCode) && $response->status->statusCode == 200) {
            $msg['status'] = \core\output\notification::NOTIFY_SUCCESS;
            $msg['message'] = get_string('addedtobackpack', 'badges');
        } else {
            if ($response) {
                // Although the specification defines that status error is a string, some providers, like Badgr, are wrongly
                // returning an array. It has been reported, but adding these extra checks doesn't hurt, just in case.
                if (
                    property_exists($response, 'status') &&
                    is_object($response->status) &&
                    property_exists($response->status, 'error')
                ) {
                    $statuserror = $response->status->error;
                    if (is_array($statuserror)) {
                        $statuserror = implode($statuserror);
                    }
                } else if (property_exists($response, 'error')) {
                    $statuserror = $response->error;
                    if (property_exists($response, 'message')) {
                        $statuserror .= '. Message: ' . $response->message;
                    }
                }
            } else {
                $statuserror = 'Empty response';
            }
            $data = [
                'badgename' => $data['assertion']['badge']['name'],
                'error' => $statuserror,
            ];

            $msg['status'] = \core\output\notification::NOTIFY_ERROR;
            $msg['message'] = get_string('backpackexporterrorwithinfo', 'badges', $data);
        }
        return $msg;
    }

    /**
     * Get assertions.
     *
     * @return array
     */
    // public function get_assertions(): array {
    //     // TODO: Implement the funcionality for displaying external badges in profile for 2.1.
    //     $msg = [];

    //     $issuer = $this->get_issuer();
    //     $client = new client($issuer, new moodle_url('/badges/mybadges.php'), '', $this->externalbackpack);
    //     if (!$client->is_logged_in()) {
    //         $redirecturl = new moodle_url('/badges/mybadges.php', ['error' => 'backpackexporterror']);
    //         redirect($redirecturl);
    //     }

    //     $this->tokendata = $this->get_stored_token($this->externalbackpack->id);
    //     $response = $this->curl_request('get.assertions');
    //     if ($response && isset($response->status->statusCode) && $response->status->statusCode == 200) {
    //         $msg['status'] = \core\output\notification::NOTIFY_SUCCESS;
    //         $msg['message'] = 'Success';
    //     }

    //     return $msg;
    // }
}
