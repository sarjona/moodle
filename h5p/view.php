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
 * Embed H5P Content
 *
 * @package    mod_h5p
 * @copyright  2016 Joubel AS <contact@joubel.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("../config.php");
require_once($CFG->dirroot.'/h5p/classes/framework.php');

$id = required_param('id', PARAM_INT);

$disabledownload = false;
$disablefullscreen = false;

// Set up view assets.
$h5p    = new \core_h5p\view_assets($id);
$content = $h5p->getcontent();

// Configure page.
require_login(0, false);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url ('/h5p/view.php', array('id' => $id)));
$PAGE->set_title('render h5p');
$PAGE->set_heading('h5p rendering');

// Embed specific page setup.
$PAGE->add_body_class('h5p-embed');
$PAGE->set_pagelayout('embedded');

// Add H5P assets to page.
$h5p->addassetstopage();

// Print page HTML.
echo $OUTPUT->header();

echo $h5p->outputview();
echo $OUTPUT->footer();
