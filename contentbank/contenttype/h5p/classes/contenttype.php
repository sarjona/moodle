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
 * H5P Content bank manager class
 *
 * @package    contenttype_h5p
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_h5p;

use stdClass;
use html_writer;

/**
 * H5P Content bank manager class
 *
 * @package    contenttype_h5p
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contenttype extends \core_contentbank\contenttype {

    /** The component for H5P. */
    public const COMPONENT   = 'contenttype_h5p';

    /**
     * @var \core_h5p\factory The \core_h5p\factory object.
     */
    private $factory;

    /**
     * Fill content type.
     *
     * @param stdClass $content Content object to fill and validate
     */
    protected static function validate_content(stdClass &$content) {
        $content->contenttype = self::COMPONENT;
    }

    /**
     * Delete information related to this content.
     */
    public function clean_content(): void {
        // Delete the H5P content.
        \core_h5p\api::delete_content_from_pluginfile_url($this->get_file_url(), $this->get_factory());
    }

    /**
     * Get the core_h5p factory.
     *
     * @return factory
     */
    protected function get_factory(): \core_h5p\factory {
        if (empty($this->factory)) {
            $this->factory = new \core_h5p\factory();
        }

        return $this->factory;
    }

    /**
     * Returns the HTML content to add to view.php visualizer.
     *
     * @param stdClass $record  Th content to be displayed.
     * @return string            HTML code to include in view.php.
     * @throws \coding_exception if content is not loaded previously.
     */
    public function get_view_content(\stdClass $record): string {
        $content = new content($record);
        $fileurl = $content->get_file_url();
        $html = html_writer::tag('h2', $content->get_name());
        $html .= \core_h5p\player::display($fileurl, new \stdClass(), true);
        return $html;
    }

    /**
     * Returns the HTML code to render the icon for H5P content types.
     *
     * @param string $contentname   The contentname to add as alt value to the icon.
     * @return string            HTML code to render the icon
     * @throws \coding_exception if not loaded.
     */
    public function get_icon(string $contentname): string {
        global $OUTPUT;
        return $OUTPUT->pix_icon('f/h5p-64', $contentname, 'moodle', ['class' => 'iconsize-big']);
    }

    /**
     * Return an array of implemented features by this plugin.
     *
     * @return array
     */
    public function get_implemented_features(): array {
        return [self::CAN_UPLOAD];
    }

    /**
     * Return an array of extensions this contenttype could manage.
     *
     * @return array
     */
    public function get_manageable_extensions(): array {
        return ['.h5p'];
    }

    /**
     * Returns user has access capability for the content itself.
     *
     * @return bool     True if content could be accessed. False otherwise.
     */
    protected function is_access_allowed(): bool {
        return true;
    }
}
