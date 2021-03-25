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

namespace core_course;

use context_course;
use core\event\course_module_updated;
use core_course\stateupdates;
use context_module;
use stdClass;
use course_modinfo;

/**
 * Contains the core course state actions.
 *
 * The methods from this class should be executed via "core_course_edit" web service.
 *
 * Each format plugin could extend this class to provide new actions to the editor.
 * Extended classes should be locate in "format_XXX\course" namespace and
 * extends core_course\stateactions.
 *
 * @package    core_course
 * @copyright  2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateactions {

    /**
     * Hide a section.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id (not used in hide action)
     * @param int $targetcmid optional target cm id (not used in hide action)
     */
    public function section_hide(stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {

        $this->set_section_visibility(0, $updates, $course, $ids, $targetsectionid, $targetcmid);
    }

    /**
     * Show a section.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id (not used in show action)
     * @param int $targetcmid optional target cm id (not used in show action)
     */
    public function section_show(stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {

        $this->set_section_visibility(1, $updates, $course, $ids, $targetsectionid, $targetcmid);
    }

    /**
     * Set section visibility.
     *
     * @param int $visible Whether the section will be set to visible or not.
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected section ids
     * @param int $targetsectionid optional target section id (not used in show action)
     * @param int $targetcmid optional target cm id (not used in show action)
     */
    protected function set_section_visibility(int $visible, stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {
        global $DB;

        $validationresult = $this->validate_sections($course, $ids);
        if (!empty($validationresult)) {
            $action = debug_backtrace()[1]['function'];
            throw new \moodle_exception($validationresult, 'core', null, $action);
        }

        $modinfo = course_modinfo::instance($course);
        $coursecontext = context_course::instance($course->id);
        // Check permission.
        require_capability('moodle/course:sectionvisibility', $coursecontext);
        foreach ($ids as $sectionnum) {
            $sectioninfo = $modinfo->get_section_info($sectionnum);
            if ($visible == (bool)$sectioninfo->visible) {
                // Only set visibility, trigger event and add this change to update state if it's different; otherwise, ignore it.
                continue;
            }

            // Set visibility to the section and trigger an event for course section update (done in course_update_section).
            $data = [
                'id' => $sectioninfo->id,
                'visible' => $visible,
                'timemodified' => time(),
            ];
            $DB->update_record('course_sections', $data);
            $sectioninfo->visible = $visible;
            course_get_format($course->id)->update_section_format_options($data);

            // Trigger an event for course section update.
            $event = \core\event\course_section_updated::create([
                'objectid' => $sectioninfo->id,
                'courseid' => $course->id,
                'context' => $coursecontext,
                'other' => ['sectionnum' => $sectioninfo->section],
            ]);
            $event->trigger();

            // Add this action to updates array.
            $updates->add_section_update($sectionnum);

            // If section visibility has changed, hide the modules in this section too.
            if (!empty($sectioninfo->sequence)) {
                $modules = explode(',', $sectioninfo->sequence);
                foreach ($modules as $moduleid) {
                    if ($cm = get_coursemodule_from_id(null, $moduleid, $course->id)) {
                        if ($visible) {
                            // As we unhide the section, we use the previously saved visibility stored in visibleold.
                            $this->set_cm_visibility($cm->visibleold, $updates, $course, [$moduleid]);
                        } else {
                            // We hide the section, so we hide the module but we store the original state in visibleold.
                            $this->set_cm_visibility(0, $updates, $course, [$moduleid]);
                            $DB->set_field('course_modules', 'visibleold', $cm->visible, ['id' => $moduleid]);
                        }
                    }
                }
            }
        }

        rebuild_course_cache($course->id, true);
    }

    /**
     * Hide a course module.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id (not used in hide action)
     * @param int $targetcmid optional target cm id (not used in hide action)
     */
    public function cm_hide(stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {

        $this->set_cm_visibility(0, $updates, $course, $ids, $targetsectionid, $targetcmid);
    }

    /**
     * Show a course module.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id (not used in show action)
     * @param int $targetcmid optional target cm id (not used in show action)
     */
    public function cm_show(stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {

        $this->set_cm_visibility(1, $updates, $course, $ids, $targetsectionid, $targetcmid);
    }

    /**
     * Set course module visibility.
     *
     * @param int $visible Whether the course module will be set to visible or not.
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids the list of affected course module ids
     * @param int $targetsectionid optional target section id (not used in this action)
     * @param int $targetcmid optional target cm id (not used in this action)
     */
    protected function set_cm_visibility(int $visible, stateupdates $updates, stdClass $course, array $ids,
            ?int $targetsectionid = null, ?int $targetcmid = null): void {

        $validationresult = $this->validate_cms($course, $ids);
        if (!empty($validationresult)) {
            $action = debug_backtrace()[1]['function'];
            throw new \moodle_exception($validationresult, 'core', null, $action);
        }

        $modinfo = course_modinfo::instance($course);

        foreach ($ids as $cmid) {
            // Check permission.
            $modcontext = context_module::instance($cmid);
            require_capability('moodle/course:activityvisibility', $modcontext);

            $cm = $modinfo->get_cm($cmid);
            if ($cm->visible == $visible) {
                // Only set visibility, trigger event and add this change to update state if it's different; otherwise, ignore it.
                continue;
            }

            // Set visibility.
            set_coursemodule_visible($cmid, $visible);

            // Trigger an event for course module update.
            course_module_updated::create_from_cm($cm, $modcontext)->trigger();

            // Add this action to updates array.
            $updates->add_cm_update($cmid);
        }
    }

    /**
     * Checks related to sections: course format support them, all given sections exist and topic 0 is not included.
     *
     * @param stdClass $course The course where given $sectionnums belong.
     * @param array $sectionnums List of sections to validate.
     * @return string A string containing the reason for not accepting sections as valid; null if they all are considered valid.
     */
    protected function validate_sections(stdClass $course, array $sectionnums): ?string {
        global $DB;

        // No section actions are allowed if course format does not support sections.
        $courseformat = course_get_format($course->id);
        if (!$courseformat->uses_sections()) {
            return 'sectionactionnotsupported';
        }

        // No section actions are allowed on the 0-section by default (overwrite in course format if needed).
        if (in_array(0, $sectionnums)) {
            return 'sectionactionnotsupportedforzerosection';
        }

        // Check if all the given sections exist.
        list($insql, $inparams) = $DB->get_in_or_equal($sectionnums, SQL_PARAMS_NAMED);
        $sql = "SELECT cs.id
                  FROM {course_sections} cs
                 WHERE cs.section {$insql}";
        $sections = $DB->get_records_sql($sql, $inparams);
        if (count($sections) != count($sectionnums)) {
            return 'unexistingsectionnumber';
        }

        return null;
    }

    /**
     * Checks related to course modules: all given cm exist.
     *
     * @param stdClass $course The course where given $cmids belong.
     * @param array $cmids List of course module ids to validate.
     * @return string A string containing the reasons for not accepting cmids as valid; null if they are considered valid.
     */
    protected function validate_cms(stdClass $course, array $cmids): ?string {

        $moduleinfo = get_fast_modinfo($course->id);
        $cms = $moduleinfo->get_cms();
        $intersect = array_intersect($cmids, array_keys($moduleinfo->get_cms()));
        if (count($cmids) != count($intersect)) {
            return 'unexistingcmid';
        }

        return null;
    }
}
