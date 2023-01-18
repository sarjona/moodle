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

namespace core_xapi\external;

use core_xapi\local\state;
use core_xapi\local\statement\item_activity;
use core_xapi\local\statement\item_agent;
use core_xapi\handler;
use core_xapi\xapi_exception;
use external_api;
use external_function_parameters;
use external_value;
use core_component;
use JsonException;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir .'/externallib.php');

/**
 * This is the external API for generic xAPI state deletion.
 *
 * @package    core_xapi
 * @since      Moodle 4.2
 * @copyright  2023 Ferran Recio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state_delete extends external_api {

    /**
     * Parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'component' => new external_value(PARAM_COMPONENT, 'Component name'),
                'activityId' => new external_value(PARAM_URL, 'xAPI activity ID IRI'),
                'agent' => new external_value(PARAM_RAW, 'The xAPI agent json'),
                'stateId' => new external_value(PARAM_ALPHAEXT, 'The xAPI state ID'),
                'registration' => new external_value(PARAM_ALPHANUMEXT, 'The xAPI registration UUID', VALUE_DEFAULT, null),
            ]
        );
    }

    /**
     * Process a statement post request.
     *
     * @param string $component The component name in frankenstyle.
     * @param string $statedata JSON object with all the statements to post.
     * @return bool[] Storing acceptance of every statement.
     */
    public static function execute(
        string $component,
        string $activityid,
        string $agent,
        string $stateid,
        ?string $registration = null
    ): bool {

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'activityId' => $activityid,
            'agent' => $agent,
            'stateId' => $stateid,
            'registration' => $registration,
        ]);
        [
            'component' => $component,
            'activityId' => $activityid,
            'agent' => $agent,
            'stateId' => $stateid,
            'registration' => $registration,
        ] = $params;

        static::validate_component($component);

        $handler = handler::create($component);

        $state = new state(
            self::get_agent_from_json($agent),
            item_activity::create_from_id($activityid),
            $stateid,
            $registration,
            null
        );

        if (!self::check_state_user($state, $handler)) {
            throw new xapi_exception('State agent is not the current user');
        }

        return $handler->state_delete($state);
    }

    /**
     * Return for execute.
     */
    public static function execute_returns() {
        return new external_value(PARAM_BOOL, 'If the state data is deleted');
    }

    /**
     * Check component name.
     *
     * Note: this function is separated mainly for testing purposes to
     * be overridden to fake components.
     *
     * @throws xapi_exception if component is not available
     * @param string $component component name
     */
    protected static function validate_component(string $component): void {
        // Check that $component is a real component name.
        $dir = core_component::get_component_directory($component);
        if (!$dir) {
            throw new xapi_exception("Component $component not available.");
        }
    }

    /**
     * Convert a json agent into a valid item_agent.
     *
     * @throws xapi_exception if JSON cannot be parsed
     * @param string $agentjson json encoded agent structure
     * @return item_agent the agent
     */
    private static function get_agent_from_json(string $agentjson): item_agent {
        try {
            $agentdata = json_decode($agentjson, null, 521, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new xapi_exception('No agent detected');
        }
        return item_agent::create_from_data($agentdata);
    }

    /**
     * Check that $USER is actor in all statements.
     *
     * @param state $statements array of statements
     * @return bool if $USER is actor of the state
     */
    private static function check_state_user(state $state): bool {
        global $USER;
        $user = $state->get_user();
        if ($user->id != $USER->id) {
            return false;
        }
        return true;
    }
}
