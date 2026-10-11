/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
/**
 * Live SEO checklist of the post editor. Same rules as Softaware\Blog\Model\SeoChecklist (which stores the score
 * on save); keep both in step.
 */
define([
    'uiComponent',
    'ko',
    'underscore',
    'mage/translate'
], function (Component, ko, _, $t) {
    'use strict';

    /**
     * @param {String} html
     * @returns {Document}
     */
    function parse(html) {
        return new DOMParser().parseFromString(String(html || '').replace(/\{\{[^}]*\}\}/g, ' '), 'text/html');
    }

    /**
     * @param {Document} doc
     * @returns {String}
     */
    function plainText(doc) {
        var clone = doc.body ? doc.body.cloneNode(true) : null;

        if (!clone) {
            return '';
        }
        _.each(clone.querySelectorAll('script, style, noscript'), function (node) {
            node.parentNode.removeChild(node);
        });
        _.each(clone.querySelectorAll('br, p, div, li, h1, h2, h3, h4, h5, h6, tr, blockquote'), function (node) {
            node.appendChild(document.createTextNode(' '));
        });

        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    /**
     * @param {String} value
     * @returns {String}
     */
    function slugify(value) {
        var text = String(value || '');

        if (text.normalize) {
            text = text.normalize('NFKD').replace(/[̀-ͯ]/g, '');
        }

        return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    return Component.extend({
        defaults: {
            template: 'Softaware_Blog/form/seo-checklist',
            ownHosts: [],
            wordsPerMinute: 200,
            limits: {
                titleMin: 30,
                titleMax: 60,
                descriptionMin: 120,
                descriptionMax: 160,
                minWords: 300
            },
            title: '',
            metaTitle: '',
            metaDescription: '',
            urlKey: '',
            keyword: '',
            content: '',
            image: [],
            imageAlt: '',
            imports: {
                title: '${ $.provider }:data.title',
                metaTitle: '${ $.provider }:data.meta_title',
                metaDescription: '${ $.provider }:data.meta_description',
                urlKey: '${ $.provider }:data.url_key',
                keyword: '${ $.provider }:data.focus_keyword',
                content: '${ $.provider }:data.content',
                image: '${ $.provider }:data.featured_image',
                imageAlt: '${ $.provider }:data.featured_image_alt'
            },
            tracks: {
                title: true,
                metaTitle: true,
                metaDescription: true,
                urlKey: true,
                keyword: true,
                content: true,
                image: true,
                imageAlt: true
            }
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.checks = ko.pureComputed(this.analyse, this);
            this.score = ko.pureComputed(function () {
                var total = 0,
                    points = 0;

                _.each(this.checks(), function (check) {
                    if (check.status === 'info') {
                        return;
                    }
                    total++;
                    points += check.status === 'good' ? 1 : check.status === 'warning' ? 0.5 : 0;
                });

                return total ? Math.round(points / total * 100) : 0;
            }, this);
            this.scoreClass = ko.pureComputed(function () {
                var score = this.score();

                return score >= 80 ? '_good' : score >= 50 ? '_fair' : '_poor';
            }, this);

            return this;
        },

        /**
         * @param {String} status
         * @returns {String}
         */
        statusLabel: function (status) {
            return {
                good: $t('Good'),
                warning: $t('Could be better'),
                problem: $t('Needs attention'),
                info: $t('Information')
            }[status] || '';
        },

        /**
         * @returns {Array}
         */
        analyse: function () {
            var limits = this.limits,
                title = String(this.metaTitle || '').trim() || String(this.title || '').trim(),
                description = String(this.metaDescription || '').trim(),
                keyword = String(this.keyword || '').trim(),
                html = String(this.content || ''),
                doc = parse(html),
                text = plainText(doc),
                words = text ? text.split(/\s+/).length : 0,
                checks = [],
                length,
                needle,
                slug,
                intro,
                internal,
                images,
                missingAlt,
                featured,
                minutes,
                hosts = _.map(this.ownHosts || [], function (host) {
                    return String(host).toLowerCase().replace(/^www\./, '');
                });

            /**
             * @param {String} id
             * @param {String} status
             * @param {String} message
             */
            function add(id, status, message) {
                checks.push({id: id, status: status, message: message});
            }

            length = title.length;
            add('title', length === 0 ? 'problem' : length >= limits.titleMin && length <= limits.titleMax ? 'good' :
                length > limits.titleMax + 10 ? 'problem' : 'warning',
                $t('SEO title: %1 characters (aim for %2 to %3).').replace('%1', length)
                    .replace('%2', limits.titleMin).replace('%3', limits.titleMax));

            length = description.length;
            add('description', length === 0 ? 'warning' :
                length >= limits.descriptionMin && length <= limits.descriptionMax ? 'good' :
                    length > limits.descriptionMax + 40 ? 'problem' : 'warning',
                length === 0 ?
                    $t('Meta description: empty, so the start of the post is used. Write one of %1 to %2 characters.')
                        .replace('%1', limits.descriptionMin).replace('%2', limits.descriptionMax) :
                    $t('Meta description: %1 characters (aim for %2 to %3).').replace('%1', length)
                        .replace('%2', limits.descriptionMin).replace('%3', limits.descriptionMax));

            if (keyword === '') {
                add('keyword', 'warning', $t('Focus keyword: not set. Add the phrase people search for to check its placement.'));
            } else {
                needle = keyword.toLowerCase();
                add('keyword_title', title.toLowerCase().indexOf(needle) !== -1 ? 'good' : 'problem',
                    $t('Focus keyword in the SEO title.'));
                add('keyword_description', description.toLowerCase().indexOf(needle) !== -1 ? 'good' : 'warning',
                    $t('Focus keyword in the meta description.'));
                slug = slugify(keyword);
                add('keyword_url', slug !== '' && String(this.urlKey || slugify(this.title)).toLowerCase().indexOf(slug) !== -1 ?
                    'good' : 'warning', $t('Focus keyword in the URL key.'));
                intro = text.split(/\s+/).slice(0, 100).join(' ').toLowerCase();
                add('keyword_intro', intro.indexOf(needle) !== -1 ? 'good' : 'warning',
                    $t('Focus keyword in the first 100 words.'));
            }

            add('length', words >= limits.minWords ? 'good' : words >= limits.minWords / 2 ? 'warning' : 'problem',
                $t('Length: %1 words (aim for at least %2).').replace('%1', words).replace('%2', limits.minWords));

            if (doc.querySelector('h1')) {
                add('headings', 'problem',
                    $t('Headings: the content has an H1. The post title is the H1, so use H2 and H3 in the content.'));
            } else {
                add('headings', doc.querySelector('h2, h3') ? 'good' : 'warning',
                    $t('Subheadings (H2, H3) break the text into sections.'));
            }

            internal = 0;
            _.each(String(html).match(/<a\s[^>]*href\s*=\s*(["'])(.*?)\1/gi) || [], function (tag) {
                var href = tag.replace(/^.*href\s*=\s*(["'])(.*?)\1.*$/i, '$2').trim(),
                    host;

                if (href === '' || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) {
                    return;
                }
                if (href.indexOf('{{') === 0 || (!/^[a-z][a-z0-9+.-]*:/i.test(href) && href.indexOf('//') !== 0)) {
                    internal++;

                    return;
                }
                host = (href.match(/^(?:[a-z]+:)?\/\/([^/?#:]+)/i) || [])[1] || '';
                if (host && hosts.indexOf(host.toLowerCase().replace(/^www\./, '')) !== -1) {
                    internal++;
                }
            });
            add('links', internal > 0 ? 'good' : 'warning',
                $t('Internal links: %1 (link to at least one product, category or post).').replace('%1', internal));

            images = String(html).match(/<img\b[^>]*>/gi) || [];
            missingAlt = _.filter(images, function (tag) {
                return !/\salt\s*=\s*(["'])\s*\S.*?\1/i.test(tag);
            }).length;
            featured = _.isArray(this.image) ? this.image.length > 0 : !!this.image;
            if (images.length === 0 && !featured) {
                add('images', 'warning', $t('Images: none. A featured image is used in lists and when the post is shared.'));
            } else if (missingAlt > 0 || (featured && String(this.imageAlt || '').trim() === '')) {
                add('images', 'problem', $t('Image alt text: missing on %1 image(s).')
                    .replace('%1', missingAlt + (featured && String(this.imageAlt || '').trim() === '' ? 1 : 0)));
            } else {
                add('images', 'good', $t('Image alt text: every image has one.'));
            }

            minutes = words > 0 ? Math.max(1, Math.ceil(words / Math.max(1, this.wordsPerMinute))) : 0;
            add('reading_time', 'info', $t('Reading time: about %1 min.').replace('%1', minutes));

            return checks;
        }
    });
});
