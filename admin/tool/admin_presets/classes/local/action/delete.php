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

namespace tool_admin_presets\local\action;

/**
 * This class extends base class and handles delete function.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete extends base {

    /**
     * Shows a confirm box
     */
    public function show(): void {

        global $DB, $CFG, $OUTPUT;

        // Getting the preset name.
        $presetdata = $DB->get_record('tool_admin_presets', array('id' => $this->id), 'name');

        $deletetext = get_string("deletepreset", "tool_admin_presets", $presetdata->name);
        $confirmurl = $CFG->wwwroot . '/admin/tool/admin_presets/index.php?action=' .
            $this->action . '&mode=execute&id=' . $this->id . '&sesskey=' . sesskey();
        $cancelurl = $CFG->wwwroot . '/admin/tool/admin_presets/index.php';

        // If the preset was applied add a warning text.
        if ($previouslyapplied = $DB->get_records('tool_admin_presets_app',
            array('adminpresetid' => $this->id))) {

            $deletetext .= '<br/><br/><strong>' .
                get_string("deletepreviouslyapplied", "tool_admin_presets") . '</strong>';
        }

        $this->outputs = $OUTPUT->confirm($deletetext, $confirmurl, $cancelurl);
    }

    /**
     * Delete the DB preset
     */
    public function execute(): void {

        global $DB, $CFG;

        confirm_sesskey();

        if (!$DB->delete_records('tool_admin_presets', array('id' => $this->id))) {
            print_error('errordeleting', 'tool_admin_presets');
        }

        // Getting items ids before deleting to delete item attributes.
        $items = $DB->get_records('tool_admin_presets_it', array('adminpresetid' => $this->id), 'id');
        foreach ($items as $item) {
            $DB->delete_records('tool_admin_presets_it_a', array('itemid' => $item->id));
        }

        if (!$DB->delete_records('tool_admin_presets_it', array('adminpresetid' => $this->id))) {
            print_error('errordeleting', 'tool_admin_presets');
        }

        // Deleting the preset applications.
        if ($previouslyapplied = $DB->get_records('tool_admin_presets_app',
            array('adminpresetid' => $this->id), 'id')) {

            foreach ($previouslyapplied as $application) {

                // Deleting items.
                if (!$DB->delete_records('tool_admin_presets_app_it',
                    array('adminpresetapplyid' => $application->id))) {

                    print_error('errordeleting', 'tool_admin_presets');
                }

                // Deleting attributes.
                if (!$DB->delete_records('tool_admin_presets_app_it_a',
                    array('adminpresetapplyid' => $application->id))) {

                    print_error('errordeleting', 'tool_admin_presets');
                }
            }

            if (!$DB->delete_records('tool_admin_presets_app',
                array('adminpresetid' => $this->id))) {

                print_error('errordeleting', 'tool_admin_presets');
            }
        }

        // Trigger the as it is usually triggered after execute finishes.
        $this->log();

        redirect($CFG->wwwroot . '/admin/tool/admin_presets/index.php');
    }

}
