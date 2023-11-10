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
 * Display a course section.
 *
 * @package     core_course
 * @copyright   2023 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

redirect_if_major_upgrade_required();

$sectionid = required_param('id', PARAM_INT);
$section = $DB->get_record('course_sections', ['id' => $sectionid], '*', MUST_EXIST);

// Defined here to avoid notices on errors.
$PAGE->set_url('/course/section.php', ['id' => $sectionid]);

if ($section->course == SITEID) {
    // This course is not a real course.
    redirect($CFG->wwwroot .'/?redirect=0');
}

$course = get_course($section->course);
// Fix course format if it is no longer installed.
$format = course_get_format($course);
$course->format = $format->get_format();

// When the course format doesn't support sections, redirect to course page.
if (!course_format_uses_sections($course->format)) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Prevent caching of this page to stop confusion when changing page after making AJAX changes.
$PAGE->set_cacheable(false);

context_helper::preload_course($course->id);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);

// Must set layout before getting section info. See MDL-47555.
$PAGE->set_pagelayout('course');
$PAGE->add_body_class('limitedwidth');

// Get section details and check it exists.
$modinfo = get_fast_modinfo($course);
$coursesections = $modinfo->get_section_info($section->section, MUST_EXIST);

// Check user is allowed to see it.
if (!$coursesections->uservisible) {
    // Check if coursesection has conditions affecting availability and if
    // so, output availability info.
    if ($coursesections->visible && $coursesections->availableinfo) {
        $sectionname = get_section_name($course, $coursesections);
        $message = get_string('notavailablecourse', '', $sectionname);
        redirect(course_get_url($course), $message, null, \core\output\notification::NOTIFY_ERROR);
    } else {
        // Note: We actually already know they don't have this capability
        // or uservisible would have been true; this is just to get the
        // correct error message shown.
        require_capability('moodle/course:viewhiddensections', $context);
    }
}

$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->set_other_editing_capability('moodle/course:update');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_other_editing_capability('moodle/course:activityvisibility');
if (course_format_uses_sections($course->format)) {
    $PAGE->set_other_editing_capability('moodle/course:sectionvisibility');
    $PAGE->set_other_editing_capability('moodle/course:movesections');
}

$renderer = $PAGE->get_renderer('format_' . $course->format);

// Make the title more specific.
$editingtitle = '';
if ($PAGE->user_is_editing()) {
    // Append this to the page title's lang string to get its equivalent when editing mode is turned on.
    $editingtitle = 'editing';
}
if (course_format_uses_sections($course->format)) {
    $sectionname = get_string('sectionname', "format_$course->format");
    $sectiontitle = get_section_name($course, $section);
    $PAGE->set_title(
        get_string(
            'coursesectiontitle' . $editingtitle,
            'moodle',
            ['course' => $course->fullname, 'sectiontitle' => $sectiontitle, 'sectionname' => $sectionname]
        )
    );
} else {
    $PAGE->set_title(get_string('coursetitle' . $editingtitle, 'moodle', ['course' => $course->fullname]));
}

// Add bulk editing control.
$bulkbutton = $renderer->bulk_editing_button($format);
if (!empty($bulkbutton)) {
    $PAGE->add_header_action($bulkbutton);
}

$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

// Show communication room status notification.
if (core_communication\api::is_available() && has_capability('moodle/course:update', $context)) {
    $communication = \core_communication\api::load_by_instance(
        $context,
        'core_course',
        'coursecommunication',
        $course->id
    );
    $communication->show_communication_room_status_notification();
}

// Display a warning if asynchronous backups are pending for this course.
if ($PAGE->user_is_editing()) {
    require_once($CFG->dirroot . '/backup/util/helper/async_helper.class.php');
    if (async_helper::is_async_pending($course->id, 'course', 'backup')) {
        echo $OUTPUT->notification(get_string('pendingasyncedit', 'backup'), 'warning');
    }
}

echo html_writer::start_tag('div', ['class' => 'course-content']);

$format->set_section_number($section->section);
$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);
echo $renderer->render($widget);

// TODO: Create a method in the course format.
// Include course format js module.
$PAGE->requires->js('/course/format/' . $course->format . '/format.js');

echo html_writer::end_tag('div');

// Trigger course viewed event.
course_view($context, $section->section);

// Determine whether the user has permission to download course content.
$candownloadcourse = \core\content::can_export_context($context, $USER);
if ($candownloadcourse) {
    // If available, include the JS to prepare the download course content modal.
    $PAGE->requires->js_call_amd('core_course/downloadcontent', 'init');
}

// Load the view JS module if completion tracking is enabled for this course.
$completion = new completion_info($course);
if ($completion->is_enabled()) {
    $PAGE->requires->js_call_amd('core_course/view', 'init');
}

echo $OUTPUT->footer();
