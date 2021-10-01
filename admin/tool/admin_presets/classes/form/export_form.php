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

namespace tool_admin_presets\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Form for exporting settings.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_form extends moodleform {

    public function definition(): void {

        global $USER, $OUTPUT;

        $mform = &$this->_form;

        // Preset attributes.
        $mform->addElement('header', 'general',
            get_string('presetsettings', 'tool_admin_presets'));

        $mform->addElement('text', 'name', get_string('name'), 'maxlength="254" size="60"');
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'comments', get_string('comments'));
        $mform->setType('comments', PARAM_CLEANHTML);

        $mform->addElement('text', 'author',
            get_string('author', 'tool_admin_presets'), 'maxlength="254" size="60"');
        $mform->setType('author', PARAM_TEXT);
        $mform->setDefault('author', $USER->firstname . ' ' . $USER->lastname);

        $mform->addElement('checkbox', 'excludesensiblesettings',
            get_string('autohidesensiblesettings', 'tool_admin_presets'));
        $mform->setDefault('excludesensiblesettings', 1);

        // Moodle settings table.
        $mform->addElement('header', 'general',
            get_string('adminsettings', 'tool_admin_presets'));

        $icon = $OUTPUT->pix_icon('i/loading_small', get_string('loading', 'tool_admin_presets'));
        $mform->addElement('html', '<ul id="settings_tree_div" class="ygtv-checkbox" role="tree">' . $icon . '</ul><br/>');

        // Submit.
        $mform->addElement('submit', 'admin_presets_submit', get_string('savechanges'));
    }
}
