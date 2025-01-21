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
 * BadgeClass JSON.
 *
 * @package    core_badges
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true); // No need for a session here.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');

$badgeid = required_param('id', PARAM_INT);
$obversion = optional_param('obversion', badges_open_badges_backpack_api(), PARAM_FLOAT);

if (!\core_badges\backpack_factory::badge_available($badgeid)) {
    header("HTTP/1.0 410 Gone");
    throw new \moodle_exception(get_string('error:relatedbadgedoesntexist', 'badges'));
}

$badge = \core_badges\backpack_factory::create_badge_exporter_from_id($badgeid, $obversion);
echo $OUTPUT->header();
echo $badge->get_json();
