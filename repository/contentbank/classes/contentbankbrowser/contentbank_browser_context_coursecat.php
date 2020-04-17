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
 * Utility class for browsing of content bank files in the course category context.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_contentbank\contentbankbrowser;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents the content bank browser in the course category context.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contentbank_browser_context_coursecat extends contentbank_browser {

    /**
     * Constructor.
     *
     * @param \context $context The current context
     */
    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * Get the content bank browser class of the parent context. Currently used to generate the navigation path.
     *
     * @return contentbank_browser|null The content bank browser of the parent context
     */
    public function get_parent(): ?contentbank_browser {
        $parentcontext = $this->context->get_parent_context();
        if ($parentcontext instanceof \context_system) {
            return new contentbank_browser_context_system($parentcontext);
        }
        return null;
    }

    /**
     * Generate folder nodes for the relevant child contexts.
     *
     * @return array The array containing the context folder nodes
     */
    protected function get_context_folders(): array {
        // Return only child contexts that are in the course context which can be accessed by the user
        // and have content bank files within the context.
        return array_reduce($this->context->get_child_contexts(), function ($list, $childcontext) {
            if ($childcontext instanceof \context_course) {
                $coursebrowser = new contentbank_browser_context_coursecat($childcontext);
                if ($coursebrowser->can_access_contentbank_context() && $coursebrowser->has_contentbank_files()) {
                    $name = $childcontext->get_context_name(false);
                    $path = base64_encode(json_encode(['contextid' => $childcontext->id]));
                    $list[] = $this->create_context_folder_node($name, $path);
                }
            }
            return $list;
        }, []);
    }
}
