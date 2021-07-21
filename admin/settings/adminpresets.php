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
 * Admin presets tool main controller
 *
 * @package          tool_admin_presets
 * @copyright        2020 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Sylvain Revenu | Pimenko
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $ADMIN->add('admin_presets', new admin_externalpage('tool_admin_presets', get_string('baseshow', 'tool_admin_presets'),
        new moodle_url('/admin/tool/admin_presets/index.php'), ['moodle/site:config']));

    // Import page.
    $ADMIN->add('admin_presets', new admin_externalpage('actionimport', get_string('actionimport', 'tool_admin_presets'),
        new moodle_url('/admin/tool/admin_presets/index.php', ['action' => 'import']), ['moodle/site:config']));

    // Export page.
    $ADMIN->add('admin_presets', new admin_externalpage('actionexport', get_string('actionexport', 'tool_admin_presets'),
        new moodle_url('/admin/tool/admin_presets/index.php', ['action' => 'export']), ['moodle/site:config']));
}