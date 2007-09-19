==== Upcoming Events ====

Tags: calendar, events, ical, feed
Contributors: StarDestroyer, sjashe
Requires at least: 2.0
Tested up to: 2.2.2
Stable tag: 0.4

This plugin can receive iCalendar feeds from third party sites and display aggregated upcoming events from multiple feeds in your sidebar.

== Description ==

Upcoming Events is a plugin that can receive iCalendar feeds from third party
sites and display aggregated upcoming events from multiple feeds. As of this
time, it has received very little testing, but it is working on the author's
site using two feeds from Google, one from Yahoo, and one from ical.mac.com.
This first release version provides the ability to add a virtually unlimited
number of feeds, but does not contain the ability to delete any (feeds can be
hidden so they don't show up in the sidebar).

ICS files are locally cached and will be automatically updated when they reach
a certain age. This age is configurable both as a global default and
individually for each user. The options range from 1 Hour to 1 Year with many
available options in between.

== Installation ==

Installation is pretty standard. Simply place the contents of the
"upcoming-events" directory into your WordPress plugins directory and visit
the Plugins page in the WordPress Admin Interface to active the plugin.
Configuration options will then be available from the "Upcoming Events" page
of the "Options" tab.

== Release Notes ==

For a full revision log, see http://dev.wp-plugins.org/log/upcoming-events

= v0.4 - 11-Sep-2007 =

* Use $wpdb->prefix for forward compatibility with Wordpress
* Provide limited support for recurring events
* Specify that the background should be White in the CSS file
* Display newly added feeds by default

= v0.3 - 6-Dec-2006 =

* Removed a debugging statement that caused days/weeks to display incorrectly
* Individual iCal feeds can be immediately updated from the admin page
* The events list is now valid XHTML 1.0 Transitional
* The admin page now produces valid XHTML 1.0 Transitional
* CSS styles are in their own seperate file for easier modification
* More information is provided in a tooltip-like popup for each event
* Basic error checking is done when making changes on the admin page

= v0.2 - 29-Nov-2006 =

* Now runs on PHP 4 (used to have PHP 5 specific code)
* Can now be loaded as a sidebar widget

= v0.1 - 22-Nov-2006 =

* Initial release

