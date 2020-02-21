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
 * @package    contentbank_h5p
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contentbank_h5p;

use core_contentbank\base;
use stdClass;
use html_writer;

defined('MOODLE_INTERNAL') || die;

/**
 * H5P Content bank manager class
 *
 * @package    contentbank_h5p
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin extends base {

    /** The component for H5P. */
    public const COMPONENT   = 'contentbank_h5p';

    /**
     * Fill content type.
     *
     * @param stdClass $content Content object to fill and validate
     */
    protected static function validate_content(stdClass &$content) {
        $content->contenttype = self::COMPONENT;
    }

    /**
     * Returns the HTML content to add to view.php visualizer.
     *
     * @return string            HTML code to include in view.php.
     * @throws \coding_exception if content is not loaded previously.
     */
    public function get_view_content(): string {
        $fileurl = $this->get_file_url();
        $player = new \core_h5p\player($fileurl, new \stdClass());
        $html = html_writer::tag('h2', $this->get_name());
        $html .= $player->get_embed_code($fileurl, true);
        $html .= $player->get_resize_code();
        return $html;
    }

    /**
     * Returns the HTML code to render the icon for H5P content types.
     *
     * @return string            HTML code to render the icon
     * @throws \coding_exception if not loaded.
     */
    public function get_icon(): string {
        global $OUTPUT;
        return $OUTPUT->pix_icon('f/h5p-64', $this->get_name(), 'moodle', ['class' => 'iconsize-big']);
    }

    /**
     * Return an array of extensions this plugin could manage.
     *
     * @return array
     */
    public static function get_manageable_extensions(): array {
        return array('.h5p');
    }

    /**
     * Returns this plugin enables uploading.
     *
     * @param \context $context   Optional context to check (default null)
     * @return bool     True if content could be uploaded. False otherwise.
     */
    public static function can_upload(\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        $hascapability = has_capability('contentbank/h5p:additem', $context);
        return ($hascapability && parent::can_upload($context));
    }
}
