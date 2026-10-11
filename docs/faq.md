# CommerceGears Blog for Magento 2: FAQ

**Does it work with Hyvä?**

Yes. The blog pages, comments, sidebar, widgets and the "From our blog" block on product pages have their own Hyvä
templates (Alpine.js and Tailwind, CSP-safe). Luma and themes based on Luma use the Luma templates. The right templates
are picked automatically for each store view.

**Can I have a different blog per store view or language?**

Posts, categories and tags can be assigned to store views and can have their own title, URL key, content and meta data
in each store view. All blog settings, including the blog path and title, can be set per store view. Hreflang links
connect the language versions of the blog home page, categories and posts.

**How do scheduled posts work?**

Set the status to Published and choose a publish date in the future. The post stays hidden until that time and then
appears by itself; cron refreshes the cached lists and sidebars every five minutes. Drafts and scheduled posts can be
previewed with the Preview button.

**What does the SEO checklist check?**

The length of the SEO title and meta description, whether the focus keyword is in the title, meta description, URL key
and first 100 words, the length of the post, subheadings, internal links and image alt texts. It updates as you type
and gives a score from 0 to 100 that is also shown in the posts grid. It does not stop you from publishing.

**Is the blog included in the XML sitemap?**

Yes, when "Add Blog Pages to the XML Sitemap" is on (the default), Magento's sitemap (Marketing > SEO & Search > Site
Map) includes the blog home page, categories and posts, optionally tag and author pages and featured images.

**How do I move my blog from WordPress?**

Export All content in WordPress (Tools > Export) and upload the file under CommerceGears > Blog > WordPress Import, or
import it with `bin/magento commercegears:blog:import-wordpress <file>`. Posts, categories, tags, authors, comments and
images on your WordPress site are imported, and 301 redirects are created from the old post addresses. Large blogs are
imported in steps by cron.

**What happens if I import the same file twice?**

Nothing is created twice. Posts imported before are skipped (your changes are kept) unless you choose "Update posts
imported before"; comments and redirects are only added once. This also means an import that stopped half way can
simply be run again.

**Are WordPress pages and shortcodes imported?**

Only blog posts are imported, not pages or custom post types. `[caption]` and `[embed]` shortcodes are converted; other
shortcodes, for example galleries or contact forms, stay in the text and are listed in the import log so you can edit
those posts.

**How are comments protected against spam?**

Comments wait for your approval unless you choose otherwise. A hidden honeypot field catches simple bots, there is a
limit of comments per IP address per hour, comments with links can always be held for review, and Google reCAPTCHA can
be switched on through Magento's reCAPTCHA modules.

**Can I show blog posts on the home page or in a CMS block?**

Yes, with the Recent Posts widget (Content > Widgets, or Insert Widget in the editor). It can show the latest posts,
the posts of one category or featured posts only. There are also widgets for the categories and the tags.

**Does the blog slow down my store?**

The blog pages, widgets and product page blocks are cached by Magento's full page cache and cleared when you change a
post. Comments are sent in the background, so post pages stay cacheable. WordPress imports run in cron, not in a page
request.

**Who can edit the blog in the admin?**

Access is set per role under System > Permissions > User Roles: posts (create and edit, publish, delete), categories,
tags, authors, comments (moderate, delete), the WordPress import and the settings each have their own permission.
