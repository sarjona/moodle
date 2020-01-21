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
 * H5P settings link.
 *
 * @package    core_h5p
 * @copyright  2019 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Manage H5P libraries page.
$managecontenttype = new admin_externalpage('managecontentype', get_string('h5pmanage', 'core_h5p'),
    new moodle_url('/h5p/libraries.php'), ['moodle/site:config', 'moodle/h5p:updatelibraries']);
$ADMIN->add('h5p', $managecontenttype);


// H5P settings.
$settings = new admin_settingpage('h5psettings', new lang_string('h5psettings', 'core_h5p'));
$ADMIN->add('h5p', $settings);

// H5P libraries handler.
$h5plibhandlers = \core_h5p\local\library\autoloader::get_all_handlers();
foreach ($h5plibhandlers as $name => $class) {
    $h5plibhandlers[$name] = get_string('pluginname', $name);
}
$defaulth5lib = \core_h5p\local\library\autoloader::get_default_handler();
$settings->add(new admin_setting_configselect('h5plibraryhandler',
    new lang_string('h5plibraryhandler', 'core_h5p'), new lang_string('h5plibraryhandler_help', 'core_h5p'),
    $defaulth5lib, $h5plibhandlers));
