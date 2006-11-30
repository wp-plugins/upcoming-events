<?php
/*
Plugin Name: Upcoming Events
Plugin URI: http://jacob.steenhagen.us/blog/?cat=14
Description: Can take multiple iCalendar feeds and aggregate them into a listing of upcoming events suitable for use in the sidebar. Goto <a href="options-general.php?page=ue1">Options &raquo; Upcoming Events</a> to define feeds.
Version: 0.2+
Author: Jacob Steenhagen
Author URI: http://jacob.steenhagen.us
*/

/*  Copyright 2006  Jacob Steenhagen  (email : jacob@steenhagen.us)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

global $ue1_version, $ue1_url;
$ue1_version = "0.2+";
$ue1_url = "http://jacob.steenhagen.us/blog/?cat=14";

require_once(dirname(__FILE__) . "/admin.php");
require_once(dirname(__FILE__) . "/ical.class.php");
require_once(dirname(__FILE__) . "/functions.php");

function ue1_get_events() {
	global $table_prefix, $wpdb, $ue1_version, $ue1_url;
	$feeds = get_option("ue1_feeds");

	$args = func_get_args();
	$feed_codes = array();
	if (!isset($args[0]) || !is_array($args[0])) {
		foreach ($feeds as $feed) {
			if ( $feed["show"] ) {
				array_push($feed_codes, $feed["code_name"]);
			}
		}
	} else {
		$feed_codes = $args[0];
	}
	$num = (isset($args[1])) ? $args[1] : get_option("ue1_show_num");
	$type = (isset($args[2])) ? $args[2] : get_option("ue1_show_type");
	
	$ics = ue1_retreive_ics($feed_codes);

	$events = array();
	foreach ($ics as $key => $val) {
		$ics = new ical;
		$ics->parse_ics($val);
		foreach ($ics->events as $e) {
			array_push($events, $e);
		}
	}
	# Now that all the events are in one array, sort it by date
	usort($events, "sort_event_date");

	# Shift out any event that's already transpired
	$saved_events = array();
	while($e = array_shift($events)) {
		if ( time() <= $e->start_time ) {
			# We're sorted by date, so once we get to the
			# present stop taking things and and put this
			# one back in.
			array_unshift($events, $e);
			break;
		}
		if ( time() <= $e->end_time ) {
			# The event hasn't ended yet, so treat it as
			# if it's still upcoming. This will result in
			# a date from the past in the upcoming events,
			# but it's better than mangling it.
			array_push($saved_events, $e);
		}
	}	
	$events = array_merge($saved_events, $events);
	# We could have messed up the sorting if a multi-day event is still
	# in progress... So resort the events.
	usort($events, "sort_event_date");

	switch (strtolower($type)) {
	case "events":
		$events = array_splice($events, 0, $num);
		break;
	case "days":
	case "weeks":
		for ($i = 0; $i < count($events) - 1; $i++) {
			if( strtotime("$num $type") < $events[$i]->start_time ) {
				break;
			}
		}
		$events = array_splice($events, 0, $i);
		break;
	}

	$prev_date = "";
	echo "<ul>\n <li style='display:none;'>\n  <ul>\n";
	foreach ($events as $e) {
		if ( $prev_date != $e->start_date ) {
			$prev_date = $e->start_date;
			echo "  </ul>\n </li>\n";
			echo " <li>" . date("D, M j", $e->start_time) . "\n";
			echo "  <ul>\n";
		}
		$ts = "";
		if ( ! $e->all_day ) {
			$ds = "ga";
			if ( date("i", $e->start_time) != 0 ) {
				$ds = "g:ia";
			}
			$ts = date($ds, $e->start_time);
			$ts = rtrim($ts, "m");
			$ts .= " - ";
		}
		echo "  <li>$ts" . $e->summary . "</li>\n";
		echo "<!--\n";
		echo $e->summary . "\n";
		echo $e->start_date . "\n";
		echo $e->end_date . "\n";
		echo $e->desc . "\n";
		echo "-->\n";
	}
	echo "  </ul>\n </li>\n</ul>\n";

	if ( get_option("ue1_show_powered") ) {
		echo "<p><small>Powered by ";
		echo "<a href='$ue1_url'>Upcoming Events v$ue1_version</a></small></p>\n";
	}
}

