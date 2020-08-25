
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
        'core/templates'
    ],
    function(
        $,
        Repository,
        Templates
    ) {
        var GLOSSARYENTRY = '[data-region="randomglossaryentry-content"]';

        /**
         * Get entry from backend.
         *
         * @method getEntry
         * @param {int} glossaryactivityid Glossary activity id
         * @return {promise} Resolved with an array of a entry
         */
        var getEntry = function(glossaryactivityid) {
            return Repository.getEntry(glossaryactivityid);
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
         * Get and show the glossary entry into the block.
         *
         * @param {object} root The root element for the items block.
         */
        var init = function(root) {
            root = $(root);

            // TODO: Add missing code here and delete example below.
            // var entry = getEntry(0);
            var entry = {
                showconcept: 'true',
                concept: 'Sara',
                definition: 'The best'
            };

            var content = root.find(GLOSSARYENTRY);

            return renderEntry(entry).then(function(html, js) {
                return Templates.replaceNodeContents(content, html, js);
            });
        };

        return {
            init: init
        };
    });