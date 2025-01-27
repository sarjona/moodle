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
 * Optionally award a badge and redirect to the my badges page.
 *
 * @package    core_badges
 * @copyright  2019 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/badgeslib.php');

use core_badges\backpack;
use core_badges\local\backpack\ob\api_base;

require_login();

// Check if badges and the external backpack are enabled.
if (empty($CFG->badges_allowexternalbackpack) || empty($CFG->enablebadges)) {
    redirect($CFG->wwwroot);
}

// Check the user has a backpack.
$backpack = backpack::get_user_backpack();
if (empty($backpack)) {
    throw new coding_exception('This user has no backpack associated with their account.');
}

$hash = required_param('hash', PARAM_ALPHANUM);

$PAGE->set_url('/badges/backpack-add.php', ['hash' => $hash]);
$PAGE->set_context(context_user::instance($USER->id));
$output = $PAGE->get_renderer('core', 'badges');

// Check the assertion belongs to the current user.
$assertion = new core_badges_assertion($hash, $backpack->apiversion);
if ($assertion->get_userid() != $USER->id) {
    throw new coding_exception('This assertion does not belong to the current user.');
}

// Send the assertion to the backpack.
$api = api_base::create_from_externalbackpack($backpack);
$notify = $api->put_assertions($hash);

$redirecturl = new moodle_url('/badges/mybadges.php');
if (!empty($notify['status'])) {
    redirect($redirecturl, $notify['message'], null, $notify['status']);
}
redirect($redirecturl);
