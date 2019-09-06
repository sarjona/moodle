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
 * Responsible for displaying the library list page
 *
 * @package    mod_hvp
 * @copyright  2016 Joubel AS <contact@joubel.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../config.php");
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/h5p/h5p_upload_form.php');

// No guest autologin.
require_login(0, false);
$PAGE->set_context(context_system::instance());
$pageurl = new moodle_url('/h5p/h5p_upload.php');
$PAGE->set_url($pageurl);
$PAGE->set_title('H5P upload');

// Create upload libraries form.
$uploadform = new \core_h5p\upload_form();
$h5pstorage = false;
if ($formdata = $uploadform->get_data()) {
    // Handle submitted valid form.
    $h5pstorage = \core_h5p\framework::instance('storage');
    $h5pstorage->savePackage(null, null, false);

}

if ($h5pstorage) {
    $h5p = new \core_h5p\view_assets($h5pstorage->contentId);
//    $h5p = new \core_h5p\h5p($h5pstorage->contentId);
	$content = $h5p->getcontent();
	$h5p->addassetstopage();
}

echo $OUTPUT->header();

\core_h5p\framework::print_messages('info', \core_h5p\framework::messages('info'));
\core_h5p\framework::print_messages('error', \core_h5p\framework::messages('error'));

if ($h5pstorage) {
	echo $h5p->outputview();
} else {
	$uploadform->display();
}

echo $OUTPUT->footer();
