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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

use cache;
use coding_exception;
use context_system;
use stdClass;
use core\url;
use core_badges\external\issuer_exporter;
use core_badges\external\badgeclass_exporter;
use core_badges\local\backpack\ob\api_base;
use core_badges\local\backpack\mapping\mapping_base;
use core_badges\local\backpack\mapping\mapping_session;

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
     */
    public function __construct(
        /** @var stdClass The external backpack record. */
        protected stdClass $externalbackpack,
    ) {
        if (!property_exists($externalbackpack, 'email')) {
            $externalbackpack->email = $externalbackpack->backpackemail;
        }

        parent::__construct($externalbackpack);

        // TODO: Clear the last authentication error.
        // $this->get_mapping_class()::set_authentication_error('');
    }

    #[\Override]
    protected static function get_api_version(): string {
        return OPEN_BADGES_V2;
    }

    #[\Override]
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
     * Get the last error message returned during an authentication request.
     *
     * @return string
     */
    public function get_authentication_error() {
        return $this->$this->get_mapping_class()::get_authentication_error();
    }

    #[\Override]
    public function connect_backpack($data): ?url {
        global $USER;

        // If backpack is not connected, need to connect first.
        // To create a new connection to the backpack, first we need to verify the user's email address:
        // 1. User enters email and clicks 'Connect to backpack'.
        // 2. After cross-checking the email address against the backpack provider, an email is sent to the specified address,
        // and the email and secret are stored in user preferences. These will be cleared upon successful verification.
        // 3. User clicks verification link in the email to confirm the backpack connection.
        // 4. User redirected to the mybackpack page.
        // While the verification process is pending, the edit_backpack_form form will present the user with options to resend the
        // verification email, and to cancel the current verification attempt and start over.

        if (isset($data->revertbutton)) {
            // TODO: Can this this be replaced with disconnect_backpack() call in this class?
            badges_disconnect_user_backpack($USER->id);
            return new url('/badges/mybackpack.php');
        }

        if (isset($data->externalbackpackid) && isset($data->backpackemail)) {
            // There are no errors, so the verification email can be sent.
            if (badges_send_verification_email($data->backpackemail, $data->externalbackpackid, $data->password)) {
                $backpackemail = get_user_preferences('badges_email_verify_address');
                \core\notification::add(
                    get_string('backpackemailverifypending', 'core_badges', $backpackemail),
                    \core\notification::INFO,
                );
                return new url('/badges/mybackpack.php');
            }

            throw new \moodle_exception('backpackcannotsendverification', 'badges');
        }

        return null;
    }

    #[\Override]
    public function validate_backpack_form($data): array {
        // We don't need to verify the email address if we're clearing a pending email verification attempt.
        if (isset($data['revertbutton'])) {
            return [];
        }

        $errors = [];
        // Email and password can't be blank.
        if (empty($data['backpackemail'])) {
            $errors['backpackemail'] = get_string('backpackemail_required', 'badges');
        }
        if (empty($data['password'])) {
            $errors['password'] = get_string('password_required', 'badges');
        }
        if (!empty($errors)) {
            return $errors;
        }

        // Check the given credentials (email and password) are valid for this backpack.
        $this->externalbackpack->email = $data['backpackemail'];
        $this->externalbackpack->password = $data['password'];
        $result = $this->authenticate();
        if ($result === false || !empty($result->error)) {
            $msg = '';
            if (is_object($result)) {
                $msg = $result?->error . '. ' . $result?->error_description;
            }
            $errors['backpackemail'] = get_string('backpackconnectionunexpectedresult', 'badges', $msg);
        }

        return $errors;
    }

    protected static function create_mapping_class(
        string $action,
        string $posturl,
        string $method = 'post',
        bool $isjson = true,
        bool $authrequired = true,
    ): mapping_base {
        return new mapping_session(
            $action,
            $posturl,
            $method,
            $isjson,
            $authrequired,
            self::get_api_version(),
        );
    }

    /**
     * Parse the post parameters and insert replacements.
     *
     * @param array|string $postparams The post parameters.
     * @param array $param The parameter value.
     * @return mixed
     */
    private function get_postdata(
        mixed $postparams = '',
        mixed $paramvalue = [],
        string $requestexporter = '',
        bool $isjson = true,
    ) {
        global $PAGE;

        $request = $postparams;
        $email = $this->externalbackpack->email;
        $password = $this->externalbackpack->password;
        if ($request === '[PARAM]') {
            $value = $paramvalue;
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
                    $request[$key] = is_array($paramvalue) ? $paramvalue[0] : $paramvalue;
                }
            }
        }
        $context = context_system::instance();
        $exporter = $requestexporter;
        $output = $PAGE->get_renderer('core', 'badges');
        if (!empty($exporter)) {
            $exporterinstance = new $exporter($value, ['context' => $context]);
            $request = $exporterinstance->export($output);
        }
        if ($isjson) {
            return json_encode($request);
        }
        return $request;
    }


    /**
     * Make an API request.
     *
     * @param mapping_base $mapping The mapping to call the request.
     * @param array|string|null $postdata The data to send in the request.
     * @return mixed
     */
    protected function request(
        mapping_base $mapping,
        $postdata = null,
        array $postparams = [],
        string $responseexporter = '',
        bool $ismultiple = false,
        ?int $backpackid = null,
    ) {
        global $PAGE;

        $response = $mapping->curl_request(
            $this->get_request_url($mapping->url, $this->externalbackpack->backpackapiurl, $postparams),
            $postdata,
        );

        $context = context_system::instance();
        if (class_exists($responseexporter)) {
            $output = $PAGE->get_renderer('core', 'badges');
            if (!$ismultiple) {
                if (count($response)) {
                    $response = $response[0];
                }
                if (empty($response)) {
                    return null;
                }
                $apidata = $responseexporter::map_external_data($response, self::get_api_version());
                $exporterinstance = new $responseexporter($apidata, ['context' => $context]);
                $data = $exporterinstance->export($output);
                return $data;
            } else {
                $result = [];
                if (empty($response)) {
                    return $result;
                }
                foreach ($response as $data) {
                    $apidata = $responseexporter::map_external_data($data, self::get_api_version());
                    $exporterinstance = new $responseexporter($apidata, ['context' => $context]);
                    $result[] = $exporterinstance->export($output);
                }
                return $result;
            }
        } else if (method_exists($mapping, $responseexporter)) {
            return $mapping->$responseexporter($response, $backpackid);
        }

    }

    /**
     * Get the id to use for requests with this api.
     *
     * @return int
     */
    private function get_auth_user_id() {
        global $USER;

        return $USER->id;
    }

    /**
     * Make an api request to get an assertion
     *
     * @param string $entityid The id of the assertion.
     * @return mixed
     */
    public function get_assertion($entityid) {
        $mapping = self::create_mapping_class(
            action: 'assertion',
            // Badgr.io does not return the public information about a badge
            // if the issuer is associated with another user. We need to pass
            // the expand parameters which are not in any specification to get
            // additional information about the assertion in a single request.
            posturl: '[URL]/backpack/assertions/[PARAM1]?expand=badgeclass&expand=issuer',
            method: 'get',
        );

        return $this->request(
            mapping: $mapping,
            postparams: [$entityid],
            responseexporter: 'core_badges\external\assertion_exporter',
        );
    }

    /**
     * Create a badgeclass assertion.
     *
     * @param string $entityid The id of the badge class.
     * @param string $data The structure of the badge class assertion.
     * @return mixed
     */
    public function put_badgeclass_assertion($entityid, $data) {
        $mapping = self::create_mapping_class(
            action: 'assertions',
            posturl: '[URL]/badgeclasses/[PARAM1]/assertions',
        );

        $postdata = $this->get_postdata('[PARAM]', $data, 'core_badges\external\assertion_exporter');
        return $this->request(
            mapping: $mapping,
            postdata: $postdata,
            postparams: [$entityid],
            responseexporter: 'core_badges\external\assertion_exporter',
            backpackid: $this->externalbackpack->id,
        );
    }

    /**
     * Update a badgeclass assertion.
     *
     * @param string $entityid The id of the badge class.
     * @param array $data The structure of the badge class assertion.
     * @return mixed
     */
    public function update_assertion(string $entityid, array $data) {
        $mapping = self::create_mapping_class(
            action: 'updateassertion',
            posturl: '[URL]/assertions/[PARAM1]?expand=badgeclass&expand=issuer',
            method: 'put',
        );

        $postdata = $this->get_postdata('[PARAM]', $data, 'core_badges\external\assertion_exporter');
        return $this->request(
            mapping: $mapping,
            postdata: $postdata,
            postparams: [$entityid],
            responseexporter: 'core_badges\external\assertion_exporter',
            backpackid: $this->externalbackpack->id,
        );
    }

    /**
     * Import a badge assertion into a backpack. This is used to handle cross domain backpacks.
     *
     * @param string $data The structure of the badge class assertion.
     * @return mixed
     * @throws coding_exception
     */
    public function import_badge_assertion(string $data) {
        $mapping = self::create_mapping_class(
            action: 'importbadge',
            // Badgr.io does not return the public information about a badge
            // if the issuer is associated with another user. We need to pass
            // the expand parameters which are not in any specification to get
            // additional information about the assertion in a single request.
            posturl: '[URL]/backpack/import',
        );

        $postdata = $this->get_postdata(['url' => '[PARAM]'], $data);
        return $this->request(
            mapping: $mapping,
            postdata: $postdata,
            responseexporter: 'core_badges\external\assertion_exporter',
            backpackid: $this->externalbackpack->id,
        );
    }

    /**
     * Create a badgeclass
     *
     * @param string $entityid The id of the entity.
     * @param string $data The structure of the badge class.
     * @return mixed
     */
    public function put_badgeclass($entityid, $data) {
        $mapping = self::create_mapping_class(
            action: 'badgeclasses',
            posturl: '[URL]/issuers/[PARAM1]/badgeclasses',
        );

        $postdata = $this->get_postdata('[PARAM]', $data, 'core_badges\external\badgeclass_exporter');
        return $this->request(
            mapping: $mapping,
            postdata: $postdata,
            postparams: [$entityid],
            responseexporter: 'core_badges\external\badgeclass_exporter',
            backpackid: $this->externalbackpack->id,
        );
    }

    /**
     * Create an issuer
     *
     * @param string $data The structure of the issuer.
     * @return mixed
     */
    public function put_issuer($data) {
        $mapping = self::create_mapping_class(
            action: 'issuers',
            posturl: '[URL]/issuers',
        );

        $postdata = $this->get_postdata('[PARAM]', $data, 'core_badges\external\issuer_exporter');
        return $this->request(
            mapping: $mapping,
            postdata: $postdata,
            responseexporter: 'core_badges\external\issuer_exporter',
            backpackid: $this->externalbackpack->id,
        );
    }

    /**
     * Delete any user access tokens in the session so we will attempt to get new ones.
     *
     * @return void
     */
    public function clear_system_user_session() {
        global $SESSION;

        $useridkey = mapping_session::get_token_key(BADGE_USER_ID_TOKEN);
        unset($SESSION->$useridkey);

        $expireskey = mapping_session::get_token_key(BADGE_EXPIRES_TOKEN);
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

        $backpackidkey = mapping_session::get_token_key(BADGE_BACKPACK_ID_TOKEN);
        $backpackid = isset($SESSION->$backpackidkey) ? $SESSION->$backpackidkey : 0;
        // If the backpack is changed we need to expire sessions.
        if ($backpackid == $this->externalbackpack->id) {
            $useridkey = mapping_session::get_token_key(BADGE_USER_ID_TOKEN);
            $authuserid = isset($SESSION->$useridkey) ? $SESSION->$useridkey : 0;
            if ($authuserid == $this->get_auth_user_id()) {
                $expireskey = mapping_session::get_token_key(BADGE_EXPIRES_TOKEN);
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

        $mapping = self::create_mapping_class(
            action: 'user',
            posturl: '[SCHEME]://[HOST]/o/token',
            isjson: false,
            authrequired: false,
        );

        $postdata = $this->get_postdata(
            postparams: ['username' => '[EMAIL]', 'password' => '[PASSWORD]'],
            isjson: false,
        );
        return $this->request(
            mapping: $mapping,
            postdata: $postdata,
            postparams: [$this->externalbackpack->email],
            responseexporter: 'oauth_token_response',
            backpackid: $this->externalbackpack->id,
        );
    }

    /**
     * Get all collections in this backpack.
     *
     * @return stdClass[] The collections.
     */
    public function get_collections() {
        if ($this->authenticate()) {
            $mapping = self::create_mapping_class(
                action: 'collections',
                posturl: '[URL]/backpack/collections',
                method: 'get',
            );

            $result = $this->request(
                mapping: $mapping,
                ismultiple: true,
                responseexporter: 'core_badges\external\collection_exporter',
                backpackid: $this->externalbackpack->id,
            );

            if ($result) {
                return $result;
            }
        }
        return [];
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
            $mapping = self::create_mapping_class(
                action: 'badges',
                posturl: '[URL]/backpack/collections/[PARAM1]',
                method: 'get',
            );

            $badges = $this->request(
                mapping: $mapping,
                postparams: [$collection->entityid],
                ismultiple: true,
                responseexporter: 'core_badges\external\collection_exporter',
                backpackid: $this->externalbackpack->id,
            );

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

    #[\Override]
    public function put_assertions(string $hash): array {
        global $DB;

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
            // $badgeid = $issuedbadge->badgeid;
            // $badge = new badge($badgeid);
            // $backpack = $DB->get_record('badge_backpack', array('userid' => $USER->id));
            // $userbackpack = badges_get_site_backpack($backpack->externalbackpackid, $USER->id);
            $assertion = new \core_badges_assertion($hash, OPEN_BADGES_V2);
            $assertiondata = $assertion->get_badge_assertion(false, false);
            $assertionid = $assertion->get_assertion_hash();
            $assertionentityid = $assertiondata['id'];
            $badgeadded = false;
            // TODO: Is this part still needed? It needs to be tested adding Site Backpack email and password to the backpack.
            // if (badges_open_badges_backpack_api() == OPEN_BADGES_V2) {
            //     $sitebackpack = badges_get_site_primary_backpack();
            //     $api = api_base::create_from_externalbackpack($sitebackpack);
            //     $response = $api->authenticate();
            //     // A numeric response indicates a valid successful authentication. Else an error object will be returned.
            //     if (is_numeric($response)) {
            //         // Create issuer.
            //         $issuer = $assertion->get_issuer();
            //         if (!($issuerentityid = badges_external_get_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_ISSUER, $issuer['email']))) {
            //             $response = $api->put_issuer($issuer);
            //             if (!$response) {
            //                 throw new \moodle_exception('invalidrequest', 'error');
            //             }
            //             $issuerentityid = $response->id;
            //             badges_external_create_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_ISSUER, $issuer['email'],
            //                 $issuerentityid);
            //         }
            //         // Create badge.
            //         $badge = $assertion->get_badge_class(false);
            //         $badgeid = $assertion->get_badge_id();
            //         if (!($badgeentityid = badges_external_get_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_BADGE, $badgeid))) {
            //             $response = $api->put_badgeclass($issuerentityid, $badge);
            //             if (!$response) {
            //                 throw new \moodle_exception('invalidrequest', 'error');
            //             }
            //             $badgeentityid = $response->id;
            //             badges_external_create_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_BADGE, $badgeid,
            //                 $badgeentityid);
            //         }
            //         // Create assertion (Award the badge!).
            //         $assertionentityid = badges_external_get_mapping(
            //             $sitebackpack->id,
            //             OPEN_BADGES_V2_TYPE_ASSERTION,
            //             $assertionid
            //         );
            //         if ($assertionentityid && strpos($sitebackpack->backpackapiurl, 'badgr')) {
            //             $assertionentityid = badges_generate_badgr_open_url(
            //                 $sitebackpack,
            //                 OPEN_BADGES_V2_TYPE_ASSERTION,
            //                 $assertionentityid
            //             );
            //         }
            //         // Create an assertion for the recipient in the issuer's account.
            //         if (!$assertionentityid) {
            //             $response = $api->put_badgeclass_assertion($badgeentityid, $assertiondata);
            //             if (!$response) {
            //                 throw new \moodle_exception('invalidrequest', 'error');
            //             }
            //             $assertionentityid = badges_generate_badgr_open_url($sitebackpack, OPEN_BADGES_V2_TYPE_ASSERTION, $response->id);
            //             $badgeadded = true;
            //             badges_external_create_mapping($sitebackpack->id, OPEN_BADGES_V2_TYPE_ASSERTION, $assertionid,
            //                 $response->id);
            //         } else {
            //             // An assertion already exists. Make sure it's up to date.
            //             $internalid = badges_external_get_mapping(
            //                 $sitebackpack->id,
            //                 OPEN_BADGES_V2_TYPE_ASSERTION,
            //                 $assertionid,
            //                 'externalid'
            //             );
            //             $response = $api->update_assertion($internalid, $assertiondata);
            //             if (!$response) {
            //                 throw new \moodle_exception('invalidrequest', 'error');
            //             }
            //         }
            //     }
            // }

            // Award/upload the badge to the user's account.
            // - If a user and site backpack have the same provider we can skip this as Badgr automatically maps recipients
            // based on email address.
            // - This is only needed when the backpacks are from different regions.
            if ($assertionentityid && !badges_external_get_mapping($this->externalbackpack->id, OPEN_BADGES_V2_TYPE_ASSERTION, $assertionid)) {
                $this->authenticate();
                $response = $this->import_badge_assertion($assertionentityid);
                if (!$response) {
                    throw new \moodle_exception('invalidrequest', 'error', '', null, $response);
                }
                $assertionentityid = $response->id;
                $badgeadded = true;
                badges_external_create_mapping($this->externalbackpack->id, OPEN_BADGES_V2_TYPE_ASSERTION, $assertionid,
                    $assertionentityid);
            }
            $response = $badgeadded ? ['success' => 'addedtobackpack'] : ['warning' => 'existsinbackpack'];
        }

        return $response;
    }


    /**
     * Select collections from a backpack.
     * TODO: Check if this method can be moved outside of the versioned OB class.
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
     * Get one collection by id.
     *
     * @param integer $collectionid
     * @return array The collection.
     */
    public function get_collection_record($collectionid) {
        global $DB;

        return $DB->get_fieldset_select('badge_external', 'entityid', 'backpackid = :bid', ['bid' => $collectionid]);
    }
}
