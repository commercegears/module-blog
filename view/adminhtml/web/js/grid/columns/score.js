/**
 * @category  CommerceGears
 * @package   CommerceGears_Blog
 * @copyright Copyright © CommerceGears (https://commercegears.com/)
 */
/**
 * SEO checklist score (0-100) shown as a status badge: 80+ green, 50+ yellow, below 50 red.
 */
define([
    'Magento_Ui/js/grid/columns/column'
], function (Column) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'CommerceGears_Blog/grid/cells/score'
        },

        /**
         * @param {Object} row
         * @returns {Boolean}
         */
        hasScore: function (row) {
            var value = row[this.index];

            return value !== null && value !== undefined && value !== '';
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        getSeverityClass: function (row) {
            var value = parseInt(row[this.index], 10);

            if (value >= 80) {
                return 'grid-severity-notice';
            }

            return value >= 50 ? 'grid-severity-minor' : 'grid-severity-critical';
        }
    });
});
