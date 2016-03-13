=== Stacksight ===
Contributors: liorkesos, bora-89, igor-lemon
Tags: administration, activity, event, monitor, multisite, multi-users, log, logger, audit log, stats, security, tracking, woocommerce, notifications, email notifications
Requires at least: 3.5
Tested up to: 4.3
Stable tag: 1.7.15
License: GPLv2 or later


<h3>Realtime inisights for you wordpress applications</h3>
Get a realtime stream of events and logs from your wordpress applications (and other opensource applications as well!)

== Description ==

Stacksight uses the awesome <a href="https://wordpress.org/plugins/aryo-activity-log/">aryo activity log</a> plugin (by <a href="http://pojo.me">pojo.me</a> to send a realtime event stream of your wordpress activity.

<strong>Stacksight logs the next events to the stacksight.io dashboard</strong><br />

* <strong>WordPress</strong> - Core Updates
* <strong>Posts</strong> - Created, Updated, Deleted
* <strong>Pages</strong> - Created, Updated, Deleted
* <strong>Custom Post Type</strong> - Created, Updated, Deleted
* <strong>Tags</strong> - Created, Edited, Deleted
* <strong>Categories</strong> - Created, Edited, Deleted
* <strong>Taxonomies</strong> - Created, Edited, Deleted
* <strong>Comments</strong> - Created, Approved, Unproved, Trashed, Untrashed, Spammed, Unspammed, Deleted
* <strong>Media</strong> - Uploaded, Edited, Deleted
* <strong>Users</strong> - Login, Logout, Login has failed, Update profile, Registered and Deleted
* <strong>Plugins</strong> - Installed, Updated, Activated, Deactivated, Changed
* <strong>Themes</strong> - Installed, Updated, Deleted, Activated, Changed (Editor and Customizer)
* <strong>Widgets</strong> - Added to a sidebar / Deleted from a sidebar, Order widgets
* <strong>Menus</strong> - A menu is being Created, Updated, Deleted
* <strong>Setting</strong> - General, Writing, Reading, Discussion, Media, Permalinks
* <strong>Options</strong> - Can be extend by east filter
* <strong>Export</strong> - User download export file from the site
* <strong>WooCommerce</strong> - Monitor all shop options
* <strong>bbPress</strong> - Forums, Topics, Replies, Taxonomies and other actions
* and much more...



<strong>Contributions:</strong><br />
==== stacksight ====
stacksight contributions can be done through submiting PRS to the [Github repo](http://github.com/stacksight/wordpress).

== Installation ==

1. Prerequisite: Get the [aryo activity log](https://wordpress.org/plugins/aryo-activity-log) plugin and activate it.
1. Upload the stacksight plugin to your plugins folder, or install using WordPress' built-in Add New Plugin installer 
1. Activate the plugin
1. Go to the 

== Screenshots ==

1. The log viewer page
2. The settings page
3. Screen Options
4. Interface for defining notification rules

== Frequently Asked Questions ==

= Requirements =
* __Requires PHP5__ for list management functionality.

= What is the plugin license? =

* This plugin is released under a GPL license.


== Changelog ==

= 1.12 =
* Move to updates index

= 1.11 =
* Change structure to not to contain the aryo-activity-plugin but instead reply on it

= 1.1 =
* Track wp events in the stacksight.io

