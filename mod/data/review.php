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
 * This file is part of the Database module for Moodle
 *
 * @copyright 2025 Justus Dieckmann
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_data
 */

use mod_data\local\persistent\record_review;
use mod_data\manager;

require_once("../../config.php");

$id = required_param('id', PARAM_INT);

$record = $DB->get_record('data_records', ['id' => $id], '*', MUST_EXIST);
$datainstance = $DB->get_record('data', ['id' => $record->dataid], '*', MUST_EXIST);

$review = \mod_data\local\persistent\record_review::get_record(['recordid' => $record->id, 'revieweruserid' => $USER->id]);
if (!$review) {
    $review = new record_review(0, (object) ['recordid' => $record->id, 'revieweruserid' => $USER->id]);
}
list($course, $cm) = get_course_and_cm_from_instance($datainstance->id, 'data');

require_login($course, true, $cm);
$PAGE->set_context(context_module::instance($cm->id));
$PAGE->set_url('/mod/data/review.php', ['id' => $record->id]);

require_capability('mod/data:reviewentry', $PAGE->context);

$form = new \mod_data\form\review_form($PAGE->url, ['persistent' => $review]);
$returnurl = new moodle_url('/mod/data/reviewview.php', ['d' => $datainstance->id]);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $form->get_data()) {
    $review->from_record($formdata);
    $review->save();
    redirect($returnurl);
}

echo $OUTPUT->header();

$manager = manager::create_from_data_record($record);
$options = [
    'page' => null,
    'baseurl' => null,
];
$parser = $manager->get_template('singletemplate', $options);
echo $OUTPUT->box_start('bg-gray m-3 p-3');
echo $parser->parse_entries([$record]);
echo $OUTPUT->box_end();

$form->display();

echo $OUTPUT->footer();
