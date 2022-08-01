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
 * Javascript module for editing a database preset.
 *
 * @module      mod_data/editpreset
 * @copyright   2022 Sara Arjona <sara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

const selectors = {
    editPresetButton: '[data-action="editpreset"]',
};

/**
 * Initialize module
 */
export const init = () => {
    registerEventListeners();
};

/**
 * Register events for update/delete links.
 */
const registerEventListeners = () => {
    document.addEventListener('click', e => {
        const editAction = e.target.closest(selectors.editPresetButton);
        if (editAction) {
            e.preventDefault();

            const modalForm = new ModalForm({
                modalConfig: {
                    title: getString('editpreset', 'mod_data'),
                },
                formClass: 'mod_data\\form\\save_as_preset',
                args: {
                    d: editAction.getAttribute('data-dataid'),
                    action: editAction.getAttribute('data-action'),
                    presetname: editAction.getAttribute('data-presetname'),
                    presetdescription: editAction.getAttribute('data-presetdescription')
                },
                saveButtonText: getString('save'),
                returnFocus: editAction,
            });

            modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, e => {
                if (e.detail.result) {
                    window.location.reload();
                } else {
                    Notification.addNotification({
                        type: 'error',
                        message:  e.detail.errors.join('<br>')
                    });
                }
            });

            modalForm.show();
        }
    });
};
