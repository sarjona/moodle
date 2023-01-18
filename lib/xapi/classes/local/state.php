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

namespace core_xapi\local;

use core_xapi\local\statement\item_agent;
use core_xapi\local\statement\item_activity;
use JsonSerializable;
use stdClass;

/**
 * State resource object for xAPI structure checking and validation.
 *
 * @package    core_xapi
 * @since      Moodle 4.2
 * @copyright  2023 Ferran Recio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state implements JsonSerializable {

    /** @var item_agent The statement agent (user). */
    protected $agent = null;

    /** @var item_activity The state activity owner (the plugin instance). */
    protected $activity = null;

    /** @var string The state identifier. */
    protected $stateid = null;

    /** @var stdClass|null The state data. */
    protected $statedata = null;

    /** @var string|null The statement registration. */
    protected $registration = null;

    /**
     * Item constructor.
     *
     * @param stdClass $data from the specific xAPI element
     */
    protected function __construct(
        item_agent $agent,
        item_activity $activity,
        string $stateid,
        ?stdClass $statedata,
        ?string $registration
    ) {
        $this->agent = $agent;
        $this->activity = $activity;
        $this->stateid = $stateid;
        $this->statedata = $statedata;
        $this->registration = $registration;
    }

    /**
     * Return the data to serialize in case JSON state when needed.
     *
     * @return stdClass the state data structure
     */
    public function jsonSerialize(): stdClass {
        return $this->statedata;
    }

    /**
     * Return the record data of this state.
     *
     * @return stdClass the record data structure
     */
    public function get_record_data(): stdClass {
        $result = (object) [
            'userid' => $this->get_user()->id,
            'itemid' => $this->get_activity_id(),
            'stateid' => $this->stateid,
            'statedata' => json_encode($this),
            'registration' => $this->registration,
        ];
        return $result;
    }

    /**
     * Returns a minified version of a given state.
     *
     * The returned structure is suitable to store in the "other" field
     * of logstore. xAPI standard specifies a list of attributes that can be calculated
     * instead of stored literally. This function get rid of these attributes.
     *
     * Note: it also converts stdClass to assoc array to make it compatible
     * with "other" field in the logstore
     *
     * @return array the minimal state needed to be stored a part from logstore data
     */
    public function minify(): ?array {
        $result = [];
        $fields = ['activity', 'stateid', 'statedata', 'registration'];
        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $result[$field] = $this->$field;
            }
        }
        return json_decode(json_encode($result), true);
    }

    /**
     * Set the state data.
     *
     * @param stdClass $statedata the state data
     */
    public function set_state_data(stdClass $statedata): void {
        $this->statedata = $statedata;
    }

    /**
     * Returns the moodle user represented by this state agent.
     *
     * @return stdClass user record
     */
    public function get_user(): stdClass {
        return $this->agent->get_user();
    }

    /**
     * Returns the statement activity ID.
     *
     * @return string activity ID
     */
    public function get_activity_id(): string {
        return $this->activity->get_id();
    }

    /**
     * Return the statement agent.
     *
     * @return item_agent
     */
    public function get_agent(): item_agent {
        return $this->agent;
    }

    /**
     * Return the statement object if it is defined.
     *
     * @return item_activity|null
     */
    public function get_activity(): ?item_activity {
        return $this->activity;
    }

    /**
     * Returns the state id.
     *
     * @return string state identifier
     */
    public function get_state_id(): string {
        return $this->stateid;
    }

    /**
     * Returns the state registration if any.
     *
     * @return string|null state registration
     */
    public function get_registration(): ?string {
        return $this->registration;
    }

}
