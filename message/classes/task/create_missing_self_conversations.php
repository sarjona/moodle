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
 * Adhoc task handling creation of self-conversations for all existing users without them.
 *
 * @package    core_message
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_message\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class handling creation of self-conversations for all existing users without them.
 *
 * @package    core_message
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_missing_self_conversations extends \core\task\adhoc_task {

    /**
     * Run this task.
     */
    public function execute() {
        global $DB, $CFG;

        // If messaging is disabled, we don't need to create self-conversations for the existing users.
        if (empty($CFG->messaging)) {
            return;
        }

        // Get all the users without a self-conversation.
        $sql = "SELECT u.id
                  FROM {user} u
                  WHERE u.id NOT IN (SELECT mcm.userid
                                     FROM {message_conversation_members} mcm
                                     INNER JOIN mdl_message_conversations mc
                                             ON mc.id = mcm.conversationid AND mc.type = ?
                                    )";
        $userids = $DB->get_records_sql($sql, [\core_message\api::MESSAGE_CONVERSATION_TYPE_SELF]);

        $locktype = 'core_message_create_missing_self_conversations';
        $timeout = 5; // In seconds.

        // Create the self-conversation for all these users.
        foreach ($userids as $userid => $user) {
            $conversation = \core_message\api::get_self_conversation($userid);
            if (empty($conversation)) {
                // Get an instance of the currently configured lock factory.
                $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

                // See if we can grab this lock.
                if ($lock = $lockfactory->get_lock($userid, $timeout)) {
                    try {
                        $conversation = \core_message\api::create_conversation(
                            \core_message\api::MESSAGE_CONVERSATION_TYPE_SELF,
                            [$userid]
                        );
                    } catch (\Throwable $e) {
                        throw $e;
                    } finally {
                        $lock->release();
                    }
                }
            }
        }
    }
}
