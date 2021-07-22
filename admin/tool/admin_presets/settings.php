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
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko
 * @orignalauthor    David Monllaó <david.monllao@urv.cat>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('tool_admin_presets_settings', get_string('pluginname', 'tool_admin_presets'));
    $ADMIN->add('tools', $settings);

    $sensiblesettingsdefault = 'recaptchapublickey@@none, recaptchaprivatekey@@none, googlemapkey@@none, ';
    $sensiblesettingsdefault .= 'secretphrase@@none, cronremotepassword@@none, smtpuser@@none, ';
    $sensiblesettingsdefault .= 'smtppass@none, proxypassword@@none, password@@quiz, ';
    $sensiblesettingsdefault .= 'enrolpassword@@moodlecourse, allowedip@@none, blockedip@@none';

    $settings->add(new admin_setting_configtextarea('tool_admin_presets/sensiblesettings',
             get_string('sensiblesettings', 'tool_admin_presets'),
             get_string('sensiblesettingstext', 'tool_admin_presets'),
             $sensiblesettingsdefault, PARAM_TEXT));

}
