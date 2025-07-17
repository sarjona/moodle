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

namespace core_courseformat\local\overview;

use core\url;
use action_link;
use core_calendar\output\humandate;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core_courseformat\output\local\overview\overviewdialog;

/**
 * Class resourceoverview
 *
 * @package    core_courseformat
 * @copyright  2025 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resourceoverview extends \core_courseformat\activityoverviewbase {

    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        if (!$this->is_resource()) {
            // Only resource activities show the actions overview
            // because they are aggregated in one table.
            return null;
        }

        if (!has_capability('report/log:view', $this->context)) {
            return null;
        }

        $content = new action_link(
            url: new url(
                '/report/log/index.php?',
                ['id' => $this->cm->course, 'modid' => $this->cm->id, 'chooselog' => 1, 'modaction' => 'r']
            ),
            text: get_string('view'),
            attributes: ['class' => button::SECONDARY_OUTLINE->classes()],
        );

        return new overviewitem(
            name: get_string('actions'),
            value: '',
            content: $content,
            textalign: text_align::CENTER,
        );
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        if (!$this->is_resource()) {
            // Only resource activities show the extra overview items
            // because they are aggregated in one table.
            return [];
        }

        return [
            'mostrecent' => $this->get_extra_most_recent_overview(),
            'type' => $this->get_extra_type_overview(),
            'totalviews' => $this->get_extra_totalviews_overview(),
        ];
    }

    /**
     * Retrieves an overview item for the extra most recent viewed date for the resource.
     *
     * @return overviewitem|null The overview item or null if not applicable.
     */
    private function get_extra_most_recent_overview(): ?overviewitem {
        $mostrecentdate = $this->get_views('most_recent_viewed_date');
        if (empty($mostrecentdate)) {
            // User does not have permission to view logs, no log reader available or no recent view found.
            return null;
        }
        $content = humandate::create_from_timestamp($mostrecentdate);

        return new overviewitem(
            name: get_string('mostrecentvieweddate', 'course'),
            value: $mostrecentdate,
            content: $content,
        );
    }

    /**
     * Retrieves an overview item for the extra type of the resource.
     *
     * @return overviewitem The overview item for the resource type.
     */
    private function get_extra_type_overview(): overviewitem {
        return new overviewitem(
            name: get_string('resource_type'),
            value: $this->cm->modfullname,
            content: $this->cm->modfullname,
        );
    }

    /**
     * Retrieves an overview item for the extra most recent viewed date for the resource.
     *
     * @return overviewitem|null The overview item or null if not applicable.
     */
    private function get_extra_totalviews_overview(): ?overviewitem {

        $totalviews = $this->get_views('count_total_views');
        if ($totalviews === null) {
            // User does not have permission to view logs or no log reader available, so we cannot provide this overview item.
            return null;
        }

        if ($totalviews > 0) {
            $overviewdialog = new overviewdialog(
                buttoncontent: $totalviews,
                title: get_string('totalviews', 'course'),
                definition: ['buttonclasses' => button::SECONDARY_OUTLINE->classes() . ' dropdown-toggle'],
            );

            $overviewdialog->add_item(
                    get_string('studentsviewed', 'course'),
                    $totalviews,
            );

            $averageviews = 0;
            $totalparticipants = $this->get_participants();
            if ($totalparticipants > 0) {
                $averageviews = round($totalviews / $totalparticipants);
            }
            $overviewdialog->add_item(
                    get_string('averageviewsperstudent', 'course'),
                    $averageviews,
            );
        }

        return new overviewitem(
            name: get_string('totalviews', 'course'),
            value: $totalviews,
            content: $overviewdialog ?? $totalviews,
        );
    }

    /**
     * Counts the number of participants enrolled in the course where the resource is located.
     *
     * @return int The number of participants enrolled in the course.
     */
    private function get_participants(): int {
        return count_enrolled_users(
            context: $this->cm->context,
            withcapability: 'mod/resource:view',
        );
    }

    /**
     * Counts the total number of views for the resource.
     *
     * @param string $action The action: count_total_views or most_recent_viewed_date.
     * @return mixed The total number of views, the most recent viewed date or null if not applicable.
     */
    private function get_views(string $action) {

        if ($action !== 'count_total_views' && $action !== 'most_recent_viewed_date') {
            // Invalid action, return null.
            debugging('Invalid action for get_views: ' . $action);
            return null;
        }

        if (!has_capability('report/log:view', $this->cm->context)) {
            // User does not have permission to view logs.
            return null;
        }

        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers('\core\log\sql_reader');
        $reader = reset($readers);
        if (empty($reader)) {
            // No log reader available.
            return null;
        }

        $select = "objectid = :resourceid
            AND courseid = :courseid
            AND component = :component
            AND action = 'viewed'
            AND target = 'course_module'
        ";
        $params = [
            'resourceid' => $this->cm->instance,
            'component' => 'mod_' . $this->cm->modname,
            'courseid' => $this->cm->course,
        ];

        switch ($action) {
            case 'count_total_views':
                // Count total views.
                return $reader->get_events_select_count($select, $params);
            case 'most_recent_viewed_date':
                // Get the most recent view date.
                $events = $reader->get_events_select($select, $params, 'timecreated DESC', 0, 1);
                if (empty($events)) {
                    return null;
                }
                return reset($events)->timecreated;
        }

        return null;
    }

    /**
     * Checks if the current activity is a resource type.
     *
     * @return bool True if the activity is a resource type, false otherwise.
     */
    protected function is_resource(): bool {
        // Check if the activity is a resource type.
        $archetype = plugin_supports(
            type: 'mod',
            name: $this->cm->modname,
            feature: FEATURE_MOD_ARCHETYPE,
            default: MOD_ARCHETYPE_OTHER
        );
        return $archetype === MOD_ARCHETYPE_RESOURCE;
    }
}
