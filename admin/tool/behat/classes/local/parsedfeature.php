<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_behat\local;

use stdClass;
use Iterator;

/**
 * Class with a scenario feature parsed.
 *
 * @package    tool_behat
 * @copyright  2023 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parsedfeature implements Iterator {
    /** @var stdClass[] each line information. */
    private array $lines = [];

    /** @var bool if the parser is ok or fail. */
    private bool $isvalid = true;

    /** @var int the current line. */
    private int $currentline = 0;

    /**
     * Get the general error, if any.
     * @return string
     */
    public function get_general_error(): string {
        if (!$this->isvalid) {
            return get_string('runner_invalidfile', 'tool_behat');
        }
        if (empty($this->lines)) {
            return get_string('runner_nosteps', 'tool_behat');
        }
        return '';
    }

    /**
     * Check if the parsed feature is valid.
     * @return bool
     */
    public function is_valid(): bool {
        return $this->isvalid && count($this->lines) > 0;
    }

    /**
     * Add a line to the parsed feature.
     * @param steprunner $step the step to add.
     */
    public function add_line(steprunner $step) {
        $this->lines[] = $step;
        if (!$step->is_valid()) {
            $this->isvalid = false;
        }
    }

    public function rewind(): void {
        $this->currentline = 0;
    }

    public function valid(): bool {
        return $this->currentline < count($this->lines);
    }

    public function key(): int {
        return $this->currentline;
    }

    public function current(): steprunner {
        return $this->lines[$this->currentline];
    }

    public function next(): void {
        $this->currentline++;
    }
}
