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
 * This is the external method for getting the information needed to get glossary entry to display.
 *
 * @package    block_glossary_random
 * @copyright  2020 Adrian Perez, Fernfachhochschule Schweiz (FFHS) <adrian.perez@ffhs.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_glossary_random\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use stdClass;

/**
 * This is the external method for getting the information needed to
 *
 * @copyright  2020 Adrian Perez, Fernfachhochschule Schweiz (FFHS) <adrian.perez@ffhs.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_entry extends external_api {

    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'blockinstanceid' => new external_value(PARAM_INT, 'Glossary random block instance id')
            ]
        );
    }

    /**
     * Return glossary entry for a glossary activity.
     *
     * @param  int $blockinstanceid The glossary random block id.
     * @return stdClass Glossary entry data
     */
    public static function execute(int $blockinstanceid): stdClass {
        $params = external_api::validate_parameters(self::execute_parameters(), [
            'blockinstanceid' => $blockinstanceid,
        ]);
        $blockinstanceid = $params['blockinstanceid'];

        // Get the random glossary block.
        $blockinstance = block_instance_by_id($blockinstanceid);

        // Get the entry to display.
        $entry = helper::get_entry($blockinstance);

        // Prepare the result.
        $result = (object)[
            'glossaryid' => $blockinstance->id,
            'entry' => $entry,
        ];

        return $result;
    }

    /**
     * Describes the get_entries return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'glossaryid' => new external_value(PARAM_INT, 'Glossary id'),
            'entry' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'ID of the context'),
                'concept' => new external_value(PARAM_RAW, 'Glossary concept'),
                'definition' => new external_value(PARAM_RAW, 'Glossary definition'),
                'definitionformat' => new external_value(PARAM_RAW, 'Glossary definition format'),
                'definitiontrust' => new external_value(PARAM_RAW, 'Glossary definition trust'),
                'showconcept' => new external_value(PARAM_BOOL, 'If the concept should be displayed')
            ]),
        ], 'Glossary entry data');
    }
}
