// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope this it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Show the tree of admins presets.
 *
 * @module     tool_admin_presets/tree
 * @package    tool_admin_presets
 * @copyright  2019 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/yui', 'core/ajax'], (Y, Ajax) => {

    let Tree = function() {
        this.view = null;
        this.nodes = null;
    };

    /**
     * Init the tree.
     *
     * @return {Promise} promise
     */
    Tree.prototype.init = function() {
        return new Promise((resolve) => {
            Y.use('yui2-treeview', (Y2) => {
                this.view = new Y2.YUI2.widget.TreeView("settings_tree_div");
                this.nodes = [];
                this.nodes.root = this.view.getRoot();
                resolve(true);
            });
        });
    };

    /**
     * Creates tree branches.
     *
     * @param {Array} ids
     * @param {Array} nodeids
     * @param {String} labels
     * @param {String} descriptions
     * @param {Array} parents
     * @return {Promise} promise
     */
    Tree.prototype.addNodes = function(ids, nodeids, labels, descriptions, parents) {
        return new Promise((resolve) => {
            Y.use('yui2-treeview', (Y2) => {
                let nelements = ids.length;
                for (let i = 0; i < nelements; i++) {

                    let settingId = ids[i];
                    let nodeId = nodeids[i];
                    let label = decodeURIComponent(labels[i]);
                    let description = decodeURIComponent(descriptions[i]);
                    let parent = parents[i];

                    let newNode = new Y2.YUI2.widget.HTMLNode(label, this.nodes[parent]);

                    newNode.settingId = settingId;
                    newNode.setNodesProperty('title', description);
                    newNode.highlightState = 1;

                    this.nodes[nodeId] = newNode;
                    resolve(true);
                }
            });
        });
    };

    /**
     * Render the tree.
     */
    Tree.prototype.render = function() {
        Y.use('yui2-treeview', (Y2) => {
            let categories = this.view.getNodesByProperty('settingId', 'category');
            // Cleaning categories without children.
            if (categories) {
                for (let i = 0; i < categories.length; i++) {
                    if (!categories[i].hasChildren()) {
                        this.view.popNode(categories[i]);
                    }
                }
            }
            categories = this.view.getRoot().children;
            if (categories) {
                for (let j = 0; j < categories.length; j++) {
                    if (!categories[j].hasChildren()) {
                        this.view.popNode(categories[j]);
                    }
                }
            }
            this.view.setNodesProperty('propagateHighlightUp', true);
            this.view.setNodesProperty('propagateHighlightDown', true);
            this.view.subscribe('clickEvent', this.view.onEventToggleHighlight);
            this.view.render();

            // Listener to create one node for each selected setting.
            Y2.YUI2.util.Event.on('id_admin_presets_submit', 'click', () => {

                // We need the moodle form to add the checked settings.
                let settingsPresetsForm = document.getElementById('id_admin_presets_submit').parentNode;

                let hiLit = this.view.getNodesByProperty('highlightState', 1);
                if (Y2.YUI2.lang.isNull(hiLit)) {
                    Y2.YUI2.log("Nothing selected");

                } else {

                    // Only for debugging.
                    let labels = [];

                    for (let i = 0; i < hiLit.length; i++) {

                        let treeNode = hiLit[i];

                        // Only settings not setting categories nor settings pages.
                        if (treeNode.settingId !== 'category' && treeNode.settingId !== 'page') {
                            labels.push(treeNode.settingId);

                            // If the node does not exists we add it.
                            if (!document.getElementById(treeNode.settingId)) {

                                let settingInput = document.createElement('input');
                                settingInput.setAttribute('type', 'hidden');
                                settingInput.setAttribute('name', treeNode.settingId);
                                settingInput.setAttribute('value', '1');
                                settingsPresetsForm.appendChild(settingInput);
                            }
                        }
                    }

                    Y2.YUI2.log("Checked settings:\n" + labels.join("\n"), "info");
                }
            });
        });
    };

    return {
        init: (action, id) => {
            // Call ajax functions to retrieve settings.
            Ajax.call([{
                methodname: 'tool_admin_presets_get_settings',
                args: {
                    action: action,
                    id: id
                }
            }], true, true)[0].done((response) => {
                    // Make the tree with settings retrieved.
                    let tree = new Tree();
                    tree.init().finally(() => {
                        tree.addNodes(response.ids,
                            response.nodes,
                            response.labels,
                            response.descriptions,
                            response.parents
                        ).finally(() => {
                            tree.render();
                        });
                    });
                }
            );
        }
    };
});
