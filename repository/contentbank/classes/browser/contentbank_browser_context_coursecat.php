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

namespace repository_contentbank\browser;

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
     * @param \context_coursecat $context The current context
     */
    public function __construct(\context_coursecat $context) {
        $this->context = $context;
    }

    /**
     * Define the allowed child context levels.
     *
     * @return array The array containing the relevant child context levels
     */
    protected function allowed_child_context_levels(): array {
        // The expected child contexts in the course category context level are the course category context
        // (ex. subcategories) and the course context.
        return [\CONTEXT_COURSECAT, \CONTEXT_COURSE];
    }

    /**
     * The required condition to enable the user to view/access the content bank content in this context.
     *
     * @return bool Whether the user can view/access the content bank content in the context
     */
    public function can_access_content(): bool {
        // The content bank repository should enable managers, course creators, editing teachers to share the content
        // created in course category context level all over the site.
        // The content from the course category context level should be available by default to either:
        // * Every authenticated user which has capability to access the content of any child course of the given
        // course category (ex. editing teachers enrolled in any category related course)
        // * Users that have capability to access content at a course category context level (ex. managers).
        $canaccesschldcontent = false;
        foreach ($this->get_child_contexts() as $childcontext) {
            $browser = \repository_contentbank\helper::get_contentbank_browser($childcontext);
            if ($canaccesschldcontent = $browser->can_access_content()) {
                break;
            }
        }
        $canaccesscoursecategorycontent = has_capability('repository/contentbank:accesscoursecategorycontent',
            $this->context);
        $canaccessgeneralcontent = has_capability('repository/contentbank:accessgeneralcontent',
            $this->context);

        return $canaccesscoursecategorycontent || ($canaccessgeneralcontent && $canaccesschldcontent);
    }
}
