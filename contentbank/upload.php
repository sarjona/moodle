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
 * Upload a file to content bank.
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once("$CFG->dirroot/contentbank/files_form.php");

require_login();

$context = \context_system::instance();
require_capability('moodle/contentbank:upload', $context);

$returnurl = new \moodle_url('/contentbank/index.php');

$PAGE->set_url('/contentbank/upload.php');
$PAGE->set_context($context);
// Make the content bank node active so that it shows up in the navbar and breadcrumbs correctly.
if ($node = $PAGE->navigation->find('contentbank', null)) {
    $node->make_active();
    $PAGE->navbar->add(get_string('upload', 'contentbank'));
}

$title = get_string('contentbank');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('contentbank');

$maxbytes = $CFG->userquota;
$maxareabytes = $CFG->userquota;
if (has_capability('moodle/user:ignoreuserquota', $context)) {
    $maxbytes = USER_CAN_IGNORE_FILE_SIZE_LIMITS;
    $maxareabytes = FILE_AREA_MAX_BYTES_UNLIMITED;
}

$extensionmanager = core_contentbank\extensions::instance();
$accepted = $extensionmanager->get_supported_extensions_as_string();

$data = new stdClass();
$options = array(
    'subdirs' => 1,
    'maxbytes' => $maxbytes,
    'maxfiles' => -1,
    'accepted_types' => $accepted,
    'areamaxbytes' => $maxareabytes
);
file_prepare_standard_filemanager($data, 'files', $options, $context, 'contentbank', 'public', 0);

$mform = new contentbank_files_form(null, array('data' => $data, 'options' => $options));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform->get_data()) {
    require_sesskey();

    // Get the file and the plugin to manage given file's extension.
    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $formdata->file, 'itemid, filepath, filename', false);

    if (!empty($files)) {
        $file = reset($files);
        $filename = $file->get_filename();
        $plugin = $extensionmanager->get_extension_supporter($extensionmanager->get_extension($filename));
        $content = new stdClass();
        $content->name = $filename;
        $manager = $plugin::create_content($content);
        file_save_draft_area_files($formdata->file, $context->id, 'contentbank', 'public', $manager->get_id());
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');

$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
