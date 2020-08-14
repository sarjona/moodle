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
 * H5P Content manager class
 *
 * @package    contenttype_h5p
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_h5p;

use stdClass;
use html_writer;

/**
 * H5P Content manager class
 *
 * @package    contenttype_h5p
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends \core_contentbank\content {

    /**
     * Set a new name to the content.
     *
     * @param string $name  The name of the content.
     * @param bool $shouldbeupdated Whether this content should be updated in DB after changing the name or not.
     * @return bool  True if the content has been succesfully updated. False otherwise.
     * @throws \coding_exception if not loaded.
     */
    public function set_name(string $name, bool $shouldbeupdated = true): bool {

        $updated = parent::set_name($name, $shouldbeupdated);

        if ($updated && $shouldbeupdated) {
            // Synchronise the content name with the H5P title.
            $h5p = \core_h5p\api::get_content_from_pathnamehash($this->get_file()->get_pathnamehash());
            if (!empty($h5p)) {
                \core_h5p\local\library\autoloader::register();
                $factory = new \core_h5p\factory();
                $h5pfs = $factory->get_framework();
                $h5pcontent = $h5pfs->loadContent($h5p->id);

                // Prepare library data.
                $h5pcontent['library']['libraryId'] = $h5pcontent['libraryId'];

                // Update the title.
                $modified = false;
                $params = json_decode($h5pcontent['params']);
                if (!empty($params->metadata)) {
                    // If metadata field exists into params, update title and extraTitle.
                    if (!empty($params->metadata->title) && $params->metadata->title != $this->get_name()) {
                        $params->metadata->title = $this->get_name();
                        $modified = true;
                    }
                    if (!empty($params->metadata->extraTitle) && $params->metadata->extraTitle != $this->get_name()) {
                        $params->metadata->extraTitle = $this->get_name();
                        $modified = true;
                    }
                } else {
                    $hastitle = !empty($h5pcontent['metadata']) && !empty($h5pcontent['metadata']->title);
                    $titlehaschanged = $hastitle && $h5pcontent['metadata']->title != $this->get_name();
                    if ($titlehaschanged) {
                        // If metadata field doesn't exist into params but $h5pcontent has it, update title and extraTitle,
                        // and add $h5pcontent->metadata to $params.
                        $h5pcontent['metadata']->title = $this->get_name();
                        if (!empty($h5pcontent['metadata']->extraTitle)) {
                            $h5pcontent['metadata']->extraTitle = $this->get_name();
                        }
                        $params->metadata = $h5pcontent['metadata'];
                    } else {
                        // If there is no metadata, update the title (to avoid having it as "Untitled").
                        $params->metadata = new stdClass();
                        $params->metadata->title = $this->get_name();
                    }
                    $modified = true;
                }

                if ($modified) {
                    $h5pcontent['params'] = json_encode($params);
                }

                if (!empty($h5pcontent['title'])) {
                    $h5pcontent['title'] = $this->get_name();
                }

                // Update the content using the editor, in order to update both (h5p table and file), using the same methods
                // that the editor, to avoid duplicating this logic.
                $editor = new \core_h5p\editor();
                $editor->set_content($h5p->id);
                $library = [
                    'name' => $h5pcontent['libraryName'],
                    'majorVersion' => $h5pcontent['libraryMajorVersion'],
                    'minorVersion' => $h5pcontent['libraryMinorVersion'],
                ];
                $h5pcontent['h5plibrary'] = \H5PCore::libraryToString($library);
                $h5pcontent['h5pparams'] = $h5pcontent['params'];

                $editor->save_content((object)$h5pcontent);
            }
        }

        return $updated;
    }
}
