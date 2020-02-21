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
 * This is the external API for this component.
 *
 * @package    core_contentbank
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;

/**
 * This is the external API for this component.
 *
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * delete_content parameters.
     *
     * @since  Moodle 3.9
     * @return external_function_parameters
     */
    public static function delete_content_parameters(): \external_function_parameters {
        return new external_function_parameters(
            [
                'contentid' => new external_value(PARAM_INT, 'The content id to delete', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Delete content from the contentbank.
     *
     * @since  Moodle 3.9
     * @param  int $contentid The content id to delete.
     * @return boolean
     * @throws \dml_missing_record_exception if there isn't any content with this identifier.
     */
    public static function delete_content(int $contentid): bool {
        global $DB;

        $params = external_api::validate_parameters(self::delete_content_parameters(), [
            'contentid' => $contentid
        ]);

        $content = $DB->get_record('contentbank_content', ['id' => $contentid], '*', MUST_EXIST);
        return base::delete_content($content);
    }

    /**
     * delete_content return
     *
     * @since  Moodle 3.9
     * @return external_value
     */
    public static function delete_content_returns(): \external_value {
        return new external_value(PARAM_BOOL, 'The success');
    }
}
