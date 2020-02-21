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

$title = get_string('contentbank');
$PAGE->set_heading($title);
$title .= ": ".$content->name;
$PAGE->set_title($title);
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('contentbank');

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');

$managerclass = "\\$content->contenttype\\plugin";
if (class_exists($managerclass)) {
    $manager = new $managerclass($content);
    echo $manager->get_view_content();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
