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
 * Testing the H5PFrameworkInterface interface implementation.
 *
 * @package    core_h5p
 * @category   test
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \core_h5p\framework;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * Test class covering the H5PFrameworkInterface interface implementation.
 *
 * @package    core_h5p
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_testcase extends advanced_testcase {

    // Test the behaviour of getPlatformInfo().
    public function test_getPlatformInfo() {
        global $CFG;

        $this->resetAfterTest();

        $CFG->version = "2019083000.05";

        $interface = framework::instance('interface');
        $platforminfo = $interface->getPlatformInfo();

        $expected = array(
            'name' => 'Moodle',
            'version' => '2019083000.05',
            'h5pVersion' => '2019083000.05'
        );

        $this->assertEquals($expected, $platforminfo);
    }

    // Test the behaviour of fetchExternalData().
    public function test_fetchExternalData() {
        global $CFG;

        $this->resetAfterTest();

        // Provide a valid URL to an external H5P content.
        $url = "https://h5p.org/sites/default/files/h5p/exports/arithmetic-quiz-22-57860.h5p";

        $interface = framework::instance('interface');
        // Test fetching an external H5P content without defining a path to where the file should be stored.
        $data = $interface->fetchExternalData($url, null, true);
        // The response should not be empty and return true if the file was successfully downloaded.
        $this->assertNotEmpty($data);
        $this->assertTrue($data);
        // The uploaded file should exist on the filesystem.
        $h5pfolderpath = $interface->getUploadedH5pFolderPath();
        $this->assertTrue(file_exists($h5pfolderpath . '.h5p'));

        $h5pfolderpath = $CFG->tempdir . uniqid('/h5p-');
        $data = $interface->fetchExternalData($url, null, true, $h5pfolderpath . '.h5p');
        // The response should not be empty and return true if the content has been successfully saved to a file.
        $this->assertNotEmpty($data);
        $this->assertTrue($data);
        // The uploaded file should exist on the filesystem.
        $this->assertTrue(file_exists($h5pfolderpath . '.h5p'));

        // Provide an URL to an external file that is not an H5P content file.
        $url = "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf";

        $data = $interface->fetchExternalData($url, null, true);
        // The response should not be empty and return true if the content has been successfully saved to a file.
        $this->assertNotEmpty($data);
        $this->assertTrue($data);

        // The uploaded file should exist on the filesystem with it's original extension.
        // NOTE: The file would be later validated by the H5P Validator.
        $h5pfolderpath = $interface->getUploadedH5pFolderPath();
        $this->assertTrue(file_exists($h5pfolderpath . '.pdf'));

        // Provide an invalid URL to an external file.
        $url = "someprotocol://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf";
        $data = $interface->fetchExternalData($url, null, true);
        // The response should be empty.
        $this->assertEmpty($data);
    }

    // Test the behaviour of setErrorMessage().
    public function test_setErrorMessage() {
        $message = "Error message";
        $code = '404';

        $interface = framework::instance('interface');
        // Set an error message.
        $interface->setErrorMessage($message, $code);
        // Get the error messages.
        $errormessages = $interface->getMessages('error');
        $expected = new stdClass();
        $expected->code = 404;
        $expected->message = 'Error message';
        $this->assertEquals($expected, $errormessages[0]);
    }

    // Test the behaviour of setInfoMessage().
    public function test_setInfoMessage() {
        $message = "Info message";

        $interface = framework::instance('interface');
        // Set an info message.
        $interface->setInfoMessage($message);
        // Get the info messages.
        $infomessages = $interface->getMessages('info');
        $expected = 'Info message';
        $this->assertEquals($expected, $infomessages[0]);
    }

    // Test the behaviour of getMessages().
    public function test_getMessages() {
        $infomessage = "Info message";
        $errormessage1 = "Error message 1";
        $errorcode1 = 404;
        $errormessage2 = "Error message 2";
        $errorcode2 = 403;

        $interface = framework::instance('interface');
        // Set an info message.
        $interface->setInfoMessage($infomessage);
        // Set an error message.
        $interface->setErrorMessage($errormessage1, $errorcode1);
         // Set another error message.
        $interface->setErrorMessage($errormessage2, $errorcode2);

        // Get the info messages.
        $infomessages = $interface->getMessages('info');
        $expected = 'Info message';
        $this->assertEquals($expected, $infomessages[0]);
        // Make sure the info messages have now been removed.
        $infomessages = $interface->getMessages('info');
        $this->assertEmpty($infomessages);

        // Get the error messages.
        $errormessages = $interface->getMessages('error');
        $this->assertEquals(2, count($errormessages));
        $expected1 = new stdClass();
        $expected1->code = 404;
        $expected1->message = 'Error message 1';
        $expected2 = new stdClass();
        $expected2->code = 403;
        $expected2->message = 'Error message 2';

        $this->assertEquals($expected1, $errormessages[0]);
        $this->assertEquals($expected2, $errormessages[1]);

         // Make sure the info messages have now been removed.
        $errormessages = $interface->getMessages('error');
        $this->assertEmpty($errormessages);
    }

    // Test the behaviour of t().
    public function test_t() {
        $message1 = 'No copyright information available for this content.';
        $message2 = 'Illegal option %option in %library';
        $message3 = 'Random message %option';

        $interface = framework::instance('interface');

        // Existing language string without passed arguments.
        $translation1 = $interface->t($message1);
        // Existing language string with passed arguments.
        $translation2 = $interface->t($message2, ['%option' => 'example', '%library' => 'Test library']);
        // Non-existing lenguage string.
        $translation3 = $interface->t($message3);

        // Make sure the string translation has been returned.
        $this->assertEquals('No copyright information available for this content.', $translation1);
        // Make sure the string translation has been returned.
        $this->assertEquals('Illegal option example in Test library', $translation2);
        // As the string does not exist in the mapping array, the passed message should be returned.
        $this->assertEquals('Random message %option', $translation3);
    }

    // Test the behaviour of loadAddons().
    public function test_loadAddons() {
        $this->resetAfterTest();

        // Create a Library addon (1.1).
        $this->create_library_record('Library', 'Lib', 1, 1, 2,
            '', '/regex1/');
        // Create a Library addon (1.3).
        $this->create_library_record('Library', 'Lib', 1, 3, 2,
            '', '/regex2/');
        // Create a Library addon (1.2).
        $this->create_library_record('Library', 'Lib', 1, 2, 2,
            '', '/regex3/');

        $interface = framework::instance('interface');
        $addons = $interface->loadAddons();

        // The addons array should return 1 result.
        $this->assertCount(1, $addons);
        // The library addon 1.3 should be returned as it is the latest version of the addon.
        $this->assertEquals('Library', $addons[0]['machineName']);
        $this->assertEquals(1, $addons[0]['majorVersion']);
        $this->assertEquals(3, $addons[0]['minorVersion']);

        // Create a Library addon (2.2)
        $this->create_library_record('Library', 'Lib', 2, 2, 2,
            '', '/regex4/');

        $addons = $interface->loadAddons();

        // Now the library addon 2.2 should be returned as it is the latest version of the addon.
        $this->assertEquals('Library', $addons[0]['machineName']);
        $this->assertEquals(2, $addons[0]['majorVersion']);
        $this->assertEquals(2, $addons[0]['minorVersion']);

        // Create a Library1 addon (1.2)
        $this->create_library_record('Library1', 'Lib1', 1, 2, 2,
            '', '/regex11/');

        $addons = $interface->loadAddons();
        // The addons array should return 2 results (Library and Library1 addon).
        $this->assertCount(2, $addons);
        $this->assertEquals('Library', $addons[0]['machineName']);
        $this->assertEquals(2, $addons[0]['majorVersion']);
        $this->assertEquals(2, $addons[0]['minorVersion']);
        $this->assertEquals('Library1', $addons[1]['machineName']);
        $this->assertEquals(1, $addons[1]['majorVersion']);
        $this->assertEquals(2, $addons[1]['minorVersion']);
    }

    // Test the behaviour of loadLibraries().
    public function test_loadLibraries() {
        $this->resetAfterTest();

        $this->generate_h5p_data();

        $interface = framework::instance('interface');
        $libraries = $interface->loadLibraries();

        $this->assertNotEmpty($libraries);
        $this->assertCount(6, $libraries);
        $this->assertEquals('MainLibrary', $libraries['MainLibrary'][0]->machine_name);
        $this->assertEquals('1', $libraries['MainLibrary'][0]->major_version);
        $this->assertEquals('0', $libraries['MainLibrary'][0]->minor_version);
        $this->assertEquals('1', $libraries['MainLibrary'][0]->patch_version);
        $this->assertEquals('MainLibrary', $libraries['MainLibrary'][0]->machine_name);
    }

    // Test the behaviour of test_getLibraryId().
    public function test_getLibraryId() {
        $this->resetAfterTest();
        // Create a library.
        $lib = $this->create_library_record('TestLibrary', 'Test', 1, 1, 2);
        $interface = framework::instance('interface');
        $libraryid = $interface->getLibraryId('TestLibrary');
        $this->assertNotFalse($libraryid);
        $this->assertEquals($lib->id, $libraryid);
        // Attempt to get the library ID for a non-existent machine name.
        $libraryid = $interface->getLibraryId('Library1');
        $this->assertFalse($libraryid);
        // Attempt to get the library ID for a non-existent major version.
        $libraryid = $interface->getLibraryId('TestLibrary', 2);
        $this->assertFalse($libraryid);
        // Attempt to get the library ID for a non-existent minor version.
        $libraryid = $interface->getLibraryId('TestLibrary', 1, 2);
        $this->assertFalse($libraryid);
    }

    // Test the behaviour of isPatchedLibrary().
    public function test_isPatchedLibrary() {
        $this->resetAfterTest();
        // Create a library.
        $this->create_library_record('TestLibrary', 'Test', 1, 1, 2);
        $interface = framework::instance('interface');
        $library = array(
            'machineName' => 'TestLibrary',
            'majorVersion' => '1',
            'minorVersion' => '1',
            'patchVersion' => '2'
        );
        // The $library should not be a patched version of present library.
        $ispatched = $interface->isPatchedLibrary($library);
        $this->assertFalse($ispatched);
        // The $library should not be a patched version of present library.
        $library['patchVersion'] = '3';
        $ispatched = $interface->isPatchedLibrary($library);
        $this->assertTrue($ispatched);
        // The $library with a different minor version should not be a patched version of present library.
        $library['minorVersion'] = '2';
        $ispatched = $interface->isPatchedLibrary($library);
        $this->assertFalse($ispatched);
    }

    // Test the behaviour of isInDevMode().
    public function test_isInDevMode() {
        $interface = framework::instance('interface');
        $isdevmode = $interface->isInDevMode();
        $this->assertFalse($isdevmode);
    }

    // Test the behaviour of mayUpdateLibraries().
    public function test_mayUpdateLibraries() {
        $interface = framework::instance('interface');
        $mayupdatelib = $interface->mayUpdateLibraries();
        $this->assertTrue($mayupdatelib);
    }

    // Test the behaviour of saveLibraryData().
    public function test_saveLibraryData() {
        global $DB;

        $this->resetAfterTest();

        $interface = framework::instance('interface');
        $librarydata = array(
            'title' => 'Test',
            'machineName' => 'TestLibrary',
            'majorVersion' => '1',
            'minorVersion' => '0',
            'patchVersion' => '2',
            'runnable' => 1,
            'fullscreen' => 1,
            'preloadedJs' => array(
                array(
                    'path' => 'js/name.min.js'
                )
            ),
            'preloadedCss' => array(
                array(
                    'path' => 'css/name.css'
                )
            ),
            'dropLibraryCss' => array(
                array(
                    'machineName' => 'Name2'
                )
            )
        );
        // Create new library.
        $interface->saveLibraryData($librarydata);
        $library = $DB->get_record('h5p_libraries', ['machinename' => $librarydata['machineName']]);

        $this->assertNotEmpty($library);
        $this->assertNotEmpty($librarydata['libraryId']);
        $this->assertEquals($librarydata['title'], $library->title);
        $this->assertEquals($librarydata['machineName'], $library->machinename);
        $this->assertEquals($librarydata['majorVersion'], $library->majorversion);
        $this->assertEquals($librarydata['minorVersion'], $library->minorversion);
        $this->assertEquals($librarydata['patchVersion'], $library->patchversion);
        $this->assertEquals($librarydata['preloadedJs'][0]['path'], $library->preloadedjs);
        $this->assertEquals($librarydata['preloadedCss'][0]['path'], $library->preloadedcss);
        $this->assertEquals($librarydata['dropLibraryCss'][0]['machineName'], $library->droplibrarycss);
        // Update a library.
        $librarydata['machineName'] = 'TestLibrary2';
        $interface->saveLibraryData($librarydata, false);
        $library = $DB->get_record('h5p_libraries', ['machinename' => $librarydata['machineName']]);
        $this->assertEquals($librarydata['machineName'], $library->machinename);
    }

    // Test the behaviour of insertContent(().
    public function test_insertContent() {
        global $DB;

        $this->resetAfterTest();

        $interface = framework::instance('interface');

        $content = array(
            'params' => '{"param1": "Test"}',
            'library' => array(
                'libraryId' => 1
            ),
            'disable' => 8
        );

        $contentmainid = sha1('path') . '/' . sha1('content');
        $contentid = $interface->insertContent($content, $contentmainid);

        $dbcontent = $DB->get_record('h5p', ['id' => $contentid]);

        $this->assertNotEmpty($dbcontent);
        $this->assertEquals($content['params'], $dbcontent->jsoncontent);
        $this->assertEquals($content['library']['libraryId'], $dbcontent->mainlibraryid);
        $this->assertEquals($content['disable'], $dbcontent->displayoptions);
    }

    // Test the behaviour of updateContent().
    public function test_updateContent() {
        global $DB;

        $this->resetAfterTest();

        $lib = $this->create_library_record('TestLibrary', 'Test', 1, 1, 2);
        $contentid = $this->create_h5p_record($lib->id);

        $content = array(
            'params' => '{"param2": "Test2"}',
            'library' => array(
                'libraryId' => $lib->id
            ),
            'disable' => 8
        );
        $interface = framework::instance('interface');
        $content['id'] = $contentid;
        $contentmainid = sha1('path') . '/' . sha1('content');

        $interface->updateContent($content, $contentmainid);

        $h5pcontent = $DB->get_record('h5p', ['id' => $contentid]);

        $this->assertNotEmpty($h5pcontent);
        $this->assertEquals($content['params'], $h5pcontent->jsoncontent);
        $this->assertEquals($content['library']['libraryId'], $h5pcontent->mainlibraryid);
        $this->assertEquals($content['disable'], $h5pcontent->displayoptions);
    }

    // Test the behaviour of saveLibraryDependencies().
    public function test_saveLibraryDependencies() {
        global $DB;

        $this->resetAfterTest();

        $library = $this->create_library_record('Library', 'Title');
        $dependency1 = $this->create_library_record('DependencyLibrary1', 'DependencyTitle1');
        $dependency2 = $this->create_library_record('DependencyLibrary2', 'DependencyTitle2');

        $dependencies = array(
            array(
                'machineName' => $dependency1->machinename,
                'majorVersion' => $dependency1->majorversion,
                'minorVersion' => $dependency1->minorversion
            ),
            array(
                'machineName' => $dependency2->machinename,
                'majorVersion' => $dependency2->majorversion,
                'minorVersion' => $dependency2->minorversion
            ),
        );
        $interface = framework::instance('interface');
        $interface->saveLibraryDependencies($library->id, $dependencies, 'preloaded');

        $libdependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library->id], 'id ASC');

        $this->assertEquals(2, count($libdependencies));
        $this->assertEquals($dependency1->id, reset($libdependencies)->requiredlibraryid);
        $this->assertEquals($dependency2->id, end($libdependencies)->requiredlibraryid);
    }

    // Test the behaviour of deleteContentData().
    public function test_deleteContentData() {
        global $DB;

        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $h5pid = $data->h5pcontent->h5pid;

        // The particular h5p content and the content libraries should exist in the db.
        $h5pcontent = $DB->get_record('h5p', ['id' => $h5pid]);
        $h5pcontentlibraries = $DB->get_records('h5p_contents_libraries', ['h5pid' => $h5pid]);
        $this->assertNotEmpty($h5pcontent);
        $this->assertNotEmpty($h5pcontentlibraries);
        $this->assertCount(5, $h5pcontentlibraries);

        $interface = framework::instance('interface');
        // Delete the h5p content and it's related data.
        $interface->deleteContentData($h5pid);

        // The particular h5p content and the content libraries should no longer exist in the db.
        $h5pcontent = $DB->get_record('h5p', ['id' => $h5pid]);
        $h5pcontentlibraries = $DB->get_record('h5p_contents_libraries', ['h5pid' => $h5pid]);
        $this->assertEmpty($h5pcontent);
        $this->assertEmpty($h5pcontentlibraries);
    }

    // Test the behaviour of deleteLibraryUsage().
    public function test_deleteLibraryUsage() {
        global $DB;

        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $h5pid = $data->h5pcontent->h5pid;

        // The particular h5p content should have 5 content libraries.
        $h5pcontentlibraries = $DB->get_records('h5p_contents_libraries', ['h5pid' => $h5pid]);
        $this->assertNotEmpty($h5pcontentlibraries);
        $this->assertCount(5, $h5pcontentlibraries);

        $interface = framework::instance('interface');
        // Delete the h5p content and it's related data.
        $interface->deleteLibraryUsage($h5pid);

        // The particular h5p content and the content libraries should no longer exist in the db.
        $h5pcontentlibraries = $DB->get_record('h5p_contents_libraries', ['h5pid' => $h5pid]);
        $this->assertEmpty($h5pcontentlibraries);
    }

    // Test the behaviour of test_saveLibraryUsage().
    public function test_saveLibraryUsage() {
        global $DB;

        $this->resetAfterTest();

        $library = $this->create_library_record('Library', 'Title');
        $dependency1 = $this->create_library_record('DependencyLibrary1', 'DependencyTitle1');
        $dependency2 = $this->create_library_record('DependencyLibrary2', 'DependencyTitle2');
        $contentid = $this->create_h5p_record($library->id);

        $dependencies = array(
            array(
                'library' => array(
                    'libraryId' => $dependency1->id,
                    'machineName' => $dependency1->machinename,
                    'dropLibraryCss' => $dependency1->droplibrarycss
                ),
                'type' => 'preloaded',
                'weight' => 1
            ),
            array(
                'library' => array(
                    'libraryId' => $dependency2->id,
                    'machineName' => $dependency2->machinename,
                    'dropLibraryCss' => $dependency2->droplibrarycss
                ),
                'type' => 'preloaded',
                'weight' => 2
            ),
        );
        $interface = framework::instance('interface');
        $interface->saveLibraryUsage($contentid, $dependencies);

        $libdependencies = $DB->get_records('h5p_contents_libraries', ['h5pid' => $contentid], 'id ASC');

        $this->assertEquals(2, count($libdependencies));
        $this->assertEquals($dependency1->id, reset($libdependencies)->libraryid);
        $this->assertEquals($dependency2->id, end($libdependencies)->libraryid);
    }

    // Test the behaviour of getLibraryUsage().
    public function test_getLibraryUsage() {
        $this->resetAfterTest();

        $generateddata = $this->generate_h5p_data();
        $library1id = $generateddata->lib1->data->id;
        $library2id = $generateddata->lib2->data->id;
        $library5id = $generateddata->lib5->data->id;

        $interface = framework::instance('interface');
        // Get the library usage for $lib1 (do not skip content).
        $data = $interface->getLibraryUsage($library1id);
        $expected = array(
            'content' => 1,
            'libraries' => 1
        );
        $this->assertEquals($expected, $data);

        // Get the library usage for $lib1 (skip content).
        $data = $interface->getLibraryUsage($library1id, true);
        $expected = array(
            'content' => -1,
            'libraries' => 1,
        );
        $this->assertEquals($expected, $data);

        // Get the library usage for $lib2 (do not skip content).
        $data = $interface->getLibraryUsage($library2id);
        $expected = array(
            'content' => 1,
            'libraries' => 2,
        );
        $this->assertEquals($expected, $data);

         // Get the library usage for $lib5 (do not skip content).
        $data = $interface->getLibraryUsage($library5id);
        $expected = array(
            'content' => 0,
            'libraries' => 1,
        );
        $this->assertEquals($expected, $data);
    }

    // Test the behaviour of loadLibrary().
    public function test_loadLibrary() {
        $this->resetAfterTest();

        $generateddata = $this->generate_h5p_data();
        $library1 = $generateddata->lib1->data;
        $library5 = $generateddata->lib5->data;

        // The preloaded dependencies.
        $preloadeddependencies = array();
        foreach ($generateddata->lib1->dependencies as $preloadeddependency) {
            $preloadeddependencies[] = array(
                'machineName' => $preloadeddependency->machinename,
                'majorVersion' => $preloadeddependency->majorversion,
                'minorVersion' => $preloadeddependency->minorversion
            );
        }
        // Create a dynamic dependency.
        $this->create_library_dependency_record($library1->id, $library5->id, 'dynamic');

        $dynamicdependencies[] = array(
            'machineName' => $library5->machinename,
            'majorVersion' => $library5->majorversion,
            'minorVersion' => $library5->minorversion
        );

        $interface = framework::instance('interface');
        $data = $interface->loadLibrary($library1->machinename, $library1->majorversion, $library1->minorversion);

        $expected = array(
            'libraryId' => $library1->id,
            'title' => $library1->title,
            'machineName' => $library1->machinename,
            'majorVersion' => $library1->majorversion,
            'minorVersion' => $library1->minorversion,
            'patchVersion' => $library1->patchversion,
            'runnable' => $library1->runnable,
            'fullscreen' => $library1->fullscreen,
            'embedTypes' => $library1->embedtypes,
            'preloadedJs' => $library1->preloadedjs,
            'preloadedCss' => $library1->preloadedcss,
            'dropLibraryCss' => $library1->droplibrarycss,
            'semantics' => $library1->semantics,
            'preloadedDependencies' => $preloadeddependencies,
            'dynamicDependencies' => $dynamicdependencies
        );
        $this->assertEquals($expected, $data);

        // Attempt to load a non-existent library.
        $data = $interface->loadLibrary('MissingLibrary', 1, 2);
        $this->assertFalse($data);
    }

    // Test the behaviour of loadLibrarySemantics().
    public function test_loadLibrarySemantics() {
        $this->resetAfterTest();

        $semantics = '
            {
                "type": "text",
                "name": "text",
                "label": "Plain text",
                "description": "Please add some text",
            }';

        $library1 = $this->create_library_record('Library1', 'Lib1', 1, 1, 2, $semantics);
        $library2 = $this->create_library_record('Library2', 'Lib2', 1, 2);

        $interface = framework::instance('interface');
        $semantics1 = $interface->loadLibrarySemantics($library1->machinename, $library1->majorversion, $library1->minorversion);
        $semantics2 = $interface->loadLibrarySemantics($library2->machinename, $library2->majorversion, $library1->minorversion);

        // The semantics for Library1 should be present.
        $this->assertNotEmpty($semantics1);
        $this->assertEquals($semantics, $semantics1);
        // The semantics for Library should be empty.
        $this->assertEmpty($semantics2);
    }

    // Test the behaviour of alterLibrarySemantics().
    public function test_alterLibrarySemantics() {
        global $DB;

        $this->resetAfterTest();

        $semantics = '
            {
                "type": "text",
                "name": "text",
                "label": "Plain text",
                "description": "Please add some text",
            }';

        $library1 = $this->create_library_record('Library1', 'Lib1', 1, 1, 2, $semantics);

        $updatedsemantics = array(
            "type" => "text",
            "name" => "updated text",
            "label" => "Updated text",
            "description" => "Please add some text",
        );

        $interface = framework::instance('interface');
        $interface->alterLibrarySemantics($updatedsemantics, 'Library1', 1, 1);

        $currentsemantics = $DB->get_field('h5p_libraries', 'semantics', array('id' => $library1->id));

        // The semantics for Library1 should be successfully updated.
        $this->assertEquals(json_encode($updatedsemantics), $currentsemantics);
    }

    // Test the behaviour of deleteLibraryDependencies().
    public function test_deleteLibraryDependencies() {
        global $DB;

        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $library1 = $data->lib1->data;

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // The lib1 should have 3 dependencies (lib2, lib3, lib4).
        $this->assertCount(3, $dependencies);

        $interface = framework::instance('interface');
        $interface->deleteLibraryDependencies($library1->id);

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // The lib1 should have 0 dependencies.
        $this->assertCount(0, $dependencies);
    }

    // Test the behaviour of deleteLibrary().
    public function test_deleteLibrary() {
        global $DB;

        $this->resetAfterTest();

        $data = $this->generate_h5p_data(true);
        $library1 = $data->lib1->data;

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // The lib1 should have 3 dependencies (lib2, lib3, lib4).
        $this->assertCount(3, $dependencies);

        $libraryfiles = $DB->get_records('files',
            array(
                'component' => \core_h5p\file_storage::COMPONENT,
                'filearea' => \core_h5p\file_storage::LIBRARY_FILEAREA,
                'itemid' => $library1->id
            )
        );
        // The library (library1) should have 7 related folders/files.
        $this->assertCount(7, $libraryfiles);

        // Delete the library.
        $interface = framework::instance('interface');
        $interface->deleteLibrary($library1);

        $lib1 = $DB->get_record('h5p_libraries', ['machinename' => $library1->machinename]);
         // The lib1 should not exist.
        $this->assertEmpty($lib1);

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // The library (library1)  should have 0 dependencies.
        $this->assertCount(0, $dependencies);

        $libraryfiles = $DB->get_records('files',
            array(
                'component' => \core_h5p\file_storage::COMPONENT,
                'filearea' => \core_h5p\file_storage::LIBRARY_FILEAREA,
                'itemid' => $library1->id
            )
        );
        // The library (library1) should have 0 related folders/files.
        $this->assertCount(0, $libraryfiles);
    }

    // Test the behaviour of loadContent().
    public function test_loadContent() {
        global $DB;

        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $h5pid = $data->h5pcontent->h5pid;
        $h5p = $DB->get_record('h5p', ['id' => $h5pid]);
        $mainlibrary = $data->mainlib->data;

        $interface = framework::instance('interface');
        $content = $interface->loadContent($h5pid);

        $expected = array(
            'id' => $h5p->id,
            'params' => $h5p->jsoncontent,
            'embedType' => 'iframe',
            'disable' => $h5p->displayoptions,
            'title' => $mainlibrary->title,
            'slug' => \H5PCore::slugify($mainlibrary->title) . '-' . $h5p->id,
            'filtered' => $h5p->filtered,
            'libraryId' => $mainlibrary->id,
            'libraryName' => $mainlibrary->machinename,
            'libraryMajorVersion' => $mainlibrary->majorversion,
            'libraryMinorVersion' => $mainlibrary->minorversion,
            'libraryEmbedTypes' => $mainlibrary->embedtypes,
            'libraryFullscreen' => $mainlibrary->fullscreen,
            'metadata' => ''
        );

        // The returned content should match the expected array.
        $this->assertEquals($expected, $content);
    }

    // Test the behaviour of loadContentDependencies().
    public function test_loadContentDependencies() {
        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $h5pid = $data->h5pcontent->h5pid;
        $dependencies = $data->h5pcontent->contentdependencies;

        // The h5p content should have 5 dependency libraries.
        $this->assertCount(5, $dependencies);

        $interface = framework::instance('interface');
        // Get all content dependencies.
        $contentdependencies = $interface->loadContentDependencies($h5pid);

        $expected = array();
        foreach ($dependencies as $dependency) {
            $expected[$dependency->machinename] = array(
                'libraryId' => $dependency->id,
                'machineName' => $dependency->machinename,
                'majorVersion' => $dependency->majorversion,
                'minorVersion' => $dependency->minorversion,
                'patchVersion' => $dependency->patchversion,
                'preloadedCss' => $dependency->preloadedcss,
                'preloadedJs' => $dependency->preloadedjs,
                'dropCss' => '0',
                'dependencyType' => 'preloaded'
            );
        }

         // The loaded content dependencies should return 5 libraries.
        $this->assertCount(5, $contentdependencies);
        $this->assertEquals($expected, $contentdependencies);

        // Add Library5 as a content dependency (dynamic dependency type).
        $library5 = $data->lib5->data;
        $this->create_contents_libraries_record($h5pid, $library5->id, 'dynamic');
        // Load all content dependencies again.
        $contentdependencies = $interface->loadContentDependencies($h5pid);
        // The loaded content dependencies should now return 6 libraries.
        $this->assertCount(6, $contentdependencies);

        // Load all content dependencies of dependency type 'dynamic'.
        $dynamiccontentdependencies = $interface->loadContentDependencies($h5pid, 'dynamic');
        // The loaded content dependencies should now return 1 library.
        $this->assertCount(1, $dynamiccontentdependencies);

        $expected = array(
            'Library5' => array(
                'libraryId' => $library5->id,
                'machineName' => $library5->machinename,
                'majorVersion' => $library5->majorversion,
                'minorVersion' => $library5->minorversion,
                'patchVersion' => $library5->patchversion,
                'preloadedCss' => $library5->preloadedcss,
                'preloadedJs' => $library5->preloadedjs,
                'dropCss' => '0',
                'dependencyType' => 'dynamic'
            )
        );

        $this->assertEquals($expected, $dynamiccontentdependencies);
    }

    // Test the behaviour of updateContentFields().
    public function test_updateContentFields() {
        global $DB;

        $this->resetAfterTest();

        $library1 = $this->create_library_record('Library1', 'Lib1', 1, 1, 2);
        $library2 = $this->create_library_record('Library2', 'Lib2', 1, 1, 2);

        $h5pid = $this->create_h5p_record($library1->id, 'iframe');

        $updatedata = array(
            'jsoncontent' => '{"value" : "test"}',
            'mainlibraryid' => $library2->id
        );
        // Update h5p content fields.
        $interface = framework::instance('interface');
        $interface->updateContentFields($h5pid, $updatedata);

        $h5p = $DB->get_record('h5p', ['id' => $h5pid]);

        $this->assertEquals('{"value" : "test"}', $h5p->jsoncontent);
        $this->assertEquals($library2->id, $h5p->mainlibraryid);
    }

    // Test the behaviour of clearFilteredParameters().
    public function test_clearFilteredParameters() {
        global $DB;

        $this->resetAfterTest();

        // Create 3 libraries.
        $library1 = $this->create_library_record('Library1', 'Lib1', 1, 1, 2);
        $library2 = $this->create_library_record('Library2', 'Lib2', 1, 1, 2);
        $library3 = $this->create_library_record('Library3', 'Lib3', 1, 1, 2);

        // Create h5p content with library1 as a main library.
        $h5pcontentid1 = $this->create_h5p_record($library1->id, 'iframe');
        // Create h5p content with library1 as a main library.
        $h5pcontentid2 = $this->create_h5p_record($library1->id, 'iframe');
        // Create h5p content with library2 as a main library.
        $h5pcontentid3 = $this->create_h5p_record($library2->id, 'iframe');
        // Create h5p content with library3 as a main library.
        $h5pcontentid4 = $this->create_h5p_record($library3->id, 'iframe');

        $h5pcontent1 = $DB->get_record('h5p', ['id' => $h5pcontentid1]);
        $h5pcontent2 = $DB->get_record('h5p', ['id' => $h5pcontentid2]);
        $h5pcontent3 = $DB->get_record('h5p', ['id' => $h5pcontentid3]);
        $h5pcontent4 = $DB->get_record('h5p', ['id' => $h5pcontentid4]);

        // The filtered parameters should be present in each h5p content.
        $this->assertNotEmpty($h5pcontent1->filtered);
        $this->assertNotEmpty($h5pcontent2->filtered);
        $this->assertNotEmpty($h5pcontent3->filtered);
        $this->assertNotEmpty($h5pcontent4->filtered);

        // Clear the filtered parameters for contents that have library1 and library3 as
        // their main library.
        $interface = framework::instance('interface');
        $interface->clearFilteredParameters([$library1->id, $library3->id]);

        $h5pcontent1 = $DB->get_record('h5p', ['id' => $h5pcontentid1]);
        $h5pcontent2 = $DB->get_record('h5p', ['id' => $h5pcontentid2]);
        $h5pcontent3 = $DB->get_record('h5p', ['id' => $h5pcontentid3]);
        $h5pcontent4 = $DB->get_record('h5p', ['id' => $h5pcontentid4]);

        // The filtered parameters should be still present only for the content that has
        // library 2 as a main library.
        $this->assertEmpty($h5pcontent1->filtered);
        $this->assertEmpty($h5pcontent2->filtered);
        $this->assertNotEmpty($h5pcontent3->filtered);
        $this->assertEmpty($h5pcontent4->filtered);
    }

    // Test the behaviour of getNumNotFiltered().
    public function test_getNumNotFiltered() {
        global $DB;

        $this->resetAfterTest();

        // Create 3 libraries.
        $library1 = $this->create_library_record('Library1', 'Lib1', 1, 1, 2);
        $library2 = $this->create_library_record('Library2', 'Lib2', 1, 1, 2);
        $library3 = $this->create_library_record('Library3', 'Lib3', 1, 1, 2);

        // Create h5p content with library1 as a main library.
        $h5pcontentid1 = $this->create_h5p_record($library1->id, 'iframe');
        // Create h5p content with library1 as a main library.
        $h5pcontentid2 = $this->create_h5p_record($library1->id, 'iframe');
        // Create h5p content with library2 as a main library.
        $h5pcontentid3 = $this->create_h5p_record($library2->id, 'iframe');
        // Create h5p content with library3 as a main library.
        $h5pcontentid4 = $this->create_h5p_record($library3->id, 'iframe');

        $h5pcontent1 = $DB->get_record('h5p', ['id' => $h5pcontentid1]);
        $h5pcontent2 = $DB->get_record('h5p', ['id' => $h5pcontentid2]);
        $h5pcontent3 = $DB->get_record('h5p', ['id' => $h5pcontentid3]);
        $h5pcontent4 = $DB->get_record('h5p', ['id' => $h5pcontentid4]);

        // The filtered parameters should be present in each h5p content.
        $this->assertNotEmpty($h5pcontent1->filtered);
        $this->assertNotEmpty($h5pcontent2->filtered);
        $this->assertNotEmpty($h5pcontent3->filtered);
        $this->assertNotEmpty($h5pcontent4->filtered);

        // Clear the filtered parameters for contents that have library1 and library3 as
        // their main library.
        $interface = framework::instance('interface');
        $interface->clearFilteredParameters([$library1->id, $library3->id]);

        // 3 contents don't have their parameters filtered.
        $countnotfiltered = $interface->getNumNotFiltered();
        $this->assertEquals(3, $countnotfiltered);
    }

    // Test the behaviour of getNumContent().
    public function test_getNumContent() {
        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $mainlibrary = $data->mainlib->data;
        $library1 = $data->lib1->data;

        $interface = framework::instance('interface');
        $countmainlib = $interface->getNumContent($mainlibrary->id);
        $countlib1 = $interface->getNumContent($library1->id);

        // 1 content is using MainLibrary as their main library.
        $this->assertEquals(1, $countmainlib);
        // 0 contents are using Library1 as their main library.
        $this->assertEquals(0, $countlib1);

        // Create new h5p content with MainLibrary as a main library.
        $h5pcontentid = $this->create_h5p_record($mainlibrary->id);
        $countmainlib = $interface->getNumContent($mainlibrary->id);
        // 2 contents are using MainLibrary as their main library.
        $this->assertEquals(2, $countmainlib);

        // Skip the newly created content from the ($h5pcontentid).
        $countmainlib = $interface->getNumContent($mainlibrary->id, [$h5pcontentid]);
        // Now, 1 content should be returned.
        $this->assertEquals(1, $countmainlib);
    }

    // Test the behaviour of isContentSlugAvailable().
    public function test_isContentSlugAvailable() {
        $this->resetAfterTest();

        $slug = 'h5p-test-slug-1';

        $interface = framework::instance('interface');
        // Currently returns always true. The slug is generated as a unique value for
        // each h5p content and it is not stored in the h5p content table.
        $isslugavailable = $interface->isContentSlugAvailable($slug);
        $this->assertTrue($isslugavailable);
    }

    /**
     * Test that a record is stored for cached assets.
     */
    public function test_saveCachedAssets() {
        global $DB;

        $this->resetAfterTest();

        $libraries = array(
            array(
                'machineName' => 'H5P.TestLib',
                'libraryId' => 405,
            ),
            array(
                'FontAwesome' => 'FontAwesome',
                'libraryId' => 406,
            ),
            array(
                'machineName' => 'H5P.SecondLib',
                'libraryId' => 407,
            ),
        );

        $key = 'testhashkey';
        $framework = framework::instance('interface');
        $framework->saveCachedAssets($key, $libraries);

        $records = $DB->get_records('h5p_libraries_cachedassets');
        $this->assertCount(3, $records);
    }

    /**
     * Test that the correct libraries are removed from the cached assets table
     */
    public function test_deleteCachedAssets() {
        global $DB;

        $this->resetAfterTest();

        $libraries = array(
            array(
                'machineName' => 'H5P.TestLib',
                'libraryId' => 405,
            ),
            array(
                'FontAwesome' => 'FontAwesome',
                'libraryId' => 406,
            ),
            array(
                'machineName' => 'H5P.SecondLib',
                'libraryId' => 407,
            ),
        );

        $key1 = 'testhashkey';
        $framework = framework::instance('interface');
        $framework->saveCachedAssets($key1, $libraries);

        $libraries = array(
            array(
                'machineName' => 'H5P.DiffLib',
                'libraryId' => 408,
            ),
            array(
                'FontAwesome' => 'FontAwesome',
                'libraryId' => 406,
            ),
            array(
                'machineName' => 'H5P.ThirdLib',
                'libraryId' => 409,
            ),
        );

        $key2 = 'secondhashkey';
        $framework = framework::instance('interface');
        $framework->saveCachedAssets($key2, $libraries);

        $libraries = array(
            array(
                'machineName' => 'H5P.AnotherDiffLib',
                'libraryId' => 410,
            ),
            array(
                'FontAwesome' => 'NotRelated',
                'libraryId' => 411,
            ),
            array(
                'machineName' => 'H5P.ForthLib',
                'libraryId' => 412,
            ),
        );

        $key3 = 'threeforthewin';
        $framework = framework::instance('interface');
        $framework->saveCachedAssets($key3, $libraries);

        $records = $DB->get_records('h5p_libraries_cachedassets');
        $this->assertCount(9, $records);

        // Selecting one library id will result in all related library entries also being deleted.
        // Going to use the FontAwesome library id. The first two hashes should be returned.
        $hashes = $framework->deleteCachedAssets(406);
        $this->assertCount(2, $hashes);
        $index = array_search($key1, $hashes);
        $this->assertEquals($key1, $hashes[$index]);
        $index = array_search($key2, $hashes);
        $this->assertEquals($key2, $hashes[$index]);
        $index = array_search($key3, $hashes);
        $this->assertFalse($index);

        // Check that the records have been removed as well.
        $records = $DB->get_records('h5p_libraries_cachedassets');
        $this->assertCount(3, $records);
    }

    // Test the behaviour of getLibraryContentCount().
    public function test_getLibraryContentCount() {
        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $mainlibrary = $data->mainlib->data;
        $library2 = $data->lib2->data;

        $interface = framework::instance('interface');
        $countlibrarycontent = $interface->getLibraryContentCount();

        $expected = array(
            "{$mainlibrary->machinename} {$mainlibrary->majorversion}.{$mainlibrary->minorversion}" => 1,
        );

        // Only MainLibrary is currently a main library to an h5p content.
        // Should return the number of cases where MainLibrary is a main library to an h5p content.
        $this->assertCount(1, $countlibrarycontent);
        $this->assertEquals($expected, $countlibrarycontent);

        // Create new h5p content with Library2 as it's main library.
        $this->create_h5p_record($library2->id);
        // Create new h5p content with MainLibrary as it's main library.
        $this->create_h5p_record($mainlibrary->id);

        $countlibrarycontent = $interface->getLibraryContentCount();

        $expected = array(
            "{$mainlibrary->machinename} {$mainlibrary->majorversion}.{$mainlibrary->minorversion}" => 2,
            "{$library2->machinename} {$library2->majorversion}.{$library2->minorversion}" => 1,
        );
        // MainLibrary and Library1 are currently main libraries to the existing h5p contents.
        // Should return the number of cases where MainLibrary and Library1 are main libraries to an h5p content.
        $this->assertCount(2, $countlibrarycontent);
        $this->assertEquals($expected, $countlibrarycontent);
    }

    // Test the behaviour of libraryHasUpgrade().
    public function test_libraryHasUpgrade() {
        $this->resetAfterTest();

        // Create a new library.
        $this->create_library_record('Library', 'Lib', 2, 2);

        // Library data with a lower major version than the present library.
        $lowermajorversion = array(
            'machineName' => 'Library',
            'majorVersion' => 1,
            'minorVersion' => 2
        );

        $interface = framework::instance('interface');
        $hasupgrade = $interface->libraryHasUpgrade($lowermajorversion);
        // The presented library has an upgraded version.
        $this->assertTrue($hasupgrade);

        // Library data with a lower minor version than the present library.
        $lowerminorversion = array(
            'machineName' => 'Library',
            'majorVersion' => 2,
            'minorVersion' => 1
        );

        $hasupgrade = $interface->libraryHasUpgrade($lowerminorversion);
        // The presented library has an upgraded version.
        $this->assertTrue($hasupgrade);

        // Library data with same major and minor version as the present library.
        $sameversion = array(
            'machineName' => 'Library',
            'majorVersion' => 2,
            'minorVersion' => 2
        );

        $hasupgrade = $interface->libraryHasUpgrade($sameversion);
        // The presented library has not got an upgraded version.
        $this->assertFalse($hasupgrade);

        // Library data with a higher major version than the present library.
        $largermajorversion = array(
            'machineName' => 'Library',
            'majorVersion' => 3,
            'minorVersion' => 2
        );

        $hasupgrade = $interface->libraryHasUpgrade($largermajorversion);
        // The presented library does not have an upgraded version.
        $this->assertFalse($hasupgrade);

        // Library data with a higher minor version than the present library.
        $largerminorversion = array(
            'machineName' => 'Library',
            'majorVersion' => 2,
            'minorVersion' => 4
        );

        $hasupgrade = $interface->libraryHasUpgrade($largerminorversion);
        // The presented library does not have an upgraded version.
        $this->assertFalse($hasupgrade);
    }

    // Test the behaviour of instance().
    public function test_instance() {
        // Test framework instance.
        $interface = framework::instance('interface');
        $this->assertInstanceOf('\core_h5p\framework', $interface);

        // Test H5PValidator instance.
        $interface = framework::instance('validator');
        $this->assertInstanceOf('\H5PValidator', $interface);

        // Test H5PStorage instance.
        $interface = framework::instance('storage');
        $this->assertInstanceOf('\H5PStorage', $interface);

        // Test H5PContentValidator instance.
        $interface = framework::instance('contentvalidator');
        $this->assertInstanceOf('\H5PContentValidator', $interface);

        // Test H5PCore instance.
        $interface = framework::instance('core');
        $this->assertInstanceOf('\H5PCore', $interface);

        // Should return \H5PCore by default.
        $interface = framework::instance();
        $this->assertInstanceOf('\H5PCore', $interface);
    }

    /**
     * Populate H5P database tables with relevant data to simulate the process of adding H5P content.
     *
     * @param bool $createlibraryfiles Whether should create library relater files on the filesystem.
     * @return object An object representing the added H5P records.
     */
    private function generate_h5p_data(bool $createlibraryfiles = false) : stdClass {
        // Create libraries.
        $mainlib = $this->create_library_record('MainLibrary', 'Main Lib', 1, 0);
        $lib1 = $this->create_library_record('Library1', 'Lib1', 2, 0);
        $lib2 = $this->create_library_record('Library2', 'Lib2', 2, 1);
        $lib3 = $this->create_library_record('Library3', 'Lib3', 3, 2);
        $lib4 = $this->create_library_record('Library4', 'Lib4', 1, 1);
        $lib5 = $this->create_library_record('Library5', 'Lib5', 1, 3);

        if ($createlibraryfiles) {
            // Get a temp path.
            $filestorage = new \core_h5p\file_storage();
            $temppath = $filestorage->getTmpPath();
            // Create library files for MainLibrary.
            $basedirectorymain = $temppath . DIRECTORY_SEPARATOR . $mainlib->machinename . '-' .
                $mainlib->majorversion . '.' . $mainlib->minorversion;
            $this->create_library_files($basedirectorymain, $mainlib->id, $mainlib->machinename,
                $mainlib->majorversion, $mainlib->minorversion);
            // Create library files for Library1.
            $basedirectory1 = $temppath . DIRECTORY_SEPARATOR . $lib1->machinename . '-' .
                $lib1->majorversion . '.' . $lib1->minorversion;
            $this->create_library_files($basedirectory1, $lib1->id, $lib1->machinename,
                $lib1->majorversion, $lib1->minorversion);
            // Create library files for Library2.
            $basedirectory2 = $temppath . DIRECTORY_SEPARATOR . $lib2->machinename . '-' .
                $lib2->majorversion . '.' . $lib2->minorversion;
            $this->create_library_files($basedirectory2, $lib2->id, $lib2->machinename,
                $lib2->majorversion, $lib2->minorversion);
            // Create library files for Library3.
            $basedirectory3 = $temppath . DIRECTORY_SEPARATOR . $lib3->machinename . '-' .
                $lib3->majorversion . '.' . $lib3->minorversion;
            $this->create_library_files($basedirectory3, $lib3->id, $lib3->machinename,
                $lib3->majorversion, $lib3->minorversion);
            // Create library files for Library4.
            $basedirectory4 = $temppath . DIRECTORY_SEPARATOR . $lib4->machinename . '-' .
                $lib4->majorversion . '.' . $lib4->minorversion;
            $this->create_library_files($basedirectory4, $lib4->id, $lib4->machinename,
                $lib4->majorversion, $lib4->minorversion);
            // Create library files for Library5.
            $basedirectory5 = $temppath . DIRECTORY_SEPARATOR . $lib5->machinename . '-' .
                $lib5->majorversion . '.' . $lib5->minorversion;
            $this->create_library_files($basedirectory5, $lib5->id, $lib5->machinename,
                $lib5->majorversion, $lib5->minorversion);
        }

        // Create h5p content.
        $h5p = $this->create_h5p_record($mainlib->id);
        // Create h5p content library dependencies.
        $this->create_contents_libraries_record($h5p, $mainlib->id);
        $this->create_contents_libraries_record($h5p, $lib1->id);
        $this->create_contents_libraries_record($h5p, $lib2->id);
        $this->create_contents_libraries_record($h5p, $lib3->id);
        $this->create_contents_libraries_record($h5p, $lib4->id);
        // Create library dependencies for $mainlib.
        $this->create_library_dependency_record($mainlib->id, $lib1->id);
        $this->create_library_dependency_record($mainlib->id, $lib2->id);
        $this->create_library_dependency_record($mainlib->id, $lib3->id);
        // Create library dependencies for $lib1.
        $this->create_library_dependency_record($lib1->id, $lib2->id);
        $this->create_library_dependency_record($lib1->id, $lib3->id);
        $this->create_library_dependency_record($lib1->id, $lib4->id);
        // Create library dependencies for $lib3.
        $this->create_library_dependency_record($lib3->id, $lib5->id);

        return (object) [
            'h5pcontent' => (object) array(
                'h5pid' => $h5p,
                'contentdependencies' => array($mainlib, $lib1, $lib2, $lib3, $lib4)
            ),
            'mainlib' => (object) array(
                'data' => $mainlib,
                'dependencies' => array($lib1, $lib2, $lib3)
            ),
            'lib1' => (object) array(
                'data' => $lib1,
                'dependencies' => array($lib2, $lib3, $lib4)
            ),
            'lib2' => (object) array(
                'data' => $lib2,
                'dependencies' => array()
            ),
            'lib3' => (object) array(
                'data' => $lib3,
                'dependencies' => array($lib5)
            ),
            'lib4' => (object) array(
                'data' => $lib4,
                'dependencies' => array()
            ),
            'lib5' => (object) array(
                'data' => $lib5,
                'dependencies' => array()
            ),
            'libtemppath' => $temppath ?? ''
        ];
    }

    /**
     * Create a record in the h5p_libraries database table.
     *
     * @param string $machinename The library machine name
     * @param string $title The library's name
     * @param int $majorversion The library's major version
     * @param int $minorversion The library's minor version
     * @param int $patchversion The library's patch version
     * @param string $semantics Json describing the content structure for the library
     * @param string $addto The plugin configuration data
     * @return stdClass An object representing the added library record
     */
    private function create_library_record(string $machinename, string $title, int $majorversion = 1,
            int $minorversion = 0, int $patchversion = 1, string $semantics = '', string $addto = null) : stdClass {
        global $DB;

        $content = array(
            'machinename' => $machinename,
            'title' => $title,
            'majorversion' => $majorversion,
            'minorversion' => $minorversion,
            'patchversion' => $patchversion,
            'runnable' => 1,
            'fullscreen' => 1,
            'embedtypes' => 'iframe',
            'preloadedjs' => 'js/example.js',
            'preloadedcss' => 'css/example.css',
            'droplibrarycss' => '',
            'semantics' => $semantics,
            'addto' => $addto
        );

        $libraryid = $DB->insert_record('h5p_libraries', $content);

        return $DB->get_record('h5p_libraries', ['id' => $libraryid]);
    }

    /**
     * Create the necessary files and return an array structure for a library.
     *
     * @param  string $uploaddirectory base directory for the library.
     * @param  int    $libraryid       The library ID.
     * @param  string $machinename     Name for this library.
     * @param  int    $majorversion    Major version (any number will do).
     * @param  int    $minorversion    Minor version (any number will do).
     */
    private function create_library_files(string $uploaddirectory, int $libraryid, string $machinename,
            int $majorversion, int $minorversion) {
        global $CFG;

        // Create library directories.
        mkdir($uploaddirectory, $CFG->directorypermissions, true);
        mkdir($uploaddirectory . DIRECTORY_SEPARATOR . 'scripts', $CFG->directorypermissions, true);
        mkdir($uploaddirectory . DIRECTORY_SEPARATOR . 'styles', $CFG->directorypermissions, true);

        // Create library.json file.
        $jsonfile = $uploaddirectory . DIRECTORY_SEPARATOR . 'library.json';
        $handle = fopen($jsonfile, 'w+');
        fwrite($handle, 'test data');
        fclose($handle);
        // Create library js file.
        $jsfile = $uploaddirectory . DIRECTORY_SEPARATOR . 'scripts/testlib.min.js';
        $handle = fopen($jsfile, 'w+');
        fwrite($handle, 'test data');
        fclose($handle);
        // Create library css file.
        $cssfile = $uploaddirectory . DIRECTORY_SEPARATOR . 'styles/testlib.min.css';
        $handle = fopen($cssfile, 'w+');
        fwrite($handle, 'test data');
        fclose($handle);

        $lib = [
            'title' => 'Test lib',
            'description' => 'Test library description',
            'majorVersion' => $majorversion,
            'minorVersion' => $minorversion,
            'patchVersion' => 2,
            'machineName' => $machinename,
            'embedTypes' => 'iframe',
            'preloadedJs' => [
                [
                    'path' => 'scripts' . DIRECTORY_SEPARATOR . 'testlib.min.js'
                ]
            ],
            'preloadedCss' => [
                [
                    'path' => 'styles' . DIRECTORY_SEPARATOR . 'testlib.min.css'
                ]
            ],
            'uploadDirectory' => $uploaddirectory,
            'libraryId' => $libraryid
        ];

        $filestorage = new \core_h5p\file_storage();
        $filestorage->saveLibrary($lib);
    }

    /**
     * Create a record in the h5p database table.
     *
     * @param int $mainlibid The ID of the content's main library
     * @param string $jsoncontent The content in json format
     * @param string $filtered The filtered content parameters
     * @return int The ID of the added record
     */
    private function create_h5p_record(int $mainlibid, string $jsoncontent = null, string $filtered = null) : int {
        global $DB;

        if (!$jsoncontent) {
            $jsoncontent = '
                {
                   "text":"<p>Dummy text<\/p>\n",
                   "questions":[
                      "<p>Test question<\/p>\n"
                   ]
                }';
        }

        if (!$filtered) {
            $filtered = '
                {
                   "text":"Dummy text",
                   "questions":[
                      "Test question"
                   ]
                }';
        }

        return $DB->insert_record(
            'h5p',
            array(
                'jsoncontent' => $jsoncontent,
                'displayoptions' => 8,
                'mainlibraryid' => $mainlibid,
                'timecreated' => time(),
                'timemodified' => time(),
                'filtered' => $filtered,
                'pathnamehash' => sha1('pathname'),
                'contenthash' => sha1('content')
            )
        );
    }

    /**
     * Create a record in the h5p_contents_libraries database table.
     *
     * @param string $h5pid The ID of the H5P content
     * @param int $libid The ID of the library
     * @param string $dependencytype The dependency type
     * @return int The ID of the added record
     */
    private function create_contents_libraries_record(string $h5pid, int $libid,
            string $dependencytype = 'preloaded') : int {
        global $DB;

        return $DB->insert_record(
            'h5p_contents_libraries',
            array(
                'h5pid' => $h5pid,
                'libraryid' => $libid,
                'dependencytype' => $dependencytype,
                'dropcss' => 0,
                'weight' => 1
            )
        );
    }

    /**
     * Create a record in the h5p_library_dependencies database table.
     *
     * @param int $libid The ID of the library
     * @param int $requiredlibid The ID of the required library
     * @param string $dependencytype The dependency type
     * @return int The ID of the added record
     */
    private function create_library_dependency_record(int $libid, int $requiredlibid,
            string $dependencytype = 'preloaded') : int {
        global $DB;

        return $DB->insert_record(
            'h5p_library_dependencies',
            array(
                'libraryid' => $libid,
                'requiredlibraryid' => $requiredlibid,
                'dependencytype' => $dependencytype
            )
        );
    }
}
