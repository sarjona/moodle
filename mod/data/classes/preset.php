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

namespace mod_data;

use stdClass;
use stored_file;

/**
 * Class preset for database activity.
 *
 * @package    mod_data
 * @copyright  2022 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset {

    /** @var manager manager instance. */
    private $manager;

    /** @var bool whether the preset is a plugin or has been saved by the user. */
    public $isplugin;

    /** @var string The preset patch (datapreset name for the plugin and /presetname/ for saved presets). */
    public $path;

    /** @var string The preset name. */
    public $name;

    /** @var string The preset shortname. For datapreset plugins that's is the folder; for saved presets, that's the preset name. */
    public $shortname;

    /** @var string The preset description. */
    public $description;

    /** @var int The preset author. */
    public $userid;

    /** @var stored_file For saved presets that's the file object for the root folder. It's null for plugin presets. */
    public $storedfile;

    /**
     * Class constructor.
     *
     * @param manager $manager the current instance manager
     * @param string $templatecontent the template string to use
     * @param array $options an array of extra diplay options
     */
    public function __construct(
        ?manager $manager,
        bool $isplugin,
        string $name,
        string $shortname,
        string $path,
        ?string $description = '',
        ?int $userid = 0,
        ?stored_file $storedfile = null
    ) {
        $this->manager = $manager;
        $this->isplugin = $isplugin;
        $this->path = $path;
        $this->name = $name;
        $this->shortname = $shortname;
        $this->description = $description;
        $this->userid = $userid;
        $this->storedfile = $storedfile;
    }

    /**
     * Create a preset instance from a stored file.
     *
     * @param manager $manager
     * @param stored_file $file
     * @return preset
     */
    public static function create_from_storedfile(manager $manager, stored_file $file): self {
        $isplugin = false;
        $path = $file->get_filepath();
        $name = trim($path, '/');
        $userid = $file->get_userid();
        $description = static::get_attribute_value($file, 'description');

        return new self($manager, $isplugin, $name, $name, $path, $description, $userid, $file);
    }

    /**
     * Create a preset instance from a plugin.
     *
     * @param manager|null $manager
     * @param string $pluginname
     * @param string $plugindir
     * @return preset
     */
    public static function create_from_plugin(?manager $manager, string $pluginname, string $plugindir): self {
        $isplugin = true;
        $path = $plugindir;
        $shortname = $pluginname;
        $name = static::get_name_from_plugin($pluginname);
        $description = static::get_description_from_plugin($pluginname);

        return new self($manager, $isplugin, $name, $shortname, $path, $description);
    }

    /**
     * Create a preset instance from a data_record entry.
     *
     * @param stdClass $record the data_record record
     * @return preset
     */
    public static function create_from_instance(manager $manager, string $presetname, ?string $description = ''): self {
        $isplugin = false;
        $path = '/' . $presetname . '/';
        $name = $presetname;
        $description = $description;

        return new self($manager, $isplugin, $name, $name, $path, $description);
    }

    public static function get_attribute_value(stored_file $file, string $name) {
        global $CFG;
        require_once($CFG->libdir.'/xmlize.php');

        $presetxml = static::get_content_from_file($file, 'preset.xml');
        $parsedxml = xmlize($presetxml, 0);

        $value = '';
        switch ($name) {
            case 'description':
                if (key_exists('description', $parsedxml['preset']['#'])) {
                    $value = $parsedxml['preset']['#']['description'][0]['#'][0];
                }
                break;
        }

        return $value;
    }

    /**
     * Save the database activity configuration as a preset.
     *
     * @param stdClass $course The course the database module belongs to.
     * @param stdClass $cm The course module record
     * @param stdClass $instance The database record
     * @param string $presetname
     * @return bool
     */
    public function save() {
        global $USER;

        $fs = get_file_storage();

        $filerecord = new stdClass;
        $filerecord->contextid = DATA_PRESET_CONTEXT;
        $filerecord->component = DATA_PRESET_COMPONENT;
        $filerecord->filearea = DATA_PRESET_FILEAREA;
        $filerecord->itemid = 0;
        $filerecord->filepath = $this->path;
        $filerecord->userid = $USER->id;

        // Create and save the preset.xml file, with the description, settings, fields...
        $filerecord->filename = 'preset.xml';
        $instance = $this->manager->get_instance();
        $fs->create_file_from_string($filerecord, static::generate_xml($instance, $this->description));

        // Create and save the template files.
        foreach (manager::TEMPLATES_LIST as $templatename => $templatefile) {
            $filerecord->filename = $templatefile;
            $fs->create_file_from_string($filerecord, $instance->{$templatename});
        }

        return true;
    }

    /**
     * Retrieve the contents of a file. That file may either be in a conventional directory of the Moodle file storage.
     *
     * @param stored_file $file the directory to look in. null if using a conventional directory
     * @param string $filename the name of the file we want
     * @return string the contents of the file or null if the file doesn't exist.
     */
    public static function get_content_from_file(stored_file $file, string $filename) {
        $fs = get_file_storage();

        $fileexists = $fs->file_exists(
            DATA_PRESET_CONTEXT,
            DATA_PRESET_COMPONENT,
            DATA_PRESET_FILEAREA,
            0,
            $file->get_filepath(),
            $filename
        );
        if ($fileexists) {
            $file = $fs->get_file(
                DATA_PRESET_CONTEXT,
                DATA_PRESET_COMPONENT,
                DATA_PRESET_FILEAREA,
                0,
                $file->get_filepath(),
                $filename
            );
            return $file->get_content();
        } else {
            return null;
        }
    }

    /**
     * Generates the XML for the database module provided
     *
     * @global moodle_database $DB
     * @param stdClass $course The course the database module belongs to.
     * @param stdClass $cm The course module record
     * @param stdClass $data The database record
     * @return string The XML for the preset
     */
    protected static function generate_xml(stdClass $data, ?string $description = '') {
        global $DB;

        // Assemble "preset.xml".
        $presetxmldata = "<preset>\n\n";

        // Raw settings are not preprocessed during saving of presets.
        $rawsettings = [
            'intro',
            'comments',
            'requiredentries',
            'requiredentriestoview',
            'maxentries',
            'rssarticles',
            'approval',
            'manageapproved',
            'defaultsortdir'
        ];

        $presetxmldata .= '<description>' . htmlspecialchars($description) . "<\description>\n\n";

        $presetxmldata .= "<settings>\n";
        // First, settings that do not require any conversion.
        foreach ($rawsettings as $setting) {
            $presetxmldata .= "<$setting>" . htmlspecialchars($data->$setting) . "</$setting>\n";
        }

        // Now specific settings.
        if ($data->defaultsort > 0 && $sortfield = data_get_field_from_id($data->defaultsort, $data)) {
            $presetxmldata .= '<defaultsort>' . htmlspecialchars($sortfield->field->name) . "</defaultsort>\n";
        } else {
            $presetxmldata .= "<defaultsort>0</defaultsort>\n";
        }
        $presetxmldata .= "</settings>\n\n";
        // Now for the fields. Grab all that are non-empty.
        $fields = $DB->get_records('data_fields', ['dataid' => $data->id]);
        ksort($fields);
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $presetxmldata .= "<field>\n";
                foreach ($field as $key => $value) {
                    if ($value != '' && $key != 'id' && $key != 'dataid') {
                        $presetxmldata .= "<$key>" . htmlspecialchars($value) . "</$key>\n";
                    }
                }
                $presetxmldata .= "</field>\n\n";
            }
        }
        $presetxmldata .= '</preset>';

        return $presetxmldata;
    }

    /**
     * Checks if a directory contains all the required files to define a preset.
     *
     * @param string $directory The patch to check if it contains the preset files or not.
     * @return bool True if the directory contains all the preset files; false otherwise.
     */
    public static function is_directory_a_preset(string $directory): bool {
        $status = true;
        $directory = rtrim($directory, '/\\') . '/';
        $presetfilenames = array_merge(array_values(manager::TEMPLATES_LIST), ['preset.xml']);
        foreach ($presetfilenames as $filename) {
            $status &= file_exists($directory.$filename);
        }

        return $status;
    }

    /**
     * Returns the best name to show for a datapreset plugin.
     *
     * @param string $pluginname The datapreset plugin name.
     * @return string The plugin preset name to display.
     */
    public static function get_name_from_plugin(string $pluginname): string {
        if (get_string_manager()->string_exists('modulename', 'datapreset_'.$pluginname)) {
            return get_string('modulename', 'datapreset_'.$pluginname);
        } else {
            return $pluginname;
        }
    }

    /**
     * Returns the description to show for a datapreset plugin.
     *
     * @param string $pluginname The datapreset plugin name.
     * @return string The plugin preset description to display.
     */
    public static function get_description_from_plugin(string $pluginname): string {
        if (get_string_manager()->string_exists('modulename_help', 'datapreset_'.$pluginname)) {
            return get_string('modulename_help', 'datapreset_'.$pluginname);
        } else {
            return '';
        }
    }
}
