/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
/**
 * Select column shown as a Magento status badge (grid-severity-* classes). Config "severity" maps option values to
 * the class: notice (green), minor (yellow), major (orange), critical (red).
 */
define([
    'Magento_Ui/js/grid/columns/select'
], function (Select) {
    'use strict';

    return Select.extend({
        defaults: {
            bodyTmpl: 'CommerceGears_Blog/grid/cells/status-badge',
            severity: {}
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        getSeverityClass: function (row) {
            var value = row[this.index];

            return this.severity[value] || 'grid-severity-minor';
        }
    });
});
