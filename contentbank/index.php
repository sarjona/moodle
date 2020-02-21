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
 * Manage content in content bank.
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/contentbank:view', $context);

$title = get_string('contentbank');
$PAGE->set_url('/contentbank/index.php');
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('contentbank');

// Get all contents managed by active plugins to render.
$foldercontents = array();
$contents = $DB->get_records('contentbank_content');
foreach ($contents as $content) {
    $plugin = core_plugin_manager::instance()->get_plugin_info($content->contenttype);
    if (!$plugin || !$plugin->is_enabled()) {
        continue;
    }
    $managerclass = "\\$content->contenttype\\plugin";
    if (class_exists($managerclass)) {
        $manager = new $managerclass($content);
        $foldercontents[] = $manager;
    }
}

// Get the toolbar ready.
$toolbar = array ();
if (has_capability('moodle/contentbank:upload', $context)) {
    // Don' show upload button if there's no plugin to support any file extension.
    $extensionmanager = core_contentbank\extensions::instance();
    $accepted = $extensionmanager->get_supported_extensions_as_string();
    if (!empty($accepted)) {
        $importurl = new moodle_url('/contentbank/upload.php');
        $toolbar[] = array('name' => 'Upload', 'link' => $importurl, 'icon' => 'i/upload');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');

$folder = new \core_contentbank\output\bankcontent($foldercontents, $toolbar);
echo $OUTPUT->render($folder);

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
