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
 * Content manager class
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

/**
 * Content manager class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class content {

    /** @var stdClass $content The content of the current instance. **/
    protected $content  = null;

    /**
     * Content bank constructor
     *
     * @param stdClass $content     A contentbanck_content record.
     * @throws \coding_exception    If content type is not right.
     */
    public function __construct(stdClass $content) {
        // Content type should exist and be linked to plugin classname.
        $classname = $content->contenttype.'\\content';
        if (get_class($this) != $classname) {
            throw new coding_exception(get_string('contentbanktypenotfound', 'error', $content->contenttype));
        }
        $typeclass = $content->contenttype.'\\contenttype';
        if (!class_exists($typeclass)) {
            throw new coding_exception(get_string('contentbanktypenotfound', 'error', $content->contenttype));
        }
        $this->content = $content;
    }

    /**
     * Fills content_bank table with appropiate information.
     *
     * @param stdClass $content  An optional content record compatible object (default null)
     * @return content       Object with content bank information.
     * @throws \coding_exception    If content type is not right.
     */
    final public static function create_content(stdClass $content = null): ?content {
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
            $classname = '\\'.$record->contenttype.'\\content';
            return new $classname($record);
        }
        return null;
    }

    /**
     * Plugins need to implement this function at least to fill the contenttype field.
     *
     * @param stdClass $content Content object to fill and validate
     */
    abstract protected static function validate_content(stdClass &$content);

    /**
     * Returns $this->content.
     *
     * @return stdClass  $this->content.
     */
    public function get_content(): stdClass {
        return $this->content;
    }

    /**
     * Returns $this->content->contenttype.
     *
     * @return string  $this->content->contenttype.
     */
    public function get_content_type(): string {
        return $this->content->contenttype;
    }


    /**
     * Delete this content from the content_bank.
     *
     * @param stdClass $record  Content record compatible object to delete.
     * @return boolean true if the content has been deleted; false otherwise.
     */
    public static function delete_content(stdClass $record): bool {
        global $DB;

        $classname = "\\$record->contenttype\\content";
        if (class_exists($classname)) {
            $content = new $classname($record);
            // Call the clean method in order to give the chance to the plugins to clean related information.
            $content->clean_content();

            // Delete the file if it exists.
            if ($file = $content->get_file()) {
                $file->delete();
            }
        }

        // Delete the contentbank DB entry.
        $DB->delete_records('contentbank_content', ['id' => $record->id]);

        return true;
    }

    /**
     * Clean the information related to this content.
     * This method will be called from delete_content and should be implemented by the plugins for deleting the information
     * related to this content when it is removed.
     */
    protected function clean_content(): void {
    }

    /**
     * Updates content_bank table with information in $this->content.
     *
     * @return boolean  True if the content has been succesfully updated. False otherwise.
     * @throws \coding_exception if not loaded.
     * @throws \dml_exception if the content record to update is not correct.
     */
    public function update_content(): bool {
        global $USER, $DB;

        $this->content->usermodified = $USER->id;
        $this->content->timemodified = time();
        return $DB->update_record('contentbank_content', $this->content);
    }

    /**
     * Set a new name to the content.
     *
     * @param string $name The name of the content.
     * @return bool  True if the content has been succesfully updated. False otherwise.
     * @throws \dml_exception
     * @throws coding_exception if not loaded.
     */
    public function set_name(string $name): bool {
        if (empty($name)) {
            return false;
        }

        // Clean name.
        $name = clean_param($name, PARAM_TEXT);
        if (\core_text::strlen($name) > 255) {
            $name = \core_text::substr($name, 0, 255);
        }

        $oldcontent = $this->content;
        $this->content->name = $name;
        $updated = $this->update_content();
        if (!$updated) {
            $this->content = $oldcontent;
        }
        return $updated;
    }

    /**
     * Check if the user can delete this content.
     *
     * @return bool     True if content could be uploaded. False otherwise.
     */
    public function can_delete(): bool {
        global $USER;

        $context = \context::instance_by_id($this->content->contextid, MUST_EXIST);

        $hascapability = has_capability('moodle/contentbank:deleteanycontent', $context);
        if ($this->content->usercreated == $USER->id) {
            // This content has been created by the current user; check if she can delete her content.
            $hascapability = $hascapability || has_capability('moodle/contentbank:deleteowncontent', $context);
        }

        return $hascapability;
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
    public function get_id(): int {
        return $this->content->id;
    }

    /**
     * Change the content instanceid value.
     *
     * @param int $instanceid    New instanceid for this content
     * @return boolean           True if the instanceid has been succesfully updated. False otherwise.
     * @throws \coding_exception if not loaded.
     * @throws \dml_exception if the content record to update is not correct.
     */
    public function set_instanceid(int $instanceid): bool {
        $this->content->instanceid = $instanceid;
        return $this->update_content();
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
     * Change the content config values.
     *
     * @param string $configdata    New config information for this content
     * @return boolean              True if the configdata has been succesfully updated. False otherwise.
     * @throws \coding_exception if not loaded.
     * @throws \dml_exception if the content record to update is not correct.
     */
    public function set_configdata(string $configdata): bool {
        $this->content->configdata = $configdata;
        return $this->update_content();
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
     * Returns the $file related to this content.
     *
     * @return stored_file  File stored in content bank area related to the given itemid.
     * @throws \coding_exception if not loaded.
     */
    public function get_file(): ?stored_file {
        $itemid = $this->get_id();
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->content->contextid,
            'contentbank',
            'public',
            $itemid,
            'itemid, 
            filepath, 
            filename',
            false
        );
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
            $this->content->contextid,
            'contentbank',
            'public',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        return $fileurl;
    }

    /**
     * Returns user has access permission for the content itself (based on what plugin needs).
     *
     * @return bool     True if content could be accessed. False otherwise.
     */
    public function can_view(): bool {
        // There's no capability at content level to check,
        // but plugins can overwrite this method in case they want to check something related to content properties.
        return true;
    }

    /**
     * Check if the user can edit this content.
     *
     * @return bool     True if content could be edited. False otherwise.
     */
    public final function can_manage(): bool {
        global $USER;

        $context = \context::instance_by_id($this->content->contextid, MUST_EXIST);
        // Check main contentbank management permission.
        $hascapability = has_capability('moodle/contentbank:manageanycontent', $context);
        if ($this->content->usercreated == $USER->id) {
            // This content has been created by the current user; check if they can manage their content.
            $hascapability = $hascapability || has_capability('moodle/contentbank:manageowncontent', $context);
        }
        return $hascapability;
    }
}
