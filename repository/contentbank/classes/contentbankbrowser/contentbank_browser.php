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

namespace repository_contentbank\contentbankbrowser;

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
     * Check whether the user can access the content bank in the current context or not.
     *
     * @return bool Whether the user can access the content bank in the current context
     */
    public function can_access_contentbank_context(): bool {
        return has_capability('moodle/contentbank:access', $this->context);
    }

    /**
     * Generate file nodes for the content bank files in the current context which can be accessed/viewed by the user.
     *
     * @return array The array containing the content bank file nodes
     */
    private function get_contentbank_files(): array {
        global $DB;
        // Get all content bank files in the context.
        $contents = $DB->get_records('contentbank_content', ['contextid' => $this->context->id]);
        // Return only content bank files which are enabled and can be accessed and viewed by the user.
        return array_reduce($contents, function($list, $content) {
            $plugin = \core_plugin_manager::instance()->get_plugin_info($content->contenttype);
            $managerclass = "\\$content->contenttype\\content";
            if ($plugin && $plugin->is_enabled() && class_exists($managerclass)) {
                $contentmanager = new $managerclass($content);
                if ($contentmanager->can_view() && $this->can_access_contentbank_context() &&
                        $file = $contentmanager->get_file()) {
                    $list[] = $this->create_contentbank_file_node($file);
                }
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
        $navigationnodes = array($this->get_navigation_node());
        // Get the parent content bank browser.
        $parent = $this->get_parent();
        // Prepend parent navigation node in the navigation nodes array until there is an existing parent.
        while ($parent !== null) {
            $parentnavigationnode = $parent->get_navigation_node();
            array_unshift($navigationnodes, $parentnavigationnode);
            $parent = $parent->get_parent();
        }
        return $navigationnodes;
    }

    /**
     * Create the context folder node.
     *
     * @param string $name The name of the context folder node
     * @param string $path The path to the context folder node
     * @return array The context folder node
     */
    protected function create_context_folder_node(string $name, string $path): array {
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
     * Check whether there are content bank files in the current context which can be accessed/viewed by the user.
     *
     * @return bool Whether there are content bank files in the current context which are available to the user
     */
    protected function has_contentbank_files() {
        return !empty($this->get_contentbank_files());
    }

    /**
     * Check whether there are child context folders in the current context which are available to the user.
     *
     * @return bool Whether there are child context folders in the current context which are available to the user
     */
    protected function has_context_folders() {
        return !empty($this->get_context_folders());
    }

    /**
     * Create the content bank file node.
     *
     * @param stored_file $file The stored file
     * @return array The content bank file node
     */
    private function create_contentbank_file_node(\stored_file $file): array {
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
     * @return array The navigation node
     */
    private function get_navigation_node(): array {
        return array(
            'path' => base64_encode(json_encode(['contextid' => $this->context->id])),
            'name' => $this->context->get_context_name(false)
        );
    }
}
