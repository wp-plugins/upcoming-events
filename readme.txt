==== Upcoming Events ====

Tags: calendar, events, ical, feed
Contributors: StarDestroyer, sjashe
Requires at least: 2.0
Tested up to: 2.3
Stable tag: 0.5

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

== Frequently Asked Questions ==

= I just updated to the current release from a version older than 0.5 and my Upcoming Events widget isn't showing up anymore. Why not? =

Starting in version 0.5 it's possible to have multiple widgets, each with a
different set of feeds shown. Unfortunately, this required adding some new
options to the database and changing the name that the widget manager sees
this widget with. So you'll need to deactivate the plugin, then reactivate it,
then visit the widget manager and add it back in. If you only want the default
settings like you had in 0.4 and below, that's all you need to do. If you want
to play with the new options and abilities, you can add multiple Upcoming
Events widgets and tweak the settings from each.

= When I add the Upcoming Events plugin instead of a list of events I get a nasty looking SQL error. What can I do? =

There was a bug in version 0.3 and below that caused the plugin to not
automatically add new feeds to the default sidebar. That was fixed for 0.4,
but if there were no feeds to show, there was still a nasty looking SQL error.
In 0.5 that was replaced with a more sensible error that says there's no feeds
to show. So make sure you either check one of the "Show in default sidebar"
boxes or, preferably, update to version 0.5 and check one of the default
sidebar options.

= Why aren't my events that occur at a specific time showing up? =

Versions before 0.5 running on PHP version 4 only showed all day events. A fix
went into 0.5 that allowed these events to show up. This also cleared up some
rare occations that showed up with a date of Dec. 31, 1969.

= Does this application support recurring events? =

Kind-of. I've yet to find a good PHP library that reads iCal files... so I had
to write my own. And I wouldn't call it good. It supports yearly recurring
events, but that's about it.

= How about timezones? =

No. I recently discovered this when I added a feed from Yahoo! for Detroit
Lions games. Hopefully I'll have it working soon.

== Release Notes ==

For a full revision log, see http://dev.wp-plugins.org/log/upcoming-events

= v0.5 - 26-Sep-2007 =

* Allow multiple widgets that can display different feeds
* Fix a PHP 4 compatibility issue
* Make the popups with event details fade in and out
* code_name can no longer start with an underscore

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

