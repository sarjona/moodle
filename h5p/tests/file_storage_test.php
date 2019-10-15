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
 * Testing the H5P H5PFileStorage interface implementation.
 *
 * @package    core_h5p
 * @category   test
 * @copyright  2019 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \core_h5p\file_storage;
defined('MOODLE_INTERNAL') || die();

/**
 * Test class covering the H5PFileStorage interface implementation.
 *
 * @package    core_h5p
 * @copyright  2019 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_storage_testcase extends advanced_testcase {

    /** @var array $files an array used in the cache tests. */
    protected $files = ['scripts' => [], 'styles' => []];
    /** @var int $libraryid an id for the library. */
    protected $libraryid = 1;

    /**
     * Create the necessary files and return an array structure for a library.
     *
     * @param  string $uploaddirectory base directory for the library.
     * @param  string $machinename     Name for this library.
     * @param  int    $majorversion    Major version (any number will do).
     * @param  int    $minorversion    Minor version (any number will do).
     * @return array A list of library data that the core API will understand.
     */
    protected function create_library(string $uploaddirectory, string $machinename, int $majorversion, int $minorversion) : array {

        $this->create_directory($uploaddirectory);
        $this->create_directory($uploaddirectory . DIRECTORY_SEPARATOR . 'scripts');
        $this->create_directory($uploaddirectory . DIRECTORY_SEPARATOR . 'styles');

        $jsonfile = $uploaddirectory . DIRECTORY_SEPARATOR . 'library.json';
        $jsfile = $uploaddirectory . DIRECTORY_SEPARATOR . 'scripts/testlib.min.js';
        $cssfile = $uploaddirectory . DIRECTORY_SEPARATOR . 'styles/testlib.min.css';
        $this->create_file($jsonfile);
        $this->create_file($jsfile);
        $this->create_file($cssfile);

        $lib = [
            'title' => 'Test lib',
            'description' => 'Test library description',
            'majorVersion' => $majorversion,
            'minorVersion' => $minorversion,
            'patchVersion' => 2,
            'machineName' => $machinename,
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
            'libraryId' => $this->libraryid
        ];
        $this->libraryid++;

        $version = "{$majorversion}.{$minorversion}.2";
        $libname = "{$machinename}-{$majorversion}.{$minorversion}";
        $path = DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . $libname . DIRECTORY_SEPARATOR . 'scripts' .
                DIRECTORY_SEPARATOR . 'testlib.min.js';
        $this->add_libfile_to_array('scripts', $path, $version);
        $path = DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . $libname . DIRECTORY_SEPARATOR . 'styles' .
                DIRECTORY_SEPARATOR . 'testlib.min.css';
        $this->add_libfile_to_array('styles', $path, $version);

        return $lib;
    }

    /**
     * Creates the file record. Currently used for the cache tests.
     *
     * @param string $type    Either 'scripts' or 'styles'.
     * @param string $path    Path to the file in the file system.
     * @param string $version Not really needed at the moment.
     */
    protected function add_libfile_to_array(string $type, string $path, string $version) {
        $this->files[$type][] = (object) [
            'path' => $path,
            'version' => "?ver=$version"
        ];
    }

    /**
     * Convenience function to create a file.
     *
     * @param  string $file path to a file.
     */
    protected function create_file(string $file) {
        $handle = fopen($file, 'w+');
        fwrite($handle, 'test data');
        fclose($handle);
    }

    /**
     * Convenience function to create a directory.
     *
     * @param  string $directory The directory to create.
     */
    protected function create_directory(string $directory) {
        global $CFG;
        mkdir($directory, $CFG->directorypermissions, true);
    }

    /**
     * Test that given the main directory of a library that all files are saved
     * into the file system.
     */
    public function test_saveLibrary() {
        global $DB;

        $this->resetAfterTest();

        $filestorage = new file_storage();
        // Get a temp path.
        $temppath = $filestorage->getTmpPath();
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'test-1.0';

        $machinename = 'TestLib';
        $majorversion = 1;
        $minorversion = 0;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib);

        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => 'library.json']);
        $filepath = DIRECTORY_SEPARATOR . "{$machinename}-{$majorversion}.{$minorversion}" . DIRECTORY_SEPARATOR;
        $this->assertEquals($filepath, $record->filepath);
        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => 'testlib.min.js']);
        $jsfilepath = "{$filepath}scripts" . DIRECTORY_SEPARATOR;
        $this->assertEquals($jsfilepath, $record->filepath);
        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => 'testlib.min.css']);
        $cssfilepath = "{$filepath}styles" . DIRECTORY_SEPARATOR;
        $this->assertEquals($cssfilepath, $record->filepath);
    }

    /**
     * Test that a content file can be saved.
     */
    public function test_saveContent() {
        global $DB;

        $this->resetAfterTest();

        $filestorage = new file_storage();
        $temppath = $filestorage->getTmpPath();
        $this->create_directory($temppath);
        $source = $temppath . DIRECTORY_SEPARATOR . 'content.json';
        $this->create_file($source);

        $filestorage->saveContent($temppath, ['id' => 5]);

        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => 'content.json']);
        $this->assertEquals(file_storage::CONTENT_FILEAREA, $record->filearea);
        $this->assertEquals('content.json', $record->filename);
        $this->assertEquals(5, $record->itemid);
    }

    /**
     * Test that content files located on the file system can be deleted.
     */
    public function test_deleteContent() {
        global $DB;

        $this->resetAfterTest();

        $filestorage = new file_storage();
        $temppath = $filestorage->getTmpPath();
        $this->create_directory($temppath);
        $source = $temppath . DIRECTORY_SEPARATOR . 'content.json';
        $this->create_file($source);

        $filestorage->saveContent($temppath, ['id' => 5]);

        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => 'content.json']);
        $this->assertEquals('content.json', $record->filename);

        // Now to delete the record.
        $filestorage->deleteContent(['id' => 5]);
        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => 'content.json']);
        $this->assertFalse($record);
    }

    /**
     * Test that returning a temp path returns what is expected by the h5p library.
     */
    public function test_getTmpPath() {
        global $CFG;

        $filestorage = new file_storage();
        $temppath = $filestorage->getTmpPath();
        $temparray = explode(DIRECTORY_SEPARATOR, $temppath);
        $h5pdirectory = array_pop($temparray);
        $this->assertTrue(stripos($h5pdirectory, 'h5p-') === 0);
    }

    /**
     * Test that the content files can be exported to a specified location.
     */
    public function test_exportContent() {
        global $DB;

        $this->resetAfterTest();

        // Create a file to store.
        $filestorage = new file_storage();
        $temppath = $filestorage->getTmpPath();
        $this->create_directory($temppath);
        $source = $temppath . DIRECTORY_SEPARATOR . 'content.json';
        $this->create_file($source);

        $filestorage->saveContent($temppath, ['id' => 5]);

        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => 'content.json']);
        $this->assertEquals('content.json', $record->filename);

        // Now export it.
        $destinationdirectory = $temppath . DIRECTORY_SEPARATOR . 'testdir';
        $this->create_directory($destinationdirectory);

        $filestorage->exportContent(5, $destinationdirectory);
        // Check that there is a file now in that directory.
        $contents = scandir($destinationdirectory);
        $value = array_search('content.json', $contents);
        $this->assertEquals('content.json', $contents[$value]);
    }

    /**
     * Test that libraries on the file system can be exported to a specified location.
     */
    public function test_exportLibrary() {
        $this->resetAfterTest();

        $filestorage = new file_storage();
        // Get a temp path.
        $temppath = $filestorage->getTmpPath();
        $this->create_directory($temppath);
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'test-1.0';

        $machinename = 'TestLib';
        $majorversion = 1;
        $minorversion = 0;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib);

        $destinationdirectory = $temppath . DIRECTORY_SEPARATOR . 'testdir';
        $this->create_directory($destinationdirectory);

        $filestorage->exportLibrary($lib, $destinationdirectory);

        $filepath = DIRECTORY_SEPARATOR . "{$machinename}-{$majorversion}.{$minorversion}" . DIRECTORY_SEPARATOR;
        // There should be at least three items here (but could be more with . and ..).
        $this->directory_and_file_check($destinationdirectory . $filepath, 'library.json', 3);
        $this->directory_and_file_check($destinationdirectory . $filepath . 'scripts', 'testlib.min.js', 1);
        $this->directory_and_file_check($destinationdirectory . $filepath . 'styles', 'testlib.min.css', 1);
    }

    /**
     * Test that an export file can be saved into the file system.
     */
    public function test_saveExport() {
        global $DB;

        $this->resetAfterTest();

        // Create a file to store.
        $filestorage = new file_storage();
        $temppath = $filestorage->getTmpPath();
        $this->create_directory($temppath);
        $filename = 'someexportedfile.h5p';
        $source = $temppath . DIRECTORY_SEPARATOR . $filename;
        $this->create_file($source);

        $filestorage->saveExport($source, $filename);

        // Check out if the file is there.
        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => $filename]);
        $this->assertEquals(file_storage::EXPORT_FILEAREA, $record->filearea);
    }

    /**
     * Test that an exort file can be deleted from the file system.
     * @return [type] [description]
     */
    public function test_deleteExport() {
        global $DB;

        $this->resetAfterTest();

        // Create a file to store.
        $filestorage = new file_storage();
        $temppath = $filestorage->getTmpPath();
        $this->create_directory($temppath);
        $filename = 'someexportedfile.h5p';
        $source = $temppath . DIRECTORY_SEPARATOR . $filename;
        $this->create_file($source);

        $filestorage->saveExport($source, $filename);

        // Check out if the file is there.
        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => $filename]);
        $this->assertEquals(file_storage::EXPORT_FILEAREA, $record->filearea);

        // Time to delete.
        $filestorage->deleteExport($filename);
        $record = $DB->get_record('files', ['component' => file_storage::COMPONENT, 'filename' => $filename]);
        $this->assertFalse($record);
    }

    /**
     * Test to check if an export file already exists on the file system.
     */
    public function test_hasExport() {
        $this->resetAfterTest();

        // Create a file to store.
        $filestorage = new file_storage();
        $temppath = $filestorage->getTmpPath();
        $this->create_directory($temppath);
        $filename = 'someexportedfile.h5p';
        $source = $temppath . DIRECTORY_SEPARATOR . $filename;
        $this->create_file($source);

        // Check that it doesn't exist in the file system.
        $this->assertFalse($filestorage->hasExport($filename));

        $filestorage->saveExport($source, $filename);
        // Now it should be present.
        $this->assertTrue($filestorage->hasExport($filename));
    }

    /**
     * Test that all the library files for an H5P activity can be concatenated into "cache" files. One for js and another for css.
     */
    public function test_cacheAssets() {
        $this->resetAfterTest();

        $filestorage = new file_storage();
        // Get a temp path.
        $temppath = $filestorage->getTmpPath();
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'test-1.0';

        $machinename = 'TestLib';
        $majorversion = 1;
        $minorversion = 0;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib);

        // Second library.
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'supertest-2.4';

        $machinename = 'SuperTest';
        $majorversion = 2;
        $minorversion = 4;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        $filestorage->saveLibrary($lib);

        $this->assertCount(2, $this->files['scripts']);
        $this->assertCount(2, $this->files['styles']);

        $key = 'testhashkey';

        $filestorage->cacheAssets($this->files, $key);
        $this->assertCount(1, $this->files['scripts']);
        $this->assertCount(1, $this->files['styles']);
        $expectedfile = DIRECTORY_SEPARATOR . file_storage::CACHED_ASSETS_FILEAREA . DIRECTORY_SEPARATOR . $key . '.js';
        $this->assertEquals($expectedfile, $this->files['scripts'][0]->path);
        $expectedfile = DIRECTORY_SEPARATOR . file_storage::CACHED_ASSETS_FILEAREA . DIRECTORY_SEPARATOR . $key . '.css';
        $this->assertEquals($expectedfile, $this->files['styles'][0]->path);
    }

    /**
     * Test that cached files can be retrieved via a key.
     */
    public function test_getCachedAssets() {
        $this->resetAfterTest();

        $filestorage = new file_storage();
        // Get a temp path.
        $temppath = $filestorage->getTmpPath();
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'test-1.0';

        $machinename = 'TestLib';
        $majorversion = 1;
        $minorversion = 0;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib);

        // Second library.
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'supertest-2.4';

        $machinename = 'SuperTest';
        $majorversion = 2;
        $minorversion = 4;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        $filestorage->saveLibrary($lib);

        $this->assertCount(2, $this->files['scripts']);
        $this->assertCount(2, $this->files['styles']);

        $key = 'testhashkey';
        $filestorage->cacheAssets($this->files, $key);

        $testarray = $filestorage->getCachedAssets($key);
        $this->assertCount(1, $testarray['scripts']);
        $this->assertCount(1, $testarray['styles']);
        $expectedfile = DIRECTORY_SEPARATOR . file_storage::CACHED_ASSETS_FILEAREA . DIRECTORY_SEPARATOR . $key . '.js';
        $this->assertEquals($expectedfile, $testarray['scripts'][0]->path);
        $expectedfile = DIRECTORY_SEPARATOR . file_storage::CACHED_ASSETS_FILEAREA . DIRECTORY_SEPARATOR . $key . '.css';
        $this->assertEquals($expectedfile, $testarray['styles'][0]->path);
    }

    /**
     * Test that cache files in the files system can be removed.
     */
    public function test_deleteCachedAssets() {
        $this->resetAfterTest();

        $filestorage = new file_storage();
        // Get a temp path.
        $temppath = $filestorage->getTmpPath();
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'test-1.0';

        $machinename = 'TestLib';
        $majorversion = 1;
        $minorversion = 0;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib);

        // Second library.
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'supertest-2.4';

        $machinename = 'SuperTest';
        $majorversion = 2;
        $minorversion = 4;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        $filestorage->saveLibrary($lib);

        $this->assertCount(2, $this->files['scripts']);
        $this->assertCount(2, $this->files['styles']);

        $key = 'testhashkey';
        $filestorage->cacheAssets($this->files, $key);

        $testarray = $filestorage->getCachedAssets($key);
        $this->assertCount(1, $testarray['scripts']);
        $this->assertCount(1, $testarray['styles']);

        // Time to delete.
        $filestorage->deleteCachedAssets([$key]);
        $testarray = $filestorage->getCachedAssets($key);
        $this->assertNull($testarray);
    }

    /**
     * Retrieve content from a file given a specific path.
     */
    public function test_getContent() {
        $this->resetAfterTest();

        $filestorage = new file_storage();
        // Get a temp path.
        $temppath = $filestorage->getTmpPath();
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'test-1.0';

        $machinename = 'TestLib';
        $majorversion = 1;
        $minorversion = 0;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib);

        $content = $filestorage->getContent($this->files['scripts'][0]->path);
        // Should return the text from the method create_file() above.
        $this->assertEquals('test data', $content);
    }

    /**
     * Test that an upgrade script can be found on the file system.
     */
    public function test_getUpgradeScript() {
        $this->resetAfterTest();
        // Upload an upgrade file.
        $machinename = 'TestLib';
        $majorversion = 3;
        $minorversion = 1;
        $filepath = DIRECTORY_SEPARATOR . "{$machinename}-{$majorversion}.{$minorversion}" . DIRECTORY_SEPARATOR;
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => file_storage::COMPONENT,
            'filearea' => file_storage::LIBRARY_FILEAREA,
            'itemid' => 15,
            'filepath' => $filepath,
            'filename' => 'upgrade.js'
        ];
        $filestorage = new file_storage();
        $fs->create_file_from_string($filerecord, 'test string info');
        $expectedfilepath = DIRECTORY_SEPARATOR . file_storage::LIBRARY_FILEAREA . $filepath . 'upgrade.js';
        $this->assertEquals($expectedfilepath, $filestorage->getUpgradeScript($machinename, $majorversion, $minorversion));
        $this->assertNull($filestorage->getUpgradeScript($machinename, $majorversion, 7));
    }

    /**
     * Test that information from a source can be saved to the specified path.
     * The zip file has the following contents
     * - h5ptest
     * |- content
     * |     |- content.json
     * |- testFont
     * |     |- testfont.min.css
     * |- testJavaScript
     * |     |- testscript.min.js
     * |- h5p.json
     */
    public function test_saveFileFromZip() {
        global $CFG;
        $ziparchive = new zip_archive();
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'h5ptest.zip';
        $result = $ziparchive->open($path, file_archive::OPEN);

        $filestorage = new file_storage();
        $basedir = $filestorage->getTmpPath();

        $files = $ziparchive->list_files();
        foreach ($files as $file) {
            if (!$file->is_directory) {
                $stream = $ziparchive->get_stream($file->index);
                $items = explode(DIRECTORY_SEPARATOR, $file->pathname);
                array_shift($items);
                $path = implode(DIRECTORY_SEPARATOR, $items);
                $filestorage->saveFileFromZip($basedir, $path, $stream);
            }
        }
        $ziparchive->close();

        $this->directory_and_file_check($basedir, 'h5p.json', 4);
        $this->directory_and_file_check($basedir . DIRECTORY_SEPARATOR . 'content', 'content.json', 1);
        $this->directory_and_file_check($basedir . DIRECTORY_SEPARATOR . 'testFont', 'testfont.min.css', 1);
        $this->directory_and_file_check($basedir . DIRECTORY_SEPARATOR . 'testJavaScript', 'testscript.min.js', 1);
    }

    /**
     * Test that a library is fully deleted from the file system
     */
    public function test_delete_library() {
        global $DB;

        $this->resetAfterTest();

        $filestorage = new file_storage();
        // Get a temp path.
        $temppath = $filestorage->getTmpPath();
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'test-1.0';

        $machinename = 'TestLib';
        $majorversion = 1;
        $minorversion = 0;
        $lib = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib);

        // Save a second library to ensure we aren't deleting all libraries, but just the one specified.
        $basedirectory = $temppath . DIRECTORY_SEPARATOR . 'awesomelib-2.1';

        $machinename = 'AwesomeLib';
        $majorversion = 2;
        $minorversion = 1;
        $lib2 = $this->create_library($basedirectory, $machinename, $majorversion, $minorversion);

        // Now run the API call.
        $filestorage->saveLibrary($lib2);

        $records = $DB->get_records('files', ['component' => file_storage::COMPONENT,
                'filearea' => file_storage::LIBRARY_FILEAREA]);
        $this->assertCount(14, $records);

        $filestorage->delete_library($lib);

        // Let's look at the records.
        $records = $DB->get_records('files', ['component' => file_storage::COMPONENT,
                'filearea' => file_storage::LIBRARY_FILEAREA]);
        $this->assertCount(7, $records);

        // Check that the db count is still the same after setting the libraryId to false.
        $lib['libraryId'] = false;
        $filestorage->delete_library($lib);

        $records = $DB->get_records('files', ['component' => file_storage::COMPONENT,
                'filearea' => file_storage::LIBRARY_FILEAREA]);
        $this->assertCount(7, $records);
    }

    /**
     * Check that there are a number of files in a directory and that a filename is found.
     *
     * @param  string $directory Directory to scan
     * @param  string $filename  File to search for in the directory
     * @param  int    $filecount Number of files expected in the directory
     */
    protected function directory_and_file_check(string $directory, string $filename, int $filecount) {
        $dirandfiles = scandir($directory);
        // Let's try and remove the . and .. if possible.
        $dirandfiles = array_diff($dirandfiles, ['.', '..']);

        $this->assertCount($filecount, $dirandfiles);
        $key = array_search($filename, $dirandfiles);
        $this->assertEquals($filename, $dirandfiles[$key]);
    }
}