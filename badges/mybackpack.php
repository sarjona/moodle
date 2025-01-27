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
 * User backpack settings page.
 *
 * @package    core
 * @subpackage badges
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/badgeslib.php');

use core_badges\local\backpack\ob_factory;

require_login();

if (empty($CFG->enablebadges)) {
    throw new \moodle_exception('badgesdisabled', 'badges');
}

$context = context_user::instance($USER->id);
require_capability('moodle/badges:manageownbadges', $context);

$disconnect = optional_param('disconnect', false, PARAM_BOOL);

if (empty($CFG->badges_allowexternalbackpack)) {
    redirect($CFG->wwwroot);
}

$PAGE->set_url(new moodle_url('/badges/mybackpack.php'));
$PAGE->set_context($context);

$title = get_string('backpackdetails', 'badges');
$PAGE->set_title($title);
$PAGE->set_heading(fullname($USER));
$PAGE->set_pagelayout('standard');

$backpack = $DB->get_record('badge_backpack', array('userid' => $USER->id));
$badgescache = cache::make('core', 'externalbadges');

if ($disconnect && $backpack) {
    require_sesskey();

    $sitebackpack = badges_get_user_backpack();
    $remote = ob_factory::create_remote_from_externalbackpack($sitebackpack);
    $remote->disconnect_backpack();
    redirect(
        new moodle_url('/badges/mybackpack.php'),
        get_string('backpackdisconnected', 'badges'),
        null,
        \core\output\notification::NOTIFY_SUCCESS,
    );
}
$warning = '';
if ($backpack) {

    $sitebackpack = badges_get_user_backpack();

    $params['email'] = $backpack->email;
    $params['backpackweburl'] = $sitebackpack->backpackweburl;
    $params['selected'] = '';

    // If backpack is connected, need to select collections (if they are supported by the backpack API).
    $remote = ob_factory::create_remote_from_externalbackpack($sitebackpack);
    if (!method_exists($remote, 'get_collections') || !method_exists($remote, 'get_collection_record') ||
            !method_exists($remote, 'set_backpack_collections')) {
        // The backpack API does not support collections.
        $err = get_string('error:nogroupssummary', 'badges');
        $err .= get_string('error:nogroupslink', 'badges', $sitebackpack->backpackweburl);
        $params['nogroups'] = $err;

        $form = new \core_badges\form\collections(new moodle_url('/badges/mybackpack.php'), $params);
    } else {
        $request = $remote->get_collections();
        $groups = $request;
        if (isset($request->groups)) {
            $groups = $request->groups;
        }

        if (empty($groups)) {
            $err = get_string('error:nogroupssummary', 'badges');
            $err .= get_string('error:nogroupslink', 'badges', $sitebackpack->backpackweburl);
            $params['nogroups'] = $err;
        } else {
            $params['groups'] = $groups;
        }
        $params['selected'] = $remote->get_collection_record($backpack->id);
        $form = new \core_badges\form\collections(new moodle_url('/badges/mybackpack.php'), $params);

        if ($form->is_cancelled()) {
            redirect(new moodle_url('/badges/mybadges.php'));
        } else if ($data = $form->get_data()) {
            if (empty($data->group)) {
                redirect(new moodle_url('/badges/mybadges.php'));
            } else {
                $groups = array_filter($data->group);
            }
            $remote->set_backpack_collections($backpack->id, $groups);
            redirect(new moodle_url('/badges/mybadges.php'));
        }
    }
} else {
    // To pass through the current state of the verification attempt to the form.
    $params['email'] = get_user_preferences('badges_email_verify_address');
    $params['backpackpassword'] = get_user_preferences('badges_email_verify_password');
    $params['backpackid'] = get_user_preferences('badges_email_verify_backpackid');

    $form = new \core_badges\form\backpack(new moodle_url('/badges/mybackpack.php'), $params);
    $data = $form->get_submitted_data();
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/badges/mybadges.php'));
    } else if ($form->is_submitted() && $data = $form->get_data()) {
        if (!empty($data->externalbackpackid)) {
            $sitebackpack = badges_get_site_backpack($data->externalbackpackid);
            $remote = ob_factory::create_remote_from_externalbackpack($sitebackpack);
            $url = $remote->connect_backpack($data);
            if (!empty($url)) {
                // Redirect to the given URL to authenticate/verify the user with the backpack provider.
                redirect($url);
            }
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $warning;
$form->display();
echo $OUTPUT->footer();
