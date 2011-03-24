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

pimcore.registerNS("pimcore.object.tags.date");
pimcore.object.tags.date = Class.create(pimcore.object.tags.abstract, {

    type: "date",

    initialize: function (data, layoutConf) {
        this.data = data;
        this.layoutConf = layoutConf;

    },

    getLayoutEdit: function () {

        var date = {
            fieldLabel: this.layoutConf.title,
            name: this.layoutConf.name,
            itemCls: "object_field",
            width: 100
        };

        if (this.data) {
            var tmpDate = new Date(parseInt(this.data) * 1000);
            date.value = tmpDate;
        }

        this.layout = new Ext.form.DateField(date);
        return this.layout;
    },

    getLayoutShow: function () {

        this.layout = this.getLayoutEdit();
        this.layout.disable();

        return this.layout;
    },

    getValue: function () {
        if (this.layout.getValue()) {
            return this.layout.getValue().getTime();
        }
        return false;
    },

    getName: function () {
        return this.layoutConf.name;
    },

    isInvalidMandatory: function () {
        if (this.getValue() == false) {
            return true;
        }
        return false;
    }
});