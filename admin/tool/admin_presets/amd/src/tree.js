/**
 * Show the tree of admins presets.
 *
 * @module     tool_admin_presets/tree
 * @package    tool_admin_presets
 * @copyright  2019 Pimenko <contact@pimenko.com>
 * @author     Jordan Kesraoui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/tree', 'core/templates', 'jquery'], (Ajax, TreeAccessible, Templates, $) => {

    /**
     * NodeTree Object.
     * @param id {string} Id of the node.
     * @param settingId {string} Id of the setting.
     * @param label {string} Label of the setting.
     * @param description {string} Description of the setting.
     * @constructor
     */
    let NodeTree = function(id, settingId, label, description) {
        this.id = id;
        this.settingId = settingId;
        this.label = label;
        this.description = description;
        this.parent = null;
        this.displayed = false;
        this.checked = true;
        this.level = 1;
        this.children = [];
    };



    /**
     * Return if the node has children or not.
     *
     * @return {boolean}
     */
    NodeTree.prototype.hasChildren = function() {
        return this.children.length > 0;
    };

    /**
     * Return if the node is empty (without children and is category type).
     * @return {boolean}
     */
    NodeTree.prototype.isEmpty = function() {
        return this.settingId === 'category' && !this.hasChildren();
    };


    /**
     * Accessible Tree of settings.
     *
     * @param rootNode {string} Element Id of the root of the tree.
     * @constructor
     */
    let Tree = function(rootNode) {
        this.view = null;
        this.nodes = [];
        this.accessibleview = null;
        this.rootNode = document.getElementById(rootNode);
    };


    /**
     * Initialise the tree.
     *
     * @param ids {array} Array of setting ids.
     * @param nodeids {array} Array of node ids.
     * @param labels {array} Array of settings labels.
     * @param descriptions {array} Arrays of settings descriptions.
     * @param parents {array} Arrays of setings parents.
     */
    Tree.prototype.init = function(ids, nodeids, labels, descriptions, parents) {
        let nelements = ids.length;

        this.rootNode.innerHTML = "";
        let promises = [];
        // Add all nodes to the Tree.
        for (let i = 0; i < nelements; i++) {

            // Search the parent of the node.
            let parent = null;

            // Create a new node.
            let newNode = new NodeTree(
                nodeids[i],
                ids[i],
                decodeURIComponent(labels[i]),
                decodeURIComponent(descriptions[i])
            );

            this.nodes[newNode.id] = newNode;
        }

        // Associate parents and children.
        for (let i = 0; i < nelements; i++) {
            if (parents[i] === 'root') {
                this.nodes[nodeids[i]].parent = null;
            } else {
                this.nodes[nodeids[i]].parent = this.nodes[parents[i]];
                this.nodes[parents[i]].children.push(this.nodes[nodeids[i]]);
            }
        }

        // Display all parent nodes.
        for (var key in this.nodes) {
            if (this.nodes.hasOwnProperty(key)) {
                if (this.nodes[key].parent === null) {
                    promises.push(this.display(key));
                }
            }
        }

        // Make the tree accessible.
        Promise.all(promises).finally((values) => {
            this.accessibleview = new TreeAccessible('#' + this.rootNode.getAttribute('id'));
        });
    };

    /**
     * Apply the events click on the element's node and his checkbox.
     *
     * @param nodeId {string} Id of the node.
     */
    Tree.prototype.applyEvent = function(nodeId) {
        let node = this.nodes[nodeId];
        // If the elements is displayed.
        if (node.displayed) {

            let elementNode = document.getElementById(nodeId);

            // Display all children node when is the node is clicked.
            elementNode.addEventListener('focus', () => {
                if (node.hasChildren()) {

                    let promises = [];
                    node.children.forEach((nodeChild) => {
                        promises.push(this.display(nodeChild.id))
                        //promises.push(this.children[index].display());
                    });

                    // Make the node accessible.
                    Promise.all(promises).finally((values) => {
                        this.accessibleview.initialiseNodes($('#' + nodeId));
                    });
                }
            });


            // Change the value of mark checked when a click on the checkbox.
            let checkboxElement = document.getElementById(nodeId + '_checkbox');
            checkboxElement.addEventListener('click', (event) => {
                event.stopPropagation();
                event.preventDefault();
                this.setChecked(nodeId, !node.checked);
            });

            // Change the value of mark checked when the enter key is pushed.
            elementNode.addEventListener('keydown', (event) => {
                if (event.key === "Enter") {
                    event.stopPropagation();
                    event.preventDefault();
                    this.setChecked(nodeId, !node.checked);
                }
            });
        }
    };

    /**
     * Display the Node in the DOM (create DOM element).
     *
     * @param nodeId {string} Id of the node.
     * @return {Promise}
     */
    Tree.prototype.display = function(nodeId) {
        return new Promise((resolve, reject) => {
            let node = this.nodes[nodeId];
            // If the elements is not yet displayed.
            if (!node.displayed && !node.isEmpty()) {
                let parentElement = null;
                // Take the root node of the tree if the Node hasn't parent.
                if (node.parent === null) {
                    parentElement = this.rootNode;
                } else {
                    parentElement = document.getElementById(node.parent.id).getElementsByTagName('ul')[0];
                    this.nodes[nodeId].level = this.nodes[node.parent.id].level + 1;
                }

                let haschildren = '';
                if (node.hasChildren()) {
                    haschildren = 'has-children';
                }
                let checked = 'checkbox-unchecked';
                if (node.checked) {
                    checked = 'checkbox-checked'
                }


                // Add the new node in the DOM.
                let newNode = {
                    "id": node.id,
                    "level": node.level,
                    "label": node.label,
                    "has_children" : haschildren,
                    "checked" : checked
                };

                // Add the node in the DOM.
                Templates.render('tool_admin_presets/tree_node', newNode)
                    .then((html) => {
                        parentElement.insertAdjacentHTML('beforeend', html);

                        // Mark the node displayed.
                        this.nodes[nodeId].displayed = true;

                        // Apply click event on the element.
                        this.applyEvent(nodeId);

                        resolve(true);
                    }).fail(function (ex) {
                    reject(false);
                    console.error(ex);
                });
            } else {
                resolve(true);
            }
        });
    };

    /**
     * Set the property checked on the node and his children.
     *
     * @param  nodeId {string} Id of the node.
     * @param checked {boolean} Checking status.
     */
    Tree.prototype.setChecked = function(nodeId, checked) {
        let node = this.nodes[nodeId];
        this.nodes[nodeId].checked = checked;

        // Change the checkbox apparence.
        if (node.displayed) {
            let checkboxElement = document.getElementById(nodeId + '_checkbox');
            if (checked) {
                checkboxElement.setAttribute('class', 'checkbox-checked');
            } else {
                checkboxElement.setAttribute('class', 'checkbox-unchecked');
            }
        }

        // Modify all children.
        if (node.hasChildren()) {
            node.children.forEach((childNode) => {
                this.setChecked(childNode.id, checked);
            });
        }
    }

    /**
     * Submit the settings to the form.
     *
     * @param buttonId {string} Id of submit button element.
     */
    Tree.prototype.submit = function(buttonId) {
        let button = document.getElementById(buttonId);

        // Event on click on the submit button.
        button.addEventListener('click', () => {
            let settingsPresetsForm = document.getElementById(buttonId).parentNode;

            // Remove all previous input created.
            settingsPresetsForm.getElementsByTagName('input').forEach((node) => {
                if (node.getAttribute('type') === 'hidden') {
                    node.remove();
                }
            });

            // Create all hidden input with nodes checked.
            for (var key in this.nodes) {
                if (this.nodes.hasOwnProperty(key)) {
                    let node = this.nodes[key];
                    if (node.settingId !== 'category' && node.settingId !== 'page' && node.checked) {
                        let settingInput = document.createElement('input');
                        settingInput.setAttribute('type', 'hidden');
                        settingInput.setAttribute('name', node.settingId);
                        settingInput.setAttribute('value', '1');
                        settingsPresetsForm.appendChild(settingInput);
                    }
                }
            }
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
                    let tree = new Tree('settings_tree_div');
                    tree.init(response.ids,
                        response.nodes,
                        response.labels,
                        response.descriptions,
                        response.parents);

                    // Set the submit event.
                    tree.submit('id_admin_presets_submit');
                }
            );
        }
    };
});
