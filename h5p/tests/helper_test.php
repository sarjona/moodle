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
 * Testing the H5P helper.
 *
 * @package    core_h5p
 * @category   test
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

/**
 * Test class covering the H5P helper.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper_testcase extends \advanced_testcase {

    /** @var \core_h5p\factory */
    private $factory;

    /**
     * Set up function for tests.
     */
    public function setUp() {
        $this->factory = new \core_h5p\factory();
    }

    /**
     * Test the behaviour of get_display_options().
     *
     * @dataProvider get_display_options_provider
     * @param  bool   $frame     Whether the frame should be displayed or not
     * @param  bool   $export    Whether the export action button should be displayed or not
     * @param  bool   $embed     Whether the embed action button should be displayed or not
     * @param  bool   $copyright Whether the copyright action button should be displayed or not
     * @param  int    $expected The expectation with the displayoptions value
     */
    public function test_get_display_options(bool $frame, bool $export, bool $embed, bool $copyright, int $expected): void {
        $this->resetAfterTest();

        $core = $this->factory->get_core();
        $config = (object)[
            'frame' => $frame,
            'export' => $export,
            'embed' => $embed,
            'copyright' => $copyright,
        ];
        $displayoptions = helper::get_display_options($core, $config);

        $this->assertEquals($expected, $displayoptions);
    }

    /**
     * Data provider for test_get_display_options().
     *
     * @return array
     */
    public function get_display_options_provider(): array {
        return [
            'All display options disabled' => [
                0,
                0,
                0,
                0,
                15,
            ],
            'All display options enabled' => [
                1,
                1,
                1,
                1,
                0,
            ],
            'Frame disabled and the rest enabled' => [
                0,
                1,
                1,
                1,
                0,
            ],
            'Only export enabled' => [
                0,
                1,
                0,
                0,
                12,
            ],
            'Only embed enabled' => [
                0,
                0,
                1,
                0,
                10,
            ],
            'Only copyright enabled' => [
                0,
                0,
                0,
                1,
                6,
            ],
        ];
    }

    /**
     * Test the behaviour of save_h5p().
     */
    public function test_save_h5p(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // This is a valid .H5P file.
        $path = __DIR__ . '/fixtures/greeting-card-887.h5p';
        $file = $this->create_stored_file_from_path($path);

        $factory = new \core_h5p\factory();
        $factory->get_framework()->get_file_userid($user->id);

        $config = (object)[
            'frame' => 1,
            'export' => 1,
            'embed' => 0,
            'copyright' => 0,
        ];
        // TESTING SCENARIO 1. There are some missing libraries in the system, so an error should be returned.
        $h5pid = helper::save_h5p($factory, $file, $config);
        $this->assertFalse($h5pid);
        $errors = $factory->get_framework()->getMessages('error');
        $this->assertCount(1, $errors);
        $error = reset($errors);
        $this->assertEquals('missing-required-library', $error->code);
        $this->assertEquals('Missing required library H5P.GreetingCard 1.0', $error->message);

        // TESTING SCENARIO 2. Add the required libraries for the .h5p file and save again it.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_h5p');
        $lib = $generator->create_library_record('H5P.GreetingCard', 'GreetingCard', 1, 0);
        $h5pid = helper::save_h5p($factory, $file, $config);
        $this->assertNotEmpty($h5pid);

        // No errors are raised.
        $errors = $factory->get_framework()->getMessages('error');
        $this->assertCount(0, $errors);

        // And the content in the .h5p file has been saved as expected.
        $h5p = $DB->get_record('h5p', ['id' => $h5pid]);
        $this->assertEquals($lib->id, $h5p->mainlibraryid);
        $this->assertEquals(helper::get_display_options($this->factory->get_core(), $config), $h5p->displayoptions);
        $this->assertContains('Hello world!', $h5p->jsoncontent);

        // TESTING SCENARIO 3. When saving an invalid .h5p file, an error should be raised.
        $path = __DIR__ . '/fixtures/h5ptest.zip';
        $file = $this->create_stored_file_from_path($path);
        $h5pid = helper::save_h5p($factory, $file, $config);
        $this->assertFalse($h5pid);
        $errors = $factory->get_framework()->getMessages('error');
        $this->assertCount(2, $errors);

        $expectederrorcodes = ['invalid-content-folder', 'invalid-h5p-json-file'];
        foreach ($errors as $error) {
            $this->assertContains($error->code, $expectederrorcodes);
        }
    }

    /**
     * Convenience to take a fixture test file and create a stored_file.
     *
     * @param string $filepath
     * @return stored_file
     */
    protected function create_stored_file_from_path($filepath) {
        $syscontext = \context_system::instance();
        $filerecord = [
            'contextid' => $syscontext->id,
            'component' => 'core_h5p',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => basename($filepath),
        ];

        $fs = get_file_storage();
        return $fs->create_file_from_pathname($filerecord, $filepath);
    }

}
