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
 * This is the external method for deleting a content.
 *
 * @package    core_contentbank
 * @since      Moodle 3.9
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;

/**
 * This is the external method for deleting a content.
 *
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_content extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contentid' => new external_value(PARAM_INT, 'The content id to delete', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Delete content from the contentbank.
     *
     * @param  int $contentid The content id to delete.
     * @return boolean True if the content has been deleted; false otherwise.
     * @throws \dml_missing_record_exception if there isn't any content with this identifier.
     */
    public static function execute(int $contentid): bool {
        global $DB;

        $params = external_api::validate_parameters(self::execute_parameters(), [
            'contentid' => $contentid
        ]);

        $content = $DB->get_record('contentbank_content', ['id' => $contentid], '*', MUST_EXIST);
        $contenttypeclass = "\\$content->contenttype\\contenttype";
        if (class_exists($contenttypeclass)) {
            $context = \context::instance_by_id($content->contextid, MUST_EXIST);
            $contenttypemanager = new $contenttypeclass($context);
            return $contenttypemanager->delete_content($content);
        }

        return false;
    }

    /**
     * Return.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'The success');
    }
}
