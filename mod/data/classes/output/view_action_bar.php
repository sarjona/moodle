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

namespace mod_data\output;

use data_portfolio_caller;
use mod_data\manager;
use moodle_url;
use portfolio_add_button;
use templatable;
use renderable;

/**
 * Renderable class for the action bar elements in the view pages in the database activity.
 *
 * @package    mod_data
 * @copyright  2021 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_action_bar implements templatable, renderable {

    /** @var int $id The database module id. */
    private $id;

    /** @var \url_select $urlselect The URL selector object. */
    private $urlselect;

    /** @var bool $hasentries Whether entries exist. */
    private $hasentries;

    /** @var bool $mode The current view mode (list, view...). */
    private $mode;

    /**
     * The class constructor.
     *
     * @param int $id The database module id.
     * @param \url_select $urlselect The URL selector object.
     * @param bool $hasentries Whether entries exist.
     * @param string $mode The current view mode (list, view...).
     */
    public function __construct(int $id, \url_select $urlselect, bool $hasentries, string $mode) {
        $this->id = $id;
        $this->urlselect = $urlselect;
        $this->hasentries = $hasentries;
        $this->mode = $mode;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output The renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $PAGE, $DB, $CFG, $USER;

        $data = [
            'urlselect' => $this->urlselect->export_for_template($output),
        ];

        // TODO: Remove the Add entry button from here (because it has been moved to the sticky footer - MDL-75401).
        $addentrybutton = new add_entries_action($this->id);
        $data['addentrybutton'] = $addentrybutton->export_for_template($output);

        $actionsselect = null;
        // Import entries.
        if (has_capability('mod/data:manageentries', $PAGE->context)) {
            $actionsselect = new \action_menu();
            $actionsselect->set_menu_trigger(get_string('actions'), 'btn btn-secondary');

            $importentrieslink = new moodle_url('/mod/data/import.php', ['d' => $this->id, 'backto' => $PAGE->url->out(false)]);
            $actionsselect->add(new \action_menu_link(
                $importentrieslink,
                null,
                get_string('importentries', 'mod_data'),
                false
            ));
        }
        // Export entries.
        if (has_capability(DATA_CAP_EXPORT, $PAGE->context) && $this->hasentries) {
            if (!$actionsselect) {
                $actionsselect = new \action_menu();
                $actionsselect->set_menu_trigger(get_string('actions'), 'btn btn-secondary');
            }
            $exportentrieslink = new moodle_url('/mod/data/export.php', ['d' => $this->id, 'backto' => $PAGE->url->out(false)]);
            $actionsselect->add(new \action_menu_link(
                $exportentrieslink,
                null,
                get_string('exportentries', 'mod_data'),
                false
            ));
        }

        // Export to portfolio. This is for exporting all records, not just the ones in the search.
        if ($this->mode == '' && !empty($CFG->enableportfolios) && $this->hasentries) {
            // Exportallentries and exportentry are basically the same capability.
            $canexport = has_capability('mod/data:exportallentries', $PAGE->context) ||
                    has_capability('mod/data:exportentry', $PAGE->context) ||
                    (has_capability('mod/data:exportownentry', $PAGE->context) &&
                    $DB->record_exists('data_records', ['userid' => $USER->id, 'dataid' => $this->id]));

            if ($canexport) {
                // Add the portfolio export button.
                require_once($CFG->libdir . '/portfoliolib.php');

                $activity = $DB->get_record('data', ['id' => $this->id], '*', MUST_EXIST);
                $manager = manager::create_from_instance($activity);
                $cm = $manager->get_coursemodule();

                $button = new portfolio_add_button();
                $button->set_callback_options(
                    'data_portfolio_caller',
                    ['id' => $cm->id],
                    'mod_data'
                );
                if (data_portfolio_caller::has_files($activity)) {
                    // No plain HTML.
                    $button->set_formats(array(PORTFOLIO_FORMAT_RICHHTML, PORTFOLIO_FORMAT_LEAP2A));
                }
                $exporturl = $button->to_html(PORTFOLIO_ADD_MOODLE_URL);
                if (!is_null($exporturl)) {
                    if (!$actionsselect) {
                        $actionsselect = new \action_menu();
                        $actionsselect->set_menu_trigger(get_string('actions'), 'btn btn-secondary');
                    }
                    $actionsselect->add(new \action_menu_link(
                        $exporturl,
                        null,
                        get_string('addtoportfolio', 'portfolio'),
                        false
                    ));
                }
            }
        }

        if ($actionsselect) {
            $data['actionsselect'] = $actionsselect->export_for_template($output);
        }

        return $data;
    }
}
