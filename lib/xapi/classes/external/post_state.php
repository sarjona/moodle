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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_component;
use core_xapi\iri;
use JsonException;
use stdClass;

/**
 * This is the external API for generic xAPI state post.
 *
 * @package    core_xapi
 * @since      Moodle 4.2
 * @copyright  2023 Ferran Recio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_state extends external_api {

    /**
     * Parameters for execute
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'component' => new external_value(PARAM_COMPONENT, 'Component name'),
                'activityId' => new external_value(PARAM_URL, 'xAPI activity ID IRI'),
                'agent' => new external_value(PARAM_RAW, 'The xAPI agent json'),
                'stateId' => new external_value(PARAM_ALPHAEXT, 'The xAPI state ID'),
                'stateData' => new external_value(PARAM_RAW, 'JSON object with the state data'),
                'registration' => new external_value(PARAM_ALPHANUMEXT, 'The xAPI registration UUID', VALUE_DEFAULT, null),
            ]
        );
    }

    /**
     * Process a state post request.
     *
     * @param string $component The component name in frankenstyle.
     * @param string $activityiri The activity IRI.
     * @param string $agent The agent JSON.
     * @param string $stateid The xAPI state id.
     * @param string $statedata JSON object with the state data
     * @param string|null $registration The xAPI registration UUID.
     * @return bool
     */
    public static function execute(
        string $component,
        string $activityiri,
        string $agent,
        string $stateid,
        string $statedata,
        ?string $registration = null
    ): bool {

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'activityId' => $activityiri,
            'agent' => $agent,
            'stateId' => $stateid,
            'stateData' => $statedata,
            'registration' => $registration,
        ]);
        [
            'component' => $component,
            'activityId' => $activityiri,
            'agent' => $agent,
            'stateId' => $stateid,
            'stateData' => $statedata,
            'registration' => $registration,
        ] = $params;

        static::validate_component($component);

        $handler = handler::create($component);
        $activityid = iri::extract($activityiri, 'activity');

        $state = new state(
            self::get_agent_from_json($agent),
            item_activity::create_from_id($activityid),
            $stateid,
            self::get_statedata_from_json($statedata),
            $registration
        );

        if (!self::check_state_user($state, $handler)) {
            throw new xapi_exception('State agent is not the current user');
        }

        return $handler->save_state($state);
    }

    /**
     * Return for execute.
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'If the state is accepted');
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
     * Convert the state data JSON into valid object.
     *
     * @throws xapi_exception if JSON cannot be parsed
     * @param string $statedatajson JSON encoded structure
     * @return stdClass the state data structure
     */
    private static function get_statedata_from_json(string $statedatajson): stdClass {
        try {
            // Force it to be an object, because some statedata might be sent as array instead of JSON.
            $statedata = json_decode($statedatajson, false, 521, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new xapi_exception('Invalid state data format');
        }
        return $statedata;
    }

    /**
     * Check that $USER is actor in the state.
     *
     * @param state $state The state object to check
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
