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

namespace core_badges\local\backpack\ob\v2p0;

use core_badges\local\backpack\mapping\mapping_session;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

use cache;
use coding_exception;
use context_system;
use stdClass;
use core_badges\external\issuer_exporter;
use core_badges\external\badgeclass_exporter;
use core_badges\local\backpack\ob\api_base;

define('BADGE_ACCESS_TOKEN', 'access');
define('BADGE_USER_ID_TOKEN', 'user_id');
define('BADGE_BACKPACK_ID_TOKEN', 'backpack_id');
define('BADGE_REFRESH_TOKEN', 'refresh');
define('BADGE_EXPIRES_TOKEN', 'expires');

/**
 * Class for communicating with backpacks using OBv2.0.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api extends api_base {

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
        return mapping_session::class;
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
}
