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
 * core_contentbank specific renderers
 *
 * @package   core_contentbank
 * @copyright  2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Class containing data for bank content
 *
 * @copyright  2020 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bankcontent implements renderable, templatable {

    /**
     * @var \core_contentbank\contenttype[]    Array of content bank contents.
     */
    private $contents;

    /**
     * @var array   $toolbar object.
     */
    private $toolbar;

    /**
     * Construct this renderable.
     *
     * @param \core_contentbank\contenttype[] $contents   Array of content bank contents.
     * @param array $toolbar     List of content bank toolbar options.
     */
    public function __construct(array $contents, array $toolbar) {
        $this->contents = $contents;
        $this->toolbar = $toolbar;
    }

    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $contentdata = array();
        foreach ($this->contents as $manager) {
            if ($manager->can_access()) {
                $contentdata[] = array(
                    'name' => $manager->get_name(),
                    'link' => $manager->get_view_url(),
                    'icon' => $manager->get_icon()
                );
            }
        }
        $data->contents = $contentdata;
        $data->tools = $this->toolbar;
        return $data;
    }
}
