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
 * Show the tree of admins presets.
 *
 * @module     tool_admin_presets/tree
 * @package    tool_admin_presets
 * @copyright  2019 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/yui'], function(Y) {
    let tree = function() {
        this.prototype.view = null;
        this.prototype.nodes = null;
    };

    return {
        /**
         * Initializes the TreeView object and adds the submit listener.
         */
        init: function() {
            Y.use('yui2-treeview', function(Y2) {
                tree.view = new Y2.YUI2.widget.TreeView("settings_tree_div");
                tree.nodes = [];
                tree.nodes.root = tree.view.getRoot();
            });
        },

        /**
         * Creates a tree branch.
         *
         * @param {Array} ids
         * @param {Array} nodeids
         * @param {String} labels
         * @param {String} descriptions
         * @param {Array} parents
         */
        addNodes: function(ids, nodeids, labels, descriptions, parents) {
            Y.use('yui2-treeview', function(Y2) {
                let nelements = ids.length;
                for (let i = 0; i < nelements; i++) {

                    let settingId = ids[i];
                    let nodeId = nodeids[i];
                    let label = decodeURIComponent(labels[i]);
                    let description = decodeURIComponent(descriptions[i]);
                    let parent = parents[i];

                    let newNode = new Y2.YUI2.widget.HTMLNode(label, tree.nodes[parent]);

                    newNode.settingId = settingId;
                    newNode.setNodesProperty('title', description);
                    newNode.highlightState = 1;

                    tree.nodes[nodeId] = newNode;
                }
            });
        },

        /**
         * Render the Treeview.
         */
        render: function() {
            Y.use('yui2-treeview', function(Y2) {
                let categories = tree.view.getNodesByProperty('settingId', 'category');
                // Cleaning categories without children.
                if (categories) {
                    for (let i = 0; i < categories.length; i++) {
                        if (!categories[i].hasChildren()) {
                            tree.view.popNode(categories[i]);
                        }
                    }
                }
                categories = tree.view.getRoot().children;
                if (categories) {
                    for (let j = 0; j < categories.length; j++) {
                        if (!categories[j].hasChildren()) {
                            tree.view.popNode(categories[j]);
                        }
                    }
                }

                // Context.tree.expandAll();.
                tree.view.setNodesProperty('propagateHighlightUp', true);
                tree.view.setNodesProperty('propagateHighlightDown', true);
                tree.view.subscribe('clickEvent', tree.view.onEventToggleHighlight);
                tree.view.render();

                // Listener to create one node for each selected setting.
                Y2.YUI2.util.Event.on('id_admin_presets_submit', 'click', function() {

                    // We need the moodle form to add the checked settings.
                    let settingsPresetsForm = document.getElementById('id_admin_presets_submit').parentNode;

                    let hiLit = tree.view.getNodesByProperty('highlightState', 1);
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
        }
    };
});
