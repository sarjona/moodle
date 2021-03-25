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

use coding_exception;
use core_course\course_format;
use renderer_base;
use stdClass;
use course_modinfo;
use JsonSerializable;

/**
 * Class to track state actions.
 *
 * The methods from this class should be executed via "stateactions" methods.
 *
 * Each format plugin could extend this class to provide new updates to the frontend
 * mutation module.
 * Extended classes should be locate in "format_XXX\course" namespace and
 * extends core_course\stateupdates.
 *
 * @package    core_course
 * @copyright  2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateupdates implements JsonSerializable {

    /** @var course_format format the course format */
    protected $format;

    /** @var renderer_base renderer format renderer */
    protected $output;

    /** @var array the tracked updates */
    protected $updates;

    /**
     * State update class constructor.
     *
     * @param course_format $format Course format.
     * @param renderer_base $output Renderer. When it is null, course format renderer will be used.
     */
    public function __construct(course_format $format, renderer_base $output = null) {
        global $PAGE;

        $this->format = $format;
        if (!$output) {
            $this->output = $this->format->get_renderer($PAGE);
        } else {
            $this->output = $output;
        }
        $this->updates = [];
    }

    /**
     * Return the data to serialize the current track in JSON.
     *
     * @return stdClass the statement data structure
     */
    public function jsonSerialize(): array {
        return $this->updates;
    }

    /**
     * Add track about a general course state change.
     */
    public function add_course_update(): void {
        $courseclass = $this->format->get_output_classname('course_format\state');
        $currentstate = new $courseclass($this->format);
        $this->updates[] = (object)[
            'name' => 'course',
            'action' => 'update',
            'fields' => $currentstate->export_for_template($this->output),
        ];
    }

    /**
     * Add track about a section state update.
     *
     * @param int $sectionnum The affected section number.
     * @return stdClass The updated object.
     */
    public function add_section_update(int $sectionnum): void {
        $this->create_or_update_section($sectionnum, 'update');
    }

    /**
     * Add track about a new section created.
     *
     * @param int $sectionnum The affected section number.
     */
    public function add_section_create(int $sectionnum): void {
        $this->create_or_update_section($sectionnum, 'create');
    }

    /**
     * Add track about section created or updated.
     *
     * @param int $sectionnum The affected section number.
     * @param string $action The action to track for the section ('create' or 'update).
     */
    protected function create_or_update_section(int $sectionnum, string $action): void {
        if ($action != 'create' && $action != 'update') {
            throw new coding_exception(
                "Invalid action passed ($action) to create_or_update_section. Only 'create' and 'update' are valid."
            );
        }
        $modinfo = course_modinfo::instance($this->format->get_course());

        $section = $modinfo->get_section_info($sectionnum);
        $sectionclass = $this->format->get_output_classname('section_format\state');
        $currentstate = new $sectionclass($this->format, $section);

        $this->updates[] = (object)[
            'name' => 'section',
            'action' => $action,
            'fields' => $currentstate->export_for_template($this->output),
        ];
    }

    /**
     * Add track about a section deleted.
     *
     * @param int $sectionnum The affected section number.
     */
    public function add_section_delete(int $sectionnum): void {
        $this->updates[] = (object)[
            'name' => 'section',
            'action' => 'delete',
            'fields' => (object)['id' => $sectionnum],
        ];
    }

    /**
     * Add track about a course module state update.
     *
     * @param int $cmid the affected course module id
     * @param bool $exportcontent if the tracked state should contain also
     *             the pre-rendered cmitem content
     */
    public function add_cm_update(int $cmid, bool $exportcontent = false): void {

        $modinfo = course_modinfo::instance($this->format->get_course());

        $cm = $modinfo->get_cm($cmid);
        $cmclass = $this->format->get_output_classname('cm_format\state');
        $section = $modinfo->get_section_info($cm->sectionnum);
        $currentstate = new $cmclass($this->format, $section, $cm, $exportcontent);

        $this->updates[] = (object)[
            'name' => 'cm',
            'action' => 'update',
            'fields' => $currentstate->export_for_template($this->output),
        ];
    }

    /**
     * Add track about a course module created.
     *
     * @param int $cmid the affected course module id
     */
    public function add_cm_create(int $cmid): void {
        $result = $this->add_section_update($cmid, true);
        $result->action = 'create';
        $this->updates[] = $result;
    }

    /**
     * Add track about a course module deleted.
     *
     * @param int $cmid the affected course module id
     */
    public function add_cm_delete(int $cmid): void {
        $this->updates[] = (object)[
            'name' => 'cm',
            'action' => 'delete',
            'fields' => (object)['id' => $cmid],
        ];
    }

}
