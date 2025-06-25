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
 * Handle forum subscription/tracking toggling.
 *
 * @module     mod_forum/forum_overview_toggle
 * @copyright  2025 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
        'jquery',
        'core/notification',
        'core/str',
        'mod_forum/repository',
        'mod_forum/selectors',
        'core/pubsub',
        'mod_forum/forum_events',
    ], function(
        $,
        Notification,
        Str,
        Repository,
        Selectors,
        PubSub,
        ForumEvents
    ) {

    /**
     * Register event listeners for the subscription toggle.
     *
     * @param {object} root The discussion list root element
     * @param {boolean} preventDefault Should the default action of the event be prevented
     */
    var registerEventListeners = function(root, preventDefault) {

        root.on('click', Selectors.forum.subscriptionToggle, function(e) {
            var toggleElement = $(this);
            var forumId = toggleElement.data('forumid');
            var subscriptionState = toggleElement.data('targetstate');

            Repository.setForumSubscriptionState(forumId, subscriptionState)
                .then(function(context) {
                    PubSub.publish(ForumEvents.SUBSCRIPTION_TOGGLED, {
                        subscriptionState: subscriptionState
                    });

                    var toggleId = toggleElement.attr('id');
                    var newTargetState = context.userstate.subscribed ? 0 : 1;
                    toggleElement.data('targetstate', newTargetState);
                    var stringKey = context.userstate.subscribed ? 'unsubscribe' : 'subscribe';
                    return Str.get_string(stringKey, 'mod_forum')
                        .then(function(string) {
                            toggleElement.closest('td').find('label[for="' + toggleId + '"]').find('span').text(string);
                            return string;
                        });

                })
                .catch(Notification.exception);

            if (preventDefault) {
                e.preventDefault();
            }
        });

        root.on('click', Selectors.forum.trackToggle, function(e) {
            var toggleElement = $(this);
            var forumId = toggleElement.data('forumid');
            var trackedState = toggleElement.data('targetstate');

            Repository.setForumTrackingState(forumId, trackedState)
                .then(function(context) {
                    var toggleId = toggleElement.attr('id');
                    var newTargetState = context.userstate.subscribed ? 0 : 1;
                    toggleElement.data('targetstate', newTargetState);
                    var stringKey = context.userstate.tracked ? 'unsubscribe' : 'subscribe';
                    return Str.get_string(stringKey, 'mod_forum')
                        .then(function(string) {
                            toggleElement.closest('td').find('label[for="' + toggleId + '"]').find('span').text(string);
                            return string;
                        });

                })
                .catch(Notification.exception);

            if (preventDefault) {
                e.preventDefault();
            }
        });
    };

    return {
        init: function(toggleSelector) {
            var toggleElement = $('#' + toggleSelector);
            registerEventListeners(toggleElement.closest('td'));
        },
    };
});
