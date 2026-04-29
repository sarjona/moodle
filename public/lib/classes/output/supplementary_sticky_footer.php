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

/**
 * Sticky footer class with supplementary content.
 *
 * @package    core_courseformat
 * @copyright  2026 Sara Arjona <sara@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplementary_sticky_footer extends \core\output\sticky_footer {
    /**
     * Constructor.
     *
     * @param string $stickycontent the footer content
     * @param string|null $supplementarytext the supplementary text
     * @param string|null $supplementarylink the supplementary link
     */
    public function __construct(
        string $stickycontent,
        /** @var string|null $supplementarytext the supplementary text */
        protected ?string $supplementarytext = null,
        /** @var string|null $supplementarylink the supplementary link */
        protected ?string $supplementarylink = null,
    ) {
        parent::__construct(
            $stickycontent,
            'course-linear-navigation justify-content-end',
        );
    }

    /**
     * Add supplementary content to the sticky footer.
     *
     * @param string|null $text
     * @param string|null $link
     */
    public function add_supplementary_content(
        ?string $text = null,
        ?string $link = null,
    ): void {
        $this->supplementarytext = $text;
        $this->supplementarylink = $link;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $data = parent::export_for_template($output);
        $data['supplementarytext'] = $this->supplementarytext;
        $data['supplementarylink'] = $this->supplementarylink;
        return $data;
    }

    #[\Override]
    public function get_template_name(\renderer_base $renderer): string {
        return 'core/supplementary_sticky_footer';
    }
}
