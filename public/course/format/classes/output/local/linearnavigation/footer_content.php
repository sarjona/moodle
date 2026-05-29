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

namespace core_courseformat\output\local\linearnavigation;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;

/**
 * Sticky footer class for linear navigation in course format.
 *
 * @package    core_courseformat
 * @copyright  2025 Laurent David <laurent.david@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class footer_content implements named_templatable, renderable {
    /**
     * Constructor.
     *
     * @param \cm_info $cm The course module info object.
     */
    public function __construct(
        /** @var \cm_info The course module info object. */
        private \cm_info $cm
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output) {
        return [
            'previousurl' => (new \moodle_url('/course/cms/' . $this->cm->id . '/previous'))->out(false),
            'nexturl' => (new \moodle_url('/course/cms/' . $this->cm->id . '/next'))->out(false),
        ];
    }

    #[\Override]
    public function get_template_name(\renderer_base $renderer): string {
        return 'core_courseformat/local/linearnavigation/footer_content';
    }
}
