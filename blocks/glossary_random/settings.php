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
 * Glossary Random block.
 *
 * @package    block_glossary_random
 * @copyright  2020 Luca Bösch <luca.boesch@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    // Default refresh button display.
    $yesno = array(0 => get_string('no'), 1 => get_string('yes'));
    $setting = new admin_setting_configselect('block_glossary_random/config_showrefreshbutton',
        new lang_string('showrefreshbutton', 'block_glossary_random'),
        new lang_string('showrefreshbutton_desc', 'block_glossary_random'), 1, $yesno);
//    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, yes);
    $settings->add($setting);
}
