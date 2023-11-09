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

namespace tool_behat\output;

use renderable;
use renderer_base;
use templatable;
use tool_behat\local\parsedfeature;

/**
 * A report to show the feature file parsing process.
 *
 * @package    core
 * @copyright  2023 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parsingresult implements renderable, templatable {

    /** @var parsedfeature the processed feature object. */
    protected $parsedfeature;

    /**
     * Constructor.
     *
     * @param parsedfeature $parsedfeature
     */
    public function __construct(parsedfeature $parsedfeature) {
        $this->parsedfeature = $parsedfeature;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = [
            'lines' => [],
            'isvalid' => $this->parsedfeature->is_valid(),
            'generalerror' => $this->parsedfeature->get_general_error(),
        ];
        foreach ($this->parsedfeature as $line) {
            $linearguments = $line->get_arguments_string();
            $data['lines'][] = [
                'text' => $line->get_text(),
                'arguments' => $linearguments,
                'hasarguments' => !empty($linearguments),
                'isvalid' => $line->is_valid(),
                'error' => $line->get_error(),
                'isexecuted' => $line->is_executed(),
            ];

        }
        $data['haslines'] = !empty($data['lines']);
        return $data;
    }
}
