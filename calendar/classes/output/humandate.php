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

namespace core_calendar\output;

use core\output\pix_icon;
use core\output\templatable;
use core\output\renderable;
use core\output\renderer_base;
use core\url;

/**
 * Class humandate.
 *
 * This class is used to render a timestamp as a human readable date.
 * The main difference between userdate and this class is that this class
 * will render the date as "Today", "Yesterday", "Tomorrow" if the date is
 * close to the current date. Also, it will add alert styling if the date
 * is near.
 *
 * @package    core_calendar
 * @copyright  2024 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class humandate implements renderable, templatable {
    /** @var int $now the current timestamp. */
    protected int $now;

    /**
     * Class constructor.
     *
     * @param int $timestamp The timestamp.
     * @param int|null $near The number of seconds that indicates a nearby date. Default to DAYSECS, use null for no indication.
     * @param bool $timeonly Wether the date should be shown completely or time only.
     * @param url|null $link URL to link the date to.
     * @param string|null $langtimeformat Lang date and time format to use to format the date.
     * @param bool $userelatives Whether to use human common words or not.
     */
    public function __construct(
        /** @var int $timestamp the timestamp. */
        protected int $timestamp,
        /** @var int|null $near the number of seconds within which a date is considered near. 1 day by default. */
        protected int|null $near = DAYSECS,
        /** @var bool $timeonly whether we should show time only or date and time. */
        protected bool $timeonly = false,
        /** @var url|null $link Link for the date. */
        protected url|null $link = null,
        /** @var string|null $langtimeformat an optional date format to apply.  */
        protected string|null $langtimeformat = null,
        /** @var bool $userelatives whether to use human relative terminology. */
        protected bool $userelatives = true,
    ) {
        $this->now = time();
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $userdate = userdate($this->timestamp, get_string('strftimedayshort'));
        $due = $this->timestamp - $this->now;
        $relative = null;
        if ($this->userelatives) {
            $relative = $this->format_relative_date();
        }

        if ($this->timeonly) {
            $date = null;
        } else {
            $date = $relative ?? $userdate;
        }
        $data = [
            'timestamp' => $this->timestamp,
            'userdate' => $userdate,
            'date' => $date,
            'time' => $this->format_time(),
            'ispast' => $this->timestamp < $this->now,
            'needtitle' => ($relative !== null || $this->timeonly),
            'link' => $this->link ? $this->link->out(false) : '',
        ];
        if (($this->near !== null) && ($due < $this->near && $due > 0)) {
            $icon = new pix_icon(
                pix: 'i/warning',
                alt: get_string('warning'),
                component: 'moodle',
                attributes: ['class' => 'me-0 pb-1']
            );
            $data['isnear'] = true;
            $data['nearicon'] = $icon->export_for_template($output);
        }
        return $data;
    }

    /**
     * Formats the timestamp as a relative date string (e.g., "Today", "Yesterday", "Tomorrow").
     *
     * This method compares the given timestamp with the current date and returns a formatted
     * string representing the relative date. If the timestamp corresponds to today, yesterday,
     * or tomorrow, it returns the appropriate string. Otherwise, it returns null.
     *
     * @return string|null
     */
    private function format_relative_date(): string|null {
        $this->now = time();

        if (date('Y-m-d', $this->timestamp) == date('Y-m-d', $this->now)) {
            $format = get_string('strftimerelativetoday', 'langconfig');
        } else if (date('Y-m-d', $this->timestamp) == date('Y-m-d', strtotime('yesterday', $this->now))) {
            $format = get_string('strftimerelativeyesterday', 'langconfig');
        } else if (date('Y-m-d', $this->timestamp) == date('Y-m-d', strtotime('tomorrow', $this->now))) {
            $format = get_string('strftimerelativetomorrow', 'langconfig');
        } else {
            return null;
        }

        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        return $calendartype->timestamp_to_date_string(
            time: $this->timestamp,
            format: $format,
            timezone: 99,
            fixday: true,
            fixhour: true,
        );
    }

    /**
     * Formats the timestamp as a human readable time.
     *
     * This method compares the given timestamp with the current date and returns a formatted
     * string representing the time.
     *
     * @return string
     */
    private function format_time(): string {

        $timeformat = get_user_preferences('calendar_timeformat');
        if (empty($timeformat)) {
            $timeformat = get_config(null, 'calendar_site_timeformat');
        }

        // Allow language customization of selected time format.
        if ($timeformat === CALENDAR_TF_12) {
            $timeformat = get_string('strftimetime12', 'langconfig');
        } else if ($timeformat === CALENDAR_TF_24) {
            $timeformat = get_string('strftimetime24', 'langconfig');
        }

        if ($timeformat) {
            return userdate($this->timestamp, $timeformat);
        }

        // Let's use default format.
        if ($this->langtimeformat === null) {
            $langtimeformat = get_string('strftimetime');
        }

        return userdate($this->timestamp, $langtimeformat);
    }
}
