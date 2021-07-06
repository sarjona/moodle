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

$string['actionexport'] = 'Add a new preset';
$string['actionimport'] = 'Import preset';
$string['actualvalue'] = 'Actual value';
$string['applyaction'] = 'Apply...';
$string['applypresetdescription'] = 'If you apply this preset, a new option "Show version history" will appear in the preset menu to let you restore the previous version';
$string['author'] = 'Author';
$string['basedescription'] = 'Presets allow you to easily switch between different site admin configurations. After selecting a preset, you can turn on more features any time as required.';
$string['created'] = 'Created';
$string['currentvalue'] = 'Current value';
$string['deletepreset'] = 'Are you sure you want to delete "{$a}" site admin preset?';
$string['deletepreviouslyapplied'] = 'This preset has been previously applied, if you delete it you can not return to the previous state';
$string['deleteshow'] = 'Delete site admin preset';
$string['disabled'] = 'Disabled';
$string['enabled'] = 'Enabled';
$string['errordeleting'] = 'Error deleting from database.';
$string['errorinserting'] = 'Error inserting into database.';
$string['errornopreset'] = 'It doesn\'t exists a preset with that name.';
$string['eventpresetdeleted'] = 'Preset deleted';
$string['eventpresetdownloaded'] = 'Preset downloaded';
$string['eventpresetexported'] = 'Preset created';
$string['eventpresetimported'] = 'Preset imported';
$string['eventpresetloaded'] = 'Preset applied';
$string['eventpresetpreviewed'] = 'Preset previewed';
$string['eventpresetreverted'] = 'Preset restored';
$string['eventpresetslisted'] = 'Presets have been listed';
$string['exportdescription'] = 'Preset created using all current site admin settings snapshot [TODO: String to be changed]';
$string['exportshow'] = 'Add a new site admin preset';
$string['falseaction'] = 'Action not supported in this version.';
$string['falsemode'] = 'Mode not supported in this version.';
$string['fullpreset'] = 'Full';
$string['fullpresetdescription'] = 'All the Lite features plus External (LTI) tool, SCORM, Workshop, Analytics, Badges, Competencies, Learning plans and lots more.';
$string['imported'] = 'Imported';
$string['importshow'] = 'Import site admin preset';
$string['includesensiblesettings'] = 'Include sensitive settings';
$string['includesensiblesettings_help'] = 'Whether sensitive settings should be included or not [TODO: Help string to be added]';
$string['litepreset'] = 'Lite';
$string['litepresetdescription'] = 'Moodle with all of the most popular features, including Assignment, Feedback, Forum, H5P, Quiz and Completion tracking.';
$string['loaddescription'] = 'This is the explanatory sentence to be displayed before applying a preset [TODO: Change this string]';
$string['loadexecute'] = 'Applied site admin preset changes';
$string['loadpreview'] = 'Preview site admin preset';
$string['loadselected'] = 'Apply';
$string['loadshow'] = 'Apply site admin preset';
$string['markedasadvanced'] = 'marked as advanced';
$string['markedasforced'] = 'marked as forced';
$string['markedaslocked'] = 'marked as locked';
$string['markedasnonadvanced'] = 'marked as non advanced';
$string['markedasnonforced'] = 'marked as non forced';
$string['markedasnonlocked'] = 'marked as non locked';
$string['newvalue'] = 'New value';
$string['nopresets'] = 'You don\'t have any site admin preset.';
$string['nothingloaded'] = 'All settings skipped, they were already loaded';
$string['novalidsettings'] = 'No valid settings';
$string['novalidsettingsselected'] = 'No valid settings selected';
$string['oldvalue'] = 'Old value';
$string['pluginname'] = 'Site admin presets';
$string['presetapplicationslisttable'] = 'Site admin preset applications table';
$string['presetslisttable'] = 'Site admin presets table';
$string['presetmoodlerelease'] = 'Moodle release';
$string['presetname'] = 'Preset name';
$string['presetsettings'] = 'Preset settings';
$string['previewpreset'] = 'Preview preset';
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
$string['renamedfrom'] = 'Renamed from {$a}';
$string['renamepreset'] = 'Name (optional)';
$string['rollback'] = 'Restore this version';
$string['rollbackdescription'] = 'Explanatory sentence to be displayed before restoring preset version [TODO: Change this string]';
$string['rollbackexecute'] = 'Restored version from "{$a}" site admin preset';
$string['rollbackfailures'] = 'The following settings can not be restored, the actual values differs from the values applied by the preset';
$string['rollbackresults'] = 'Settings successfully restored';
$string['rollbackshow'] = '"{$a}" site admin preset version history';
$string['selectfile'] = 'Select file';
$string['sensiblesettings'] = 'Sensitive setting to skip if "Auto exclude sensitive settings" is checked';
$string['sensiblesettingstext'] = 'Add elements separating by \',\' and with format SETTINGNAME@@PLUGINNAME';
$string['settingname'] = 'Setting name';
$string['settingsapplied'] = 'Settings applied';
$string['settingsappliednotification'] = 'Settings for this preset have been applied successfully. In the preset menu now there is a new option, "Show version history", to restore the previous settings before this preset was applied.';
$string['settingsnotapplicable'] = 'Settings not applicable to this Moodle version';
$string['settingsnotapplied'] = 'Settings skipped, they were already loaded';
$string['settingstobeapplied'] = 'Settings to be applied';
$string['showhistory'] = 'Show version history';
$string['site'] = 'Site';
$string['skippedchanges'] = 'Skipped settings table';
$string['timeapplied'] = 'Time applied';
$string['wrongfile'] = 'Wrong file';
$string['wrongid'] = 'Wrong id';
