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
 * Render H5P content from an H5P id.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');

$clean = optional_param('clean', 0, PARAM_INT);
// TODO: Check if the user has access to the H5P content.
require_login(null, false);

// Set up the H5P player class.
$manager = new \core_h5p\manager();

// Configure page.
$context = context_system::instance();
// TODO: update the correct context.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url ('/h5p/url_processor.php'));
// TODO: set the title and the heading. They should be added to the h5p lang or taken from the metadata.
$PAGE->set_title('process h5p');
$PAGE->set_heading('h5p processing');

$PAGE->set_pagelayout('standard');

// Print page HTML.
echo $OUTPUT->header();

// Upload a h5p file in a course resource,
// Save the resource and copy paste it here.
$url = 'http://nucky.fritz.box/moodle_sara/pluginfile.php/27/mod_resource/content/1/agamotto.h5p';

if ($clean) {
	$manager->clean_db();
}

echo $manager->get_h5p_id($url);

$errors = array_map(function ($message) {
    return $message->message;
}, \core_h5p\framework::messages('error'));

$messages = array_merge(\core_h5p\framework::messages('info'), $errors);

if (count($messages) > 0) {
	echo print_r($messages, true);
}
echo $OUTPUT->footer();
