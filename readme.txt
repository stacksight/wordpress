=== Stacksight ===
Contributors: liorkesos, bora-89, igor-lemon, zstolar
Tags: administration, activity, event, monitor, multisite, multi-users, log, logger, audit log, stats, security, tracking, woocommerce, notifications, email notifications
Requires at least: 3.5
Tested up to: 4.3
Stable tag: 1.11.0
License: GPLv2 or later


Operational Insights for your Open Source Applications
Manage all your open source apps from one easy-to-use dashboard. From updates to backups and security, Stacksight will allow you to solve problems faster, reduce troubleshooting time and avoid the constant juggling between different plug-ins and tools.

== Description ==

StackSight provides operational insights for opensource applications providing  live sream of logs and events,  Security, Performance and Accessibility, and additional health metrics and integrations to .

If you have more than 3-5 websites, you’re quickly overwhelmed by events and updates on them. Stacksight puts all the information in a one easy-to-use dashboard and allows you to diagnose and solve problems faster, reduce troubleshooting time and avoid the constant juggling between different plug-ins and tools.

###Updates
Get real-time tracking of events and changes in your website. Configure your notifications and integrations and start making troubleshooting a breeze.
###Events
Stacksight informs you about available updates and keep your system  in perfect condition at all time. See the list of logged events further down this list.
###Logs
Get real-time logging information of warnings and errors regarding the Wordpress app. Share insights on the fly with other team members and solve problems faster.
###Organizational Policies 
Stacksight allows you to set your policy around the matrix above and track your performance over time, get alerted when something goes wrong...
###Debugging collaboration
With stacksight debugging tools finding an accessibility issue, performance, security etc is simple and visual than ever.

###Stacksight logs the following events to the stacksight.io dashboard:
* **WordPress** - Core Updates
* **Posts** - Created, Updated, Deleted
* **Pages** - Created, Updated, Deleted
* **Custom Post Type** - Created, Updated, Deleted
* **Tags** - Created, Edited, Deleted
* **Categories** - Created, Edited, Deleted
* **Taxonomies** - Created, Edited, Deleted
* **Comments** - Created, Approved, Unproved, Trashed, Untrashed, Spammed, Unspammed, Deleted
* **Media** - Uploaded, Edited, Deleted
* **Users** - Login, Logout, Login has failed, Update profile, Registered and Deleted
* **Plugins** - Installed, Updated, Activated, Deactivated, Changed
* **Themes** - Installed, Updated, Deleted, Activated, Changed (Editor and Customizer)
* **Widgets** - Added to a sidebar / Deleted from a sidebar, Order widgets
* **Menus** - A menu is being Created, Updated, Deleted
* **Setting** - General, Writing, Reading, Discussion, Media, Permalinks
* **Options** - Can be extend by east filter
* **Export** - User download export file from the site
* **WooCommerce** - Monitor all shop options
* **bbPress** - Forums, Topics, Replies, Taxonomies and other actions
* and **much more**...

### Cross-Platform
Stacksight is not limited only to Wordpress. If you host or manage multiple Open Source applications, such as Drupal, Magento, Moodle, or any other PHP based application (Simphony



==== stacksight ====

stacksight contributions can be done through submiting PRS to the [Github repo](http://github.com/stacksight/wordpress).

== Installation ==

1. Upload the Stacksight plugin to your plugins folder, or install using WordPress' built-in Add New Plugin installer
2. Activate the plugin
3. Create a stack in a group: Go to https://apps.stacksight.io/

    3.1 If you do not have a group yet, create a group.
4. In your group, add a new wordpress stack by inserting the URL or by “Add a local stack” and follow the instructions.

== Screenshots ==

1. The general settings page
2. The features settings page
3. Add stack
4. Add custom stack

== Frequently Asked Questions ==

= Requirements =
* __Requires PHP5__ for list management functionality.

= What is the plugin license? =

* This plugin is released under a GPL license.


== Changelog ==
= 1.11.0 =
- Save application settings in DB

= 1.10.5 =
- Fix compatibility with PHP < 5.5
- Fix multisite for path like subdomains

= 1.10.4 =
- Add link to documentation with instruction how to init plugin
- Fix bug with much clinking on the button for sending all data from all subdomains
- Add time limits for handshake and sending all data from all subdomains
- Changed handshake description

= 1.10.3 =
Fixed bugs with regular mode

= 1.10.2 =
- Add button to sends full data from all subsites
- Fix multisite domain with path mode
- Fix multisite Inventory
- Fix multipple sends data

= 1.10.1 =
- Added new events for plugins and themes
- Fixed last login field in Inventory

= 1.10.0 =
- Added new functionality for working with multisite

= 1.9.3 =
- Added MySQLi support
- Fixed RTL issue

= 1.12 =
* Move to updates index

= 1.11 =
* Change structure to not to contain the aryo-activity-plugin but instead reply on it

= 1.1 =
* Track wp events in the stacksight.io