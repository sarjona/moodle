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
 * Generic content bank visualizer.
 *
 * @package   core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/contentbank:view', $context);

$id = required_param('id', PARAM_INT);
$deletecontent = optional_param('deletecontent', null, PARAM_INT);

$PAGE->requires->js_call_amd('core_contentbank/actions', 'init');

$content = $DB->get_record('contentbank_content', ['id' => $id], '*', MUST_EXIST);

$returnurl = new \moodle_url('/contentbank/index.php');
$plugin = core_plugin_manager::instance()->get_plugin_info($content->contenttype);
if (!$plugin || !$plugin->is_enabled()) {
    print_error('unsupported', 'core_contentbank', $returnurl);
}

$PAGE->set_url(new \moodle_url('/contentbank/view.php', ['id' => $id]));
$PAGE->set_context($context);

// Make the content bank node active so that it shows up in the navbar and breadcrumbs correctly.
if ($node = $PAGE->navigation->find('contentbank', null)) {
    $node->make_active();
    $PAGE->navbar->add($content->name);
}

$managerclass = "\\$content->contenttype\\plugin";
if (class_exists($managerclass)) {
    $manager = new $managerclass($content);
}

$title = get_string('contentbank');
$PAGE->set_heading($title);
$title .= ": ".$content->name;
$PAGE->set_title($title);
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('contentbank');

if ($manager && $manager->can_delete()) {
    // Create the cog menu with all the secondary actions, such as delete, rename...
    $actionmenu = new action_menu();
    $actionmenu->set_alignment(action_menu::TR, action_menu::BR);
    // Add the delete content item to the menu.
    $attributes = [
                'data-action' => 'deletecontent',
                'data-contentname' => $content->name,
                'data-contentid' => $content->id,
            ];
    $actionmenu->add_secondary_action(new action_menu_link(
        new moodle_url('#'),
        new pix_icon('t/delete', get_string('delete')),
        get_string('delete'),
        false,
        $attributes
    ));

    // Add the cog menu to the header.
    $PAGE->add_header_action(html_writer::div(
        $OUTPUT->render($actionmenu),
        'd-print-none',
        ['id' => 'region-main-settings-menu']
    ));
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');

if ($manager) {
    echo $manager->get_view_content();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
