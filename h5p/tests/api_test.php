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
 * Testing the H5P API.
 *
 * @package    core_h5p
 * @category   test
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types = 1);

namespace core_h5p;

use advanced_testcase;

/**
 * Test class covering the H5P API.
 *
 * @package    core_h5p
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_testcase extends \advanced_testcase {

    /**
     * Set up function for tests.
     */
    public function setUp() {
        $this->factory = new \core_h5p\factory();
    }

    /**
     * Test the behaviour of get_content_from_pluginfile_url().
     */
    public function test_get_content_from_pluginfile_url(): void {
        $this->setRunTestInSeparateProcess(true);
        $this->resetAfterTest();

        // Create the H5P data.
        $filename = 'find-the-words.h5p';
        $path = __DIR__ . '/fixtures/' . $filename;
        $fakefile = helper::create_fake_stored_file_from_path($path);
        $config = (object)[
            'frame' => 1,
            'export' => 1,
            'embed' => 0,
            'copyright' => 0,
        ];

        // Get URL for this H5P content file.
        $syscontext = \context_system::instance();
        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            \core_h5p\file_storage::COMPONENT,
            'unittest',
            $fakefile->get_itemid(),
            '/',
            $filename
        );

        // Scenario 1: Get the H5P for this URL and check there isn't any existing H5P (because it hasn't been saved).
        list($newfile, $h5p) = api::get_content_from_pluginfile_url($url->out());
        $this->assertEquals($fakefile->get_pathnamehash(), $newfile->get_pathnamehash());
        $this->assertEquals($fakefile->get_contenthash(), $newfile->get_contenthash());
        $this->assertFalse($h5p);

        // Scenario 2: Save the H5P and check now the H5P is exactly the same as the original one.
        $h5pid = helper::save_h5p($this->factory, $fakefile, $config);
        list($newfile, $h5p) = api::get_content_from_pluginfile_url($url->out());

        $this->assertEquals($h5pid, $h5p->id);
        $this->assertEquals($fakefile->get_pathnamehash(), $h5p->pathnamehash);
        $this->assertEquals($fakefile->get_contenthash(), $h5p->contenthash);

        // Scenario 3: Get the H5P for an unexisting H5P file.
        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            \core_h5p\file_storage::COMPONENT,
            'unittest',
            $fakefile->get_itemid(),
            '/',
            'unexisting.h5p'
        );
        list($newfile, $h5p) = api::get_content_from_pluginfile_url($url->out());
        $this->assertFalse($newfile);
        $this->assertFalse($h5p);
    }

    /**
     * Test the behaviour of create_content_from_pluginfile_url().
     */
    public function test_create_content_from_pluginfile_url(): void {
        global $DB;

        $this->setRunTestInSeparateProcess(true);
        $this->resetAfterTest();

        // Create the H5P data.
        $filename = 'find-the-words.h5p';
        $path = __DIR__ . '/fixtures/' . $filename;
        $fakefile = helper::create_fake_stored_file_from_path($path);
        $config = (object)[
            'frame' => 1,
            'export' => 1,
            'embed' => 0,
            'copyright' => 0,
        ];

        // Get URL for this H5P content file.
        $syscontext = \context_system::instance();
        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            \core_h5p\file_storage::COMPONENT,
            'unittest',
            $fakefile->get_itemid(),
            '/',
            $filename
        );

        // Scenario 1: Create the H5P from this URL and check the content is exactly the same as the fake file.
        $messages = new \stdClass();
        list($newfile, $h5pid) = api::create_content_from_pluginfile_url($url->out(), $config, $this->factory, $messages);
        $this->assertNotFalse($h5pid);
        $h5p = $DB->get_record('h5p', ['id' => $h5pid]);
        $this->assertEquals($fakefile->get_pathnamehash(), $h5p->pathnamehash);
        $this->assertEquals($fakefile->get_contenthash(), $h5p->contenthash);
        $this->assertTrue(empty($messages->error));
        $this->assertTrue(empty($messages->info));

        // Scenario 2: Create the H5P for an unexisting H5P file.
        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            \core_h5p\file_storage::COMPONENT,
            'unittest',
            $fakefile->get_itemid(),
            '/',
            'unexisting.h5p'
        );
        list($newfile, $h5p) = api::create_content_from_pluginfile_url($url->out(), $config, $this->factory, $messages);
        $this->assertFalse($newfile);
        $this->assertFalse($h5p);
        $this->assertTrue(empty($messages->error));
        $this->assertTrue(empty($messages->info));
    }

    /**
     * Test the behaviour of delete_content_from_pluginfile_url().
     */
    public function test_delete_content_from_pluginfile_url(): void {
        global $DB;

        $this->setRunTestInSeparateProcess(true);
        $this->resetAfterTest();

        // Create the H5P data.
        $filename = 'find-the-words.h5p';
        $path = __DIR__ . '/fixtures/' . $filename;
        $fakefile = helper::create_fake_stored_file_from_path($path);
        $config = (object)[
            'frame' => 1,
            'export' => 1,
            'embed' => 0,
            'copyright' => 0,
        ];

        // Get URL for this H5P content file.
        $syscontext = \context_system::instance();
        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            \core_h5p\file_storage::COMPONENT,
            'unittest',
            $fakefile->get_itemid(),
            '/',
            $filename
        );


        // Scenario 1: Try to remove the H5P content for an undeployed file.
        list($newfile, $h5p) = api::get_content_from_pluginfile_url($url->out());
        $this->assertEquals(0, $DB->count_records('h5p'));
        api::delete_content_from_pluginfile_url($url->out(), $this->factory);
        $this->assertEquals(0, $DB->count_records('h5p'));

        // Scenario 2: Deploy an H5P from this URL, check it's created, remove it and check it has been removed as expected.
        $this->assertEquals(0, $DB->count_records('h5p'));

        $messages = new \stdClass();
        list($newfile, $h5pid) = api::create_content_from_pluginfile_url($url->out(), $config, $this->factory, $messages);
        $this->assertEquals(1, $DB->count_records('h5p'));

        api::delete_content_from_pluginfile_url($url->out(), $this->factory);
        $this->assertEquals(0, $DB->count_records('h5p'));

        // Scenario 3: Try to remove the H5P for an unexisting H5P URL.
        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            \core_h5p\file_storage::COMPONENT,
            'unittest',
            $fakefile->get_itemid(),
            '/',
            'unexisting.h5p'
        );
        $this->assertEquals(0, $DB->count_records('h5p'));
        api::delete_content_from_pluginfile_url($url->out(), $this->factory);
        $this->assertEquals(0, $DB->count_records('h5p'));
    }
}
