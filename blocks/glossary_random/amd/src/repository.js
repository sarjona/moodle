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
 * A javascript module to handle user ajax actions.
 *
 * @module     block_glossary_random/repository
 * @package    block_glossary_random
 * @copyright  2020 Adrian Perez, Fernfachhochschule Schweiz (FFHS) <adrian.perez@ffhs.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    /**
     * Get the entry to display of a glossary.
     *
     * @method getEntry
     * @param {int} blockinstanceid Random glossary block instance id
     * @return {promise} Resolved with an array of a entry
     */
    var getEntry = function(blockinstanceid) {
        var args = {};
        if (typeof blockinstanceid !== 'undefined') {
            args.blockinstanceid = blockinstanceid;
        }
        var request = {
            methodname: 'block_glossary_random_get_entry',
            args: args
        };
        return Ajax.call([request])[0];
    };
    return {
        getEntry: getEntry
    };
});