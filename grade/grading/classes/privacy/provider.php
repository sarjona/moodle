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
 * @package    core_grading
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_grading\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
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
    \core_privacy\local\request\subsystem\plugin_provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('grading_definitions', [
                'method' => 'privacy:metadata:grading_definitions:method',
                'areaid' => 'privacy:metadata:grading_definitions:areaid',
                'name' => 'privacy:metadata:grading_definitions:name',
                'description' => 'privacy:metadata:grading_definitions:description',
                'status' => 'privacy:metadata:grading_definitions:status',
                'copiedfromid' => 'privacy:metadata:grading_definitions:copiedfromid',
                'timecopied' => 'privacy:metadata:grading_definitions:timecopied',
                'timecreated' => 'privacy:metadata:grading_definitions:timecreated',
                'usercreated' => 'privacy:metadata:grading_definitions:usercreated',
                'timemodified' => 'privacy:metadata:grading_definitions:timemodified',
                'usermodified' => 'privacy:metadata:grading_definitions:usermodified',
                'options' => 'privacy:metadata:grading_definitions:options',
            ], 'privacy:metadata:grading_definitions');

        return $collection;
    }

    /**
     * Exports the data related to grading definitions for the $method, within the specified
     * context/subcontext.
     *
     * @param  \context         $context Context owner of the data.
     * @param  array            $subcontext Subcontext owner of the data.
     * @param  string           $method The owner of the data (usually a component name).
     * @param  int              $userid The user whose information is to be exported.
     */
    public static function export_definitions(\context $context, array $subcontext, string $method, int $userid = 0) {
        global $DB, $USER;

        $join = '';
        $select = 'd.method = :method';
        $params = [
            'method' => $method,
            'contextlevel' => CONTEXT_MODULE
        ];

        $join .= "  JOIN {grading_areas} a ON a.id = d.areaid
                    JOIN {context} c ON a.contextid = c.id AND c.contextlevel = :contextlevel";
        $select .= ' AND a.contextid = :contextid';

        $params['contextlevel'] = CONTEXT_MODULE;
        $params['contextid'] = $context->id;

        if (!empty($userid)) {
            $params['usercreated'] = $userid;
            $params['usermodified'] = $userid;
            $select .= ' AND (usercreated = :usercreated OR usermodified = :usermodified)';
        }

        $sql = "SELECT d.id,
                       d.method,
                       d.name,
                       d.description,
                       d.timecopied,
                       d.timecreated,
                       d.usercreated,
                       d.timemodified,
                       d.usermodified
                  FROM {grading_definitions} d
                 $join
                 WHERE $select";
        $definitions = $DB->get_recordset_sql($sql, $params);
        $defdata = [];
        foreach ($definitions as $definition) {
            $tmpdata = (object) [
                'method' => $definition->method,
                'name' => $definition->name,
                'description' => $definition->description,
                'timecreated' => transform::datetime($definition->timecreated),
                'usercreated' => transform::user($definition->usercreated),
                'timemodified' => transform::datetime($definition->timemodified),
                'usermodified' => transform::user($definition->usermodified),
            ];
            if (!empty($definition->timecopied)) {
                $tmpdata->timecopied = transform::datetime($definition->timecopied);
            }
            $defdata[] = $tmpdata;
        }
        $definitions->close();

        if (!empty($defdata)) {
            $data = (object) [
                'definitions' => $defdata,
            ];

            writer::with_context($context)->export_related_data($subcontext, 'gradingmethod', $data);
        }
    }

    /**
     * Deletes all grading definitions for a method.
     *
     * @param  string           $method The owner of the data (usually a component name).
     * @param  int              $userid The owner of the data.
     */
    public static function delete_definitions(string $method, $userid = null) {
        global $DB;

        $select = 'method = :method';
        $params = [
            'method' => $method,
        ];

        if (!empty($userid)) {
            $params['usercreated'] = $userid;
            $params['usermodified'] = $userid;
            $select .= ' AND (usercreated = :usercreated OR usermodified = :usermodified)';
        }

        $DB->delete_records_select('grading_definitions', $select, $params);
    }
}
