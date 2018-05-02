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
 * Privacy class for requesting user data.
 *
 * @package    gradingform_guide
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_guide\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\writer;

/**
 * Privacy class for requesting user data.
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\subsystem\provider,
        \core_privacy\local\request\user_preference_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_subsystem_link('core_grading', [], 'privacy:metadata:core_grading');

        $collection->add_user_preference(
            'gradingform_guide-showmarkerdesc',
            'privacy:metadata:preference:showmarkerdesc'
        );
        $collection->add_user_preference(
            'gradingform_guide-showstudentdesc',
            'privacy:metadata:preference:showstudentdesc'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $sql = "SELECT c.id
                  FROM {grading_definitions} d
                  JOIN {grading_areas} a ON a.id = d.areaid
                  JOIN {context} c ON a.contextid = c.id AND c.contextlevel = :contextlevel
                 WHERE d.method = 'guide'
                   AND (d.usercreated = :usercreated OR d.usermodified = :usermodified)";

        $params = [
            'usercreated' => $userid,
            'usermodified' => $userid,
            'contextlevel' => CONTEXT_MODULE
        ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // Remove contexts different from MODULE.
        $contexts = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->id;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                // Export grading definitions created or modified on this context.
                \core_grading\privacy\provider::export_definitions($context, [], 'guide', $userid);
            }
        }
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $prefvalue = get_user_preferences('gradingform_guide-showmarkerdesc', null, $userid);
        if ($prefvalue !== null) {
            $transformedvalue = transform::yesno($prefvalue);
            writer::export_user_preference(
                'gradingform_guide',
                'gradingform_guide-showmarkerdesc',
                $transformedvalue,
                get_string('privacy:metadata:preference:showmarkerdesc', 'gradingform_guide')
            );
        }

        $prefvalue = get_user_preferences('gradingform_guide-showstudentdesc', null, $userid);
        if ($prefvalue !== null) {
            $transformedvalue = transform::yesno($prefvalue);
            writer::export_user_preference(
                'gradingform_guide',
                'gradingform_guide-showstudentdesc',
                $transformedvalue,
                get_string('privacy:metadata:preference:showstudentdesc', 'gradingform_guide')
            );
        }
    }

    /**
     * Delete all use data which matches the specified $context.
     *
     * We never delete guides to the grading content.
     *
     * @param context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * We never delete guides to the grading content.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }
}
