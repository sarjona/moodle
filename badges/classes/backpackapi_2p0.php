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

namespace core_badges;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

use cache;
use coding_exception;
use core_badges\external\issuer_exporter;
use core_badges\external\badgeclass_exporter;
use core_badges\local\backpack\v2p0\assertion_exporter;
use stdClass;
use context_system;

define('BADGE_ACCESS_TOKEN', 'access');
define('BADGE_USER_ID_TOKEN', 'user_id');
define('BADGE_BACKPACK_ID_TOKEN', 'backpack_id');
define('BADGE_REFRESH_TOKEN', 'refresh');
define('BADGE_EXPIRES_TOKEN', 'expires');

/**
 * Class for communicating with backpacks using OBv2.0.
 *
 * @package    core_badges
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backpackapi_2p0 extends backpackapi_base {

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
        if (!property_exists($externalbackpack, 'email')) {
            $externalbackpack->email = $externalbackpack->backpackemail;
        }

        parent::__construct($externalbackpack);

        // Clear the last authentication error.
        $this->get_mapping_class()::set_authentication_error('');
    }

    protected function get_api_version(): string {
        return OPEN_BADGES_V2;
    }

    protected function get_mapping_class(): string {
        return backpackapi_mapping_session::class;
    }

    /**
     * Get the mappings supported by this usage and api version.
     *
     * @return array The mappings.
     */
    protected function get_mappings(): array {
        $mapping[] = [
            'collections',                              // Action.
            '[URL]/backpack/collections',               // URL.
            [],                                         // Post params.
            true,                                       // Multiple.
            'get',                                      // Method.
            true,                                       // JSON Encoded.
            true,                                       // Auth required.
            $this->get_api_version(),                   // Backpack version.
            '',                                         // Request exporter.
            'core_badges\external\collection_exporter', // Response exporter.
        ];
        $mapping[] = [
            'user',                                     // Action.
            '[SCHEME]://[HOST]/o/token',                // URL.
            ['username' => '[EMAIL]', 'password' => '[PASSWORD]'], // Post params.
            false,                                      // Multiple.
            'post',                                     // Method.
            false,                                      // JSON Encoded.
            false,                                      // Auth required.
            $this->get_api_version(),                   // Backpack version.
            '',                                         // Request exporter.
            'oauth_token_response',                     // Response exporter.
        ];
        $mapping[] = [
            'assertion',                                // Action.
            // Badgr.io does not return the public information about a badge
            // if the issuer is associated with another user. We need to pass
            // the expand parameters which are not in any specification to get
            // additional information about the assertion in a single request.
            '[URL]/backpack/assertions/[PARAM2]?expand=badgeclass&expand=issuer',
            [],                                         // Post params.
            false,                                      // Multiple.
            'get',                                      // Method.
            true,                                       // JSON Encoded.
            true,                                       // Auth required.
            $this->get_api_version(),                   // Backpack version.
            '',                                         // Request exporter.
            'core_badges\external\assertion_exporter',  // Response exporter.
        ];
        $mapping[] = [
            'importbadge',                                // Action.
            // Badgr.io does not return the public information about a badge
            // if the issuer is associated with another user. We need to pass
            // the expand parameters which are not in any specification to get
            // additional information about the assertion in a single request.
            '[URL]/backpack/import',
            ['url' => '[PARAM]'],  // Post params.
            false,                                          // Multiple.
            'post',                                         // Method.
            true,                                           // JSON Encoded.
            true,                                           // Auth required.
            $this->get_api_version(),                   // Backpack version.
            '',                                             // Request exporter.
            'core_badges\external\assertion_exporter',      // Response exporter.
        ];
        $mapping[] = [
            'badges',                                   // Action.
            '[URL]/backpack/collections/[PARAM1]',      // URL.
            [],                                         // Post params.
            true,                                       // Multiple.
            'get',                                      // Method.
            true,                                       // JSON Encoded.
            true,                                       // Auth required.
            $this->get_api_version(),                   // Backpack version.
            '',                                         // Request exporter.
            'core_badges\external\collection_exporter', // Response exporter.
        ];
        $mapping[] = [
            'issuers',                                  // Action.
            '[URL]/issuers',                            // URL.
            '[PARAM]',                                  // Post params.
            false,                                      // Multiple.
            'post',                                     // Method.
            true,                                       // JSON Encoded.
            true,                                       // Auth required.
            $this->get_api_version(),                   // Backpack version.
            'core_badges\external\issuer_exporter',     // Request exporter.
            'core_badges\external\issuer_exporter',     // Response exporter.
        ];
        $mapping[] = [
            'badgeclasses',                             // Action.
            '[URL]/issuers/[PARAM2]/badgeclasses',      // URL.
            '[PARAM]',                                  // Post params.
            false,                                      // Multiple.
            'post',                                     // Method.
            true,                                       // JSON Encoded.
            true,                                       // Auth required.
            $this->get_api_version(),                   // Backpack version.
            'core_badges\external\badgeclass_exporter', // Request exporter.
            'core_badges\external\badgeclass_exporter', // Response exporter.
        ];
        $mapping[] = [
            'assertions',                               // Action.
            '[URL]/badgeclasses/[PARAM2]/assertions',   // URL.
            '[PARAM]',                                  // Post params.
            false,                                      // Multiple.
            'post',                                     // Method.
            true,                                       // JSON Encoded.
            true,                                       // Auth required.
            $this->get_api_version(),                   // Backpack version.
            'core_badges\external\assertion_exporter',  // Request exporter.
            'core_badges\external\assertion_exporter',  // Response exporter.
        ];
        $mapping[] = [
            'updateassertion',                          // Action.
            '[URL]/assertions/[PARAM2]?expand=badgeclass&expand=issuer',
            '[PARAM]',                                  // Post params.
            false,                                      // Multiple.
            'put',                                      // Method.
            true,                                       // JSON Encoded.
            true,                                       // Auth required.
            $this->get_api_version(),                   // Backpack version.
            'core_badges\external\assertion_exporter',  // Request exporter.
            'core_badges\external\assertion_exporter',  // Response exporter.
        ];

        return $mapping;
    }

    /**
     * Disconnect the backpack from this user.
     *
     * @return bool
     */
    public function disconnect_backpack(): bool {
        global $DB, $USER;

        if (\core\session\manager::is_loggedinas()) {
            // Can't change someone elses backpack settings.
            return false;
        }

        $badgescache = cache::make('core', 'externalbadges');

        $DB->delete_records('badge_external', array('backpackid' => $this->externalbackpack->id));
        $DB->delete_records('badge_backpack', array('userid' => $USER->id));
        $badgescache->delete($USER->id);
        $this->clear_system_user_session();

        return true;
    }

    /**
     * Make an api request
     *
     * @param string $action The api function.
     * @param string $collection An api parameter
     * @param string $entityid An api parameter
     * @param string $postdata The body of the api request.
     * @return mixed
     */
    private function curl_request($action, $collection = null, $entityid = null, $postdata = null) {
        foreach ($this->mappings as $mapping) {
            if ($mapping->is_match($action)) {
                return $mapping->request(
                    $this->externalbackpack->backpackapiurl,
                    $collection,
                    $entityid,
                    $this->externalbackpack->email,
                    $this->externalbackpack->password,
                    $postdata,
                    $this->externalbackpack->id,
                );
            }
        }

        throw new coding_exception('Unknown request');
    }

    /**
     * Get the id to use for requests with this api.
     *
     * @return integer
     */
    private function get_auth_user_id() {
        global $USER;

        return $USER->id;
    }

    /**
     * Get the name of the key to store this access token type.
     *
     * @param string $type
     * @return string
     */
    private function get_token_key($type): string {
        return 'badges_backpack_' . $type . '_token';
    }

    /**
     * Make an api request to get an assertion
     *
     * @param string $entityid The id of the assertion.
     * @return mixed
     */
    public function get_assertion($entityid) {
        return $this->curl_request('assertion', null, $entityid);
    }

    /**
     * Create a badgeclass assertion.
     *
     * @param string $entityid The id of the badge class.
     * @param string $data The structure of the badge class assertion.
     * @return mixed
     */
    public function put_badgeclass_assertion($entityid, $data) {
        return $this->curl_request('assertions', null, $entityid, $data);
    }

    /**
     * Update a badgeclass assertion.
     *
     * @param string $entityid The id of the badge class.
     * @param array $data The structure of the badge class assertion.
     * @return mixed
     */
    public function update_assertion(string $entityid, array $data) {
        return $this->curl_request('updateassertion', null, $entityid, $data);
    }

    /**
     * Import a badge assertion into a backpack. This is used to handle cross domain backpacks.
     *
     * @param string $data The structure of the badge class assertion.
     * @return mixed
     * @throws coding_exception
     */
    public function import_badge_assertion(string $data) {
        return $this->curl_request('importbadge', null, null, $data);
    }

    /**
     * Select collections from a backpack.
     *
     * @param string $backpackid The id of the backpack
     * @param stdClass[] $collections List of collections with collectionid or entityid.
     * @return boolean
     */
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
            $obj->entityid = $collection;
            $obj->collectionid = -1;
            if (!$DB->record_exists('badge_external', (array) $obj)) {
                $DB->insert_record('badge_external', $obj);
            }
        }
        $badgescache->delete($USER->id);
        return true;
    }

    /**
     * Create a badgeclass
     *
     * @param string $entityid The id of the entity.
     * @param string $data The structure of the badge class.
     * @return mixed
     */
    public function put_badgeclass($entityid, $data) {
        return $this->curl_request('badgeclasses', null, $entityid, $data);
    }

    /**
     * Create an issuer
     *
     * @param string $data The structure of the issuer.
     * @return mixed
     */
    public function put_issuer($data) {
        return $this->curl_request('issuers', null, null, $data);
    }

    /**
     * Delete any user access tokens in the session so we will attempt to get new ones.
     *
     * @return void
     */
    public function clear_system_user_session() {
        global $SESSION;

        $useridkey = $this->get_token_key(BADGE_USER_ID_TOKEN);
        unset($SESSION->$useridkey);

        $expireskey = $this->get_token_key(BADGE_EXPIRES_TOKEN);
        unset($SESSION->$expireskey);
    }

    /**
     * Authenticate using the stored email and password and save the valid access tokens.
     *
     * @return mixed The id of the authenticated user as returned by the backpack. Can have
     *    different formats - numeric, empty, object with 'error' property, etc.
     */
    public function authenticate() {
        global $SESSION;

        $backpackidkey = $this->get_token_key(BADGE_BACKPACK_ID_TOKEN);
        $backpackid = isset($SESSION->$backpackidkey) ? $SESSION->$backpackidkey : 0;
        // If the backpack is changed we need to expire sessions.
        if ($backpackid == $this->externalbackpack->id) {
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
        }
        return $this->curl_request('user', $this->externalbackpack->email);
    }

    /**
     * Get all collections in this backpack.
     *
     * @return stdClass[] The collections.
     */
    public function get_collections() {
        if ($this->authenticate()) {
            $result = $this->curl_request('collections');
            if ($result) {
                return $result;
            }
        }
        return [];
    }

    /**
     * Get one collection by id.
     *
     * @param integer $collectionid
     * @return array The collection.
     */
    public function get_collection_record($collectionid) {
        global $DB;

        return $DB->get_fieldset_select('badge_external', 'entityid', 'backpackid = :bid', ['bid' => $collectionid]);
    }

    /**
     * Handle the response from getting a collection to map to an id.
     *
     * @param stdClass $data The response data.
     * @return string The collection id.
     */
    public function get_collection_id_from_response($data) {
        return $data->entityId;
    }

    /**
     * Get the last error message returned during an authentication request.
     *
     * @return string
     */
    public function get_authentication_error() {
        return $this->$this->get_mapping_class()::get_authentication_error();
    }

    /**
     * Get the list of badges in a collection.
     *
     * @param stdClass $collection The collection to deal with.
     * @param boolean $expanded Fetch all the sub entities.
     * @return stdClass[]
     */
    public function get_badges($collection, $expanded = false) {
        global $PAGE;

        if ($this->authenticate()) {
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
                $context = context_system::instance();
                $output = $PAGE->get_renderer('core', 'badges');
                foreach ($badges->assertions as $assertion) {
                    $remoteassertion = $this->get_assertion($assertion);
                    // Remote badge was fetched nested in the assertion.
                    $remotebadge = $remoteassertion->badgeclass;
                    if (!$remotebadge) {
                        continue;
                    }
                    $apidata = badgeclass_exporter::map_external_data($remotebadge, $this->get_api_version());
                    $exporterinstance = new badgeclass_exporter($apidata, ['context' => $context]);
                    $remotebadge = $exporterinstance->export($output);

                    $remoteissuer = $remotebadge->issuer;
                    $apidata = issuer_exporter::map_external_data($remoteissuer, $this->get_api_version());
                    $exporterinstance = new issuer_exporter($apidata, ['context' => $context]);
                    $remoteissuer = $exporterinstance->export($output);

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

        return [];
    }

    /**
     * Send an assertion to the backpack.
     *
     * @param string $hash The assertion hash
     * @return array
     */
    public function put_assertions(string $hash): array {
        global $USER, $DB;

        $issuedbadge = new \core_badges\output\issued_badge($hash);
        if (!empty($issuedbadge->recipient->id)) {
            // The flow for issuing a badge is:
            // * Create issuer
            // * Create badge
            // * Create assertion (Award the badge!)

            // With the introduction OBv2.1 and MDL-65959 to allow cross region Badgr imports the above (old) procedure will
            // only be completely performed if both the site and user backpacks conform to the same apiversion.
            // Else we will attempt at pushing the assertion to the user's backpack. In this case, the id set against the assertion
            // has to be a publicly accessible resource.

            // Get the backpack.
            $badgeid = $issuedbadge->badgeid;
            $badge = new badge($badgeid);
            $backpack = $DB->get_record('badge_backpack', array('userid' => $USER->id));
            $userbackpack = badges_get_site_backpack($backpack->externalbackpackid, $USER->id);
            // $assertion = new \core_badges_assertion($hash, OPEN_BADGES_V2);
            // $assertiondata = $assertion->get_badge_assertion(false, false);
            $assertion = new assertion_exporter($hash);
            $assertiondata = $assertion->export(false, false);
            $assertionid = $assertion->get_assertion_hash();
            $assertionentityid = $assertiondata['id'];
            $badgeadded = false;
            if (badges_open_badges_backpack_api() == OPEN_BADGES_V2) {
                $sitebackpack = badges_get_site_primary_backpack();
                $api = backpackapi_base::create_from_externalbackpack($userbackpack);
                $response = $api->authenticate();

                // A numeric response indicates a valid successful authentication. Else an error object will be returned.
                if (is_numeric($response)) {
                    // Create issuer.
                    $issuer = $assertion->get_issuer();
                    if (!($issuerentityid = badges_external_get_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_ISSUER, $issuer['email']))) {
                        $response = $api->put_issuer($issuer);
                        if (!$response) {
                            throw new \moodle_exception('invalidrequest', 'error');
                        }
                        $issuerentityid = $response->id;
                        badges_external_create_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_ISSUER, $issuer['email'],
                            $issuerentityid);
                    }
                    // Create badge.
                    $badge = $assertion->get_badge_class(false);
                    $badgeid = $assertion->get_badge_id();
                    if (!($badgeentityid = badges_external_get_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_BADGE, $badgeid))) {
                        $response = $api->put_badgeclass($issuerentityid, $badge);
                        if (!$response) {
                            throw new \moodle_exception('invalidrequest', 'error');
                        }
                        $badgeentityid = $response->id;
                        badges_external_create_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_BADGE, $badgeid,
                            $badgeentityid);
                    }

                    // Create assertion (Award the badge!).
                    $assertionentityid = badges_external_get_mapping(
                        $sitebackpack->id,
                        OPEN_BADGES_V2_TYPE_ASSERTION,
                        $assertionid
                    );

                    if ($assertionentityid && strpos($sitebackpack->backpackapiurl, 'badgr')) {
                        $assertionentityid = badges_generate_badgr_open_url(
                            $sitebackpack,
                            OPEN_BADGES_V2_TYPE_ASSERTION,
                            $assertionentityid
                        );
                    }

                    // Create an assertion for the recipient in the issuer's account.
                    if (!$assertionentityid) {
                        $response = $api->put_badgeclass_assertion($badgeentityid, $assertiondata);
                        if (!$response) {
                            throw new \moodle_exception('invalidrequest', 'error');
                        }
                        $assertionentityid = badges_generate_badgr_open_url($sitebackpack, OPEN_BADGES_V2_TYPE_ASSERTION, $response->id);
                        $badgeadded = true;
                        badges_external_create_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_ASSERTION, $assertionid,
                            $response->id);
                    } else {
                        // An assertion already exists. Make sure it's up to date.
                        $internalid = badges_external_get_mapping(
                            $sitebackpack->id,
                            OPEN_BADGES_V2_TYPE_ASSERTION,
                            $assertionid,
                            'externalid'
                        );
                        $response = $api->update_assertion($internalid, $assertiondata);
                        if (!$response) {
                            throw new \moodle_exception('invalidrequest', 'error');
                        }
                    }
                }
            }

            // Now award/upload the badge to the user's account.
            // - If a user and site backpack have the same provider we can skip this as Badgr automatically maps recipients
            // based on email address.
            // - This is only needed when the backpacks are from different regions.
            if ($assertionentityid && !badges_external_get_mapping($userbackpack->id, OPEN_BADGES_V2_TYPE_ASSERTION, $assertionid)) {
                $userapi = backpackapi_base::create_from_externalbackpack($userbackpack);
                $userapi->authenticate();
                $response = $userapi->import_badge_assertion($assertionentityid);
                if (!$response) {
                    throw new \moodle_exception('invalidrequest', 'error');
                }
                $assertionentityid = $response->id;
                $badgeadded = true;
                badges_external_create_mapping($userbackpack->id, OPEN_BADGES_V2_TYPE_ASSERTION, $assertionid,
                    $assertionentityid);
            }

            $response = $badgeadded ? ['success' => 'addedtobackpack'] : ['warning' => 'existsinbackpack'];
        }

        return $response;
    }
}
