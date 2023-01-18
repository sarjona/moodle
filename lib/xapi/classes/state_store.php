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

namespace core_xapi;

use core_xapi\local\state;

/**
 * The state store manager.
 *
 * @package    core_xapi
 * @since      Moodle 4.2
 * @copyright  2022 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state_store {

    /** @var string component name in frankenstyle. */
    protected $component;

    /**
     * Constructor for a xAPI handler base class.
     *
     * @param string $component the component name
     */
    public function __construct(string $component) {
        $this->component = $component;
    }

    /**
     * Delete any extra state data stored in the database.
     *
     * This method will be called only if the state is accepted by validate_state.
     *
     * Plugins may overridde this method add extra clean up tasks to the deletion.
     *
     * @param state $state
     * @return bool if the state is removed
     */
    public function delete(state $state): bool {
        global $DB;
        $data = (object) [
            'component' => $this->component,
            'userid' => $state->get_user()->id,
            'itemid' => $state->get_activity_id(),
            'stateid' => $state->get_state_id(),
            'registration' => $state->get_registration(),
        ];
        return $DB->delete_record('xapi_states', $data);
    }

    /**
     * Get a state object from the database.
     *
     * This method will be called only if the state is accepted by validate_state.
     *
     * Plugins may overridde this method if they store some data in different tables.
     *
     * @param state $state
     * @return state the state
     */
    public function get(state $state): state {
        global $DB;
        $data = (object) [
            'component' => $this->component,
            'userid' => $state->get_user()->id,
            'itemid' => $state->get_activity_id(),
            'stateid' => $state->get_state_id(),
            'registration' => $state->get_registration(),
        ];
        $statedata = $DB->get_field('xapi_states', 'statedata', $data);
        if (!empty($statedata)) {
            $statedata = json_decode($statedata, null, 521, JSON_THROW_ON_ERROR);
        }
        $state->set_state_data($statedata);
        return $state;
    }

    /**
     * Inserts an state object into the database.
     *
     * This method will be called only if the state is accepted by validate_state.
     *
     * Plugins may overridde this method if they store some data in different tables.
     *
     * @param state $state
     * @return bool if the state is inserted/updated
     */
    public function put(state $state): bool {
        global $DB;
        $data = (object) [
            'component' => $this->component,
            'userid' => $state->get_user()->id,
            'itemid' => $state->get_activity_id(),
            'stateid' => $state->get_state_id(),
            'registration' => $state->get_registration(),
        ];
        $record = $DB->get_record('xapi_states', $data) ?: $data;
        $record->statedata = json_encode($state);
        if (isset($record->id)) {
            $result = $DB->update_record('xapi_states', $record);
        } else {
            $result = $DB->insert_record('xapi_states', $record);
        }
        return $result ? true : false;
    }

    /**
     * Remove all states from the component
     *
     * Plugins may overridde this method if they store some data in different tables.
     *
     * @param string|null $itemid
     * @param int|null $userid
     * @param string|null $stateid
     * @param string|null $registration
     */
    public function wipe(
        ?string $itemid = null,
        ?int $userid = null,
        ?string $stateid = null,
        ?string $registration = null
    ): void {
        global $DB;
        $data = (object) ['component' => $this->component];
        if ($itemid) {
            $data->itemid = $itemid;
        }
        if ($userid) {
            $data->userid = $userid;
        }
        if ($stateid) {
            $data->stateid = $stateid;
        }
        if ($registration) {
            $data->registration = $registration;
        }
        $DB->delete_record('xapi_states', $data);
    }

    /**
     * Execute a state store clean up.
     *
     * Plugins can override this methos to provide an alternative clean up logic.
     */
    public function cleanup() {
        global $DB;
        $xapicleanupperiod = get_config('core', 'xapicleanupperiod');
        if (empty($xapicleanupperiod)) {
            return;
        }
        $todelete = time() - $xapicleanupperiod;
        $DB->delete_records_select(
            'xapi_states',
            'component = :component AND timemodified < :todelete',
            ['component' => $this->component, 'todelete' => $todelete]
        );
    }
}
