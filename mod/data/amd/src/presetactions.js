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
 * Module to manage content bank actions, such as delete or rename.
 *
 * @module     mod_data/presetactions
 * @copyright  2022 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/templates',
    'core/url',
    'core/modal_factory',
    'core/modal_events'],
function($, Ajax, Notification, Str, Templates, Url, ModalFactory, ModalEvents) {

    /**
     * List of action selectors.
     *
     * @type {{DELETE_PRESET: string}}
     */
    var ACTIONS = {
        DELETE_PRESET: '[data-action="deletepreset"]',
    };

    /**
     * Actions class.
     */
    var Actions = function() {
        this.registerEvents();
    };

    /**
     * Register event listeners.
     */
    Actions.prototype.registerEvents = function() {
        $(ACTIONS.DELETE_PRESET).click(function(e) {
            e.preventDefault();

            var presetname = $(this).data('presetname');
            var dataid = $(this).data('dataid');
            var strings = [
                {
                    key: 'deleteconfirm',
                    component: 'mod_data',
                    param: {
                        name: presetname,
                    }
                },
                {
                    key: 'deletewarning',
                    component: 'mod_data',
                },
                {
                    key: 'delete',
                    component: 'core'
                },
            ];
            var deleteButtonText = '';
            Str.get_strings(strings).then(function(langStrings) {
                var modalTitle = langStrings[0];
                var modalBody = langStrings[1];
                deleteButtonText = langStrings[2];

                return ModalFactory.create({
                    title: modalTitle,
                    body: modalBody,
                    type: ModalFactory.types.SAVE_CANCEL,
                    large: true
                });
            }).done(function(modal) {
                modal.setSaveButtonText(deleteButtonText);
                modal.getRoot().on(ModalEvents.save, function() {
                    // The action is now confirmed, sending an action for it.
                    return deletePreset(dataid, presetname);
                });

                // Handle hidden event.
                modal.getRoot().on(ModalEvents.hidden, function() {
                    // Destroy when hidden.
                    modal.destroy();
                });

                // Show the modal.
                modal.show();

                return;
            }).catch(Notification.exception);
        });
    };

    /**
     * Delete site user preset.
     *
     * @param {int} dataid The id of the current database activity.
     * @param {string} presetname The preset name to delete.
     */
    function deletePreset(dataid, presetname) {
        var request = {
            methodname: 'mod_data_delete_saved_preset',
            args: {
                dataid: dataid,
                presetnames: {presetname},
            }
        };

        var requestType = 'success';
        Ajax.call([request])[0].then(function(data) {
            if (data.result) {
                return 'presetdeleted';
            }
            requestType = 'error';
            return 'presetnotdeleted';

        }).done(function(message) {
            var params = {
                d: dataid
            };
            if (requestType == 'success') {
                params.statusmsg = message;
            } else {
                params.errormsg = message;
            }
            // Redirect to the main content bank page and display the message as a notification.
            window.location.href = Url.relativeUrl('mod/data/preset.php', params, false);
        }).fail(Notification.exception);
    }

    return /** @alias module:mod_data/presetactions */ {
        // Public variables and functions.

        /**
         * Initialise the preset actions.
         *
         * @method init
         * @return {Actions}
         */
        'init': function() {
            return new Actions();
        }
    };
});
