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
 * Render H5P content from an H5P file.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');

$url = required_param('url', PARAM_LOCALURL);
$url = urldecode($url);

$config = new stdClass();
$config->frame = optional_param('frame', 0, PARAM_INT);
$config->export = optional_param('export', 0, PARAM_INT);
$config->embed = optional_param('embed', 0, PARAM_INT);
$config->copyright = optional_param('copyright', 0, PARAM_INT);

require_login(null, false);

// Configure page.
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url ('/h5p/embed.php', array('url' => $url)));

// Set up the H5P player class.
$h5pplayer = new \core_h5p\player($url, $config);

$PAGE->set_title($h5pplayer->get_title());
$PAGE->set_heading($h5pplayer->get_title());

// Embed specific page setup.
$PAGE->add_body_class('h5p-embed');
$PAGE->set_pagelayout('embedded');

// Add H5P assets to the page.
$h5pplayer->add_assets_to_page();

// Print page HTML.
echo $OUTPUT->header();

echo $h5pplayer->output();

echo $OUTPUT->footer();
