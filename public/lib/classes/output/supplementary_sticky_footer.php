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

namespace core\output;

use stdClass;

/**
 * Sticky footer class with supplementary content.
 *
 * @package    core
 * @copyright  2026 Sara Arjona <sara@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplementary_sticky_footer extends \core\output\sticky_footer {

    /** @var stdClass|null Object containing supplementary content with 'text' and 'link' properties. */
    protected ?stdClass $supplementarycontent = null;

    /**
     * Add supplementary content to the sticky footer.
     *
     * @param string $text The supplementary text to be displayed in the sticky footer.
     * @param string|null $link An optional link for the supplementary text.
     */
    public function add_supplementary_content(
        string $text,
        ?string $link = null,
    ): void {
        $this->supplementarycontent = new stdClass();
        $this->supplementarycontent->text = $text;
        $this->supplementarycontent->link = $link;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $data = parent::export_for_template($output);
        // Only add supplementary content if it's set.
        if ($this->supplementarycontent !== null) {
            $data['supplementarytext'] = $this->supplementarycontent->text;
            if (!empty($this->supplementarycontent->link)) {
                $data['supplementarylink'] = $this->supplementarycontent->link;
            }
        }
        return $data;
    }

    #[\Override]
    public function get_template_name(\renderer_base $renderer): string {
        return 'core/supplementary_sticky_footer';
    }
}
