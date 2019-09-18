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

        // Provide a valid URL to an external H5P content.
        $url = "https://h5p.org/sites/default/files/h5p/exports/arithmetic-quiz-22-57860.h5p";

        $interface = framework::instance('interface');
        // Test fetching an external H5P content without defining a path to where the file should be stored.
        $data = $interface->fetchExternalData($url, null, true);
        // The response should not be empty and return the file content as a string.
        $this->assertNotEmpty($data);
        $this->assertIsString($data);

        $data = $interface->fetchExternalData($url, null, true, $CFG->tempdir . uniqid('/h5p-'));
        // The response should not be empty and return true if the content has been successfully saved to a file.
        $this->assertNotEmpty($data);
        $this->assertTrue($data);
        // The uploaded file should exist on the filesystem.
        $h5pfolderpath = $interface->getUploadedH5pFolderPath();
        $this->assertTrue(file_exists($h5pfolderpath . '.h5p'));

        // Provide an URL to an external file that is not an H5P content file.
        $url = "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf";

        $data = $interface->fetchExternalData($url, null, true, $CFG->tempdir . uniqid('/h5p-'));
        // The response should not be empty and return true if the content has been successfully saved to a file.
        $this->assertNotEmpty($data);
        $this->assertTrue($data);

        // The uploaded file should exist on the filesystem with it's original extension.
        // NOTE: The file would be later validated by the H5P Validator.
        $h5pfolderpath = $interface->getUploadedH5pFolderPath();
        $this->assertTrue(file_exists($h5pfolderpath . '.pdf'));

        // Provide an invalid URL to an external file.
        $url = "someprotocol://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf";
        $data = $interface->fetchExternalData($url, null, true, $CFG->tempdir . uniqid('/h5p-'));
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
        $errormessages = framework::messages('error');
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
        $infomessages = framework::messages('info');
        $expected = 'Info message';
        $this->assertEquals($expected, $infomessages[0]);
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
        // $library should not be a patched version of present library.
        $ispatched = $interface->isPatchedLibrary($library);
        $this->assertFalse($ispatched);
        // $library should not be a patched version of present library.
        $library['patchVersion'] = '3';
        $ispatched = $interface->isPatchedLibrary($library);
        $this->assertTrue($ispatched);
        // $library with a different minor version should not be a patched version of present library.
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
                'path' => 'js/name.min.js'
            ),
            'preloadedCss' => array(
                'path' => 'css/name.css'
            ),
            'dropLibraryCss' => array(
                'machineName' => 'Name2'
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
        $this->assertEquals($librarydata['preloadedJs']['path'], $library->preloadedjs);
        $this->assertEquals($librarydata['preloadedCss']['path'], $library->preloadedcss);
        $this->assertEquals($librarydata['dropLibraryCss']['machineName'], $library->droplibrarycss);
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
            )
        );
        $contentid = $interface->insertContent($content);

        $dbcontent = $DB->get_record('h5p', ['id' => $contentid]);

        $this->assertNotEmpty($dbcontent);
        $this->assertEquals($content['params'], $dbcontent->jsoncontent);
        $this->assertEquals($content['library']['libraryId'], $dbcontent->mainlibraryid);
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
            )
        );
        $interface = framework::instance('interface');
        $content['id'] = $contentid;
        $interface->updateContent($content);

        $h5pcontent = $DB->get_record('h5p', ['id' => $contentid]);

        $this->assertNotEmpty($h5pcontent);
        $this->assertEquals($content['params'], $h5pcontent->jsoncontent);
        $this->assertEquals($content['library']['libraryId'], $h5pcontent->mainlibraryid);
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

        $libdependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library->id]);

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

        $libdependencies = $DB->get_records('h5p_contents_libraries', ['h5pid' => $contentid]);

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
        // Get the library usage for $lib1 (do not skip content)
        $data = $interface->getLibraryUsage($library1id);
        $expected = array(
            'content' => 1,
            'libraries' => 1
        );
        $this->assertEquals($expected, $data);

        // Get the library usage for $lib1 (skip content)
        $data = $interface->getLibraryUsage($library1id, true);
        $expected = array(
            'content' => -1,
            'libraries' => 1,
        );
        $this->assertEquals($expected, $data);

        // Get the library usage for $lib2 (do not skip content)
        $data = $interface->getLibraryUsage($library2id);
        $expected = array(
            'content' => 1,
            'libraries' => 2,
        );
        $this->assertEquals($expected, $data);

         // Get the library usage for $lib5 (do not skip content)
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
            'embedTypes' => '',
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

        $library1 = $this->create_library_record('Library1', 'Lib1', 1, 1,2, $semantics);
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

        $library1 = $this->create_library_record('Library1', 'Lib1', 1, 1,2, $semantics);

        $updatedsemantics = '
            {
                "type": "text",
                "name": "updated text",
                "label": "Updated text",
                "description": "Please add some text",
            }';

        $interface = framework::instance('interface');
        $interface->alterLibrarySemantics($updatedsemantics, 'Library1', 1, 1);

        $currentsemantics = $DB->get_field('h5p_libraries', 'semantics', array('id' => $library1->id));

        // The semantics for Library1 should be successfully updated.
        $this->assertEquals($updatedsemantics, $currentsemantics);
    }

    // Test the behaviour of deleteLibraryDependencies().
    public function test_deleteLibraryDependencies() {
        global $DB;

        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $library1 = $data->lib1->data;

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // lib1 should have 3 dependencies (lib2, lib3, lib4).
        $this->assertCount(3, $dependencies);

        $interface = framework::instance('interface');
        $interface->deleteLibraryDependencies($library1->id);

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // lib1 should have 0 dependencies.
        $this->assertCount(0, $dependencies);
    }

    // TODO: Test deletion of file as well.
    // Test the behaviour of deleteLibrary().
    public function test_deleteLibrary() {
        global $DB;

        $this->resetAfterTest();

        $data = $this->generate_h5p_data();
        $library1 = $data->lib1->data;

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // lib1 should have 3 dependencies (lib2, lib3, lib4).
        $this->assertCount(3, $dependencies);

        $interface = framework::instance('interface');
        $interface->deleteLibrary($library1);

        $lib1 = $DB->get_record('h5p_libraries', ['machinename' => $library1->machinename]);
         // lib1 should not exist.
        $this->assertEmpty($lib1);

        $dependencies = $DB->get_records('h5p_library_dependencies', ['libraryid' => $library1->id]);
        // lib1 should have 0 dependencies.
        $this->assertCount(0, $dependencies);
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
            'embedType' => $h5p->embedtype,
            'libraryId' => $mainlibrary->id,
            'libraryName' => $mainlibrary->machinename,
            'libraryMajorVersion' => $mainlibrary->majorversion,
            'libraryMinorVersion' => $mainlibrary->minorversion,
            'libraryEmbedTypes' => '',
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

        $h5pid = $this->create_h5p_record($library1->id, 'div');

        $updatedata = array(
            'jsoncontent' => '{"value" : "test"}',
            'embedtype' => 'iframe',
            'mainlibraryid' => $library2->id
        );
        // Update h5p content fields
        $interface = framework::instance('interface');
        $interface->updateContentFields($h5pid, $updatedata);

        $h5p = $DB->get_record('h5p', ['id' => $h5pid]);

        $this->assertEquals('{"value" : "test"}', $h5p->jsoncontent);
        $this->assertEquals('iframe', $h5p->embedtype);
        $this->assertEquals($library2->id, $h5p->mainlibraryid);
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

    // Test the behaviour of message().
    public function test_message() {
        // Add new error message.
        $errormsgs = framework::messages('error', 'This is an error message', 400);

        $this->assertIsArray($errormsgs);
        $this->assertCount(1, $errormsgs);
        $this->assertIsObject($errormsgs[0]);

        $expected = (object) array(
            'code' => 400,
            'message' => 'This is an error message'
        );
        $this->assertEquals($errormsgs[0], $expected);

        // Add new error message.
        $errormsgs = framework::messages('error', 'This is an error message 2', 401);

        $this->assertCount(2, $errormsgs);
        $expected = (object) array(
            'code' => 401,
            'message' => 'This is an error message 2'
        );
        $this->assertEquals($errormsgs[1], $expected);

        // Get the error messages (Should return the messages and remove them them).
        $errormsgs = framework::messages('error');
        $this->assertCount(2, $errormsgs);
        // Attemt to get the error messages again, there should be no messages available.
        $errormsgs = framework::messages('error');
        $this->assertEmpty($errormsgs);

        // Add new info message.
        $infomsg = framework::messages('info', 'This is an info message');

        $this->assertCount(1, $infomsg);
        $this->assertIsString($infomsg[0]);
        $expected = 'This is an info message';
        $this->assertEquals($infomsg[0], $expected);
    }

    /**
     * Populate H5P database tables with relevant data to simulate the process of adding H5P content.
     *
     * @return object An object representing the added H5P records.
     */
    private function generate_h5p_data() {
        // Create libraries.
        $mainlib = $this->create_library_record('MainLibrary', 'Main');
        $lib1 = $this->create_library_record('Library1', 'Lib1');
        $lib2 = $this->create_library_record('Library2', 'Lib2');
        $lib3 = $this->create_library_record('Library3', 'Lib3');
        $lib4 = $this->create_library_record('Library4', 'Lib4');
        $lib5 = $this->create_library_record('Library5', 'Lib5');
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
            )
        ];
    }

    /**
     * Create a record in the h5p_libraries database table.
     *
     * @param string $machinename The library machine name
     * @param string $title The library's name
     * @param string $majorversion The library's major version
     * @param string $minorversion The library's minor version
     * @param string $patchversion The library's patch version
     * @param string $semantics Json describing the content structure for the library
     * @return object An object representing the added library record
     */
    private function create_library_record($machinename, $title, $majorversion = 1, $minorversion = 0,
            $patchversion = 1, $semantics = '') {
        global $DB;

        $content = array(
            'machinename' => $machinename,
            'title' => $title,
            'majorversion' => $majorversion,
            'minorversion' => $minorversion,
            'patchversion' => $patchversion,
            'runnable' => 1,
            'fullscreen' => 1,
            'preloadedjs' => 'js/example.js',
            'preloadedcss' => 'css/example.css',
            'droplibrarycss' => '',
            'semantics' => $semantics
        );

        $libraryid = $DB->insert_record('h5p_libraries', $content);

        return $DB->get_record('h5p_libraries', ['id' => $libraryid]);
    }

    /**
     * Create a record in the h5p database table.
     *
     * @param string $mainlibid The ID of the content's main library
     * @param string $embedtype A csv of embed types
     * @param string $jsoncontent The content in json format
     * @return int The ID of the added record
     */
    private function create_h5p_record($mainlibid, $embedtype = 'div', $jsoncontent = null) {
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

        return $DB->insert_record(
            'h5p',
            array(
                'jsoncontent' => $jsoncontent,
                'embedtype' => $embedtype,
                'mainlibraryid' => $mainlibid,
                'timecreated' => time(),
                'timemodified' => time()
            )
        );
    }

    /**
     * Create a record in the h5p_contents_libraries database table.
     *
     * @param string $h5pid The ID of the H5P content
     * @param string $libid The ID of the library
     * @param string $dependencytype The dependency type
     * @return int The ID of the added record
     */
    private function create_contents_libraries_record($h5pid, $libid, $dependencytype = 'preloaded') {
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
     * @param string $libid The ID of the library
     * @param string $requiredlibid The ID of the required library
     * @param string $dependencytype The dependency type
     * @return int The ID of the added record
     */
    private function create_library_dependency_record($libid, $requiredlibid, $dependencytype = 'preloaded') {
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
