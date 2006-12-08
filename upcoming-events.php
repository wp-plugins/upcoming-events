<?php
/*
Plugin Name: Upcoming Events
Plugin URI: http://jacob.steenhagen.us/blog/?cat=14
Description: Can take multiple iCalendar feeds and aggregate them into a listing of upcoming events suitable for use in the sidebar. Goto <a href="options-general.php?page=ue1">Options &raquo; Upcoming Events</a> to define feeds.
Version: 0.3+
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
$ue1_version = "0.3+";
$ue1_url = "http://jacob.steenhagen.us/blog/?cat=14";

require_once(dirname(__FILE__) . "/admin.php");
require_once(dirname(__FILE__) . "/ical.class.php");
require_once(dirname(__FILE__) . "/functions.php");

add_action('wp_head', 'ue1_css');

function ue1_css() {
	echo "<style type=\"text/css\">\n";
	echo "@import url(" . get_bloginfo('url')  . "/wp-content/plugins/upcoming-events/events.css.php);\n";
	echo "</style>\n";
}

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

	$popup_html = array("empty");
	$prev_date = "";
	echo "<ul>\n <li style='display:none;'>\n  <ul>\n   <li> </li>\n";
	$i = 1;
	foreach ($events as $e) {
		if ( $prev_date != $e->start_date ) {
			$prev_date = $e->start_date;
			echo "  </ul>\n </li>\n";
			echo " <li><span class='ue1_date'>" . date("D, M j", $e->start_time) . "</span>\n";
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
		echo "   <li id='ue1-$i' onmouseover='ue1_show($i)' onmouseout='ue1_hide($i)'>$ts" . htmlentities($e->summary) . "</li>\n";
		$popup = '<div id="ue1-popup-'.$i.'" class="ue1-popup">';
		if ( $e->same_day ) {
			$popup .= '<h3 class="ue1-popup-date">';
			$popup .= date("F j, Y", $e->start_time);
			$popup .= '</h3>';
			if (! $e->all_day) {
				$popup .= '<h4 class="ue1-popup-time">';
				$popup .= date("g:i", $e->start_time);
				$popup .= " - ";
				$popup .= date("g:i", $e->end_time);
				$popup .= '</h4>';
			}
		} else {
			if ( date("n", $e->start_time) == date("n", $e->end_time) ) {
				# At least it's all in the same month!
				$popup .= '<h3 class="ue1-popup-date">';
				$popup .= date("F j", $e->start_time);
				$popup .= " - ";
				$popup .= date("j, Y", strtotime($e->end_date));
				$popup .= '</h3>';
			} elseif ( date("Y", $e->start_time) == date("Y", $e->end_time) ) {
				# Well, the same year is good, right?
				$popup .= '<h3 class="ue1-popup-date">';
				$popup .= date("M j", $e->start_time);
				$popup .= " - ";
				$popup .= date("M j, Y", strtotime($e->end_date));
				$popup .= '</h3>';
			} else {
				# Not the same month or year...
				$popup .= '<h3 class="ue1-popup-date">';
				$popup .= date("M j, Y", $e->start_time);
				$popup .= " - ";
				$popup .= date("M j, Y", strtotime($e->end_date));
				$popup .= '</h3>';
			}
		}
		$popup .= '<div class="ue1-popup-summary">';
		$popup .= $e->summary;
		$popup .= '</div>';
		if ( $e->location ) {
			$popup .= '<div class="ue1-popup-location">';
			$popup .= $e->location;
			$popup .= '</div>';
		}
		if ( $e->desc ) {
			$popup .= '<div class="ue1-popup-desc">';
			$popup .= $e->desc;
			$popup .= '</div>';
		}
		$popup .= '</div>';
		array_push($popup_html, $popup);
		$i++;
	}
	echo "  </ul>\n </li>\n</ul>\n";

?>
<script type="text/javascript">
<!--
var ue1_curr_popup = "";
var ue1_curr_popup_i = 0;
var hovering_popup = false;
var create_timer = "";
var destroy_timer = "";

function ue1_show(i) {
	if (! document.getElementById ) {
		// If we don't support DOM, don't try to run
		return;
	}
	if (hovering_popup) { return; }
	if (ue1_curr_popup_i == i) {
		if (destroy_timer) {
			clearTimeout(destroy_timer);
			destroy_timer = "";
		}
		return;
	}
	if (ue1_curr_popup) {
		if (destroy_timer) {
			clearTimeout(destroy_timer);
		}
		destroy_timer = setTimeout("ue1_destroy("+ue1_curr_popup_i+")", 500);
	}
	if (create_timer) {
		clearTimeout(create_timer);
		create_timer = "";
	}
	create_timer = setTimeout("ue1_create("+i+")", 1000);
}

function ue1_create(i) {
	ue1_event = document.getElementById("ue1-"+i);

	ue1_curr_popup_i = i;
	ue1_curr_popup = document.createElement("div");
	ue1_curr_popup.innerHTML = popup[i];
	ue1_curr_popup.style.position = 'absolute';
	if (ue1_curr_popup.addEventListener) {
		// Unfortunately, not everybody supports this method. If they
		// don't, then the popup just disappears sooner
		ue1_curr_popup.addEventListener("mouseover", ue1_popup_hover, false);
		ue1_curr_popup.addEventListener("mouseout", ue1_popup_unhover, false);
	}

	ue1_event.appendChild(ue1_curr_popup);
}

function ue1_hide(i) {
	if (! document.getElementById ) {
		// If we don't support DOM, don't try to run
		return;
	}
	if (hovering_popup) { return; }
        if (create_timer) {
                clearTimeout(create_timer);
                create_timer = "";
        }
	if (ue1_curr_popup) {
		destroy_timer = setTimeout("ue1_destroy("+i+")", 800);
	}
}

function ue1_destroy(i) {
	ue1_event = document.getElementById("ue1-"+i);
	ue1_event.removeChild(ue1_curr_popup);
	ue1_curr_popup = "";
	ue1_curr_popup_i = 0;
	if (destroy_timer) {
		clearTimeout(destroy_timer);
		destroy_timer = "";
	}
	hovering_popup = false; // There's no popup to hover
}

function ue1_popup_hover() {
	hovering_popup = true;
	if (destroy_timer) {
		clearTimeout(destroy_timer);
		destroy_timer = "";
	}
}

function ue1_popup_unhover() {
	hovering_popup = false;
}

var popup = new Array();
<?php
	for ($i = 1; $i < count($popup_html); $i++) {
		echo "popup[$i] = '" . addslashes($popup_html[$i]) . "';\n";
	}
?>
//-->
</script>

<?php
	if ( get_option("ue1_show_powered") ) {
		echo '<ul style="list-style-type:none;list-style-image:none;"><li><small>Powered by ';
		echo "<a href='$ue1_url'>Upcoming Events v$ue1_version</a></small></li></ul>\n";
	}
}

