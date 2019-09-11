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
 * H5P player class.
 *
 * @package    core
 * @subpackage h5p
 * @copyright  2019 Moodle
 * @author     Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

class player {
    protected $embedtype;
    protected $settings;

    /**
     * Inits the H5P player for rendering the content.
     *
     * @param string $pluginfile Local URL of the H5P file to display.
     */
    public function __construct(string $pluginfile) {
        global $CFG;

    }

    /**
     * Adds js assets to the current page.
     */
    public function addassetstopage() {
        global $PAGE, $CFG;

        foreach ($this->jsrequires as $script) {
            $PAGE->requires->js($script, true);
        }

        foreach ($this->cssrequires as $css) {
            $PAGE->requires->css($css);
        }

        // Print JavaScript settings to page.
        $PAGE->requires->data_for_js('H5PIntegration', $this->settings, true);
    }

    /**
     * Outputs an H5P content.
     */
    public function outputview() {
        if ($this->embedtype === 'div') {
            echo "<div class=\"h5p-content\" data-content-id=\"{$this->idnumber}\"></div>";
        } else {
            echo "<div class=\"h5p-iframe-wrapper\">" .
                 "<iframe id=\"h5p-iframe-{$this->idnumber}\"" .
                 " class=\"h5p-iframe\"" .
                 " data-content-id=\"{$this->idnumber}\"" .
                 " style=\"height:1px; min-width: 100%\"" .
                 " src=\"about:blank\"" .
                 " frameBorder=\"0\"" .
                 " scrolling=\"no\">" .
                 "</iframe>" .
                 "</div>";
        }
    }
}
