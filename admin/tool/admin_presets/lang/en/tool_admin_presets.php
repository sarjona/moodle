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
 * Admin tool presets plugin to load some settings.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accessdenied'] = 'Access denied';
$string['actionbase'] = 'Presets';
$string['actiondelete'] = 'Delete preset';
$string['actionexport'] = 'Export settings';
$string['actionimport'] = 'Import settings';
$string['actionload'] = 'Load settings';
$string['actionrollback'] = 'Revert applied changes';
$string['actualvalue'] = 'Actual value';
$string['admin_presets:addinstance'] = 'Add a new admin presets tool';
$string['adminsettings'] = 'Admin settings';
$string['author'] = 'Author';
$string['appliedchanges'] = 'Applied settings table';
$string['autohidesensiblesettings'] = 'Auto exclude sensitive settings';
$string['baseshow'] = 'list';
$string['created'] = 'Created';
$string['deletepreset'] = 'Preset {$a} will be deleted, are you sure?';
$string['deletepreviouslyapplied'] = 'This preset has been previously applied, if you delete it you can not return to the previous state';
$string['deleteexecute'] = 'execution';
$string['deleteshow'] = 'confirm';
$string['errorupgradetablenames'] = 'admin_presets upgrade failed,
 upgrade Moodle in order to upgrade admin_presets. You can restore the previous admin/tool/admin_presets code until then';
$string['errorupgradetablenamesdebug'] = 'The table names exceeds the limit of allowed characters,
 this is solved using the latest Moodle 2.0, Moodle 2.1 and Moodle 2.2 releases';
$string['errordeleting'] = 'Error deleting from DB';
$string['errorinserting'] = 'Error inserting into DB';
$string['errornopreset'] = 'It doesn\'t exists a preset with that name';
$string['eventpresetdeleted'] = 'Preset deleted';
$string['eventpresetdownloaded'] = 'Preset downloaded';
$string['eventpresetexported'] = 'Preset exported';
$string['eventpresetimported'] = 'Preset imported';
$string['eventpresetloaded'] = 'Preset loaded';
$string['eventpresetpreviewed'] = 'Preset previewed';
$string['eventpresetreverted'] = 'Preset reverted';
$string['eventpresetslisted'] = 'Presets have been listed';
$string['exportexecute'] = 'saving';
$string['exportshow'] = 'select settings';
$string['falseaction'] = 'Action not supported in this version';
$string['falsemode'] = 'Mode not supported in this version';
$string['headingload'] = 'Select settings to load';
$string['imported'] = 'Imported';
$string['importexecute'] = 'importing';
$string['importshow'] = 'select file';
$string['load'] = 'Load';
$string['loadexecute'] = 'applied changes';
$string['loadpreview'] = 'preview preset';
$string['loadselected'] = 'Apply';
$string['loadshow'] = 'select settings';
$string['markedasadvanced'] = 'marked as advanced';
$string['markedasforced'] = 'marked as forced';
$string['markedaslocked'] = 'marked as locked';
$string['markedasnonadvanced'] = 'marked as non advanced';
$string['markedasnonforced'] = 'marked as non forced';
$string['markedasnonlocked'] = 'marked as non locked';
$string['newvalue'] = 'New setting value';
$string['loading'] = 'loading';
$string['noparamtype'] = 'There are no param type for that setting';
$string['nopresets'] = 'You don\'t have presets';
$string['nothingloaded'] = 'All preset settings skipped, they are already loaded';
$string['notpreviouslyapplied'] = 'Preset not previously applied';
$string['novalidsettings'] = 'No valid settings';
$string['novalidsettingsselected'] = 'No valid settings selected';
$string['oldvalue'] = 'Old setting value';
$string['pluginname'] = 'Admin presets';
$string['presetapplicationslisttable'] = 'Preset applications table';
$string['presetslisttable'] = 'Presets table';
$string['presetmoodlerelease'] = 'Moodle release';
$string['presetname'] = 'Preset name';
$string['presetsettings'] = 'Preset settings';
$string['preview'] = 'preview';
$string['previewpreset'] = 'Preview preset';
$string['renamepreset'] = 'Rename preset';
$string['rollback'] = 'Restore this version';
$string['rollbackexecute'] = 'return to previous state';
$string['rollbackfailures'] = 'The following settings can not be restored,
 the actual values differs from the values applied by the preset';
$string['rollbackresults'] = 'Settings successfully restored';
$string['rollbackshow'] = 'preset applications list';
$string['selectedvalues'] = 'setting selected values';
$string['selectfile'] = 'Select file';
$string['sensiblesettings'] = 'Sensitive setting to skip if "Auto exclude sensitive settings" is checked';
$string['sensiblesettingstext'] = 'Add elements separating by \',\' and with format SETTINGNAME@@PLUGINNAME';
$string['settingname'] = 'Setting name';
$string['settingvalue'] = 'with value';
$string['settingsapplied'] = 'Settings applied';
$string['settingsnotapplicable'] = 'Settings not applicable to this Moodle version';
$string['settingsnotapplied'] = 'Settings skipped, they are all already loaded';
$string['show'] = 'Show';
$string['showhistory'] = 'Show version history';
$string['site'] = 'Site';
$string['skippedchanges'] = 'Skipped settings table';
$string['successimported'] = 'Preset imported';
$string['timeapplied'] = 'Time applied';
$string['toexportclick'] = 'To export your settings click {$a}';
$string['toimportclick'] = 'To import a admin preset click {$a}';
$string['value'] = 'setting value';
$string['voidvalue'] = 'that setting does not have a value';
$string['wrongfile'] = 'Wrong file';
$string['wrongid'] = 'Wrong id';
$string['privacy:metadata:admin_presets'] = 'The list of configuration presets.';
$string['privacy:metadata:admin_presets:comments'] = 'A description about the preset.';
$string['privacy:metadata:admin_presets:moodlerelease'] = 'The Moodle release version where the preset is based on.';
$string['privacy:metadata:admin_presets:name'] = 'The name of the preset.';
$string['privacy:metadata:admin_presets:site'] = 'The Moodle site where this preset was created.';
$string['privacy:metadata:admin_presets:timecreated'] = 'The time that the change was made.';
$string['privacy:metadata:admin_presets:userid'] = 'The user who create the preset.';
$string['privacy:metadata:tool_admin_presets_app'] = 'The configuration presets that have been applied.';
$string['privacy:metadata:tool_admin_presets_app:adminpresetid'] = 'The id of the preset applied.';
$string['privacy:metadata:tool_admin_presets_app:time'] = 'The time that the preset was applied.';
$string['privacy:metadata:tool_admin_presets_app:userid'] = 'The user who applied the preset.';
