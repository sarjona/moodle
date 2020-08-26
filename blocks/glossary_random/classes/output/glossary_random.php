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
 * Class containing data for glossary_random block.
 *
 * @package    block_glossary_random
 * @copyright  2020 Luca Bösch <luca.boesch@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_glossary_random\output;

defined('MOODLE_INTERNAL') || die();

use block_glossary_random\helper;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class containing data for glossary_random block.
 *
 * @copyright  2020 Luca Bösch <luca.boesch@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossary_random implements renderable, templatable {

    /**
     * @var object An object containing the configuration information for the current instance of this block.
     */
    protected $config;

    /**
     * @var object An object containing the information to render the current glossary entry in this block.
     */
    protected $glossaryentry;

    /**
     * @var object An object containing the instance id of this block.
     */
    protected $blockinstanceid;

    /**
     * Constructor.
     *
     * @param object $config An object containing the configuration information for the current instance of this block.
     * @param object $glossaryentry An object containing the information to render the current glossary entry in this block.
     */
    public function __construct($config, $glossaryentry, $blockinstanceid) {
        $this->config = $config;
        $this->glossaryentry = $glossaryentry;
        $this->blockinstanceid = $blockinstanceid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $this->glossaryentry->showconcept = empty($this->glossaryentry->concept) ? 0 : $this->config->showconcept;
        $this->glossaryentry->showrefreshbutton = isset($this->config->showrefreshbutton) ? $this->config->showrefreshbutton : 0;
        $this->glossaryentry->blockinstanceid = $this->blockinstanceid;
        $this->glossaryentry->reloadtime =
                isset($this->config->updatedynamically) ? helper::get_updatedynamically_time($this->config->updatedynamically) : 0;

        return $this->glossaryentry;
    }
}
