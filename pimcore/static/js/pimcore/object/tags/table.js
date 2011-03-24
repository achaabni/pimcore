/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @copyright  Copyright (c) 2009-2010 elements.at New Media Solutions GmbH (http://www.elements.at)
 * @license    http://www.pimcore.org/license     New BSD License
 */

pimcore.registerNS("pimcore.object.tags.table");
pimcore.object.tags.table = Class.create(pimcore.object.tags.abstract, {

    type: "table",

    initialize: function (data, layoutConf) {

        this.layoutConf = layoutConf;

        if (!data) {
            data = [
                [" "]
            ];
            if (this.layoutConf.cols) {
                for (var i = 0; i < (this.layoutConf.cols - 1); i++) {
                    data[0].push(" ");
                }
            }
            if (this.layoutConf.rows) {
                for (var i = 0; i < (this.layoutConf.rows - 1); i++) {
                    data.push(data[0]);
                }
            }
            if (this.layoutConf.data) {
                try {
                    var dataRows = this.layoutConf.data.split("\n");
                    var dataGrid = [];
                    for (var i = 0; i < dataRows.length; i++) {
                        dataGrid.push(dataRows[i].split("|"));
                    }

                    data = dataGrid;
                }
                catch (e) {
                    console.log(e);
                }
            }
        }

        this.data = data;
    },

    getLayoutEdit: function () {


        var options = {};
        options.name = this.layoutConf.name;
        options.frame = true;
        options.layout = "fit";
        options.title = this.layoutConf.title;
        options.cls = "object_field";

        if (!this.panel) {
            this.panel = new Ext.Panel(options);
        }

        this.initStore(this.data);
        this.initGrid();

        return this.panel;
    },


    getLayoutShow: function () {

        this.layout = this.getLayoutEdit();
        this.layout.disable();

        return this.layout;
    },


    initGrid: function () {

        this.panel.removeAll();

        var data = this.store.queryBy(function(record, id) {
            return true;
        });
        var columns = [];

        if (data.items[0]) {
            var keys = Object.keys(data.items[0].data);

            for (var i = 0; i < keys.length; i++) {
                columns.push({
                    dataIndex: keys[i],
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                });
            }
        }


        this.grid = new Ext.grid.EditorGridPanel({
            store: this.store,
            width: 700,
            height: 300,
            columns:columns,
            stripeRows: true,
            columnLines: true,
            clicksToEdit: 2,
            autoHeight: true,
            tbar: [
                {
                    iconCls: "pimcore_tag_table_addcol",
                    handler: this.addColumn.bind(this)
                },
                {
                    iconCls: "pimcore_tag_table_delcol",
                    handler: this.deleteColumn.bind(this)
                },
                {
                    iconCls: "pimcore_tag_table_addrow",
                    handler: this.addRow.bind(this)
                },
                {
                    iconCls: "pimcore_tag_table_delrow",
                    handler: this.deleteRow.bind(this)
                },
                {
                    iconCls: "pimcore_tag_table_empty",
                    handler: this.initStore.bind(this, [
                        [" "]
                    ])
                }
            ]
        });
        this.panel.add(this.grid);
        this.panel.doLayout();
    },

    initStore: function (data) {
        var storeFields = [];
        if (data[0]) {
            for (var i = 0; i < data[0].length; i++) {
                storeFields.push({
                    name: "col_" + i
                });
            }
        }

        this.store = new Ext.data.ArrayStore({
            fields: storeFields
        });

        this.store.loadData(data);
        this.initGrid();
    },

    addColumn : function  () {

        var currentData = this.getValue();

        for (var i = 0; i < currentData.length; i++) {
            currentData[i].push(" ");
        }

        this.initStore(currentData);
    },

    addRow: function  () {
        var initData = {};

        for (var o = 0; o < this.grid.getColumnModel().config.length; o++) {
            initData["col_" + o] = " ";
        }

        this.store.add(new this.store.recordType(initData, this.store.getCount() + 1));
    },

    deleteRow : function  () {
        var selected = this.grid.getSelectionModel();
        if (selected.selection) {
            this.store.remove(selected.selection.record);
        }
    },

    deleteColumn: function () {
        var selected = this.grid.getSelectionModel();

        if (selected.selection) {
            var column = selected.selection.cell[1];

            var currentData = this.getValue();

            for (var i = 0; i < currentData.length; i++) {
                currentData[i].splice(column, 1);
            }

            this.initStore(currentData);
        }
    },

    getValue: function () {
        var data = this.store.queryBy(function(record, id) {
            return true;
        });

        var storedData = [];
        var tmData = [];
        for (var i = 0; i < data.items.length; i++) {
            tmData = [];

            keys = Object.keys(data.items[i].data);
            for (var u = 0; u < keys.length; u++) {
                tmData.push(data.items[i].data[keys[u]]);
            }
            storedData.push(tmData);
        }

        return storedData;
    },

    getName: function () {
        return this.layoutConf.name;
    },

    markMandatory: function () {
        if (this.panel) {
            this.panel.getEl().addClass("object_mendatory_error");
        }
    },

    unmarkMandatory: function () {
        if (this.panel) {
            this.panel.getEl().removeClass("object_mendatory_error");
        }
    }
});