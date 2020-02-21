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
 * Content bank extensions manager class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank;

defined('MOODLE_INTERNAL') || die();

/**
 * Content bank extensions manager class
 *
 * Usage:
 * $manager = core_contentbank_extensions::instance();
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extensions {

    /** @var core_contentbank_extensions caches a singleton instance */
    static private $instance;

    /** @var array Array of available 'types' objects */
    private $types;

    /** @var array Array of supported file extensions */
    private $supportedextensions;


    /**
     * Returns a singleton instance of a manager
     *
     * @return core_contentbank_extensions
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct a new core_contentbank_extensions instance
     *
     * @see core_contentbank_extensions::instance()
     */
    private function __construct() {
        $this->types = $this->get_types();
    }

    /**
     * Obtains the list of core_contentbank_content objects currently active.
     *
     * The list is not in rank order and does not include players which are disabled.
     *
     * @return core_contentbank_content[] Array of core_contentbank_content objects
     */
    private function get_types() {
        if ($this->types) {
            return $this->types;
        }
        $types = \core\plugininfo\contentbank::get_enabled_plugins();
        $this->types = [];
        foreach ($types as $name) {
            $classname = "\\contentbank_$name\\plugin";
            if (class_exists($classname)) {
                $this->types[] = $classname;
            }
        }
        return $this->types;
    }

    /**
     * Obtains an array of supported extensions by active plugins.
     *
     * @return array The array with all the extensions supported.
     */
    public function get_supported_extensions() {
        if ($this->supportedextensions) {
            return $this->supportedextensions;
        }
        $extensions = array();
        foreach ($this->get_types() as $type) {
            if ($type::can_upload()) {
                foreach ($type::get_manageable_extensions() as $extension) {
                    if (!empty($extension) && !in_array($extension, $extensions)) {
                        $extensions[] = $extension;
                    }
                }
            }
        }
        $this->supportedextensions = $extensions;
        return $this->supportedextensions;
    }

    /**
     * Obtains a string with all supported extensions by active plugins.
     * Mainly to use as filepicker options parameter.
     *
     * @return string A string with all the extensions supported.
     */
    public function get_supported_extensions_as_string() {
        return implode(',', self::get_supported_extensions());
    }

    /**
     * Returns the file extension for a file.
     *
     * @param  string $filename The name of the file
     * @return string The extension of the file
     */
    public function get_extension(string $filename) {
        $dot = strrpos($filename, '.');
        if ($dot === false) {
            return '';
        }
        return strtolower(substr($filename, $dot));
    }

    /**
     * Get the first content bank plugin supports a file extension.
     *
     * @param string $extension Content file extension
     * @return content_bank_content Content bank plugin supports the file extension
     */
    public function get_extension_supporter(string $extension) {
        foreach ($this->get_types() as $type) {
            $extensions = $type::get_manageable_extensions();
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }

        return null;
    }
}
