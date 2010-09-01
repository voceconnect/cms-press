=== Plugin Name ===
Contributors: prettyboymp
Donate link: http://voceconnect.com/
Tags: cms, post types, taxonomies
Requires at least: 2.9
Tested up to: 3.0.
Stable tag: trunk

Adds ability to create custom content types (post_types) and taxonomies to your WordPress installation.

== Description ==

CMS Press opens up the ability to create and manage custom content types and taxonomies for your WordPress site.
It adds the flexibility to have more than just posts and pages for content by allowing the user to register their
own post_types that can use their separate theming from the post and page template along with its own permalink structure.

Along with custom post_types, CMS Press gives users the also register their own taxonomies.  With the addition of
custom taxonomies, a user can tag or categorize content separate from the default tags and categories.

== Installation ==

1. Upload the `cms-press` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Why would I need this plugin? =

For most sites, having posts and pages and categories and tags will be enough to do everything the user wants.  However, there are some
sites that need the content to be further separated.

= What if I'm running WordPress v2.8.6 or below? =

Versions before v2.9 of WordPress do not support custom post_types, so there is no option for running this plugin in those releases.

== Screenshots ==

1. Creating a new content type of `Game Review`
2. Creating a new taxonomy of vGame Genre`
3. Editing a `Game Review` post.

== Changelog ==
= 0.1.8 =
* actually fixing the searchable return for dynamic content types to always be boolean
= 0.1.7 =
* fixing issue with loading of dynamic content types from caching before class is defined
* fixed is_searchable return for dynamic content types to always be boolean
= 0.1.6 =
* fixed bug with reversing of plural and singular label
= 0.1.5 =
* switching labels to array
= 0.1.4 =
* added capability string management for 3.0
* fixed checks against empty bool vals (props jimpisaacs)
* added more advanced label handling, not yet propagated to dynamic handlers.
= 0.1.3 =
* fixed issue with 2.9s column handling of urls with new or add
* fixed edit url from front end in 2.9
* fixed issue with post slug metabox keeping slug from being editable in 2.9
* updated support arrays to latest core
* added warnings against use of tag or category
= 0.1.2 =
* updated activation hook to allow symlinking of plugin
* fixed issue with non queryable post types from correctly listing posts
* code cleanup
* fixed issue with post_types with dashes getting incorrect screen meta
* corrected preview handling
* adding latest core registration vars for taxonomies and post_types
= 0.1.1 =
* added work around for WP 2.9 that was keeping users from using post_types that started with 'add' or 'new' due to WP bug.
= 0.1.0 =
* added permalink warnings
* added permission checks
* added title and editor support handling for wp 3.0
= 0.1.RC =
* updated post_type registration to latest HEAD
* readme.txt fixes
= 0.1.Beta.2 =
* added back hierarchical taxonomy support for WP 3.0
* improved permalink structure handling
* moved legacy for WP 2.9 support to separate area
* fixes to preview urls for drafts
* altered menu position of taxonomies for WP 2.9
* il8n updates
* misc bug fixes
= 0.1.Beta =
* Initial Beta Release
