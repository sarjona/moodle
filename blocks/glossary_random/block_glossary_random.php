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
 * Glossary Random block.
 *
 * @package   block_glossary_random
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_glossary_random extends block_base {

    /**
     * @var cm_info|stdClass has properties 'id' (course module id) and 'uservisible'
     *     (whether the glossary is visible to the current user)
     */
    protected $glossarycm = null;

    /**
     * @var stdClass contains the glossary entry object.
     */
    private $glossaryentry = null;

    function init() {
        $this->title = get_string('pluginname','block_glossary_random');
    }

    function has_config() {
        return true;
    }

    function specialization() {
        global $CFG, $DB;

        require_once($CFG->libdir . '/filelib.php');

        $this->course = $this->page->course;

        // load userdefined title and make sure it's never empty
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname','block_glossary_random');
        } else {
            $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        }

        if (empty($this->config->glossary)) {
            return false;
        }

        if (!isset($this->config->nexttime)) {
            $this->config->nexttime = 0;
        }

        //check if it's time to put a new entry in cache
        if (time() > $this->config->nexttime) {

            if (!($cm = $this->get_glossary_cm()) || !$cm->uservisible) {
                // Skip generating of the cache if we can't display anything to the current user.
                return false;
            }

            // Get glossary entry.
            $this->glossaryentry = \block_glossary_random\helper::get_entry($this, $cm);

            $this->config->cache = $this->glossaryentry;
            $this->instance_config_commit();
        }
    }

    /**
     * Replace the instance's configuration data with those currently in $this->config;
     */
    function instance_config_commit($nolongerused = false) {
        // Unset config variables that are no longer used.
        unset($this->config->globalglossary);
        unset($this->config->courseid);
        parent::instance_config_commit($nolongerused);
    }

    /**
     * Checks if glossary is available - it should be either located in the same course or be global
     *
     * @return null|cm_info|stdClass object with properties 'id' (course module id) and 'uservisible'
     */
    public function get_glossary_cm() {
        global $DB;
        if (empty($this->config->glossary)) {
            // No glossary is configured.
            return null;
        }

        if (!empty($this->glossarycm)) {
            return $this->glossarycm;
        }

        if (!empty($this->page->course->id)) {
            // First check if glossary belongs to the current course (we don't need to make any DB queries to find it).
            $modinfo = get_fast_modinfo($this->page->course);
            if (isset($modinfo->instances['glossary'][$this->config->glossary])) {
                $this->glossarycm = $modinfo->instances['glossary'][$this->config->glossary];
                if ($this->glossarycm->uservisible) {
                    // The glossary is in the same course and is already visible to the current user,
                    // no need to check if it is global, save on DB query.
                    return $this->glossarycm;
                }
            }
        }

        // Find course module id for the given glossary, only if it is global.
        $cm = $DB->get_record_sql("SELECT cm.id, cm.visible AS uservisible
              FROM {course_modules} cm
                   JOIN {modules} md ON md.id = cm.module
                   JOIN {glossary} g ON g.id = cm.instance
             WHERE g.id = :instance AND md.name = :modulename AND g.globalglossary = 1",
            ['instance' => $this->config->glossary, 'modulename' => 'glossary']);

        if ($cm) {
            // This is a global glossary, create an object with properties 'id' and 'uservisible'. We don't need any
            // other information so why bother retrieving it. Full access check is skipped for global glossaries for
            // performance reasons.
            $this->glossarycm = $cm;
        } else if (empty($this->glossarycm)) {
            // Glossary does not exist. Remove it in the config so we don't repeat this check again later.
            //$this->config->glossary = 0;
            //$this->instance_config_commit();
        }

        return $this->glossarycm;
    }

    function instance_allow_multiple() {
    // Are you going to allow multiple instances of each block?
    // If yes, then it is assumed that the block WILL USE per-instance configuration
        return true;
    }

    function get_content() {
        if ($this->content !== NULL) {
            return $this->content;
        }
        $this->content = (object)['text' => '', 'footer' => ''];

        if (!$cm = $this->get_glossary_cm()) {
            if ($this->user_can_edit()) {
                $this->content->text = get_string('notyetconfigured', 'block_glossary_random');
            }
            return $this->content;
        }

        if (empty($this->config->cache)) {
            $this->config->cache = '';
        }

        if ($cm->uservisible) {
            // Show glossary if visible and place links in footer.
            $this->glossaryentry = $this->config->cache;
            if (has_capability('mod/glossary:write', context_module::instance($cm->id))) {
                $this->content->footer = html_writer::link(new moodle_url('/mod/glossary/edit.php', ['cmid' => $cm->id]),
                    format_string($this->config->addentry)) . '<br/>';
            }

            $this->content->footer .= html_writer::link(new moodle_url('/mod/glossary/view.php', ['id' => $cm->id]),
                format_string($this->config->viewglossary));
        } else {
            // Otherwise just place some text, no link.
            $this->content->footer = format_string($this->config->invisible);
        }

        $renderable = new \block_glossary_random\output\glossary_random($this->config, $this->glossaryentry, $this->instance->id);
        $renderer = $this->page->get_renderer('block_glossary_random');

        $this->content->text = $renderer->render($renderable);

        return $this->content;
    }

    /**
     * Return the plugin config settings for external functions.
     *
     * @return stdClass the configs for both the block instance and plugin
     * @since Moodle 3.8
     */
    public function get_config_for_external() {
        // Return all settings for all users since it is safe (no private keys, etc..).
        $configs = !empty($this->config) ? $this->config : new stdClass();

        return (object) [
            'instance' => $configs,
            'plugin' => new stdClass(),
        ];
    }
}
