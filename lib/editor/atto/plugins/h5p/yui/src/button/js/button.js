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

/*
 * @package    atto_h5p
 * @copyright  2019 Bas Brands  <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module moodle-atto_h5p-button
 */

/**
 * Atto h5p content tool.
 *
 * @namespace M.atto_h5p
 * @class Button
 * @extends M.editor_atto.EditorPlugin
 */

var CSS = {
        CONTENTWARNING: 'att_h5p_contentwarning',
        H5PBROWSER: 'openh5pbrowser',
        INPUTALT: 'atto_h5p_altentry',
        INPUTH5PFILE: 'atto_h5p_file',
        INPUTH5PURL: 'atto_h5p_url',
        INPUTSUBMIT: 'atto_h5p_urlentrysubmit',
        OPTION_DOWNLOAD_BUTTON: 'atto_h5p_option_download_button',
        OPTION_COPYRIGHT_BUTTON: 'atto_h5p_option_copyright_button',
        OPTION_EMBED_BUTTON: 'atto_h5p_option_embed_button',
        URLWARNING: 'atto_h5p_warning'
    },
    SELECTORS = {
        CONTENTWARNING: '.' + CSS.CONTENTWARNING,
        H5PBROWSER: '.' + CSS.H5PBROWSER,
        INPUTH5PFILE: '.' + CSS.INPUTH5PFILE,
        INPUTH5PURL: '.' + CSS.INPUTH5PURL,
        INPUTSUBMIT: '.' + CSS.INPUTSUBMIT,
        OPTION_DOWNLOAD_BUTTON: '.' + CSS.OPTION_DOWNLOAD_BUTTON,
        OPTION_COPYRIGHT_BUTTON: '.' + CSS.OPTION_COPYRIGHT_BUTTON,
        OPTION_EMBED_BUTTON: '.' + CSS.OPTION_EMBED_BUTTON,
        URLWARNING: '.' + CSS.URLWARNING
    },

    COMPONENTNAME = 'atto_h5p',

    TEMPLATE = '' +
            '<form class="atto_form">' +
                '{{#if canEmbed}}' +
                '<div class="mb-4">' +
                    '<label for="{{elementid}}_{{CSS.INPUTH5PURL}}">{{get_string "enterurl" component}}</label>' +
                    '<div style="display:none" role="alert" class="alert alert-warning mb-1 {{CSS.URLWARNING}}">' +
                        '{{get_string "invalidh5purl" component}}' +
                    '</div>' +
                    '<textarea rows="3" class="form-control {{CSS.INPUTH5PURL}}" type="url" ' +
                    'id="{{elementid}}_{{CSS.INPUTH5PURL}}" />{{embedURL}}</textarea>' +
                    '<div class="description small mb-2">{{get_string "enterurl_desc" component}}</div>' +
                '</div>' +
                '{{/if}}' +
                '{{#if canUpload}}' +
                '<div class="mb-4">' +
                    '<label for="{{elementid}}_{{CSS.H5PBROWSER}}">{{get_string "h5pfile" component}}</label>' +
                    '<div style="display:none" role="alert" class="alert alert-warning mb-1 {{CSS.CONTENTWARNING}}">' +
                        '{{get_string "noh5pcontent" component}}' +
                    '</div>' +
                    '<div class="input-group input-append w-100">' +
                        '<input class="form-control {{CSS.INPUTH5PFILE}}" type="url" value="{{fileURL}}" ' +
                        'id="{{elementid}}_{{CSS.INPUTH5PFILE}}" size="32"/>' +
                        '<span class="input-group-append">' +
                            '<button class="btn btn-secondary {{CSS.H5PBROWSER}}" type="button">' +
                            '{{get_string "browserepositories" component}}</button>' +
                        '</span>' +
                    '</div>' +
                    '<div class="description small mb-2">{{get_string "h5pfile_desc" component}}</div>' +
                    '<div class="mt-2 mb-1 ml-2">{{get_string "h5poptions" component}}</div>' +
                    '<div class="form-check ml-2">' +
                        '<input type="checkbox" {{optionDownloadButton}} ' +
                        'class="form-check-input {{CSS.OPTION_DOWNLOAD_BUTTON}}"' +
                        'id="{{elementid}}_h5p-option-allow-download"/>' +
                        '<label class="form-check-label" for="{{elementid}}_h5p-option-allow-download">' +
                        '{{get_string "downloadbutton" component}}' +
                        '</label>' +
                    '</div>' +
                    '<div class="form-check ml-2">' +
                        '<input type="checkbox" {{optionEmbedButton}} ' +
                        'class="form-check-input {{CSS.OPTION_EMBED_BUTTON}}" ' +
                            'id="{{elementid}}_h5p-option-embed-button"/>' +
                        '<label class="form-check-label" for="{{elementid}}_h5p-option-embed-button">' +
                        '{{get_string "embedbutton" component}}' +
                        '</label>' +
                    '</div>' +
                    '<div class="form-check ml-2">' +
                        '<input type="checkbox" {{optionCopyrightButton}} ' +
                        'class="form-check-input {{CSS.OPTION_COPYRIGHT_BUTTON}}" ' +
                            'id="{{elementid}}_h5p-option-copyright-button"/>' +
                        '<label class="form-check-label" for="{{elementid}}_h5p-option-copyright-button">' +
                        '{{get_string "copyrightbutton" component}}' +
                        '</label>' +
                    '</div>' +
                '</div>' +
                '{{/if}}' +
                '<div class="text-center">' +
                '<button class="btn btn-secondary {{CSS.INPUTSUBMIT}}" type="submit">' + '' +
                    '{{get_string "saveh5p" component}}</button>' +
                '</div>' +
            '</form>',

        H5PURL = '' +
            '<div class="position-relative h5p-embed-placeholder" data-h5pplaceholder="url">' +
                '<div class="attoh5poverlay"></div>' +
                '<iframe id="h5pcontent" class="h5pcontent" src="{{url}}/embed" ' +
                    'data-type="url" data-url="{{url}}" width="100%" height="637" frameborder="0"' +
                    'allowfullscreen="{{allowfullscreen}}" allowmedia="{{allowmedia}}">' +
                '</iframe>' +
                '<script src="' + M.cfg.wwwroot + '/lib/editor/atto/plugins/h5p/js/h5p-resizer.js"' +
                    'charset="UTF-8"></script>' +
                '</div>' +
            '</div>' +
            '<div><br></div>',

        H5PEMBED = '' +
            '<div class="position-relative h5p-embed-placeholder" data-h5pplaceholder="embed">' +
                '<div class="attoh5poverlay"></div>' +
                '<div class="h5pcontent" data-type="embed">{{{iframe}}}</div>' +
                '<script src="' + M.cfg.wwwroot + '/lib/editor/atto/plugins/h5p/js/h5p-resizer.js"' +
                    'charset="UTF-8"></script>' +
                '</div>' +
            '</div>' +
            '<div><br></div>',

        H5PFILE = '' +
            '<div class="position-relative h5p-file-placeholder" data-h5pplaceholder="file">' +
                '<div class="attoh5poverlay"></div>' +
                '<iframe id="h5pcontent" class="h5pcontent" ' +
                    'src="' + M.cfg.wwwroot + '/h5p/embed.php?url={{h5pfile}}' +
                    '{{#if optionDownloadButton}}&export=1{{/if}}' +
                    '{{#if optionEmbedButton}}&embed=1{{/if}}' +
                    '{{#if optionCopyrightButton}}&copyright=1{{/if}}" ' +
                    'data-file="{{h5pfile}}" data-type="file" width="100%" height="230" frameborder="0" ' +
                    'data-export="{{#if optionDownloadButton}}=1{{/if}}" ' +
                    'data-embed="{{#if optionEmbedButton}}=1{{/if}}" ' +
                    'data-copyright="{{#if optionCopyrightButton}}=1{{/if}}" ' +
                    'allowfullscreen="{{allowfullscreen}}" allowmedia="{{allowmedia}}">' +
                '</iframe>' +
                '<script src="' + M.cfg.wwwroot + '/lib/editor/atto/plugins/h5p/js/h5p-resizer.js"' +
                    'charset="UTF-8"></script>' +
                '</div>' +
            '</div>' +
            '<div><br></div>';

