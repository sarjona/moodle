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
 * Utility class for browsing of content bank files.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_contentbank;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for the content bank browsers.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Check whether the user can access the content bank in the current context or not.
     *
     * @return bool Whether the user can access the content bank in the current context
     */
    public static function can_access_contentbank_context($context): bool {
        return has_capability('moodle/contentbank:access', $context);
    }

    /**
     * Get the content bank repository browser for a certain context.
     *
     * @param context $context The context
     * @return \repository_contentbank\browser\contentbank_browser|null The content bank repository browser
     */
    public static function get_contentbank_browser(\context $context):
            ?\repository_contentbank\browser\contentbank_browser {
        switch ($context->contextlevel) {
            case CONTEXT_SYSTEM:
                return new \repository_contentbank\browser\contentbank_browser_context_system($context);
            case CONTEXT_COURSECAT:
                return new \repository_contentbank\browser\contentbank_browser_context_coursecat($context);
            case CONTEXT_COURSE:
                return new \repository_contentbank\browser\contentbank_browser_context_course($context);
        }
        return null;
    }

    /**
     * Create the context folder node.
     *
     * @param string $name The name of the context folder node
     * @param string $path The path to the context folder node
     * @return array The context folder node
     */
    public static function create_context_folder_node(string $name, string $path): array {
        global $OUTPUT;

        return array(
            'title' => $name,
            'datemodified' => '',
            'datecreated' => '',
            'path' => $path,
            'thumbnail' => $OUTPUT->image_url(file_folder_icon(90))->out(false),
            'children' => array()
        );
    }

    /**
     * Create the content bank file node.
     *
     * @param stored_file $file The stored file
     * @return array The content bank file node
     */
    public static function create_contentbank_file_node(\stored_file $file): array {
        global $OUTPUT;

        $params = array(
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea'  => $file->get_filearea(),
            'itemid'    => $file->get_itemid(),
            'filepath'  => $file->get_filepath(),
            'filename'  => $file->get_filename()
        );

        $encodedpath = base64_encode(json_encode($params));

        $node = array(
            'title' => $file->get_filename(),
            'size' => $file->get_filesize(),
            'datemodified' => $file->get_timemodified(),
            'datecreated' => $file->get_timecreated(),
            'author' => $file->get_author(),
            'license' => $file->get_license(),
            'isref' => $file->is_external_file(),
            'source'=> $encodedpath,
            'icon' => $OUTPUT->image_url(file_file_icon($file, 24))->out(false),
            'thumbnail' => $OUTPUT->image_url(file_file_icon($file, 90))->out(false)
        );

        if ($file->get_status() == 666) {
            $node['originalmissing'] = true;
        }

        return $node;
    }

    /**
     * Generate a navigation node.
     *
     * @param \context $context The context
     * @return array The navigation node
     */
    public static function create_navigation_node(\context $context): array {
        return array(
            'path' => base64_encode(json_encode(['contextid' => $context->id])),
            'name' => $context->get_context_name(false)
        );
    }
}