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
 * Contains class used to prepare the conversation for display.
 *
 * @package   core_message
 * @copyright 2018 Sara Arjona <sara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_message\output\messagearea;

defined('MOODLE_INTERNAL') || die();

use core_message\api;
use renderable;
use templatable;

/**
 * Class to prepare the conversation messages for display.
 *
 * @package   core_message
 * @copyright 2018 Sara Arjona <sara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation implements templatable, renderable {

    /**
     * @var array The messages.
     */
    public $messages;

    /**
     * @var int The current user id.
     */
    public $currentuserid;

    /**
     * @var int The conversation id.
     */
    public $convid;

    /**
     * @var \stdClass The users who have sent some message to this conversation subset.
     */
    public $members;

    /**
     * Constructor.
     *
     * @param int $currentuserid The current user we are wanting to view messages for.
     * @param int $convid The conversation id we are wanting to view messages for.
     * @param array $messages The conversation messages.
     */
    public function __construct($currentuserid, $convid, $messages) {
        $ufields = 'id, ' . get_all_user_name_fields(true) . ', lastaccess';

        $this->currentuserid = $currentuserid;
        $this->convid = $convid;
        if ($convid) {
            $this->members = \core_message\api::get_conversation_members($convid, false);
        }
        $this->messages = $messages;

        // Get users who have sent some message to this conversation subset.
        $memberids = array_unique(array_map(function($message) {
            return $message->useridfrom;
        }, $this->messages));
        $this->members = array_map(function($memberid) use ($ufields) {
            return \core_user::get_user($memberid, '*', MUST_EXIST);
        }, $memberids);
    }

    /**
     * Export data to be rendered.
     *
     * @param  \renderer_base $output Renderer to be used to render the page elements.
     * @return stdClass Exported data.
     */
    public function export_for_template(\renderer_base $output) {
        global $USER, $PAGE;

        $data = new \stdClass();
        $data->conversationid = $this->convid;
        $data->iscurrentuser = $USER->id == $this->currentuserid;
        $data->currentuserid = $this->currentuserid;
        if (!empty($this->members)) {
            foreach ($this->members as $member) {
                $convmember = new \stdClass();
                $convmember->id = $member->id;
                $convmember->fullname = fullname($member);
                $userpicture = new \user_picture($member);
                $userpicture->size = 0; // Size f2.
                $convmember->profileimageurlsmall = $userpicture->get_url($PAGE)->out(false);
                $data->members[] = $convmember;
            }
        }

        $data->messages = array();
        foreach ($this->messages as $message) {
            $message = new message($message);
            $data->messages[] = $message->export_for_template($output);
        }

        return $data;
    }
}