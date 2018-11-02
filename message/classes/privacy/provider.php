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
 * Privacy Subsystem implementation for core_message.
 *
 * @package    core_message
 * @category   privacy
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_message\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for core_message.
 *
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\subsystem\provider,
    \core_privacy\local\request\user_preference_provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table(
            'messages',
            [
                'useridfrom' => 'privacy:metadata:messages:useridfrom',
                'conversationid' => 'privacy:metadata:messages:conversationid',
                'subject' => 'privacy:metadata:messages:subject',
                'fullmessage' => 'privacy:metadata:messages:fullmessage',
                'fullmessageformat' => 'privacy:metadata:messages:fullmessageformat',
                'fullmessagehtml' => 'privacy:metadata:messages:fullmessagehtml',
                'smallmessage' => 'privacy:metadata:messages:smallmessage',
                'timecreated' => 'privacy:metadata:messages:timecreated'
            ],
            'privacy:metadata:messages'
        );

        $items->add_database_table(
            'message_user_actions',
            [
                'userid' => 'privacy:metadata:message_user_actions:userid',
                'messageid' => 'privacy:metadata:message_user_actions:messageid',
                'action' => 'privacy:metadata:message_user_actions:action',
                'timecreated' => 'privacy:metadata:message_user_actions:timecreated'
            ],
            'privacy:metadata:message_user_actions'
        );

        $items->add_database_table(
            'message_conversation_members',
            [
                'conversationid' => 'privacy:metadata:message_conversation_members:conversationid',
                'userid' => 'privacy:metadata:message_conversation_members:userid',
                'timecreated' => 'privacy:metadata:message_conversation_members:timecreated',
            ],
            'privacy:metadata:message_conversation_members'
        );

        $items->add_database_table(
            'message_contacts',
            [
                'userid' => 'privacy:metadata:message_contacts:userid',
                'contactid' => 'privacy:metadata:message_contacts:contactid',
                'timecreated' => 'privacy:metadata:message_contacts:timecreated',
            ],
            'privacy:metadata:message_contacts'
        );

        $items->add_database_table(
            'message_contact_requests',
            [
                'userid' => 'privacy:metadata:message_contact_requests:userid',
                'requesteduserid' => 'privacy:metadata:message_contact_requests:requesteduserid',
                'timecreated' => 'privacy:metadata:message_contact_requests:timecreated',
            ],
            'privacy:metadata:message_contact_requests'
        );

        $items->add_database_table(
            'message_users_blocked',
            [
                'userid' => 'privacy:metadata:message_users_blocked:userid',
                'blockeduserid' => 'privacy:metadata:message_users_blocked:blockeduserid',
                'timecreated' => 'privacy:metadata:message_users_blocked:timecreated',
            ],
            'privacy:metadata:message_users_blocked'
        );

        $items->add_database_table(
            'notifications',
            [
                'useridfrom' => 'privacy:metadata:notifications:useridfrom',
                'useridto' => 'privacy:metadata:notifications:useridto',
                'subject' => 'privacy:metadata:notifications:subject',
                'fullmessage' => 'privacy:metadata:notifications:fullmessage',
                'fullmessageformat' => 'privacy:metadata:notifications:fullmessageformat',
                'fullmessagehtml' => 'privacy:metadata:notifications:fullmessagehtml',
                'smallmessage' => 'privacy:metadata:notifications:smallmessage',
                'component' => 'privacy:metadata:notifications:component',
                'eventtype' => 'privacy:metadata:notifications:eventtype',
                'contexturl' => 'privacy:metadata:notifications:contexturl',
                'contexturlname' => 'privacy:metadata:notifications:contexturlname',
                'timeread' => 'privacy:metadata:notifications:timeread',
                'timecreated' => 'privacy:metadata:notifications:timecreated',
            ],
            'privacy:metadata:notifications'
        );

        // Note - we are not adding the 'message' and 'message_read' tables
        // as they are legacy tables. This information is moved to these
        // new tables in a separate ad-hoc task. See MDL-61255.

        // Now add that we also have user preferences.
        $items->add_user_preference('core_message_messageprovider_settings',
            'privacy:metadata:preference:core_message_settings');

        return $items;
    }

    /**
     * Store all user preferences for core message.
     *
     * @param  int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $preferences = get_user_preferences(null, null, $userid);
        foreach ($preferences as $name => $value) {
            if ((substr($name, 0, 16) == 'message_provider') || ($name == 'message_blocknoncontacts')) {
                writer::export_user_preference(
                    'core_message',
                    $name,
                    $value,
                    get_string('privacy:request:preference:set', 'core_message', (object) [
                        'name' => $name,
                        'value' => $value,
                    ])
                );
            }
        }
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        global $DB;

        $contextlist = new contextlist();

        // Messages are in the user context.
        // For the sake of performance, there is no need to call add_from_sql for each of the below cases.
        // It is enough to add the user's context as soon as we come to the conclusion that the user has some data.
        // Also, the order of checking is sorted by the probability of occurrence (just by guess).
        // There is no need to check the message_user_actions table, as there needs to be a message in order to be a message action.
        // So, checking messages table would suffice.

        $hasdata = false;
        $hasdata = $hasdata || $DB->record_exists_select('notifications', 'useridfrom = ? OR useridto = ?', [$userid, $userid]);
        $hasdata = $hasdata || $DB->record_exists('message_conversation_members', ['userid' => $userid]);
        $hasdata = $hasdata || $DB->record_exists('messages', ['useridfrom' => $userid]);
        $hasdata = $hasdata || $DB->record_exists_select('message_contacts', 'userid = ? OR contactid = ?', [$userid, $userid]);
        $hasdata = $hasdata || $DB->record_exists_select('message_users_blocked', 'userid = ? OR blockeduserid = ?',
                [$userid, $userid]);
        $hasdata = $hasdata || $DB->record_exists_select('message_contact_requests', 'userid = ? OR requesteduserid = ?',
                [$userid, $userid]);
        $sql = "SELECT mc.id
              FROM {message_conversations} mc
              JOIN {message_conversation_members} mcm
                ON (mcm.conversationid = mc.id AND mcm.userid = :userid)
             WHERE mc.contextid IS NULL";
	    $hasdata = $hasdata || $DB->record_exists_sql($sql, ['userid' => $userid]);

        if ($hasdata) {
            $contextlist->add_user_context($userid);
        }

        // Search for conversations for this user in other contexts.
        $sql = "SELECT mc.contextid
              FROM {message_conversations} mc
              JOIN {message_conversation_members} mcm
                ON (mcm.conversationid = mc.id AND mcm.userid = :userid)
              JOIN {context} ctx
                ON mc.contextid = ctx.id";
        $params = [
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        $userid = $context->instanceid;

        // Messages are in the user context.
        // For the sake of performance, there is no need to call add_from_sql for each of the below cases.
        // It is enough to add the user's context as soon as we come to the conclusion that the user has some data.
        // Also, the order of checking is sorted by the probability of occurrence (just by guess).
        // There is no need to check the message_user_actions table, as there needs to be a message in order to be a message action.
        // So, checking messages table would suffice.

        $hasdata = false;
        $hasdata = $hasdata || $DB->record_exists_select('notifications', 'useridfrom = ? OR useridto = ?', [$userid, $userid]);
        $hasdata = $hasdata || $DB->record_exists('message_conversation_members', ['userid' => $userid]);
        $hasdata = $hasdata || $DB->record_exists('messages', ['useridfrom' => $userid]);
        $hasdata = $hasdata || $DB->record_exists_select('message_contacts', 'userid = ? OR contactid = ?', [$userid, $userid]);
        $hasdata = $hasdata || $DB->record_exists_select('message_users_blocked', 'userid = ? OR blockeduserid = ?',
                        [$userid, $userid]);
        $hasdata = $hasdata || $DB->record_exists_select('message_contact_requests', 'userid = ? OR requesteduserid = ?',
                        [$userid, $userid]);

        if ($hasdata) {
            $userlist->add_user($userid);
        }
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        // Get valid contexts.
        $contexts = array_reduce($contextlist->get_contexts(), function($carry, $context) use($userid) {
            $level = $context->contextlevel;
            if ($level == CONTEXT_USER && $userid == $context->instanceid) {
                $carry[$level][] = $context->instanceid;
            } else if ($level != CONTEXT_SYSTEM) {
                // Any other context but the system one.
                $carry['other'][] = $context->id;
            }
            return $carry;
        }, [
            CONTEXT_USER => [],
            'other' => [],
        ]);

        $exportemptycontext = false;
        if (!empty($contexts[CONTEXT_USER])) {
            // Export all the messaging information for the given userid.

            // Export the contacts.
            self::export_user_data_contacts($userid);

            // Export the contact requests.
            self::export_user_data_contact_requests($userid);

            // Export the blocked users.
            self::export_user_data_blocked_users($userid);

            // Export the notifications.
            self::export_user_data_notifications($userid);

            // Export also the conversations without context (because they are related to the user context).
            $exportemptycontext = true;
        }

        if ($exportemptycontext || !empty($contexts['other'])) {
            // Export the conversations.
            self::export_user_data_conversations($userid, $contexts['other'], $exportemptycontext);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        switch ($context->contextlevel) {
            case CONTEXT_USER:
                // Delete only the messaging information for this user.
                static::delete_all_user_data($context->instanceid);
                break;

            default:
                // Delete only conversations in this context.
                static::delete_all_context_data([$context->id]);
                break;

        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $contextids = [];
        foreach ($contextlist->get_contexts() as $context) {
            $level = $context->contextlevel;
            if ($level == CONTEXT_USER && $userid == $context->instanceid) {
                // User attempts to delete data in their own context.
                static::delete_all_user_data($userid);
            } else {
                // Get the context ids.
                $contextids[] = $context->id;
            }
        }

        if (!empty($contextids)) {
            static::delete_all_context_user_data($contextids, $userid);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        // Remove invalid users. If it ends up empty then early return.
        $userids = array_filter($userlist->get_userids(), function($userid) use($context) {
            return $context->instanceid == $userid;
        });

        if (empty($userids)) {
            return;
        }

        static::delete_all_user_data($context->instanceid);
    }

    /**
     * Delete all the data for a user.
     *
     * @param int $userid The user ID.
     * @return void
     */
    protected static function delete_all_user_data(int $userid) {
        global $DB;

        $DB->delete_records('messages', ['useridfrom' => $userid]);
        $DB->delete_records('message_user_actions', ['userid' => $userid]);
        $DB->delete_records('message_conversation_members', ['userid' => $userid]);
        $DB->delete_records_select('message_contacts', 'userid = ? OR contactid = ?', [$userid, $userid]);
        $DB->delete_records_select('message_contact_requests', 'userid = ? OR requesteduserid = ?', [$userid, $userid]);
        $DB->delete_records_select('message_users_blocked', 'userid = ? OR blockeduserid = ?', [$userid, $userid]);
        $DB->delete_records_select('notifications', 'useridfrom = ? OR useridto = ?', [$userid, $userid]);
    }

    /**
     * Delete all the data in several contexts for all the users.
     *
     * @param  array $contextids The context identifiers where we have to delete messaging data.
     * @return void
     */
    protected static function delete_all_context_data(array $contextids) {
        global $DB;

        if (empty($contextids)) {
            return;
        }

        list($contextidsql, $contextidparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $select = "component = 'core_group' AND itemtype = 'groups' AND contextid $contextidsql";
        // Get and remove all the conversations and messages for the specified contexts.
        if ($conversationids = $DB->get_records_select('message_conversations', $select, $contextidparams, '', 'id')) {
            $conversationids = array_keys($conversationids);
            $messageids = $DB->get_records_list('messages', 'conversationid', $conversationids);
            $messageids = array_keys($messageids);

            // Delete messages and user_actions.
            $DB->delete_records_list('message_user_actions', 'messageid', $messageids);
            $DB->delete_records_list('messages', 'id', $messageids);

            // Delete members and conversations.
            $DB->delete_records_list('message_conversation_members', 'conversationid', $conversationids);
            $DB->delete_records_list('message_conversations', 'id', $conversationids);
        }
    }

    /**
     * Delete all the data in several contexts for the specified user.
     *
     * @param  array $contextids The context identifiers where we have to delete messaging data.
     * @param  int $userid The user ID.
     * @return void
     */
    protected static function delete_all_context_user_data(array $contextids, int $userid) {
        global $DB;

        if (empty($contextids)) {
            return;
        }

        list($contextidsql, $contextidparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $select = "mc.component = 'core_group' AND mc.itemtype = 'groups' AND mc.contextid $contextidsql";

        // Get conversations in these contexts where the specified userid is a member of.
        $sql = "SELECT DISTINCT mcm.conversationid as id
                  FROM {message_conversation_members} mcm
            INNER JOIN {message_conversations} mc
                    ON mc.id = mcm.conversationid AND $select
                 WHERE mcm.userid = :userid";
        $conversationids = array_keys($DB->get_records_sql($sql, ['userid' => $userid] + $contextidparams));
        list($conversationidsql, $conversationidparams) = $DB->get_in_or_equal($conversationids, SQL_PARAMS_NAMED);

        // Get all the messages in the context conversations which the userid has sent.
        $sql = "SELECT DISTINCT m.id
                  FROM {messages} m
            INNER JOIN {message_conversations} mc
                    ON mc.id = m.conversationid AND mc.id $conversationidsql
                 WHERE m.useridfrom = :userid";
        $params = ['userid' => $userid] + $conversationidparams;
        $messageids = array_keys($DB->get_records_sql($sql, $params));

        if (!empty($messageids)) {
            // Delete all the messages and user_actions for the userid.
            $DB->delete_records_list('message_user_actions', 'messageid', $messageids);
            $DB->delete_records_list('messages', 'id', $messageids);
        }

        // In that case, conversations can't be removed, because they could have more members and messages.
        // So, remove only userid from the context conversations where he/she is member.
        $sql = "conversationid $conversationidsql AND userid = :userid";
        // Reuse the $params var because it contains the userid and the conversationids.
        $DB->delete_records_select('message_conversation_members', $sql, $params);
    }

    /**
     * Export the messaging contact data.
     *
     * @param int $userid
     */
    protected static function export_user_data_contacts(int $userid) {
        global $DB;

        $context = \context_user::instance($userid);

        // Get the user's contacts.
        if ($contacts = $DB->get_records_select('message_contacts', 'userid = ? OR contactid = ?', [$userid, $userid], 'id ASC')) {
            $contactdata = [];
            foreach ($contacts as $contact) {
                $contactdata[] = (object) [
                    'contact' => transform::user($contact->contactid)
                ];
            }
            writer::with_context($context)->export_data([get_string('contacts', 'core_message')], (object) $contactdata);
        }
    }

    /**
     * Export the messaging contact requests data.
     *
     * @param int $userid
     */
    protected static function export_user_data_contact_requests(int $userid) {
        global $DB;

        $context = \context_user::instance($userid);

        if ($contactrequests = $DB->get_records_select('message_contact_requests', 'userid = ? OR requesteduserid = ?',
                [$userid, $userid], 'id ASC')) {
            $contactrequestsdata = [];
            foreach ($contactrequests as $contactrequest) {
                if ($userid == $contactrequest->requesteduserid) {
                    $maderequest = false;
                    $contactid = $contactrequest->userid;
                } else {
                    $maderequest = true;
                    $contactid = $contactrequest->requesteduserid;
                }

                $contactrequestsdata[] = (object) [
                    'contactrequest' => transform::user($contactid),
                    'maderequest' => transform::yesno($maderequest)
                ];
            }
            writer::with_context($context)->export_data([get_string('contactrequests', 'core_message')],
                (object) $contactrequestsdata);
        }
    }

    /**
     * Export the messaging blocked users data.
     *
     * @param int $userid
     */
    protected static function export_user_data_blocked_users(int $userid) {
        global $DB;

        $context = \context_user::instance($userid);

        if ($blockedusers = $DB->get_records('message_users_blocked', ['userid' => $userid], 'id ASC')) {
            $blockedusersdata = [];
            foreach ($blockedusers as $blockeduser) {
                $blockedusersdata[] = (object) [
                    'blockeduser' => transform::user($blockeduser->blockeduserid)
                ];
            }
            writer::with_context($context)->export_data([get_string('blockedusers', 'core_message')], (object) $blockedusersdata);
        }
    }

    /**
     * Export the messaging data.
     *
     * @param int $userid The user identifier.
     * @param array $contextids The context identifiers where we have to export messaging data.
     * @param bool $exportemptycontext True if we should also export conversations without any context; false otherwise.
     */
    protected static function export_user_data_conversations(int $userid, array $contextids, bool $exportemptycontext) {
        global $DB;

        $contextidsql = '';
        $contextidparams = [];
        if (!empty($contextids)) {
            // Get the context ids.
            list($contextidsql, $contextidparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
            $contextidsql = "mc.contextid $contextidsql";
        }

        if ($exportemptycontext) {
            // Export also the conversations without any context.
            $condition = "mc.contextid IS NULL";
            if (!empty($contextidsql)) {
                $contextidsql = "($contextidsql OR $condition)";
            } else {
                $contextidsql = $condition;
            }
        }

        if (!empty($contextidsql)) {
             $contextidsql = "AND $contextidsql";
        }

        $sql = "SELECT DISTINCT mcm.conversationid as id, mc.*
                  FROM {message_conversation_members} mcm
            INNER JOIN {message_conversations} mc
                    ON mc.id = mcm.conversationid $contextidsql
                 WHERE mcm.userid = :userid";
        if ($conversations = $DB->get_records_sql($sql, ['userid' => $userid] + $contextidparams)) {
            // Ok, let's get the other users in the private conversations.
            // We don't need this information for the group conversations, because they are organised by group name.
            $privateconversationids = array_map(function($conversation) {
                if ($conversation->type == \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL) {
                    return $conversation->id;
                }
            }, $conversations);
            list($conversationidsql, $conversationparams) = $DB->get_in_or_equal($privateconversationids, SQL_PARAMS_NAMED);
            $userfields = \user_picture::fields('u');
            $userssql = "SELECT DISTINCT mcm.conversationid, $userfields
                                    FROM {user} u
                              INNER JOIN {message_conversation_members} mcm
                                      ON u.id = mcm.userid
                                   WHERE mcm.conversationid $conversationidsql
                                     AND mcm.userid != :userid
                                     AND u.deleted = 0";
            $otherusers = $DB->get_records_sql($userssql, $conversationparams + ['userid' => $userid]);

            // Export conversation messages.
            foreach ($conversations as $conversation) {
                self::export_user_data_conversation_messages($userid, $conversation, $otherusers);
            }
        }
    }

    /**
     * Export conversation messages.
     *
     * @param int $userid The user identifier.
     * @param \stdClass $conversation The conversation to export the messages.
     * @param array $otherusers Array with all the users who have a private conversation with $userid.
     */
    protected static function export_user_data_conversation_messages(int $userid, \stdClass $conversation, array $otherusers) {
        global $DB;

        // Get all the messages for this conversation from start to finish.
        $sql = "SELECT m.*, muadelete.timecreated as timedeleted, muaread.timecreated as timeread
                  FROM {messages} m
             LEFT JOIN {message_user_actions} muadelete
                    ON m.id = muadelete.messageid AND muadelete.action = :deleteaction AND muadelete.userid = :deleteuserid
             LEFT JOIN {message_user_actions} muaread
                    ON m.id = muaread.messageid AND muaread.action = :readaction AND muaread.userid = :readuserid
                 WHERE conversationid = :conversationid
              ORDER BY m.timecreated ASC";
        $messages = $DB->get_recordset_sql($sql, ['deleteaction' => \core_message\api::MESSAGE_ACTION_DELETED,
            'readaction' => \core_message\api::MESSAGE_ACTION_READ, 'conversationid' => $conversation->id,
            'deleteuserid' => $userid, 'readuserid' => $userid]);
        $messagedata = [];
        foreach ($messages as $message) {
            $timeread = !is_null($message->timeread) ? transform::datetime($message->timeread) : '-';
            $issender = $userid == $message->useridfrom;

            $data = [
                'issender' => transform::yesno($issender),
                'message' => message_format_message_text($message),
                'timecreated' => transform::datetime($message->timecreated),
                'timeread' => $timeread
            ];
            if ($conversation->type == \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP && !$issender) {
                // Only export sender for group conversations when is not the current user.
                $data['sender'] = transform::user($message->useridfrom);
            }

            if (!is_null($message->timedeleted)) {
                $data['timedeleted'] = transform::datetime($message->timedeleted);
            }

            $messagedata[] = (object) $data;
        }
        $messages->close();

        if (!empty($messagedata)) {
            // Get context and subcontext.
            if (empty($conversation->contextid)) {
                // Conversations without contextid are located in the user context.
                $context = \context_user::instance($userid);

                // User conversations are stored in 'Messages | <Other user full name>'.
                if (isset($otherusers[$conversation->id])) {
                    $otheruserfullname = fullname($otherusers[$conversation->id]);
                } else {
                    // It's possible the other user has requested to be deleted, so might not exist
                    // as a conversation member, or they have just been deleted.
                    $otheruserfullname = get_string('unknownuser', 'core_message');
                }

                $subcontext = [get_string('messages', 'core_message'), $otheruserfullname];
            } else {
                // This conversation has its own context.
                $context = \context::instance_by_id($conversation->contextid);

                // If the itemtype doesn't exist in the component string file, the raw itemtype will be returned.
                if (get_string_manager()->string_exists($conversation->itemtype, $conversation->component)) {
                    $itemtypestring = get_string($conversation->itemtype, $conversation->component);
                } else {
                    $itemtypestring = $conversation->itemtype;
                }
                // Context conversations are stored in 'Messages | <Conversation item type> | <Conversation name>'.
                $subcontext = [
                    get_string('messages', 'core_message'),
                    $itemtypestring,
                    $conversation->name
                ];
            }

            // Export the conversation messages.
            writer::with_context($context)->export_data($subcontext, (object) $messagedata);
        }
    }

    /**
     * Export the notification data.
     *
     * @param int $userid
     */
    protected static function export_user_data_notifications(int $userid) {
        global $DB;

        $context = \context_user::instance($userid);

        $notificationdata = [];
        $select = "useridfrom = ? OR useridto = ?";
        $notifications = $DB->get_recordset_select('notifications', $select, [$userid, $userid], 'timecreated ASC');
        foreach ($notifications as $notification) {
            $timeread = !is_null($notification->timeread) ? transform::datetime($notification->timeread) : '-';

            $data = (object) [
                'subject' => $notification->subject,
                'fullmessage' => $notification->fullmessage,
                'smallmessage' => $notification->smallmessage,
                'component' => $notification->component,
                'eventtype' => $notification->eventtype,
                'contexturl' => $notification->contexturl,
                'contexturlname' => $notification->contexturlname,
                'timeread' => $timeread,
                'timecreated' => transform::datetime($notification->timecreated)
            ];

            $notificationdata[] = $data;
        }
        $notifications->close();

        writer::with_context($context)->export_data([get_string('notifications', 'core_message')], (object) $notificationdata);
    }
}
