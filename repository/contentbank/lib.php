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
 * This plugin is used to access the content bank files.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * repository_contentbank class is used to browse the content bank files
 *
 * @package   repository_contentbank
 * @copyright 2020 Mihail Geshoski <mihail@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_contentbank extends repository {

    /**
     * Get file listing.
     *
     * @param string $encodedpath
     * @param string $page
     * @return array
     */
    public function get_listing($encodedpath = '', $page = '') {
        $ret = array();
        $ret['dynload'] = true;
        $ret['nosearch'] = true;
        $ret['nologin'] = true;

        // Return the parameters from the encoded path if the encoded path is not empty.
        if (!empty($encodedpath)) {
            $params = json_decode(base64_decode($encodedpath), true);
            if (is_array($params) && isset($params['contextid'])) {
                $context = context::instance_by_id(clean_param($params['contextid'], PARAM_INT));
            }
        }
        // Return the current context if the context was not specified in the encoded path.
        // The current context should be an instance of context_system, context_coursecat or course related contexts.
        if (empty($context) && !empty($this->context)) {
            if ($this->context instanceof \context_system || $this->context instanceof \context_coursecat) {
                $context = $this->context;
            } else if ($coursecontext = $this->context->get_course_context(false)) {
                $context = $coursecontext;
            }
        }
        // If not, return the system context as a default context.
        if (empty($context)) {
            $context = context_system::instance();
        }

        $ret['list'] = [];
        $ret['path'] = [];

        // Get the content bank browser for the specified context.
        if ($browser = \repository_contentbank\helper::get_contentbank_browser($context)) {
            $manageurl = new moodle_url('/contentbank/index.php', ['contextid' => $context->id]);
            $canuploadcontent = has_capability('moodle/contentbank:upload', $context);
            $ret['manage'] = $canuploadcontent ? $manageurl->out() : '';
            $ret['list'] = $browser->get_content();
            $ret['path'] = $browser->get_navigation();
        }

        return $ret;
    }

    /**
     * Does this repository used to browse moodle files?
     *
     * @return boolean
     */
    public function has_moodle_files() {
        return true;
    }

    /**
     * User cannot use the external link to dropbox
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_REFERENCE;
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }

    /**
     * Repository method to make sure that user can access particular file.
     *
     * This is checked when user tries to pick the file from repository to deal with
     * potential parameter substitutions is request
     *
     * @param string $source
     * @return bool whether the file is accessible by current user
     */
    public function file_is_accessible($source) {
        global $DB;

        $fileparams = json_decode(base64_decode($source));
        $itemid = clean_param($fileparams->itemid, PARAM_INT);

        $contentbankfile = $DB->get_record('contentbank_content', ['id' => $itemid]);

        $plugin = \core_plugin_manager::instance()->get_plugin_info($contentbankfile->contenttype);
        $managerclass = "\\$contentbankfile->contenttype\\content";
        if ($plugin && $plugin->is_enabled() && class_exists($managerclass)) {
            $contentmanager = new $managerclass($contentbankfile);
            return $contentmanager->can_view() && has_capability('moodle/contentbank:access', $this->context);
        }

        return false;
    }
}
