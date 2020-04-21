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
 * Utility class for browsing of content bank files in the system context.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_contentbank\browser;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents the content bank browser in the system context.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contentbank_browser_context_system extends contentbank_browser {

    /**
     * Constructor.
     *
     * @param \context_system $context The current context
     */
    public function __construct(\context_system $context) {
        $this->context = $context;
    }

    /**
     * Get the content bank browser class of the parent context. Currently used to generate the navigation path.
     *
     * @return contentbank_browser|null The content bank browser of the parent context
     */
    public function get_parent(): ?contentbank_browser {
        return null;
    }

    /**
     * Generate folder nodes for the relevant child contexts.
     *
     * @return array The array containing the context folder nodes
     */
    protected function get_context_folders(): array {
        // Return only child contexts that are in the course category context which contain
        // content bank files accessible to the user within the context or contain child course context folders
        // available to the user.
        return array_reduce($this->context->get_child_contexts(), function ($list, $childcontext) {
            // Make sure the child context is an instance of the course category context class.
            if ($childcontext instanceof \context_coursecat) {
                // Initialize the course category content bank browser.
                $coursecategorybrowser = new contentbank_browser_context_coursecat($childcontext);
                $hascontentbankfiles = !empty($coursecategorybrowser->get_contentbank_files());
                $hascontextfolders = !empty($coursecategorybrowser->get_context_folders());
                if ($hascontentbankfiles || $hascontextfolders) {
                    // Create the context folder node.
                    $name = $childcontext->get_context_name(false);
                    $path = base64_encode(json_encode(['contextid' => $childcontext->id]));
                    $list[] = \repository_contentbank\helper::create_context_folder_node($name, $path);
                }
            }
            return $list;
        }, []);
    }
}
