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
 * Testing fixtures for message.
 *
 * @package    core_message
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_message\test;

defined('MOODLE_INTERNAL') || die();

/**
 * Testing fixtures for message.
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_message {

    /**
     * Send a fake message.
     *
     * {@link message_send()} does not support transaction, this function will simulate a message
     * sent from a user to another. We should stop using it once {@link message_send()} will support
     * transactions. This is not clean at all, this is just used to add rows to the table.
     *
     * @param stdClass $userfrom user object of the one sending the message.
     * @param int $convid id of the conversation in which we'll send the message.
     * @param string $message message to send.
     * @param int $time the time the message was sent.
     * @return int the id of the message which was sent.
     * @throws dml_exception if the conversation doesn't exist.
     */
    public static function send_fake_message_to_conversation(\stdClass $userfrom, int $convid, string $message = 'Hello world!',
            int $time = null) : int {
        global $DB;

        $conversationrec = $DB->get_record('message_conversations', ['id' => $convid], 'id', MUST_EXIST);
        $conversationid = $conversationrec->id;

        $time = $time ?? time();
        $record = new \stdClass();
        $record->useridfrom = $userfrom->id;
        $record->conversationid = $conversationid;
        $record->subject = 'No subject';
        $record->fullmessage = $message;
        $record->smallmessage = $message;
        $record->timecreated = $time;

        return $DB->insert_record('messages', $record);
    }
}
