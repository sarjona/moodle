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
 * Web interface to list and filter steps
 *
 * @package    tool_behat
 * @copyright  2012 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_behat\local\runner;
use tool_behat\form\featureimport;
use tool_behat\output\parsingresult;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/behat/locallib.php');
require_once($CFG->libdir . '/behat/classes/behat_config_manager.php');

// Executing behat generator can take some time.
core_php_time_limit::raise(300);

admin_externalpage_setup('toolbehat_runner');

$currenturl = new moodle_url('/admin/tool/behat/runner.php');
$runner = new runner();

try {
    $runner->init();
} catch (Exception $e) {
    echo $output->notification(get_string('runner_notready', 'tool_behat'));
    echo $output->footer();
    die;
}

/** @var core_renderer $output*/
$output = $PAGE->get_renderer('core');

echo $output->header();
echo $output->heading(get_string('runner', 'tool_behat'));

echo $output->paragraph(get_string('runner_description', 'tool_behat'));
echo $output->paragraph(get_string('runner_filedesc', 'tool_behat'));

$mform = new featureimport();

$data = null;
if (!$mform->is_cancelled()) {
    $data = $mform->get_data();
};

// For now all behat scenarios steps are executen, even the background ones. However, it will
// be great if, in the future, we can select which scenarios to execute.

if (empty($data)) {
    $mform->display();
    echo $OUTPUT->footer();
    die;
}

$content = $mform->get_feature_contents();

if (empty($content)) {
    echo $output->notification(get_string('runner_invalidfile', 'tool_behat', $parsedfeature->get_general_error()));
    echo $output->continue_button($currenturl);
    echo $output->footer();
    die;
}

try {
    $parsedfeature = $runner->parse_feature($content);
} catch (\Throwable $th) {
    echo $output->notification(get_string('runner_errorparsing', 'tool_behat', $th->getMessage()));
    echo $output->continue_button($currenturl);

    echo $output->footer();
    die;
}

if ($parsedfeature->is_valid()) {
    foreach ($parsedfeature as $step) {
        $step->execute();
    }
}

echo $output->render(new parsingresult($parsedfeature));
echo $output->continue_button($currenturl);
echo $output->footer();
