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
 * Run a test scenario generator feature file.
 *
 * @package    tool_behat
 * @copyright  2014 NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_behat\local\runner;

if (isset($_SERVER['REMOTE_ADDR'])) {
    die(); // No access from web!
}

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../../../lib/clilib.php');
require_once(__DIR__ . '/../../../../lib/behat/classes/behat_config_manager.php');

ini_set('display_errors', '1');
ini_set('log_errors', '1');

list($options, $unrecognised) = cli_get_params(
    array(
        'help' => false,
        'feature' => '',
    ),
    array(
        'h' => 'help',
        'f' => 'feature',
    )
);

// Checking run.php CLI script usage.
$help = "
Run a feature file into the current Moodle instance. The feature file can only
contains scenarios with core_data_generator steps. It is not yet compatible
with scenario outlines. All scenarios will be executed at once, event background
steps.

Usage:
  php run.php [--feature=\"value\"] [--help]

Options:
--feature          Execute specified feature file (Absolute path of feature file).

-h, --help         Print out this help

Example from Moodle root directory:
\$ php admin/tool/behat/cli/testscenario.php --feature=/path/to/some/testing/scenario.feature
";

if (!empty($options['help'])) {
    echo $help;
    exit(0);
}

if (empty($options['feature'])) {
    echo "Missing feature file path.\n";
    exit(0);
}

$featurefile = $options['feature'];
if (!file_exists($featurefile)) {
    echo "Feature file not found.\n";
    exit(0);
}

$runner = new runner();

try {
    $runner->init();
} catch (Exception $e) {
    echo "Something is wrong with the behat setup.\n";
    echo "  You ran \"php admin/tool/behat/cli/init.php\" from your Moodle root directory.\n";
    exit(0);
}

$content = file_get_contents($featurefile);

if (empty($content)) {
    echo "The feature file is empty.\n";
    exit(0);
}

try {
    $parsedfeature = $runner->parse_feature($content);
} catch (\Throwable $th) {
    echo "Error parsing feature file: {$th->getMessage()}\n";
    echo "Use the web version of the tool to see the parsing details:\n";
    echo "  Site administration -> development -> Create testing scenarios\n";
    exit(0);
}

if (!$parsedfeature->is_valid()) {
    echo "The file is not valid: {$th->getMessage()}\n";
    echo "Use the web version of the tool to see the details:\n";
    echo "  Site administration -> development -> Create testing scenarios\n";
    exit(0);
}

$total = 0;
$success = 0;

foreach ($parsedfeature as $step) {
    if($step->execute()) {
        echo "\nOK: {$step->get_text()}\n";
        echo "{$step->get_arguments_string()}\n";
        $success++;
    } else {
        echo "\nFAIL: {$step->get_text()}\n";
        echo "{$step->get_arguments_string()}\n";
        echo "{$step->get_error()}\n";
    }
    $total++;
}

echo "\n{$success}/{$total} steps executed successfully.\n";

if ($success < $total) {
    echo "\nSome steps failed.\n";
    exit(1);
} else {
    echo "\nAll steps executed successfully.\n";
    exit(0);
}
