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

namespace repository_contentbank\browser;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for the content bank browsers.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class contentbank_browser {

    /** @var context The current context. */
    protected $context;

    /**
     * Get the content bank browser class of the parent context. Currently used to generate the navigation path.
     *
     * @return contentbank_browser|null The content bank browser of the parent context
     */
    abstract public function get_parent(): ?self;

    /**
     * Generate folder nodes for the relevant child contexts.
     *
     * @return array The array containing the context folder nodes
     */
    abstract protected function get_context_folders(): array;

    /**
     * Get all content nodes in the current context which can be viewed/accessed by the user.
     *
     * @return array The array containing all nodes which can be viewed/accessed by the user in the current context
     */
    public function get_content() {
        return array_merge($this->get_context_folders(), $this->get_contentbank_files());
    }

    /**
     * Generate file nodes for the content bank files in the current context which can be accessed/viewed by the user.
     *
     * @return array The array containing the content bank file nodes
     */
    protected function get_contentbank_files(): array {
        $cb = new \core_contentbank\contentbank();
        // Return all content bank files in the current context.
        $contents = $cb->search_contents(null, $this->context->id);
        return array_reduce($contents, function($list, $content) {
            if (\repository_contentbank\helper::can_access_contentbank_context($this->context) &&
                    $content->can_view() && $file = $content->get_file()) {
                $list[] = \repository_contentbank\helper::create_contentbank_file_node($file);
            }
            return $list;
        }, []);
    }

    /**
     * Generate the full navigation to the current node.
     *
     * @return array The array containing the path to each node in the navigation
     */
    public function get_navigation(): array {
        // Get the current navigation node.
        $currentnavigationnode = \repository_contentbank\helper::create_navigation_node($this->context);
        $navigationnodes = array($currentnavigationnode);
        // Get the parent content bank browser.
        $parent = $this->get_parent();
        // Prepend parent navigation node in the navigation nodes array until there is an existing parent.
        while ($parent !== null) {
            $parentnavigationnode = \repository_contentbank\helper::create_navigation_node($parent->context);
            array_unshift($navigationnodes, $parentnavigationnode);
            $parent = $parent->get_parent();
        }
        return $navigationnodes;
    }
}
