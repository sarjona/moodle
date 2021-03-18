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
 * Upload a file to content bank.
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');

require_login();

debugging('Use of contentbank/upload.php have been deprecated. This page has been implemented using a modal form in index.php.',
    DEBUG_DEVELOPER);

$contextid = optional_param('contextid', \context_system::instance()->id, PARAM_INT);
$id = optional_param('id', null, PARAM_INT);
$params = ['contextid' => $contextid];
if ($id) {
    $params['id'] = $id;
    $url = '/contentbank/view.php';
} else {
    $url = '/contentbank/index.php';
}
redirect((new \moodle_url($url, $params))->out(false));
