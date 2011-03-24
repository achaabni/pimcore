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

pimcore.registerNS("pimcore.object.tags.textarea");
pimcore.object.tags.textarea = Class.create(pimcore.object.tags.abstract, {

    type: "textarea",

    initialize: function (data, layoutConf) {
        this.data = data;
        this.layoutConf = layoutConf;

    },

    getLayoutEdit: function () {


        if (parseInt(this.layoutConf.width) < 1) {
            this.layoutConf.width = 100;
        }
        if (parseInt(this.layoutConf.height) < 1) {
            this.layoutConf.height = 100;
        }

        var conf = {
            width: this.layoutConf.width,
            height: this.layoutConf.height,
            fieldLabel: this.layoutConf.title,
            itemCls: "object_field"
        };

        if (this.data) {
            conf.value = this.data;
        }

        this.layout = new Ext.form.TextArea(conf);

        return this.layout;
    },


    getLayoutShow: function () {

        this.layout = this.getLayoutEdit();
        this.layout.disable();

        return this.layout;
    },

    getValue: function () {
        return this.layout.getValue();
    },

    getName: function () {
        return this.layoutConf.name;
    }
});