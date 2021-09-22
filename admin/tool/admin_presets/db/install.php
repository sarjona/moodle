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
 * Install code for Admin tool presets plugin.
 *
 * @package    tool_admin_presets
 * @copyright  2021 Sara Arjona (sara@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use tool_admin_presets\helper;

/**
 * Perform the post-install procedures.
 */
function xmldb_tool_admin_presets_install() {

    // Create the "Lite Moodle" preset.
    // TODO: Confirm strings and move them to the lang file.
    $data = [
        'name' => 'Lite Moodle',
        'comments' => 'Minimalist version with only a few of the most common plugins and features enabled.',
    ];
    $presetid = helper::create_preset($data);

    // Add settings to the "Lite Moodle" preset.
    helper::create_item($presetid, 'usecomments', '0');
    helper::create_item($presetid, 'usetags', '0');
    helper::create_item($presetid, 'enablenotes', '0');
    helper::create_item($presetid, 'enableblogs', '0');
    helper::create_item($presetid, 'enablebadges', '0');
    helper::create_item($presetid, 'enableanalytics', '0');
    helper::create_item($presetid, 'enabled', '0', 'core_competency');

    helper::create_item($presetid, 'showdataretentionsummary', '0', 'tool_dataprivacy');
    helper::create_item($presetid, 'forum_maxattachments', '3');
    helper::create_item($presetid, 'customusermenuitems', 'preferences,moodle|/user/preferences.php|t/preferences');

    // TODO: Modules: Hide chat, database, external tool, IMS content package, lesson, SCORM, survey, wiki, workshop.

    // TODO: Availability restrictions: Hide Grouping, User profile.

    // TODO: Blocks: Hide Activities, Administration, Blog menu, Blog tags, Calendar, Comments, Community finder,
    // Course completion status, Course overview (legacy), Course/site summary, Courses, Flickr, Global search, Latest badges,
    // Learning plans, Logged in user, Login, Main menu, Mentees, Navigation, Network servers, People, Private files,
    // Recent blog entries, RSS feeds, Search forums, Section links, Self completion, Social activities, Tags, YouTube.

    // TODO: Course formats: Disable Social format.

    // TODO: Data formats: Disable Javascript Object Notation (.json).

    // TODO: Enrolments: Disable Cohort sync.

    // TODO: Filter: Disable MathJax, Activity names auto-linking.

    // TODO: Question behaviours: Disable Adaptive mode (no penalties), Deferred feedback with CBM, Immediate feedback with CBM.

    // TODO: Question types: Disable Calculated, Calculated multichoice, Calculated simple, Description, Drag and drop markers,
    // Drag and drop onto image, Embedded answers (Cloze), Essay, Numerical, Random short-answer matching.

    // TODO: Repositories: Disable Server files, URL downloader, Wikimedia, YouTube videos.

    // TODO: Text editors: Disable TinyMCE HTML editor.

    // Create the "Full Moodle" preset.
    // TODO: Do we really need to create "Full Moodle" or can be just explain administrators to "revert" Lite Moodle when they want
    // to enable Full Moodle?
    // TODO: Confirm strings and move them to the lang file.
    $data = [
        'name' => 'Full Moodle',
        'comments' => 'The default Moodle installation with most of the features and plugins enabled',
    ];
    $presetid = helper::create_preset($data);

    // Add settings to the "Full Moodle" preset.
    helper::create_item($presetid, 'usecomments', '1');
    helper::create_item($presetid, 'usetags', '1');
    helper::create_item($presetid, 'enablenotes', '1');
    helper::create_item($presetid, 'enableblogs', '1');
    helper::create_item($presetid, 'enablebadges', '1');
    helper::create_item($presetid, 'enableanalytics', '1');
    helper::create_item($presetid, 'enabled', '1', 'core_competency');

    helper::create_item($presetid, 'showdataretentionsummary', '1', 'tool_dataprivacy');
    helper::create_item($presetid, 'forum_maxattachments', '9');
    helper::create_item($presetid, 'customusermenuitems', 'grades,grades|/grade/report/mygrades.php|t/grades
            messages,message|/message/index.php|t/message
            preferences,moodle|/user/preferences.php|t/preferences');

    // TODO: Enable plugins.

}
