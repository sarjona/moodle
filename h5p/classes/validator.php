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
 * H5P factory class.
 * This class is used to decouple the construction of H5P related objects.
 *
 * @package    core_h5p
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

use H5PValidator;
use ZipArchive;
use stdClass;;

/**
 * H5P factory class.
 * This class is used to decouple the construction of H5P related objects.
 *
 * @package    core_h5p
 * @copyright  2019 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator extends H5PValidator {
    /**
     * Validates a .h5p file.
     *
     * Note: We do some overriding here to take an arbitrary uploaded file from any user and use it to fetch the main
     * library direct from H5P (a theoretically a trusted source).
     *
     * @param bool $skipContent
     * @param bool $upgradeOnly
     * @return bool Whether the file is valid
     */
    public function isValidPackage($skipContent = false, $upgradeOnly = false) {
        // Create a temporary dir to extract package in.
        $tmpdir = $this->h5pF->getUploadedH5pFolderPath();
        $zipfile = $this->h5pF->getUploadedH5pPath();

        $starttime = microtime();
        [
            'valid' => $valid,
            'path' => $contentfilelocation,
            'h5p.json' => $mainJsonData,
            'content.json' => $contentJsonData,
        ] = $this->extract_content_files($zipfile);
        unlink($zipfile);
        $starttime = microtime();

        if (!$valid) {
            return false;
        }

        $this->h5pC->librariesJsonData = [];
        $mainlibraryname = $mainJsonData['mainLibrary'];
        foreach ($mainJsonData['preloadedDependencies'] as $idx => $dependency) {
            if ($latestlibrary = $this->h5pF->get_latest_library_version($dependency['machineName'])) {
                if ($dependency['majorVersion'] < $latestlibrary->minorversion || $dependency['minorVersion'] < $latestlibrary->minorversion) {
                    $dependency['majorVersion'] = $latestlibrary->majorversion;
                    $dependency['minorVersion'] = $latestlibrary->minorversion;
                    $mainJsonData['preloadedDependencies'][$idx] = $dependency;
                }
            }

            if ($dependency['machineName'] == $mainlibraryname) {
                $maindependency = $dependency;
                break;
            }
        }
        if (null === $maindependency) {
            throw new \moodle_exception('Nope');
        }
        if (!$this->h5pF->getLibraryId($maindependency['machineName'], $maindependency['majorVersion'], $maindependency['minorVersion'])) {
            error_log("Could not find a library with that id for {$maindependency['machineName']} version {$maindependency['majorVersion']}.{$maindependency['minorVersion']}");
            // Download the official source of the main library for the uploaded h5p file.
            $h5ppath = make_request_directory() . "/library.h5p";
            $result = download_file_content(
                $this->get_h5p_url_for_main_library($mainlibraryname),
                null,
                null,
                true,
                300,
                20,
                false,
                $h5ppath
            );
            $starttime = microtime();
            if ($result === false) {
                throw new moodle_exception('Unable to download H5P library');
            }

            // Update the framework to point ot this file and then call the parent isValidPackage to unpack and validate that package.
            $this->h5pF->getUploadedH5pPath($h5ppath);
            error_log("Validating package...");
            $starttime = microtime();
            $valid = parent::isValidPackage($skipContent, $upgradeOnly);
            error_log("Validated package in " . microtime_diff($starttime, microtime()));
            $starttime = microtime();
        }

        foreach ($mainJsonData['preloadedDependencies'] as $idx => $dependency) {
            if ($latestlibrary = $this->h5pF->get_latest_library_version($dependency['machineName'])) {
                if ($dependency['majorVersion'] < $latestlibrary->minorversion || $dependency['minorVersion'] < $latestlibrary->minorversion) {
                    $dependency['majorVersion'] = $latestlibrary->majorversion;
                    $dependency['minorVersion'] = $latestlibrary->minorversion;
                    $mainJsonData['preloadedDependencies'][$idx] = $dependency;
                }
            }
        }

        if (!$skipContent) {
            // Replace any content of the downloaded official H5P file with our own.
            core::deleteFileTree("{$tmpdir}/content");
            rename("{$contentfilelocation}/content", "{$tmpdir}/content");
            $this->h5pC->contentJsonData = $contentJsonData;

            // Set the details of the actually uploaded file.
            $this->h5pC->mainJsonData = $mainJsonData;
        }

        return $valid;
    }

    /**
     * Get the H5P URL for the specified main library.
     *
     * @param string $mainlibrary The name of the main library
     * @return string The URL for the main library
     */
    protected function get_h5p_url_for_main_library(string $mainlibrary): string {
        return "https://api.h5p.org/v1/content-types/{$mainlibrary}";
    }

    /**
     * Extract just the content and main h5p.json file.
     *
     * @param string $h5pfile The path to the uploaded H5P file on disk
     * @return array
     */
    protected function extract_content_files(string $h5pfile): ?array {
        $skipContent = false;
        $targetdir = make_request_directory();
        $targetdir = make_temp_directory('h5p');

        // Extract and then remove the package file.
        $zip = new ZipArchive();

        // Open the package
        if ($zip->open($h5pfile) !== true) {
            $this->h5pF->setErrorMessage($this->h5pF->t('The file you uploaded is not a valid HTML5 Package (We are unable to unzip it)'), 'unable-to-unzip');
            return null;
        }

        if ($this->h5pC->disableFileCheck !== true) {
            list($contentWhitelist, $contentRegExp) = $this->getWhitelistRegExp(false);
        }
        $valid = true;
        $libraries = array();

        $totalSize = 0;
        $mainH5pExists = false;
        $contentExists = false;

        $filestoextract = [];

        // Check for valid file types, JSON files + file sizes before continuing to unpack.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileStat = $zip->statIndex($i);

            $filename = \core_text::strtolower($fileStat['name']);
            if (preg_match('/(^[\._]|\/[\._])/', $filename) !== 0) {
                continue; // Skip any file or folder starting with a . or _
            }

            if ($filename === 'h5p.json') {
                $mainH5pExists = true;
            } else if (!$skipContent && $filename === 'content/content.json') {
                $contentExists = true;
            } else if (!$skipContent && substr($filename, 0, 8) === 'content/') {
                // This is a content file, check that the file type is allowed
                if ($skipContent === false && $this->h5pC->disableFileCheck !== true && !preg_match($contentRegExp, $filename)) {
                    $this->h5pF->setErrorMessage($this->h5pF->t('File "%filename" not allowed. Only files with the following extensions are allowed: %files-allowed.', array('%filename' => $fileStat['name'], '%files-allowed' => $contentWhitelist)), 'not-in-whitelist');
                    $valid = false;
                }
            } else {
                // This must be a library file.
                // We only process libraries from h5p.json so skip this one.
                continue;
            }

            $fileStream = $zip->getStream($fileStat['name']);
            $this->h5pC->fs->saveFileFromZip($targetdir, $fileStat['name'], $fileStream);

            if (!empty($this->h5pC->maxFileSize) && $fileStat['size'] > $this->h5pC->maxFileSize) {
                // Error file is too large
                $this->h5pF->setErrorMessage($this->h5pF->t('One of the files inside the package exceeds the maximum file size allowed. (%file %used > %max)', array('%file' => $fileStat['name'], '%used' => ($fileStat['size'] / 1048576) . ' MB', '%max' => ($this->h5pC->maxFileSize / 1048576) . ' MB')), 'file-size-too-large');
                $valid = false;
            }
            $totalSize += $fileStat['size'];

        }
        $zip->close();

        if (!empty($this->h5pC->maxTotalSize) && $totalSize > $this->h5pC->maxTotalSize) {
            // Error total size of the zip is too large
            $this->h5pF->setErrorMessage($this->h5pF->t('The total size of the unpacked files exceeds the maximum size allowed. (%used > %max)', array('%used' => ($totalSize / 1048576) . ' MB', '%max' => ($this->h5pC->maxTotalSize / 1048576) . ' MB')), 'total-size-too-large');
            $valid = false;
        }

        if (!$valid) {
            return null;
        }

        if ($skipContent === false) {
            // Not skipping content, require two valid JSON files from the package
            if (!$contentExists) {
                $this->h5pF->setErrorMessage($this->h5pF->t('A valid content folder is missing'), 'invalid-content-folder');
                $valid = false;
            } else {
                $contentJsonData = json_decode(file_get_contents("{$targetdir}/content/content.json"), true);
                if ($contentJsonData === NULL) {
                    @$zip->close();
                    return null; // Breaking error when reading from the archive.
                } else if ($contentJsonData === false) {
                    $valid = false; // Validation error when parsing JSON
                }
            }

            if (!$mainH5pExists) {
                $this->h5pF->setErrorMessage($this->h5pF->t('A valid main h5p.json file is missing'), 'invalid-h5p-json-file');
                $valid = false;
            } else {
                $mainH5pData = json_decode(file_get_contents("{$targetdir}/h5p.json"), true);
                if ($mainH5pData === null) {
                    @$zip->close();
                    return null; // Breaking error when reading from the archive.
                } else if ($mainH5pData === false) {
                    $valid = false; // Validation error when parsing JSON
                } else if (!$this->isValidH5pData((array) $mainH5pData, 'h5p.json', $this->h5pRequired, $this->h5pOptional)) {
                    $this->h5pF->setErrorMessage($this->h5pF->t('The main h5p.json file is not valid'), 'invalid-h5p-json-file'); // Is this message a bit redundant?
                    $valid = false;
                }
            }
        }

        if (!$valid) {
            return null;
        }

        return [
            'valid' => $valid,
            'path' => $targetdir,
            'h5p.json' => $mainH5pData,
            'content.json' => $contentJsonData,
        ];
    }
}
