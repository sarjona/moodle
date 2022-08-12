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
 * @package    datafield
 * @subpackage tag
 * @copyright  2015 onwards Andrew Hancox (andrewdchancox@googlemail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('moodle/site:config', $context);
require_once($CFG->dirroot . '/mod/data/field/tag/migrate/migrateform.php');

$PAGE->set_url('/mod/data/field/tag/migrate/migrate.php');
$PAGE->set_title(get_string('migrate', 'datafield_tag'));
$PAGE->set_heading(get_string('migrate', 'datafield_tag'));

$form = new migrateform();
if ($form->migratetext()) {
    $form = new migrateform();
}

echo $OUTPUT->header();
echo $form->render();
echo $OUTPUT->footer();