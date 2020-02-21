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
 * Content bank manager class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank;

use stored_file;
use stdClass;
use coding_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Content bank manager class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base {

    /** @var stdClass This content's context. **/
    protected $context = null;

    /** @var stdClass $content The object to manage this content. **/
    protected $content  = null;

    /**
     * Content bank constructor
     *
     * @param stdClass $content     A contentbanck_content record.
     * @throws \coding_exception    If content type is not right.
     */
    public function __construct(stdClass $content) {
        // Conten type should exist and be linked to plugin classname.
        $classname = $content->contenttype.'\\plugin';
        if (get_class($this) != $classname) {
            throw new coding_exception(get_string('contentbanktypenotfound', 'error', $content->contenttype));
        }
        $this->content = $content;
        $this->context = \context::instance_by_id($content->contextid);
    }

    /**
     * Fills content_bank table with appropiate information.
     *
     * @param stdClass $content  An optional content record compatible object (default null)
     * @return stdClass          Object with content bank information.
     * @throws \coding_exception If is called direct from 'base' class.
     */
    public static function create_content(stdClass $content = null): ?base {
        global $USER, $DB;

        $record = new stdClass();
        $record->name = $content->name ?? '';
        $record->contenttype = $content->contenttype ?? '';
        $record->contextid = $content->contextid ?? \context_system::instance()->id;
        $record->usercreated = $content->usercreated ?? $USER->id;
        $record->timecreated = time();
        $record->usermodified = $record->usercreated;
        $record->timemodified = $record->timecreated;
        $record->configdata = $content->configdata ?? '';

        static::validate_content($record);

        $record->id = $DB->insert_record('contentbank_content', $record);
        if ($record->id) {
            $classname = '\\'.$record->contenttype.'\\plugin';
            return new $classname($record);
        }
        return null;
    }

    /**
     * Validates it's called from the plugin and not from 'base' class.
     *
     * @param stdClass $content Content object to fill and validate
     * @throws coding_exception If is called direct from 'base' class.
     */
    protected static function validate_content(stdClass &$content) {
        // Don't allow 'base' class validate_content() to be called. This function should be called in the plugin.
        throw new coding_exception('create_content() in \'base\' is not allowed. Please use plugin\'s create_content() instead.');
    }

    /**
     * Updates content_bank table with information in $this->content.
     *
     * @return boolean  True if the content has been succesfully updated. False otherwise.
     * @throws \coding_exception if not loaded.
     */
    public function update_content(): bool {
        global $USER, $DB;

        $content = $this->content;
        $content->usermodified = $USER->id;
        $content->timemodified = time();
        return $DB->update_record('contentbank_content', $content);
    }

    /**
     * Returns the name of the content.
     *
     * @return string   The name of the content.
     * @throws \coding_exception if not loaded.
     */
    public function get_name(): string {
        return $this->content->name;
    }

    /**
     * Returns the content ID.
     *
     * @return int   The content ID.
     * @throws \coding_exception if not loaded.
     */
    public function get_id(): string {
        return $this->content->id;
    }

    /**
     * Returns the contenttype of this content.
     *
     * @return string   $this->content->contenttype
     * @throws \coding_exception if not loaded.
     */
    public function get_content_type(): string {
        return $this->content->contenttype;
    }

    /**
     * Returns the $instanceid of this content.
     *
     * @return int   contentbank instanceid
     * @throws \coding_exception if not loaded.
     */
    public function get_instanceid(): int {
        return $this->content->instanceid;
    }

    /**
     * Returns the $file related to this content.
     *
     * @return stored_file  File stored in content bank area related to the given itemid.
     * @throws \coding_exception if not loaded.
     */
    public function get_file(): ?stored_file {
        $itemid = $this->get_id();
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'contentbank', 'public', $itemid, 'itemid, filepath, filename', false);
        if (!empty($files)) {
            $file = reset($files);
            return $file;
        }
        return null;
    }

    /**
     * Returns the file url related to this content.
     *
     * @return string       URL of the file stored in content bank area related to the given itemid.
     * @throws \coding_exception if not loaded.
     */
    public function get_file_url(): string {
        if (!$file = $this->get_file()) {
            return '';
        }
        $fileurl = moodle_url::make_pluginfile_url(
            $this->context->id,
            'contentbank',
            'public',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        return $fileurl;
    }

    /**
     * Return the content config values.
     *
     * @return mixed   Config information for this content (json decoded)
     * @throws \coding_exception if not loaded.
     */
    public function get_configdata() {
        return $this->content->configdata;
    }

    /**
     * Change the content config values.
     *
     * @param string $configdata    New config information for this content
     * @return boolean              True if the configdata has been succesfully updated. False otherwise.
     * @throws \coding_exception if not loaded.
     */
    public function set_configdata(string $configdata): bool {
        $this->content->configdata = $configdata;
        return $this->update_content();
    }

    /**
     * Returns the URL where the content will be visualized.
     *
     * @return string            URL where to visualize the given content.
     * @throws \coding_exception if not loaded.
     */
    public function get_view_url(): string {
        return new moodle_url('/contentbank/view.php', ['id' => $this->get_id()]);
    }

    /**
     * Returns the HTML content to add to view.php visualizer.
     *
     * @return string            HTML code to include in view.php.
     * @throws \coding_exception if not loaded.
     */
    public function get_view_content(): string {
        // Plugins would manage visualization. Content bank does visualize any content by itself.
        return '';
    }

    /**
     * Returns the HTML code to render the icon for content bank contents.
     *
     * @return string           HTML code to render the icon
     * @throws \coding_exception if not loaded.
     */
    public function get_icon(): string {
        global $OUTPUT;
        return $OUTPUT->pix_icon('f/unknown-64', $this->get_name(), 'moodle', ['class' => 'iconsize-big']);
    }

    /**
     * Return an array of extensions the plugin could manage.
     *
     * @return array
     */
    public static function get_manageable_extensions(): array {
        // Plugins would manage extensions. Content bank does not manage any extension by itself.
        return array();
    }

    /**
     * Returns the content type enables uploading.
     *
     * @param \context $context   Optional context to check (default null)
     * @return bool     True if content could be uploaded. False otherwise.
     */
    public static function can_upload(\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return has_capability('moodle/contentbank:upload', $context);
    }
}