Y.namespace('M.atto_h5p').Button = Y.Base.create('button', Y.M.editor_atto.EditorPlugin, [], {
    /**
     * A reference to the current selection at the time that the dialogue
     * was opened.
     *
     * @property _currentSelection
     * @type Range
     * @private
     */
    _currentSelection: null,

    /**
     * A reference to the currently open form.
     *
     * @param _form
     * @type Node
     * @private
     */
    _form: null,

    /**
     * A reference to the currently selected H5P placeholder.
     *
     * @param _form
     * @type Node
     * @private
     */
    _placeholderH5P: null,

    /**
     * Allowed methods of adding H5P.
     *
     * @param _allowedmethods
     * @type String
     * @private
     */
    _allowedmethods: 'none',

    initializer: function() {
        this._allowedmethods = this.get('allowedmethods');
        if (this._allowedmethods === 'none') {
            // Plugin not available here.
            return;
        }
        this.addButton({
            icon: 'icon',
            iconComponent: 'atto_h5p',
            callback: this._displayDialogue,
            tags: '.attoh5poverlay',
            tagMatchRequiresAll: false
        });

        this.editor.delegate('dblclick', this._handleDblClick, '.attoh5poverlay', this);
        this.editor.delegate('click', this._handleClick, '.attoh5poverlay', this);
    },

    /**
     * Handle a double click on a H5P Placeholder.
     *
     * @method _handleDblClick
     * @private
     */
    _handleDblClick: function() {
        this._displayDialogue();
    },

    /**
     * Handle a click on a H5P Placeholder.
     *
     * @method _handleClick
     * @param {EventFacade} e
     * @private
     */
    _handleClick: function(e) {
        var h5pplaceholder = e.target;

        var selection = this.get('host').getSelectionFromNode(h5pplaceholder);
        if (this.get('host').getSelection() !== selection) {
            this.get('host').setSelection(selection);
        }
    },

    /**
     * Display the h5p editing tool.
     *
     * @method _displayDialogue
     * @private
     */
    _displayDialogue: function() {
        // Store the current selection.
        this._currentSelection = this.get('host').getSelection();

        this._placeholderH5P = this._getH5PIframe();

        if (this._currentSelection === false) {
            return;
        }

        var dialogue = this.getDialogue({
            headerContent: M.util.get_string('h5pproperties', COMPONENTNAME),
            width: 'auto',
            focusAfterHide: true
        });

        // Set the dialogue content, and then show the dialogue.
        dialogue.set('bodyContent', this._getDialogueContent())
            .show();
    },

    /**
     * Get the H5P iframe
     *
     * @method _resolveH5P
     * @return {Node} The H5P iframe selected.
     * @private
     */
    _getH5PIframe: function() {
        var selectednode = this.get('host').getSelectionParentNode();
        if (!selectednode) {
            return;
        }
        var type = selectednode.getAttribute('data-h5pplaceholder');
        if (type == "url" || type == "file") {
            return Y.one(selectednode).one('iframe.h5pcontent');
        } else if (type == "embed") {
            return Y.one(selectednode).one('div.h5pcontent');
        }
    },

    /**
     * Get the H5P button permissions.
     *
     * @return {Object} H5P button permissions.
     * @private
     */
    _getPermissions: function() {
        var permissions = {
            'canUpload': false,
            'canUploadAndEbmed': false,
            'canEmbed': false
        };

        if (this.get('host').canShowFilepicker('h5p')) {
            if (this._allowedmethods === 'both') {
                permissions.canUploadAndEmbed = true;
                permissions.canUpload = true;
            } else if (this._allowedmethods === 'upload') {
                permissions.canUpload = true;
            }
        }

        if (this._allowedmethods === 'both' || this._allowedmethods === 'embed') {
            permissions.canEmbed = true;
        }
        return permissions;
    },


    /**
     * Return the dialogue content for the tool, attaching any required
     * events.
     *
     * @method _getDialogueContent
     * @return {Node} The content to place in the dialogue.
     * @private
     */
    _getDialogueContent: function() {

        var permissions = this._getPermissions();

        var fileURL,
            embedURL,
            embedActive = 'active',
            fileActive,
            optionDownloadButton,
            optionEmbedButton,
            optionCopyrightButton;

        if (this._placeholderH5P) {
            var type = this._placeholderH5P.getAttribute('data-type');
            Y.log('type' + type);
            if (type === 'file') {
                fileURL = this._placeholderH5P.getAttribute('data-file');
                optionDownloadButton = (this._placeholderH5P.getAttribute('data-export') ? 'checked' : '');
                optionEmbedButton = (this._placeholderH5P.getAttribute('data-embed') ? 'checked' : '');
                optionCopyrightButton = (this._placeholderH5P.getAttribute('data-copyright') ? 'checked' : '');
                embedActive = '';
                fileActive = 'active';
            } else if (type === 'url') {
                embedURL = this._placeholderH5P.getAttribute('data-url');
            } else if (type === 'embed') {
                Y.log('type embed');
                embedURL = this._placeholderH5P.getContent();
                Y.log(embedURL);
            }
        }


        var template = Y.Handlebars.compile(TEMPLATE),
            content = Y.Node.create(template({
                elementid: this.get('host').get('elementid'),
                CSS: CSS,
                component: COMPONENTNAME,
                canUpload: permissions.canUpload,
                canEmbed: permissions.canEmbed,
                fileURL: fileURL,
                embedURL: embedURL,
                fileActive: fileActive,
                embedActive: embedActive,
                canUploadAndEmbed: permissions.canUploadAndEmbed,
                optionDownloadButton: optionDownloadButton,
                optionEmbedButton: optionEmbedButton,
                optionCopyrightButton: optionCopyrightButton
            }));

        this._form = content;

        // Listen to and act on Dialogue content events.
        this._setEventListeners();

        return content;
    },

    /**
     * Update the dialogue after an h5p was selected in the File Picker.
     *
     * @method _filepickerCallback
     * @param {object} params The parameters provided by the filepicker
     * containing information about the h5p.
     * @private
     */
    _filepickerCallback: function(params) {
        if (params.url !== '') {
            var input = this._form.one(SELECTORS.INPUTH5PFILE);
            input.set('value', params.url);
            this._form.one(SELECTORS.INPUTH5PURL).set('value', '');
            this._removeWarnings();
        }
    },

    /**
     * Set event Listeners for Dialogue content actions.
     *
     * @method  _setEventListeners
     * @private
     */
    _setEventListeners: function() {
        var form = this._form;
        var permissions = this._getPermissions();

        form.one(SELECTORS.INPUTSUBMIT).on('click', this._setH5P, this);

        if (permissions.canUpload) {
            form.one(SELECTORS.H5PBROWSER).on('click', function() {
                this.get('host').showFilepicker('h5p', this._filepickerCallback, this);
            }, this);
        }

        if (permissions.canUploadAndEmbed) {
            form.one(SELECTORS.INPUTH5PFILE).on('change', function() {
                form.one(SELECTORS.INPUTH5PURL).set('value', '');
                this._removeWarnings();
            }, this);
            form.one(SELECTORS.INPUTH5PURL).on('change', function() {
                form.one(SELECTORS.INPUTH5PFILE).set('value', '');
                this._removeWarnings();
            }, this);
        }
    },

    /**
     * Remove warnings shown in the dialogue.
     *
     * @method _removeWarnings
     * @private
     */
    _removeWarnings: function() {
        var form = this._form;
        form.one(SELECTORS.URLWARNING).setStyle('display', 'none');
        form.one(SELECTORS.CONTENTWARNING).setStyle('display', 'none');
    },

    /**
     * Update the h5p in the contenteditable.

     *
     * @method _setH5P
     * @param {EventFacade} e
     * @private
     */
    _setH5P: function(e) {
        var form = this._form,
            url = form.one(SELECTORS.INPUTH5PURL).get('value'),
            h5phtml,
            host = this.get('host'),
            h5pfile,
            permissions = this._getPermissions();

        if (permissions.canEmbed) {
            url = form.one(SELECTORS.INPUTH5PURL).get('value');
        }

        if (permissions.canUpload) {
            h5pfile = form.one(SELECTORS.INPUTH5PFILE).get('value');
        }

        e.preventDefault();

        // Check if there are any issues.
        if (this._updateWarning()) {
            return;
        }

        // Focus on the editor in preparation for inserting the h5p.
        host.focus();

        if (url !== '') {

            // If a H5P placeholder was selected we only update the placeholder.
            if (this._placeholderH5P) {
                this._placeholderH5P.setAttribute('src', url);
            } else {
                host.setSelection(this._currentSelection);

                if (this._validEmbed(url)) {
                    var embedtemplate = Y.Handlebars.compile(H5PEMBED);
                    var re = /^(<iframe.*<\/iframe>)/;
                    var iframe = url.match(re)[0];
                    h5phtml = embedtemplate({
                        iframe: iframe
                    });
                } else {
                    var urltemplate = Y.Handlebars.compile(H5PURL);
                    h5phtml = urltemplate({
                        url: url,
                        allowfullscreen: 'allowfullscreen',
                        allowmedia: 'geolocation *; microphone *; camera *; midi *; encrypted-media *'
                    });
                }

                this.get('host').insertContentAtFocusPoint(h5phtml);
            }

            this.markUpdated();
        } else if (h5pfile !== '') {

            host.setSelection(this._currentSelection);

            var optionDownloadButton = form.one(SELECTORS.OPTION_DOWNLOAD_BUTTON).get('checked');
            var optionEmbedButton = form.one(SELECTORS.OPTION_EMBED_BUTTON).get('checked');
            var optionCopyrightButton = form.one(SELECTORS.OPTION_COPYRIGHT_BUTTON).get('checked');

            var filetemplate = Y.Handlebars.compile(H5PFILE);
            h5phtml = filetemplate({
                h5pfile: h5pfile,
                allowfullscreen: 'allowfullscreen',
                allowmedia: 'geolocation *; microphone *; camera *; midi *; encrypted-media *',
                optionDownloadButton: optionDownloadButton,
                optionEmbedButton: optionEmbedButton,
                optionCopyrightButton: optionCopyrightButton
            });

            this.get('host').insertContentAtFocusPoint(h5phtml);

            this.markUpdated();
        }

        this.getDialogue({
            focusAfterHide: null
        }).hide();
    },

    /**
     * Check if this could be a h5p embed.
     *
     * @method _validEmbed
     * @param {String} str
     * @return {boolean} whether this is a iframe tag.
     * @private
     */
    _validEmbed: function(str) {
        var pattern = new RegExp('^(<iframe).*(<\\/iframe>)'); // Port and path.
        return !!pattern.test(str);
    },

    /**
     * Check if this could be a h5p URL.
     *
     * @method _validURL
     * @param {String} str
     * @return {boolean} whether this is a valid URL.
     * @private
     */
    _validURL: function(str) {
        var pattern = new RegExp('^(https?:\\/\\/)?' + // Protocol.
            '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' + // Domain name.
            '((\\d{1,3}\\.){3}\\d{1,3}))' + // OR ip (v4) address.
            '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'); // Port and path.
        return !!pattern.test(str);
    },

    /**
     * Update the url warning.
     *
     * @method _updateWarning
     * @return {boolean} whether a warning should be displayed.
     * @private
     */
    _updateWarning: function() {
        var form = this._form,
            state = true,
            url,
            h5pfile,
            permissions = this._getPermissions();


        if (permissions.canEmbed) {
            url = form.one(SELECTORS.INPUTH5PURL).get('value');
            if (url !== '') {
                if (this._validURL(url)) {
                    form.one(SELECTORS.URLWARNING).setStyle('display', 'none');
                    state = false;
                } else if (this._validEmbed(url)) {
                    form.one(SELECTORS.URLWARNING).setStyle('display', 'none');
                    state = false;
                } else {
                    form.one(SELECTORS.URLWARNING).setStyle('display', 'block');
                    state = true;
                }
                return state;
            }
        }

        if (permissions.canUpload) {
            h5pfile = form.one(SELECTORS.INPUTH5PFILE).get('value');
            if (h5pfile !== '') {
                form.one(SELECTORS.CONTENTWARNING).setStyle('display', 'none');
                state = false;
            } else {
                form.one(SELECTORS.CONTENTWARNING).setStyle('display', 'block');
                state = true;
            }
        }

        return state;
    }
}, {
    ATTRS: {
        /**
         * The allowedmethods of adding h5p content.
         *
         * @attribute allowedmethods
         * @type String
         */
        allowedmethods: {
            value: null
        }
    }
});
