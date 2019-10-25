<?php

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/filelib.php");

//use moodle_url;

class downloader {
    protected $factory;

    public function __construct() {
        $this->factory = new \core_h5p\factory();
    }
    public function fetch_all(): void {
        $framework = $this->factory->get_framework();

        $url = static::get_api_url(null);
        $json = download_file_content($url, null, [
            'uuid' => 'foo',
        ]);
        $contenttypes = json_decode($json);
        foreach ($contenttypes->contentTypes as $type) {
            $library = [
                'machineName' => $type->id,
                'majorVersion' => $type->version->major,
                'minorVersion' => $type->version->minor,
                'patchVersion' => $type->version->patch,
            ];

            $shoulddownload = true;
            if ($framework->getLibraryId($type->id, $type->version->major, $type->version->minor)) {
                if (!$framework->isPatchedLibrary($library)) {
                    $shoulddownload = false;
                }
            }
            if ($shoulddownload) {
                error_log("Will update the {$type->id} library");
                $id = $this->fetch_package($type);
                error_log("Successfully fetched {$id}");
            } else {
                error_log("Will NOT update the {$type->id} library");
            }
        }
    }

    public function fetch_package(\stdClass $package): ?int {
        $framework = $this->factory->get_framework();
        $validator = $this->factory->get_validator();

        $downloadpath = make_request_directory();
        $downloadfile = "{$downloadpath}/type.h5p";

        $url = $this->get_api_url($package->id);
            $result = download_file_content(
                $url,
                null,
                null,
                true,
                300,
                20,
                false,
                $downloadfile
            );

        // Add manually the extension to the file to avoid the validation fails.
        $framework->getUploadedH5pPath($downloadfile);
        $framework->getUploadedH5pFolderPath($downloadpath);

        // Check if the h5p file is valid before saving it.
        if ($validator->isValidPackage(false, false)) {
            $h5pstorage = $this->factory->get_storage();
            $id = $h5pstorage->savePackage([], null, true);
            var_dump($id);

            return $id;
        }

        return null;
    }

    protected function get_api_url(?string $library): moodle_url {
        return new moodle_url("https://api.h5p.org/v1/content-types/{$library}");
    }
}

$downloader = new downloader();
$downloader->fetch_all();
