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
 * @module     core_contentbank/actions
 * @package    core_contentbank
 * @copyright  2020 Sara Arjona <sara@moodle.com>
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
     * @type {{DELETE_CONTENT: string}}
     */
    var ACTIONS = {
        RENAME_CONTENT: '[data-action="renamecontent"]',
        DELETE_CONTENT: '[data-action="deletecontent"]',
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
        $(ACTIONS.RENAME_CONTENT).click(function(e) {
            e.preventDefault();

            var contentname = $(this).data('contentname');
            var contentid = $(this).data('contentid');

            var strings = [
                {
                    key: 'renamecontent',
                    component: 'core_contentbank'
                },
                {
                    key: 'rename',
                    component: 'core_contentbank'
                },
            ];

            var saveButtonText = '';
            Str.get_strings(strings).then(function(langStrings) {
                var modalTitle = langStrings[0];
                saveButtonText = langStrings[1];

                return ModalFactory.create({
                    title: modalTitle,
                    body: Templates.render('core_contentbank/renamecontent', {'contentid': contentid, 'name': contentname}),
                    type: ModalFactory.types.SAVE_CANCEL
                });
            }).then(function(modal) {
                modal.setSaveButtonText(saveButtonText);
                modal.getRoot().on(ModalEvents.save, function() {
                    // The action is now confirmed, sending an action for it.
                    var newname = $("#newname").val();
                    return renameContent(contentid, newname);
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

        $(ACTIONS.DELETE_CONTENT).click(function(e) {
            e.preventDefault();

            var contentname = $(this).data('contentname');
            var contentid = $(this).data('contentid');

            var strings = [
                {
                    key: 'deletecontent',
                    component: 'core_contentbank'
                },
                {
                    key: 'deletecontentconfirm',
                    component: 'core_contentbank',
                    param: {
                        name: contentname,
                    }
                },
                {
                    key: 'delete',
                    component: 'core'
                },
            ];

            var deleteButtonText = '';
            Str.get_strings(strings).then(function(langStrings) {
                var modalTitle = langStrings[0];
                var modalContent = langStrings[1];
                deleteButtonText = langStrings[2];

                return ModalFactory.create({
                    title: modalTitle,
                    body: modalContent,
                    type: ModalFactory.types.SAVE_CANCEL,
                    large: true
                });
            }).done(function(modal) {
                modal.setSaveButtonText(deleteButtonText);
                modal.getRoot().on(ModalEvents.save, function() {
                    // The action is now confirmed, sending an action for it.
                    return deleteContent(contentid);
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
     * Rename content in the content bank.
     *
     * @param {int} contentid The content to rename.
     * @param {string} name The new name for the content.
     */
    function renameContent(contentid, name) {
        var request = {
            methodname: 'core_contentbank_rename_content',
            args: {
                contentid: contentid,
                name: name
            }
        };

        var requestType = 'success';
        Ajax.call([request])[0].then(function(data) {
            if (data) {
                return Str.get_string('contentrenamed', 'core_contentbank');
            }
            requestType = 'error';
            return Str.get_string('contentnotrenamed', 'core_contentbank');

        }).then(function(message) {
            var params = null;
            if (requestType == 'success') {
                params = {
                    id: contentid,
                    statusmsg: message
                };
            } else {
                params = {
                    id: contentid,
                    errormsg: message
                };
            }
            // Redirect to the content view page and display the message as a notification.
            window.location.href = Url.relativeUrl('contentbank/view.php', params, false);
            return;
        }).catch(Notification.exception);
    }

    /**
     * Delete content from the content bank.
     *
     * @param {int} contentid The content to delete.
     */
    function deleteContent(contentid) {
        var request = {
            methodname: 'core_contentbank_delete_content',
            args: {
                contentid: contentid
            }
        };

        var requestType = 'success';
        Ajax.call([request])[0].then(function(data) {
            if (data) {
                return Str.get_string('contentdeleted', 'core_contentbank');
            }
            requestType = 'error';
            return Str.get_string('contentnotdeleted', 'core_contentbank');

        }).done(function(message) {
            var params = null;
            if (requestType == 'success') {
                params = {
                    statusmsg: message
                };
            } else {
                params = {
                    errormsg: message
                };
            }
            // Redirect to the main content bank page and display the message as a notification.
            window.location.href = Url.relativeUrl('contentbank/index.php', params, false);
        }).fail(Notification.exception);
    }

    return /** @alias module:core_contentbank/actions */ {
        // Public variables and functions.

        /**
         * Initialise the unified user filter.
         *
         * @method init
         * @return {Actions}
         */
        'init': function() {
            return new Actions();
        }
    };
});
