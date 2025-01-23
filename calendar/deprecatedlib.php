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
 * List of deprecated calendar functions.
 *
 * @package     core_calendar
 * @copyright   2025 Amaia Anabitarte <amaia@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the representation day.
 *
 * @param int $tstamp Timestamp in GMT
 * @param int|bool $now current Unix timestamp
 * @param bool $usecommonwords
 * @return string the formatted date/time
 *
 * @deprecated since Moodle 5.0.
 * @todo MDL-84268 Final deprecation in Moodle 6.0.
 */
#[\core\attribute\deprecated(
    replacement: '\core_calendar\output\humandate',
    since: '5.0',
    mdl: 'MDL-83873',
)]
function calendar_day_representation($tstamp, $now = false, $usecommonwords = true) {
    static $shortformat;

    \core\deprecation::emit_deprecation_if_present(__FUNCTION__);

    if (empty($shortformat)) {
        $shortformat = get_string('strftimedayshort');
    }

    if ($now === false) {
        $now = time();
    }

    // To have it in one place, if a change is needed.
    $formal = userdate($tstamp, $shortformat);

    $datestamp = usergetdate($tstamp);
    $datenow = usergetdate($now);

    if ($usecommonwords == false) {
        // We don't want words, just a date.
        return $formal;
    } else if ($datestamp['year'] == $datenow['year'] && $datestamp['yday'] == $datenow['yday']) {
        return get_string('today', 'calendar');
    } else if (($datestamp['year'] == $datenow['year'] && $datestamp['yday'] == $datenow['yday'] - 1 ) ||
            ($datestamp['year'] == $datenow['year'] - 1 && $datestamp['mday'] == 31 && $datestamp['mon'] == 12
                    && $datenow['yday'] == 1)) {
        return get_string('yesterday', 'calendar');
    } else if (($datestamp['year'] == $datenow['year'] && $datestamp['yday'] == $datenow['yday'] + 1 ) ||
            ($datestamp['year'] == $datenow['year'] + 1 && $datenow['mday'] == 31 && $datenow['mon'] == 12
                    && $datestamp['yday'] == 1)) {
        return get_string('tomorrow', 'calendar');
    } else {
        return $formal;
    }
}

/**
 * return the formatted representation time.
 *
 * @param int $time the timestamp in UTC, as obtained from the database
 * @return string the formatted date/time
 *
 * @deprecated since Moodle 5.0.
 * @todo MDL-84268 Final deprecation in Moodle 6.0.
 */
#[\core\attribute\deprecated(
    replacement: '\core_calendar\output\humandate',
    since: '5.0',
    mdl: 'MDL-83873',
)]
function calendar_time_representation($time) {
    static $langtimeformat = null;

    \core\deprecation::emit_deprecation_if_present(__FUNCTION__);

    if ($langtimeformat === null) {
        $langtimeformat = get_string('strftimetime');
    }

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

    return userdate($time, empty($timeformat) ? $langtimeformat : $timeformat);
}

/**
 * Get event format time.
 *
 * @param calendar_event $event event object
 * @param int $now current time in gmt
 * @param array $linkparams list of params for event link
 * @param bool $usecommonwords the words as formatted date/time.
 * @param int $showtime determine the show time GMT timestamp
 * @return string $eventtime link/string for event time
 *
 * @deprecated since Moodle 5.0.
 * @todo MDL-84268 Final deprecation in Moodle 6.0.
 */
#[\core\attribute\deprecated(
    replacement: '\core_calendar\output\humantimeperiod',
    since: '5.0',
    mdl: 'MDL-83873',
)]
function calendar_format_event_time($event, $now, $linkparams = null, $usecommonwords = true, $showtime = 0) {
    \core\deprecation::emit_deprecation_if_present(__FUNCTION__);

    $starttime = $event->timestart;
    $endtime = $event->timestart + $event->timeduration;

    if (empty($linkparams) || !is_array($linkparams)) {
        $linkparams = [];
    }

    $linkparams['view'] = 'day';

    // OK, now to get a meaningful display.
    // Check if there is a duration for this event.
    if ($event->timeduration) {
        // Get the midnight of the day the event will start.
        $usermidnightstart = usergetmidnight($starttime);
        // Get the midnight of the day the event will end.
        $usermidnightend = usergetmidnight($endtime);
        // Check if we will still be on the same day.
        if ($usermidnightstart == $usermidnightend) {
            // Check if we are running all day.
            if ($event->timeduration == DAYSECS) {
                $time = get_string('allday', 'calendar');
            } else { // Specify the time we will be running this from.
                $datestart = calendar_time_representation($starttime);
                $dateend = calendar_time_representation($endtime);
                $time = $datestart . ' <strong>&raquo;</strong> ' . $dateend;
            }

            // Set printable representation.
            if (!$showtime) {
                $day = calendar_day_representation($event->timestart, $now, $usecommonwords);
                $url = calendar_get_link_href(new \moodle_url(CALENDAR_URL . 'view.php', $linkparams), 0, 0, 0, $endtime);
                $eventtime = \html_writer::link($url, $day) . ', ' . $time;
            } else {
                $eventtime = $time;
            }
        } else { // It must spans two or more days.
            $daystart = calendar_day_representation($event->timestart, $now, $usecommonwords) . ', ';
            if ($showtime == $usermidnightstart) {
                $daystart = '';
            }
            $timestart = calendar_time_representation($event->timestart);
            $dayend = calendar_day_representation($event->timestart + $event->timeduration, $now, $usecommonwords) . ', ';
            if ($showtime == $usermidnightend) {
                $dayend = '';
            }
            $timeend = calendar_time_representation($event->timestart + $event->timeduration);

            // Set printable representation.
            if ($now >= $usermidnightstart && $now < strtotime('+1 day', $usermidnightstart)) {
                $url = calendar_get_link_href(new \moodle_url(CALENDAR_URL . 'view.php', $linkparams), 0, 0, 0, $endtime);
                $eventtime = $timestart . ' <strong>&raquo;</strong> ' . \html_writer::link($url, $dayend) . $timeend;
            } else {
                // The event is in the future, print start and end links.
                $url = calendar_get_link_href(new \moodle_url(CALENDAR_URL . 'view.php', $linkparams), 0, 0, 0, $starttime);
                $eventtime = \html_writer::link($url, $daystart) . $timestart . ' <strong>&raquo;</strong> ';

                $url = calendar_get_link_href(new \moodle_url(CALENDAR_URL . 'view.php', $linkparams),  0, 0, 0, $endtime);
                $eventtime .= \html_writer::link($url, $dayend) . $timeend;
            }
        }
    } else { // There is no time duration.
        $time = calendar_time_representation($event->timestart);
        // Set printable representation.
        if (!$showtime) {
            $day = calendar_day_representation($event->timestart, $now, $usecommonwords);
            $url = calendar_get_link_href(new \moodle_url(CALENDAR_URL . 'view.php', $linkparams),  0, 0, 0, $starttime);
            $eventtime = \html_writer::link($url, $day) . ', ' . trim($time);
        } else {
            $eventtime = $time;
        }
    }

    // Check if It has expired.
    if ($event->timestart + $event->timeduration < $now) {
        $eventtime = '<span class="dimmed_text">' . str_replace(' href=', ' class="dimmed" href=', $eventtime) . '</span>';
    }

    return $eventtime;
}
