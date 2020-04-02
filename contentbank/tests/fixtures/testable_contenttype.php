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
 * Testable contenttype plugin class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contenttype_testable;

/**
 * Testable contenttype plugin class.
 *
 * @package    core_contentbank
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contenttype extends \core_contentbank\contenttype {

    /** The component for testing. */
    public const COMPONENT   = 'contenttype_testable';

    /**
     * Fill content type.
     *
     * @param \stdClass $content Content object to fill and validate
     */
    protected static function validate_content(\stdClass &$content) {
        $content->contenttype = self::COMPONENT;
    }

    /**
     * Returns the URL where the content will be visualized.
     *
     * @return string            URL where to visualize the given content.
     * @throws \coding_exception if not loaded.
     */
    public function get_view_url(): string {
        $fileurl = $this->get_file_url($this->get_id());
        $url = $fileurl."?forcedownload=1";

        return $url;
    }

    /**
     * Returns the HTML code to render the icon for the testable content types.
     *
     * @return string            HTML code to render the icon
     * @throws \coding_exception if not loaded.
     */
    public function get_icon(): string {
        global $OUTPUT;

        return $OUTPUT->pix_icon('f/archive-64', $this->get_name(), 'moodle', ['class' => 'iconsize-big']);
    }

    /**
     * Returns user has access capability for the content itself.
     *
     * @return bool     True if content could be accessed. False otherwise.
     */
    protected function is_access_allowed(): bool {
        return true;
    }

    /**
     * Return an array of implemented features by this plugin.
     *
     * @return array
     */
    public static function get_implemented_features(): array {
        return [];
    }

    /**
     * Return an array of extensions this plugin could manage.
     *
     * @return array
     */
    public static function get_manageable_extensions(): array {
        return  ['.txt', '.png', '.h5p'];
    }
}
