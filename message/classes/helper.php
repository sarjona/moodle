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
 * Contains helper class for the message area.
 *
 * @package    core_message
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_message;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for the message area.
 *
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Helper function to retrieve the messages between two users
     *
     * @param int $userid the current user
     * @param int $otheruserid the other user
     * @param int $timedeleted the time the message was deleted
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $sort
     * @param int $timefrom the time from the message being sent
     * @param int $timeto the time up until the message being sent
     * @return array of messages
     */
    public static function get_messages($userid, $otheruserid, $timedeleted = 0, $limitfrom = 0, $limitnum = 0,
                                        $sort = 'timecreated ASC', $timefrom = 0, $timeto = 0) {
        global $DB;

        $hash = self::get_conversation_hash([$userid, $otheruserid]);

        $sql = "SELECT m.id, m.useridfrom, m.subject, m.fullmessage, m.fullmessagehtml,
                       m.fullmessageformat, m.smallmessage, m.timecreated, muaread.timecreated AS timeread
                  FROM {message_conversations} mc
            INNER JOIN {messages} m
                    ON m.conversationid = mc.id
             LEFT JOIN {message_user_actions} muaread
                    ON (muaread.messageid = m.id
                   AND muaread.userid = :userid1
                   AND muaread.action = :readaction)";
        $params = ['userid1' => $userid, 'readaction' => api::MESSAGE_ACTION_READ, 'convhash' => $hash];

        if (empty($timedeleted)) {
            $sql .= " LEFT JOIN {message_user_actions} mua
                             ON (mua.messageid = m.id
                            AND mua.userid = :userid2
                            AND mua.action = :deleteaction
                            AND mua.timecreated is NOT NULL)";
        } else {
            $sql .= " INNER JOIN {message_user_actions} mua
                              ON (mua.messageid = m.id
                             AND mua.userid = :userid2
                             AND mua.action = :deleteaction
                             AND mua.timecreated = :timedeleted)";
            $params['timedeleted'] = $timedeleted;
        }

        $params['userid2'] = $userid;
        $params['deleteaction'] = api::MESSAGE_ACTION_DELETED;

        $sql .= " WHERE mc.convhash = :convhash";

        if (!empty($timefrom)) {
            $sql .= " AND m.timecreated >= :timefrom";
            $params['timefrom'] = $timefrom;
        }

        if (!empty($timeto)) {
            $sql .= " AND m.timecreated <= :timeto";
            $params['timeto'] = $timeto;
        }

        if (empty($timedeleted)) {
            $sql .= " AND mua.id is NULL";
        }

        $sql .= " ORDER BY m.$sort";

        $messages = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        foreach ($messages as &$message) {
            $message->useridto = ($message->useridfrom == $userid) ? $otheruserid : $userid;
        }

        return $messages;
    }

    /**
     * Helper function to retrieve the messages between two users
     *
     * @param  int $userid The current user.
     * @param  int $convid The conversation identifier.
     * @param  int $timedeleted The time the message was deleted
     * @param  int $limitfrom Return a subset of records, starting at this point (optional).
     * @param  int $limitnum Return a subset comprising this many records in total (optional, required if $limitfrom is set).
     * @param  string $sort The column name to order by including optionally direction.
     * @param  int $timefrom The time from the message being sent.
     * @param  int $timeto The time up until the message being sent.
     * @return array of messages
     */
    public static function get_conversation_messages(int $userid, int $convid, int $timedeleted = 0, int $limitfrom = 0,
                                                     int $limitnum = 0, string $sort = 'timecreated ASC', int $timefrom = 0,
                                                     int $timeto = 0) : array {
        global $DB;

        $sql = "SELECT m.id, m.useridfrom, m.subject, m.fullmessage, m.fullmessagehtml,
                       m.fullmessageformat, m.smallmessage, m.timecreated, muaread.timecreated AS timeread
                  FROM {message_conversations} mc
            INNER JOIN {messages} m
                    ON m.conversationid = mc.id
             LEFT JOIN {message_user_actions} muaread
                    ON (muaread.messageid = m.id
                   AND muaread.userid = :userid1
                   AND muaread.action = :readaction)";
        $params = ['userid1' => $userid, 'readaction' => api::MESSAGE_ACTION_READ, 'convid' => $convid];

        if (empty($timedeleted)) {
            $sql .= " LEFT JOIN {message_user_actions} mua
                             ON (mua.messageid = m.id
                            AND mua.userid = :userid2
                            AND mua.action = :deleteaction
                            AND mua.timecreated is NOT NULL)";
        } else {
            $sql .= " INNER JOIN {message_user_actions} mua
                              ON (mua.messageid = m.id
                             AND mua.userid = :userid2
                             AND mua.action = :deleteaction
                             AND mua.timecreated = :timedeleted)";
            $params['timedeleted'] = $timedeleted;
        }

        $params['userid2'] = $userid;
        $params['deleteaction'] = api::MESSAGE_ACTION_DELETED;

        $sql .= " WHERE mc.id = :convid";

        if (!empty($timefrom)) {
            $sql .= " AND m.timecreated >= :timefrom";
            $params['timefrom'] = $timefrom;
        }

        if (!empty($timeto)) {
            $sql .= " AND m.timecreated <= :timeto";
            $params['timeto'] = $timeto;
        }

        if (empty($timedeleted)) {
            $sql .= " AND mua.id is NULL";
        }

        $sql .= " ORDER BY m.$sort";

        $messages = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);

        return $messages;
    }

    /**
     * Helper function to return a conversation messages with the involved members (only the ones
     * who have sent any of these messages).
     *
     * @param int $userid The current userid.
     * @param int $convid The conversation id.
     * @param array $messages The formated array messages.
     * @return array A conversation array with the messages and the involved members.
     */
    public static function create_conversation_messages(int $userid, int $convid, array $messages) : array {
        global $USER;

        // Create the conversation array.
        $conversation = array(
            'id' => $convid,
            'iscurrentuser' => ($USER->id == $userid),
            'currentuserid' => $userid,
        );

        // Store the messages.
        $arrmessages = array();

        // We always view messages from oldest to newest, ensure we have it in that order.
        $lastmessage = end($messages);
        $firstmessage = reset($messages);
        if ($lastmessage->timecreated < $firstmessage->timecreated) {
            $messages = array_reverse($messages);
        }

        // Keeps track of the last day, month and year combo we were viewing.
        $day = '';
        $month = '';
        $year = '';
        foreach ($messages as $message) {
            // Check if we are now viewing a different block period.
            $displayblocktime = false;
            $date = usergetdate($message->timecreated);
            if ($day != $date['mday'] || $month != $date['month'] || $year != $date['year']) {
                $day = $date['mday'];
                $month = $date['month'];
                $year = $date['year'];
                $displayblocktime = true;
            }
            // Store the message to pass to the renderable.
            $msg = new \stdClass();
            $msg->id = $message->id;
            $msg->text = message_format_message_text($message);
            $msg->currentuserid = $userid;
            $msg->useridfrom = $message->useridfrom;
            $msg->displayblocktime = $displayblocktime;
            $msg->timecreated = $message->timecreated;
            $msg->timeread = $message->timeread;
            $arrmessages[] = $msg;
        }
        // Add the messages to the conversation.
        $conversation['messages'] = $arrmessages;

        // Get the users who have sent any of the $messages.
        $memberids = array_unique(array_map(function($message) {
            return $message->useridfrom;
        }, $messages));
        $ufields = 'id, ' . get_all_user_name_fields(true) . ', lastaccess';
        $arrmembers = array_map(function($memberid) use ($ufields) {
            global $PAGE;

            $user = \core_user::get_user($memberid, '*', MUST_EXIST);
            $member = new \stdClass();
            $member->id = $memberid;
            $member->fullname = fullname($user);
            $userpicture = new \user_picture($user);
            $userpicture->size = 0; // Size f2.
            $member->profileimageurlsmall = $userpicture->get_url($PAGE)->out(false);

            return $member;
        }, $memberids);

        // Add the members to the conversation.
        $conversation['members'] = $arrmembers;

        return $conversation;
    }

    /**
     * Helper function to return an array of messages.
     *
     * @param int $userid
     * @param array $messages
     * @return array
     */
    public static function create_messages($userid, $messages) {
        // Store the messages.
        $arrmessages = array();

        // We always view messages from oldest to newest, ensure we have it in that order.
        $lastmessage = end($messages);
        $firstmessage = reset($messages);
        if ($lastmessage->timecreated < $firstmessage->timecreated) {
            $messages = array_reverse($messages);
        }

        // Keeps track of the last day, month and year combo we were viewing.
        $day = '';
        $month = '';
        $year = '';
        foreach ($messages as $message) {
            // Check if we are now viewing a different block period.
            $displayblocktime = false;
            $date = usergetdate($message->timecreated);
            if ($day != $date['mday'] || $month != $date['month'] || $year != $date['year']) {
                $day = $date['mday'];
                $month = $date['month'];
                $year = $date['year'];
                $displayblocktime = true;
            }
            // Store the message to pass to the renderable.
            $msg = new \stdClass();
            $msg->id = $message->id;
            $msg->text = message_format_message_text($message);
            $msg->currentuserid = $userid;
            $msg->useridfrom = $message->useridfrom;
            if (!empty($message->useridto)) {
                $msg->useridto = $message->useridto;
            }
            if (!empty($message->convid)) {
                $msg->conversationid = $message->convid;
            }
            $msg->displayblocktime = $displayblocktime;
            $msg->timecreated = $message->timecreated;
            $msg->timeread = $message->timeread;
            $arrmessages[] = $msg;
        }

        return $arrmessages;
    }

    /**
     * Helper function for creating a contact object.
     *
     * @param \stdClass $contact
     * @param string $prefix
     * @return \stdClass
     */
    public static function create_contact($contact, $prefix = '') {
        global $PAGE;

        // Create the data we are going to pass to the renderable.
        $userfields = \user_picture::unalias($contact, array('lastaccess'), $prefix . 'id', $prefix);
        $data = new \stdClass();
        $data->userid = $userfields->id;
        $data->useridfrom = null;
        $data->fullname = fullname($userfields);
        // Get the user picture data.
        $userpicture = new \user_picture($userfields);
        $userpicture->size = 1; // Size f1.
        $data->profileimageurl = $userpicture->get_url($PAGE)->out(false);
        $userpicture->size = 0; // Size f2.
        $data->profileimageurlsmall = $userpicture->get_url($PAGE)->out(false);
        // Store the message if we have it.
        $data->ismessaging = false;
        $data->lastmessage = null;
        $data->messageid = null;
        if (isset($contact->smallmessage)) {
            $data->ismessaging = true;
            // Strip the HTML tags from the message for displaying in the contact area.
            $data->lastmessage = clean_param($contact->smallmessage, PARAM_NOTAGS);
            $data->useridfrom = $contact->useridfrom;
            if (isset($contact->messageid)) {
                $data->messageid = $contact->messageid;
            }
        }
        $data->isonline = null;
        if (self::show_online_status($userfields)) {
            $data->isonline = self::is_online($userfields->lastaccess);
        }
        $data->isblocked = isset($contact->blocked) ? (bool) $contact->blocked : false;
        $data->isread = isset($contact->isread) ? (bool) $contact->isread : false;
        $data->unreadcount = isset($contact->unreadcount) ? $contact->unreadcount : null;

        return $data;
    }

    /**
     * Helper function for checking if we should show the user's online status.
     *
     * @param \stdClass $user
     * @return boolean
     */
    public static function show_online_status($user) {
        global $CFG;

        require_once($CFG->dirroot . '/user/lib.php');

        if ($lastaccess = user_get_user_details($user, null, array('lastaccess'))) {
            if (isset($lastaccess['lastaccess'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper function for checking the time meets the 'online' condition.
     *
     * @param int $lastaccess
     * @return boolean
     */
    public static function is_online($lastaccess) {
        global $CFG;

        // Variable to check if we consider this user online or not.
        $timetoshowusers = 300; // Seconds default.
        if (isset($CFG->block_online_users_timetosee)) {
            $timetoshowusers = $CFG->block_online_users_timetosee * 60;
        }
        $time = time() - $timetoshowusers;

        return $lastaccess >= $time;
    }

    /**
     * Get providers preferences.
     *
     * @param array $providers
     * @param int $userid
     * @return \stdClass
     */
    public static function get_providers_preferences($providers, $userid) {
        $preferences = new \stdClass();

        // Get providers preferences.
        foreach ($providers as $provider) {
            foreach (array('loggedin', 'loggedoff') as $state) {
                $linepref = get_user_preferences('message_provider_' . $provider->component . '_' . $provider->name
                    . '_' . $state, '', $userid);
                if ($linepref == '') {
                    continue;
                }
                $lineprefarray = explode(',', $linepref);
                $preferences->{$provider->component.'_'.$provider->name.'_'.$state} = array();
                foreach ($lineprefarray as $pref) {
                    $preferences->{$provider->component.'_'.$provider->name.'_'.$state}[$pref] = 1;
                }
            }
        }

        return $preferences;
    }

    /**
     * Requires the JS libraries for the toggle contact button.
     *
     * @return void
     */
    public static function togglecontact_requirejs() {
        global $PAGE;

        static $done = false;
        if ($done) {
            return;
        }

        $PAGE->requires->js_call_amd('core_message/toggle_contact_button', 'enhance', array('#toggle-contact-button'));
        $done = true;
    }

    /**
     * Returns the attributes to place on a contact button.
     *
     * @param object $user User object.
     * @param bool $iscontact
     * @return array
     */
    public static function togglecontact_link_params($user, $iscontact = false) {
        $params = array(
            'data-userid' => $user->id,
            'data-is-contact' => $iscontact,
            'id' => 'toggle-contact-button',
            'role' => 'button',
            'class' => 'ajax-contact-button',
        );

        return $params;
    }

    /**
     * Returns the conversation hash between users for easy look-ups in the DB.
     *
     * @param array $userids
     * @return string
     */
    public static function get_conversation_hash(array $userids) {
        sort($userids);

        return sha1(implode('-', $userids));
    }

    /**
     * Returns the cache key for the time created value of the last message of this conversation.
     *
     * @param int $convid The conversation identifier.
     * @return string The key.
     */
    public static function get_last_message_time_created_cache_key(int $convid) {
        return $convid;
    }

    /**
     * Checks if legacy messages exist for a given user.
     *
     * @param int $userid
     * @return bool
     */
    public static function legacy_messages_exist($userid) {
        global $DB;

        $sql = "SELECT id
                  FROM {message} m
                 WHERE useridfrom = ?
                    OR useridto = ?";
        $messageexists = $DB->record_exists_sql($sql, [$userid, $userid]);

        $sql = "SELECT id
                  FROM {message_read} m
                 WHERE useridfrom = ?
                    OR useridto = ?";
        $messagereadexists = $DB->record_exists_sql($sql, [$userid, $userid]);

        return $messageexists || $messagereadexists;
    }
}
