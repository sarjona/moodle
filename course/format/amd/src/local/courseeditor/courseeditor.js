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

import {Reactive} from 'core/reactive';
import notification from 'core/notification';
import Exporter from 'core_courseformat/local/courseeditor/exporter';
import log from 'core/log';
import ajax from 'core/ajax';

/**
 * Main course editor module.
 *
 * All formats can register new components on this object to create new reactive
 * UI components that watch the current course state.
 *
 * @module     core_courseformat/local/courseeditor/courseeditor
 * @class     core_courseformat/local/courseeditor/courseeditor
 * @copyright  2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class extends Reactive {

    /**
     * Set up the course editor when the page is ready.
     *
     * The course can only be loaded once per instance. Otherwise an error is thrown.
     *
     * @param {number} courseId course id
     */
    async loadCourse(courseId) {

        if (this.courseId) {
            throw new Error(`Cannot load ${courseId}, course already loaded with id ${this.courseId}`);
        }

        // Default view format setup.
        this._editing = false;
        this._supportscomponents = false;

        this.courseId = courseId;

        let stateData;

        try {
            stateData = await this.getServerCourseState();
        } catch (error) {
            log.error("EXCEPTION RAISED WHILE INIT COURSE EDITOR");
            log.error(error);
            return;
        }

        this.setInitialState(stateData);
    }

    /**
     * Setup the current view settings
     *
     * @param {Object} setup format, page and course settings
     * @property {boolean} setup.editing if the page is in edit mode
     * @property {boolean} setup.supportscomponents if the format supports components for content
     */
    setViewFormat(setup) {
        this._editing = setup.editing ?? false;
        this._supportscomponents = setup.supportscomponents ?? false;
    }

    /**
     * Load the current course state from the server.
     *
     * @returns {Object} the current course state
     */
    async getServerCourseState() {
        const courseState = await ajax.call([{
            methodname: 'core_courseformat_get_state',
            args: {
                courseid: this.courseId,
            }
        }])[0];

        const stateData = JSON.parse(courseState);

        return {
            course: {},
            section: [],
            cm: [],
            ...stateData,
        };
    }

    /**
     * Return the current edit mode.
     *
     * Components should use this method to check if edit mode is active.
     *
     * @return {boolean} if edit is enabled
     */
    get isEditing() {
        return this._editing ?? false;
    }

    /**
     * Return a data exporter to transform state part into mustache contexts.
     *
     * @return {Exporter} the exporter class
     */
    getExporter() {
        return new Exporter(this);
    }

    /**
     * Return if the current course support components to refresh the content.
     *
     * @returns {boolean} if the current content support components
     */
    get supportComponents() {
        return this._supportscomponents ?? false;
    }

    /**
     * Dispatch a change in the state.
     *
     * Usually reactive modules throw an error directly to the components when something
     * goes wrong. However, course editor can directly display a notification.
     *
     * @method dispatch
     * @param {string} actionName the action name (usually the mutation name)
     * @param {*} param any number of params the mutation needs.
     */
    async dispatch(...args) {
        try {
            await super.dispatch(...args);
        } catch (error) {
            // Display error modal.
            notification.exception(error);
            // Force unlock all elements.
            super.dispatch('unlockAll');
        }
    }
}
