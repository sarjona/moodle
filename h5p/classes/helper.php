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
 * Contains helper class for the H5P area.
 *
 * @package    core_h5p
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for the H5P area.
 *
 * @copyright  2019 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Store an H5P file.
     *
     * @param factory $factory The \core_h5p\factory object
     * @param stored_file $file Moodle file instance
     * @param stdClass $config Button options config
     * @param bool $onlyupdatelibs Whether new libraries can be installed or only the existing ones can be updated
     * @param bool $skipcontent Should the content be skipped (so only the libraries will be saved)?
     *
     * @return int|false|null The H5P identifier or null if there is an error when saving or false if it's not a valid H5P package
     */
    public static function save_h5p(factory $factory, \stored_file $file, \stdClass $config, bool $onlyupdatelibs = false,
            bool $skipcontent = false) {
        // This may take a long time.
        \core_php_time_limit::raise();

        $core = $factory->get_core();
        $path = $core->fs->getTmpPath();
        $core->h5pF->getUploadedH5pFolderPath($path);
        // Add manually the extension to the file to avoid the validation fails.
        $path .= '.h5p';
        $core->h5pF->getUploadedH5pPath($path);

        // Copy the .h5p file to the temporary folder.
        $file->copy_content_to($path);

        // Check if the h5p file is valid before saving it.
        $h5pvalidator = $factory->get_validator();
        if ($h5pvalidator->isValidPackage($skipcontent, $onlyupdatelibs)) {
            $h5pstorage = $factory->get_storage();

            $content = [
                'pathnamehash' => $file->get_pathnamehash(),
                'contenthash' => $file->get_contenthash(),
            ];
            $options = ['disable' => self::get_display_options($core, $config)];

            $h5pstorage->savePackage($content, null, $skipcontent, $options);

            return $h5pstorage->contentId;
        }

        return false;
    }


    /**
     * Get the representation of display options as int.
     *
     * @param core $core The \core_h5p\core object
     * @param stdClass $config Button options config
     *
     * @return int The representation of display options as int
     */
    public static function get_display_options(core $core, \stdClass $config) : int {
        $export = isset($config->export) ? $config->export : 0;
        $embed = isset($config->embed) ? $config->embed : 0;
        $copyright = isset($config->copyright) ? $config->copyright : 0;
        $frame = ($export || $embed || $copyright);
        if (!$frame) {
            $frame = isset($config->frame) ? $config->frame : 0;
        }

        $disableoptions = [
            core::DISPLAY_OPTION_FRAME     => $frame,
            core::DISPLAY_OPTION_DOWNLOAD  => $export,
            core::DISPLAY_OPTION_EMBED     => $embed,
            core::DISPLAY_OPTION_COPYRIGHT => $copyright,
        ];

        return $core->getStorableDisplayOptions($disableoptions, 0);
    }

}
