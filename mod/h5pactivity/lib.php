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
 * Library of interface functions and constants.
 *
 * @package     mod_h5pactivity
 * @copyright   2020 Ferran Recio <ferran@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Checks if H5P activity supports a specific feature.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_SHOW_DESCRIPTION
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_MODEDIT_DEFAULT_COMPLETION
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_BACKUP_MOODLE2
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function h5pactivity_supports(string $feature): ?bool {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_MODEDIT_DEFAULT_COMPLETION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_h5pactivity into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param stdClass $data An object from the form.
 * @param mod_h5pactivity_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function h5pactivity_add_instance(stdClass $data, mod_h5pactivity_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $cmid = $data->coursemodule;

    $data->id = $DB->insert_record('h5pactivity', $data);

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $data->id, array('id' => $cmid));
    h5pactivity_set_mainfile($data);

    // Extra fields required in grade related functions.
    $data->cmid = $data->coursemodule;
    h5pactivity_grade_item_update($data);
    return $data->id;
}

/**
 * Updates an instance of the mod_h5pactivity in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param stdClass $data An object from the form in mod_form.php.
 * @param mod_h5pactivity_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function h5pactivity_update_instance(stdClass $data, mod_h5pactivity_mod_form $mform = null): bool {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    h5pactivity_set_mainfile($data);

    // Extra fields required in grade related functions.
    $data->cmid = $data->coursemodule;
    h5pactivity_grade_item_update($data);
    h5pactivity_update_grades($data);

    return $DB->update_record('h5pactivity', $data);
}

/**
 * Removes an instance of the mod_h5pactivity from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function h5pactivity_delete_instance(int $id): bool {
    global $DB;

    $activity = $DB->get_record('h5pactivity', array('id' => $id));
    if (!$activity) {
        return false;
    }

    $DB->delete_records('h5pactivity', array('id' => $id));

    h5pactivity_grade_item_delete($activity);

    return true;
}

/**
 * Is a given scale used by the instance of mod_h5pactivity?
 *
 * This function returns if a scale is being used by one mod_h5pactivity
 * if it has support for grading and scales.
 *
 * @param int $moduleinstanceid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by the given mod_h5pactivity instance.
 */
function h5pactivity_scale_used(int $moduleinstanceid, int $scaleid): bool {
    global $DB;

    if ($scaleid && $DB->record_exists('h5pactivity', array('id' => $moduleinstanceid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of mod_h5pactivity.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by any mod_h5pactivity instance.
 */
function h5pactivity_scale_used_anywhere(int $scaleid): bool {
    global $DB;

    if ($scaleid and $DB->record_exists('h5pactivity', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given mod_h5pactivity instance.
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param bool $reset Reset grades in the gradebook.
 * @return void.
 */
function h5pactivity_grade_item_update(stdClass $moduleinstance, bool $reset=false): void {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($moduleinstance->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    if (isset($moduleinstance->cmidnumber)) {
        $item['idnumber'] = $moduleinstance->cmidnumber;
    }

    if ($moduleinstance->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $moduleinstance->grade;
        $item['grademin']  = 0;
    } else if ($moduleinstance->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$moduleinstance->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($reset) {
        $item['reset'] = true;
    }
    grade_update('mod/h5pactivity', $moduleinstance->course, 'mod',
            'h5pactivity', $moduleinstance->id, 0, null, $item);
}

/**
 * Delete grade item for given mod_h5pactivity instance.
 *
 * @param stdClass $moduleinstance Instance object.
 * @return int Returns GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED
 */
function h5pactivity_grade_item_delete(stdClass $moduleinstance): ?int {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/h5pactivity', $moduleinstance->course, 'mod', 'h5pactivity',
            $moduleinstance->id, 0, null, array('deleted' => 1));
}

/**
 * Update mod_h5pactivity grades in the gradebook.
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 */
function h5pactivity_update_grades(stdClass $moduleinstance, int $userid = 0): void {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();
    grade_update('mod/h5pactivity', $moduleinstance->course, 'mod',
            'h5pactivity', $moduleinstance->id, 0, $grades);
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid Coude ID
 * @param string $type optional type (default '')
 */
function h5pactivity_reset_gradebook(int $courseid, string $type=''): void {
    global $DB;

    $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
              FROM {h5pactivity} a, {course_modules} cm, {modules} m
             WHERE m.name='h5pactivity' AND m.id=cm.module AND cm.instance=s.id AND s.course=?";

    if ($activities = $DB->get_records_sql($sql, array($courseid))) {
        foreach ($activities as $activity) {
            h5pactivity_grade_item_update($activity, true);
        }
    }
}

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}.
 *
 * @package     mod_h5pactivity
 * @category    files
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return string[] array of pair file area => human file area name
 */
function h5pactivity_get_file_areas(stdClass $course, stdClass $cm, stdClass $context): array {
    $areas = array();
    $areas['package'] = get_string('areapackage', 'mod_h5pactivity');
    return $areas;
}

/**
 * File browsing support for data module.
 *
 * @package     mod_h5pactivity
 * @category    files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info_stored|null file_info_stored instance or null if not found
 */
function h5pactivity_get_file_info(file_browser $browser, array $areas, stdClass $course,
            stdClass $cm, context $context, string $filearea, int $itemid,
            string $filepath, string $filename): ?file_info_stored {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
        return null;
    }

    // No writing for now!

    $fs = get_file_storage();

    if ($filearea === 'package') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_h5pactivity', 'package', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_h5pactivity', 'package', 0);
            } else {
                // Not found.
                return null;
            }
        }
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $areas[$filearea], false, true, false, false);
    }
    return null;
}

/**
 * Serves the files from the mod_h5pactivity file areas.
 *
 * @package     mod_h5pactivity
 * @category    files
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param stdClass $context The mod_h5pactivity's context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments (itemid, path).
 * @param bool $forcedownload Whether or not force download.
 * @param array $options Additional options affecting the file serving.
 */
function h5pactivity_pluginfile(stdClass $course, stdClass $cm, stdClass $context,
            string $filearea, array $args, bool $forcedownload, array $options = array()): void {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    $fullpath = '';

    if ($filearea === 'package') {
        $revision = (int)array_shift($args); // Prevents caching problems - ignored here.
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_h5pactivity/package/0/$relativepath";
    }
    if (!empty($fullpath)) {
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash(sha1($fullpath));
        if (!empty($file)) {
            // Finally send the file.
            send_stored_file($file, $lifetime, 0, false, $options);
        }
    }

    send_file_not_found();
}

/**
 * Saves draft files as the activity package.
 *
 * @param stdClass $data an object from the form
 */
function h5pactivity_set_mainfile(stdClass $data): void {
    $fs = get_file_storage();
    $cmid = $data->coursemodule;
    $context = context_module::instance($cmid);

    if (!empty($data->packagefile)) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_h5pactivity', 'package');
        file_save_draft_area_files($data->packagefile, $context->id, 'mod_h5pactivity', 'package',
            0, array('subdirs' => 0, 'maxfiles' => 1));
    }
}
