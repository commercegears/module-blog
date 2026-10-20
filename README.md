# CommerceGears Blog for Magento 2

`commercegears/module-blog` (Magento module `CommerceGears_Blog`): a blog inside the Magento store, for Luma and Hyvä.

## Features

- Posts with a WYSIWYG content editor, excerpt, featured image, author, status and publish date (drafts, scheduled
  posts that go live by themselves, featured posts), preview links for drafts.
- Nested categories, tags and author pages; every post, category and tag can have its own text, URL key and meta data
  per store view.
- SEO: blog path, URL prefixes and suffix, meta title/description/keywords, robots per page type and per post,
  canonical URLs, Open Graph and Twitter Card tags, BlogPosting/Blog/BreadcrumbList JSON-LD, hreflang between store
  views, blog pages in Magento's XML sitemap, 301 redirects when the suffix changes.
- SEO checklist in the post editor: title and meta description length, focus keyword placement, length, headings,
  internal links, image alt texts and reading time, updated as you type; the score is shown in the posts grid.
- Comments with moderation (approve, reject, reply in the store's name), honeypot, hourly limit per IP address,
  optional Google reCAPTCHA (Magento's reCAPTCHA modules), optional consent checkbox and an e-mail to the store.
- Related products on posts (with price and Add to Cart), related posts on product pages, previous/next post, related posts.
- CMS widgets: Recent Posts, Categories and Tags. RSS feed for the blog and for each category.
- WordPress import: upload the WordPress export file (WXR) in the admin or import it on the command line. Posts,
  categories (with parents), tags, authors and comments are imported, images on the WordPress site are downloaded,
  301 redirects are created from the old addresses. Large files are imported in steps by cron. Importing the same file
  again adds nothing twice.
- Sample content command for a quick start.

## Admin

- CommerceGears > Blog > Posts, Categories, Tags, Authors, Comments, WordPress Import.
- Settings: Stores > Configuration > CommerceGears > Blog (section `commercegears_blog`).
- Permissions (System > User Roles): CommerceGears > Blog with Posts (Create and Edit, Publish, Delete), Categories,
  Tags, Authors, Comments (Moderate and Reply, Delete), WordPress Import; Stores > Configuration > CommerceGears Blog.

## Command line

```
bin/magento commercegears:blog:import-wordpress <file> [--store=1,2] [--no-images] [--no-redirects] [--no-comments] [--drafts] [--update] [--queue]
bin/magento commercegears:blog:import-wordpress --job=<id>     # run or continue a job queued in the admin
bin/magento commercegears:blog:publish-scheduled               # refresh the caches of scheduled posts that are due
bin/magento commercegears:blog:sample-data [--remove]          # add or remove the sample posts
```

Cron jobs: `commercegears_blog_publish_scheduled` (every 5 minutes, group `default`) and `commercegears_blog_process_imports`
(every minute, own group `commercegears_blog` in a separate process).

## Compatibility

Magento Open Source and Adobe Commerce 2.4.6 to 2.4.9, PHP 8.2 to 8.5 (Magento 2.4.6: PHP 8.2), Luma and Hyvä themes (Hyvä templates are
Alpine CSP-safe). Requires `commercegears/module-core` ^2.0.

## Limitations

- WordPress pages and custom post types are not imported. Shortcodes other than `[caption]` and `[embed]` stay in the
  content; the import log lists them. Images hosted outside the WordPress site stay linked to their source.
- Redirects are created for post addresses with a path (`/2024/05/my-post/`), not for `?p=123` links or archive pages.
- Authors are the same in every store view.
- Changing a post's URL key or the blog path does not create redirects from the old addresses (add them in Marketing >
  SEO & Search > URL Rewrites).
- The hourly comment limit uses the visitor's IP address as Magento sees it; behind a proxy or CDN, set up Magento to
  read the visitor's address from the proxy's header.
- The footer link setting applies to Luma-based themes; Hyvä footers are theme templates.

Documentation: `docs/user-guide.md`, `docs/faq.md`.
