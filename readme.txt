=== Stacksight ===
Contributors: liorkesos, bora-89 , pojo.me, KingYes, ariel.k, maor
Tags: administration, activity, event, monitor, multisite, multi-users, log, logger, audit log, stats, security, tracking, woocommerce, notifications, email notifications
Requires at least: 3.5
Tested up to: 4.3
Stable tag: 2.2.5
License: GPLv2 or later
Credits: 


== Description ==

<h3>Realtime inisights for you wordpress applications</h3>
Get a realtime stream of events and logs from your wordpress applications (and other opensource applications as well!)

<strong>Credits:</strong>
Stacksight is a fork of the awesome (aryo-activity-log)[https://wordpress.org/plugins/aryo-activity-log/] plugin (by (pojo.me)[http://pojo.me]) which is used to collect some of the wordpress event stream. Go pojo Go!

<strong>Stacksights logs the next events to the stacksight.io dashboard</strong><br />

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

<strong>New!</strong> You are now able to get email notifications once an event you have defined (via rules) has occured. This is useful in cases you must know right away when someone does something on your site.

<h4>Aryo Activity Translators:</h4>
* German (de_DE) - [Robert Harm](http://www.mapsmarker.com/)
* Dutch (nl_NL) - [Tom Aalbers](http://www.dtaalbers.com/)
* Serbo-Croatian (sr_RS) - [Borisa Djuraskovic](http://www.webhostinghub.com/)
* Danish (da_DK) - [Morten Dalgaard Johansen](http://www.iosoftgame.com/)
* Hebrew (he_IL) + RTL Support - [Pojo.me](http://pojo.me/)
* Armenia (hy_AM) - Hayk Jomardyan
* Brazilian Portuguese (pt_BR) - [Criação de Sites](http://www.techload.com.br/criacao-de-sites-ribeirao-preto)
* Turkish (tr_TR) - [Ahmet Kolcu](http://ahmetkolcu.org)
* Persian (fa_IR) - [Promising](http://vwp.ir/)
* Russian (ru_RU) - Oleg Reznikov
* Polish (pl_PL) - Maciej Gryniuk
* Czech (cs_CZ) - Martin Kokeš
* Finnish (fi) - Nazq

<strong>Contributions:</strong><br />
==== stacksight ====
stacksight contributions can be done through submiting PRS to the [Github repo](http://github.com/stacksight/wordpress).

==== Activity Log ==== 
Would you like to like to contribute to Activity Log? You are more than welcome to submit your pull requests on the [GitHub repo](https://github.com/KingYes/wordpress-aryo-activity-log). Also, if you have any notes about the code, please open a ticket on ths issue tracker.


== Installation ==

1. Upload plugin files to your plugins folder, or install using WordPress' built-in Add New Plugin installer
1. Activate the plugin
1. Go to the plugin page (under Dashboard > Stacksight)

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

= 2.2.6 =
* Tweak! - Added more actions/types in notification

= 2.2.5 =
* New! - Added translate: Finnish (fi) - Thanks to Nazq ([topic](https://wordpress.org/support/topic/finnish-translation-1))
* Tweak! - Better actions label in list table
* Fixed! - Notice php warring in MU delete site
* Tested up to WordPress v4.3

= 2.2.4 =
* New! - Added translate: Czech (cs_CZ) - Thanks to Martin Kokeš ([#76](https://github.com/KingYes/wordpress-aryo-activity-log/pull/76))

= 2.2.3 =
* Tweak! - Added more filters in table list columns

= 2.2.2 =
* Fixed! some PHP strict standards (PHP v5.4+)

= 2.2.1 =
* Fixes from prev release

= 2.2.0 =
* New! - Adds search box, to allow users to search the description field.
* New! - Allows users to now filter by action
* New! - Added translate: Polish (pl_PL) - Thanks to Maciej Gryniuk
* Tweak! - SQL Optimizations for larger sites

= 2.1.16 =
* New! Added translate: Russian (ru_RU) - Thanks to Oleg Reznikov
* Fixes Undefined property with some 3td party themes/plugins
* Tested up to WordPress v4.2

= 2.1.15 =
* Tested up to WordPress v4.1
* Change plugin name to "Activity Log"

= 2.1.14 =
* New! Added translate: Persian (fa_IR) - Thanks to [Promising](http://vwp.ir/)

= 2.1.13 =
* New! Added filter by User Roles ([#67](https://github.com/KingYes/wordpress-aryo-activity-log/issues/67))

= 2.1.12 =
* New! Added translate: Turkish (tr_TR) - Thanks to [Ahmet Kolcu](http://ahmetkolcu.org/)

= 2.1.11 =
* Fixed! Compatible for old WP version

= 2.1.10 =
* New! Now tracking when menus created and deleted
* New! Added translate: Portuguese (pt_BR) - Thanks to [Criação de Sites](http://www.techload.com.br/criacao-de-sites-ribeirao-preto)

= 2.1.9 =
* New! Store all WooCommerce settings ([#62](https://github.com/KingYes/wordpress-aryo-activity-log/issues/62))
* Tested up to WordPress v4.0

= 2.1.8 =
* New! Now tracking when plugins installed and updated ([#59](https://github.com/KingYes/wordpress-aryo-activity-log/pull/59) and [#43](https://github.com/KingYes/wordpress-aryo-activity-log/issues/43))

= 2.1.7 =
* New! Now tracking when user download export file from the site ([#58](https://github.com/KingYes/wordpress-aryo-activity-log/issues/58) and [#63](https://github.com/KingYes/wordpress-aryo-activity-log/pull/63))

