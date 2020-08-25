
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
 * Javascript to initialise the Recently accessed items block.
 *
 * @module     block_glossary_random/main
 * @package    block_glossary_random
 * @copyright  2020 Adrian Perez, Fernfachhochschule Schweiz (FFHS) <adrian.perez@ffhs.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(
    [
        'jquery',
        'block_glossary_random/repository',
        'core/templates',
        'core/custom_interaction_events'
    ],
    function(
        $,
        Repository,
        Templates,
        CustomEvents
    ) {
        var GLOSSARYENTRY = '[data-region="randomglossaryentry-content"]';
        var REFRESHBUTTON = '[id="refresh_glossary_button"]';

        var content = '';
        var timer = '';
        var blockinstanceid = '';

        /**
         * Get entry from backend.
         *
         * @method getEntry
         * @param {int} blockinstanceid Glossary block instance id
         * @return {promise} Resolved with an array of a entry
         */
        var getEntry = function(blockinstanceid) {
            return Repository.getEntry(blockinstanceid);
        };

        /**
         * Render the block content.
         *
         * @method renderEntry
         * @param {array} array containing entry of glossary item.
         * @return {promise} Resolved with HTML and JS strings
         */
        var renderEntry = function(entry) {
            return Templates.render('block_glossary_random/view', entry);
        };

        /**
         * Relodas the content of the block.
         *
         * @method reloadContent
         * @param {content} object of the element to be replaced
         */
        var reloadContent = function(content) {
            return getEntry(blockinstanceid)
                .then(function(entry) {
                    return renderEntry(entry.data);
                }).then(function(html, js) {
                    return Templates.replaceNodeContents(content, html, js);
                }).catch(Notification.exception);

        };

        /**
         * Event listener for the refresh button.
         *
         * @param {object} root The root element for the overview block
         */
        var refreshButton = function(root) {
            CustomEvents.define(root, [
                CustomEvents.events.activate
            ]);

            root.on(CustomEvents.events.activate, REFRESHBUTTON, function(e, data) {
                reloadContent(content);
                data.originalEvent.preventDefault();
            });
        };

        /**
         * Get and show the glossary entry into the block.
         *
         * @param {object} root The root element for the items block.
         */
        var init = function(root) {
            root = $(root);

            content = root.find(GLOSSARYENTRY);
            timer = root.attr('data-reloadtime') || null;
            blockinstanceid = root.attr('data-blockinstanceid') || null;

            // Init event click listener.
            refreshButton(root);

            // Run periodical timer if set.
            if (timer > 0) {
                setInterval(() => {
                    reloadContent(content);
                }, timer);
            }
        };

        return {
            init: init
        };
    });