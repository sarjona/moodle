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
 * Generator for the fake fullfeatured module.
 *
 * @package   mod_fullfeatured_generator
 * @copyright 2026 Sara Arjona <sara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_fullfeatured_generator extends testing_module_generator {
    /**
     * Creates an instance of the fake module without relying on add_moduleinfo().
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        global $DB;

        $this->instancecount++;
        $record = (object) ($record ?? []);
        $options = $options ?? [];

        if (empty($record->course)) {
            throw new coding_exception('module generator requires $record->course');
        }

        if (empty($record->name)) {
            $record->name = 'Fake fullfeatured test module ' . $this->instancecount;
        }

        $modulename = $this->get_modulename();
        $dbman = $DB->get_manager();
        // All modules must have a table. Simulate the installation of the plugin by creating the table if it does not exist yet.
        $table = new \xmldb_table($modulename);
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        // Register manually the module in the modules table if it does not exist yet, to simulate the installation of the plugin.
        if (!$DB->record_exists('modules', ['name' => $modulename])) {
            $DB->insert_record('modules', (object) [
                'name' => $modulename,
                'cron' => 0,
                'lastcron' => 0,
                'search' => '',
                'visible' => 1,
            ]);
        }

        $record = $this->prepare_moduleinfo_record($record, $options);
        // Retrieve the course record.
        if (!empty($record->course->id)) {
            $course = $record->course;
            $record->course = $record->course->id;
        } else {
            $course = get_course($record->course);
        }

        $moduleinfo = set_moduleinfo_defaults($record);

        // First add course_module record because we need the context.
        $newcm = new stdClass();
        $newcm->course           = $course->id;
        $newcm->module           = $moduleinfo->module;
        $newcm->instance         = 0; // Not known yet, will be updated later (this is similar to restore code).
        $newcm->visible          = $moduleinfo->visible;
        $newcm->visibleold       = $moduleinfo->visible;
        $newcm->visibleoncoursepage = $moduleinfo->visibleoncoursepage;
        if (isset($moduleinfo->cmidnumber)) {
            $newcm->idnumber         = $moduleinfo->cmidnumber;
        }
        $moduleinfo->coursemodule = add_course_module($newcm);

        $addinstancefunction = $modulename . '_add_instance';
        if (function_exists($addinstancefunction)) {
            $moduleinfo->instance = $addinstancefunction($moduleinfo, null);
        } else {
            $moduleinfo->instance = $DB->insert_record($modulename, (object) [
                'course' => $record->course,
                'name' => $record->name,
            ]);
        }

        $DB->set_field('course_modules', 'instance', $moduleinfo->instance, ['id' => $moduleinfo->coursemodule]);
        $instance = $DB->get_record($modulename, ['id' => $moduleinfo->instance], '*', MUST_EXIST);

        return $instance;
    }
}
