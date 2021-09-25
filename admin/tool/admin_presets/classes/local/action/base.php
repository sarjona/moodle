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

namespace tool_admin_presets\local\action;

defined('MOODLE_INTERNAL') || die();

use context_system;
use moodle_url;
use tool_admin_presets\manager;
use tool_admin_presets\output\presets_list;
use tool_admin_presets\output\export_import;

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin tool presets main controller class.
 *
 * @package          tool_admin_presets
 * @copyright        2021 Pimenko <support@pimenko.com><pimenko.com>
 * @author           Jordan Kesraoui | Sylvain Revenu | Pimenko based on David Monllaó <david.monllao@urv.cat> code
 * @license          http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base {

    protected static $eventsactionsmap = [
        'base' => 'presets_listed',
        'delete' => 'preset_deleted',
        'export' => 'preset_exported',
        'import' => 'preset_imported',
        'preview' => 'preset_previewed',
        'load' => 'preset_loaded',
        'rollback' => 'preset_reverted',
        'download_xml' => 'preset_downloaded'
    ];

    protected $action;
    protected $mode;
    protected $id;
    protected $adminroot;
    protected $outputs;
    protected $moodleform;
    protected $rel;
    protected $manager;

    /**
     * Loads common class attributes and initializes sensible settings and DB - XML relations
     */
    public function __construct() {

        $this->manager = new manager();
        $this->action = optional_param('action', 'base', PARAM_ALPHA);
        $this->mode = optional_param('mode', 'show', PARAM_ALPHAEXT);
        $this->id = optional_param('id', false, PARAM_INT);

        // DB - XML relations.
        $this->rel = [
            'name' => 'NAME',
            'comments' => 'COMMENTS',
            'timecreated' => 'PRESET_DATE',
            'site' => 'SITE_URL',
            'author' => 'AUTHOR',
            'moodleversion' => 'MOODLE_VERSION',
            'moodlerelease' => 'MOODLE_RELEASE'
        ];

        // Sensible settings.
        $sensiblesettings = explode(',', str_replace(' ', '', get_config('tool_admin_presets', 'sensiblesettings')));
        $this->sensiblesettings = array_combine($sensiblesettings, $sensiblesettings);
    }

    /**
     * Method to list the presets available on the system
     *
     * It allows users to access the different preset
     * actions (preview, load, download, delete and rollback)
     */
    public function show(): void {
        global $DB, $OUTPUT;

        $options = new export_import();
        $this->outputs = $OUTPUT->render($options);

        $presets = $DB->get_records('tool_admin_presets');
        $list = new presets_list($presets, true);
        $this->outputs .= $OUTPUT->render($list);
    }

    /**
     * Main display method
     *
     * Prints the block header and the common block outputs, the
     * selected action outputs, his form and the footer
     *
     * $outputs value depends on $mode and $action selected
     */
    public function display(): void {
        global $OUTPUT;

        $this->_display_header();

        // Other outputs.
        if (!empty($this->outputs)) {
            echo $this->outputs;
        }

        // Form.
        if ($this->moodleform) {
            $this->moodleform->display();
        }

        // Footer.
        echo $OUTPUT->footer();
    }

    /**
     * Displays the header
     */
    protected function _display_header(): void {

        global $CFG, $PAGE, $OUTPUT, $SITE;

        // Strings.
        $actionstr = get_string('action' . $this->action, 'tool_admin_presets');
        $modestr = get_string($this->action . $this->mode, 'tool_admin_presets');
        $titlestr = get_string('pluginname', 'tool_admin_presets');

        // Header.
        $PAGE->set_title($titlestr);
        $PAGE->set_heading($SITE->fullname);

        $PAGE->navbar->add(get_string('pluginname', 'tool_admin_presets'),
            new moodle_url($CFG->wwwroot . '/admin/tool/admin_presets/index.php'));

        $PAGE->navbar->add($actionstr . ': ' . $modestr);

        echo $OUTPUT->header();

        echo $OUTPUT->heading($actionstr . ': ' . $modestr, 1);
    }

    public function log(): void {
        // The only read action we store is list presets and preview.
        $islist = ($this->action == 'base' && $this->mode == 'show');
        $ispreview = ($this->action == 'load' && $this->mode == 'show');
        if ($this->mode != 'show' || $islist || $ispreview) {
            $action = $this->action;
            if ($ispreview) {
                $action = 'preview';
            }

            if ($this->mode != 'execute' && $this->mode != 'show') {
                $action = $this->mode;
            }

            if (array_key_exists($action, self::$eventsactionsmap)) {
                $eventnamespace = '\\tool_admin_presets\\event\\' . self::$eventsactionsmap[$action];
                $eventdata = [
                    'context' => context_system::instance(),
                    'objectid' => $this->id
                ];
                $event = $eventnamespace::create($eventdata);
                $event->trigger();
            }
        }
    }
}
