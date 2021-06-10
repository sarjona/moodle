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
 * Open the editor to modify an H5P content from a given H5P URL.
 *
 * @package    core_h5p
 * @copyright  2021 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once("$CFG->libdir/formslib.php");
require_once("$CFG->libdir/filestorage/file_storage.php");

require_login(null, false);

$contenturl = required_param('url', PARAM_LOCALURL);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);

// If no returnurl is defined, use local_referer.
if (empty($returnurl)) {
    $returnurl = get_local_referer(false);
    if (empty($returnurl)) {
        // If local referer is empty, returnurl will be set to default site page.
        $returnurl = new \moodle_url('/');
    }
}

$context = \context_system::instance();
if (!empty($contenturl)) {
    list($file, $h5p) = \core_h5p\api::get_original_content_from_pluginfile_url($contenturl);
    if ($file) {
        // Check if the user can edit the content behind the given URL.
        if (\core_h5p\api::can_edit_content($file)) {
            if (!$h5p) {
                // This H5P file hasn't been deployed yet, so it should be saved to create the entry into the H5P DB.
                \core_h5p\local\library\autoloader::register();
                $factory = new \core_h5p\factory();
                $config = new \stdClass();
                $onlyupdatelibs = !\core_h5p\helper::can_update_library($file);
                $contentid = \core_h5p\helper::save_h5p($factory, $file, $config, $onlyupdatelibs, false);
            } else {
                // The H5P content exists. Update the contentid value.
                list($context, $course, $cm) = get_context_info_array($file->get_contextid());
                if ($course) {
                    $context = \context_course::instance($course->id);
                }
                $contentid = $h5p->id;
            }
        }
    }
}

if (empty($contentid)) {
    throw new \moodle_exception('error:emptycontentid', 'core_h5p', $returnurl);
}

$pagetitle = get_string('h5peditor', 'core_h5p');
$url = new \moodle_url("/h5p/edit.php");

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

$values = [
    'id' => $contentid,
    'contenturl' => $contenturl,
    'returnurl' => $returnurl,
];

$form = new \core_h5p\form\editcontent_form(null, $values);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $form->save_h5p($data);
    if (!empty($returnurl)) {
        redirect($returnurl);
    }
}

echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();
