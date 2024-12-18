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

namespace core_badges;

use coding_exception;
use stdClass;

/**
 * Base class for communicating with backpacks.
 *
 * @package    core_badges
 * @copyright  2024 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class backpackapi_base {

    /** @var \core_badges\backpack_api_mapping[] List of backpack API requests. */
    protected $mappings = [];

    /**
     * Base constructor.
     *
     * The $extra parameter has been added here for future-proofing.
     * This allows named parameters to be used and allows classes extending to
     * make use of parameters in newer versions even if they don't exist in older versions.
     *
     * @param stdClass $externalbackpack The external backpack record
     * @param mixed ...$extra Extra arguments to allow for future versions to add more options
     */
    public function __construct(
        /** @var stdClass The external backpack record. */
        protected stdClass $externalbackpack,
        mixed ...$extra,
    ) {
        $this->externalbackpack = $externalbackpack;
        if ($this->externalbackpack->apiversion != $this->get_api_version()) {
            throw new coding_exception('Incorrect backpack version');
        }

        $this->define_mappings();
    }

    /**
     * Return the API version supported by this backpack.
     *
     * @return string The API version.
     */
    abstract protected function get_api_version(): string;

    /**
     * Define the mappings supported by this usage and api version.
     */
    protected function define_mappings(): void {
        foreach ($this->get_mappings() as $mapping) {
            $mapping[] = false; // Site api function.
            $mapping[] = $this->get_api_version(); // API version.
            $this->mappings[] = new ($this->get_mapping_class())(...$mapping);
        }
    }

    abstract protected function get_mapping_class(): string;

    /**
     * Get the mappings supported by this usage and api version.
     *
     * @return array The mappings.
     */
    abstract protected function get_mappings(): array;

    /**
     * Disconnect the backpack from current user.
     *
     * @return bool
     */
    abstract public function disconnect_backpack(): bool;

    /**
     * Create a new backpackapi instance from an external backpack record.
     *
     * @param \stdClass $externalbackpack The external backpack record
     * @param mixed ...$extra Extra arguments
     * @throws \coding_exception
     * @return \core_badges\backpackapi_base The new backpackapi instance
     */
    public static function create_from_externalbackpack(
        stdClass $externalbackpack,
        mixed ...$extra,
    ) : self {
        $apiversion = $externalbackpack->apiversion;
        if ($apiversion == OPEN_BADGES_V2) {
            $apiversion = '2p0';
        } else {
            $apiversion = str_replace(".", "p", $externalbackpack->apiversion);
        }

        $classname = __NAMESPACE__ . '\\backpackapi_' . $apiversion;
        if (!class_exists($classname)) {
            throw new coding_exception('Invalid backpack version');
        }

        return new $classname($externalbackpack, $extra);
    }

}
