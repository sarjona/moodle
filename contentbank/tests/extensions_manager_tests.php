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
 * Test for extensions manager.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test for extensions manager.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_contentbank_extensions_testcase extends advanced_testcase {

    /**
     * Tests for uploaded file.
     */
    public function test_get_extension_supporter() {
        $this->resetAfterTest();

        $extensions = \core_contentbank\extensions::instance();

        // Create content.
        $record = new stdClass();
        $content = contentbank_h5p\plugin::create_content($record);
        $classname = '\\'.get_class($content);

        // Create a dummy file.
        $filename = 'content.h5p';
        $dummy = array(
            'contextid' => context_system::instance()->id,
            'component' => 'contentbank',
            'filearea' => 'public',
            'itemid' => $content->get_id(),
            'filepath' => '/',
            'filename' => $filename
        );
        $fs = get_file_storage();
        $fs->create_file_from_string($dummy, 'dummy content');

        $file = $content->get_file();
        $extension = $extensions->get_extension($file->get_filename());

        $this->assertEquals('.h5p', $extension);
        $this->assertEquals($classname, $extensions->get_extension_supporter($extension));
    }
}
