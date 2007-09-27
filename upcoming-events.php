<?php
/*
Plugin Name: Upcoming Events
Plugin URI: http://jacob.steenhagen.us/blog/upcoming-events
Description: Can take multiple iCalendar feeds and aggregate them into a listing of upcoming events suitable for use in the sidebar. Goto <a href="options-general.php?page=ue1">Options &raquo; Upcoming Events</a> to define feeds.
Version: 0.5
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
$ue1_version = "0.5";
$ue1_url = "http://jacob.steenhagen.us/blog/upcoming-events";

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
	global $ue1_version, $ue1_url, $ue1_js_popup_start;
	$feeds = get_option("ue1_feeds");

	$exec_start = microtime_float();

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
	$num = (!empty($args[1])) ? $args[1] : get_option("ue1_show_num");
	$type = (!empty($args[2])) ? $args[2] : get_option("ue1_show_type");

	if (empty($feed_codes)) {
		echo "Error: No feeds selected for this block\n";
		return;
	}
	
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
		# We check for recurrence after an in process event because the
		# recurrence check changes the start_time and end_time which
		# would make it like any event that recures in the future is
		# still in progress.
		if ( $e->recurs ) {
			$r_next = $e->next_recurrence();
			if ( isset($r_next) ) {
				# We found a next recurrence. Save this in a
				# seperate array that will be merged back in
				array_push($saved_events, $e);
			}
		}
	}	
	$events = array_merge($saved_events, $events);
	# We probably messed up the sort order between events still inprogress
	# and recurring events. So resort it.
	usort($events, "sort_event_date");

	switch (strtolower($type)) {
	case "events":
		$events = array_splice($events, 0, $num);
		break;
	case "days":
	case "weeks":
		for ($i = 0; $i < count($events); $i++) {
			if( strtotime("$num $type") < $events[$i]->start_time ) {
				break;
			}
		}
		$events = array_splice($events, 0, $i);
		break;
	}

	static $popup_html = array("empty");
	$prev_date = "";
	echo "<ul>\n <li style='display:none;'>\n  <ul>\n   <li> </li>\n";
	static $i = 1;
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
			$popup .= '<div class="ue1-popup-date">';
			$popup .= date("F j, Y", $e->start_time);
			$popup .= '</div>';
			if (! $e->all_day) {
				$popup .= '<div class="ue1-popup-time">';
				$popup .= date("g:i", $e->start_time);
				$popup .= " - ";
				$popup .= date("g:i", $e->end_time);
				$popup .= '</div>';
			}
		} else {
			if ( date("n", $e->start_time) == date("n", $e->end_time) ) {
				# At least it's all in the same month!
				$popup .= '<div class="ue1-popup-date">';
				$popup .= date("F j", $e->start_time);
				$popup .= " - ";
				$popup .= date("j, Y", strtotime($e->end_date));
				$popup .= '</div>';
			} elseif ( date("Y", $e->start_time) == date("Y", $e->end_time) ) {
				# Well, the same year is good, right?
				$popup .= '<div class="ue1-popup-date">';
				$popup .= date("M j", $e->start_time);
				$popup .= " - ";
				$popup .= date("M j, Y", strtotime($e->end_date));
				$popup .= '</div>';
			} else {
				# Not the same month or year...
				$popup .= '<div class="ue1-popup-date">';
				$popup .= date("M j, Y", $e->start_time);
				$popup .= " - ";
				$popup .= date("M j, Y", strtotime($e->end_date));
				$popup .= '</div>';
			}
		}
		$popup .= '<div class="ue1-popup-summary">';
		$popup .= htmlentities($e->summary);
		$popup .= '</div>';
		if ( $e->location ) {
			$popup .= '<div class="ue1-popup-location">';
			$popup .= htmlentities($e->location);
			$popup .= '</div>';
		}
		if ( $e->desc ) {
			$popup .= '<div class="ue1-popup-desc">';
			$popup .= htmlentities($e->desc);
			$popup .= '</div>';
		}
		if ( $e->recurs ) {
			# This needs to be much more complicated once r_byday
			# support is figured out
			$popup .= '<div class="ue1-popup-recur">';
			$popup .= '<div class="ue1-popup-recur-every">';
			$popup .= 'Recurs every';
			if ( $e->r_interval > 1 ) {
				$popup .= " " . $e->r_freq;
			}
			switch ($e->r_freq) {
			case "yearly":
				$popup .= " year";
				break;
			}
			if ($e->r_interval > 1) {
				$popup .= "s";
			}
			$popup .= '</div>';
			$r_next = $e->next_recurrence($e->start_time);
			if ( isset($r_next) ) {
				$popup .= '<div class="ue1-popup-recur-next">';
				$popup .= 'Next recurrence: ';
				$popup .= date("j-M-Y", $e->start_time);
				$popup .= '</div>';
			}
			$popup .= '</div>';
		}
		$popup .= '</div>';
		$popup = preg_replace("/\n/", "<br />", $popup);
		$popup = strip_newlines($popup);
		array_push($popup_html, $popup);
		$i++;
	}
	echo "  </ul>\n </li>\n</ul>\n";

	if (empty($ue1_js_popup_start)) {
		// We haven't done any JS yet for UE1, so output these libraries
		$ue1_js_popup_start = 1;
?>
<script type="text/javascript">
<!--
var ue1_curr_popup = "";
var ue1_curr_popup_i = 0;
var hovering_popup = false;
var create_timer = "";
var destroy_timer = "";
var fade_timer = new Array();
var fade_speed = 3; // How many miliseconds between each percentage of fade

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
	var create_delay = 500;
	if (ue1_curr_popup) {
		if (destroy_timer) {
			clearTimeout(destroy_timer);
		}
		destroy_timer = setTimeout("ue1_destroy("+ue1_curr_popup_i+")", 500);
		create_delay += 600;
	}
	if (create_timer) {
		clearTimeout(create_timer);
		create_timer = "";
	}
	create_timer = setTimeout("ue1_create("+i+")", create_delay);
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
		ue1_add_event(ue1_curr_popup, "mouseover", ue1_popup_hover);
		ue1_add_event(ue1_curr_popup, "mouseout", ue1_popup_unhover);
	}

	// Make this fully transpartent so we can fade in
	ue1_curr_popup.style.opacity = 0;
	ue1_curr_popup.style.MozOpacity = 0;
	ue1_curr_popup.style.filter = "alpha(opacity=0)";

	ue1_event.parentNode.appendChild(ue1_curr_popup);

	// Set up the fade....
	for (var f = 1; f <= 100; f++) {
		fade_timer[f] = setTimeout("ue1_fade("+f+", true)", (f * fade_speed));
	}

}

function ue1_fade(f, fade_in) {
	var o = ue1_curr_popup;
	if (o) {
		var opacity = fade_in ? f : (101 - f);
		o.style.opacity = opacity  / 100;
		o.style.MozOpacity = opacity / 100;
		o.style.filter = "alpha(opacity="+opacity+")";
		clearTimeout(fade_timer[f]);
		fade_timer[f] = "";
	} else {
		ue1_stop_fade();
	}
}

function ue1_stop_fade() {
	for (var f = 100; f >= 1; f--) {
		if (!fade_timer[f]) {
			// We're done
			return;
		}
		clearTimeout(fade_timer[f]);
		fade_timer[f] = "";
	}
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
	// Set up the fade....
	ue1_stop_fade();
	for (var f = 1; f <= 100; f++) {
		fade_timer[f] = setTimeout("ue1_fade("+f+", false)", (f * fade_speed));
	}
	setTimeout("ue1_remove_popup("+i+")", (101 * fade_speed));
}

function ue1_remove_popup(i) {
	ue1_event = document.getElementById("ue1-"+i);
	if (ue1_curr_popup) {
		ue1_curr_popup.parentNode.removeChild(ue1_curr_popup);
	}
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

function ue1_add_event(o, type, fn) {
	if (o.addEventListener) {
		o.addEventListener(type, fn, false);
		return true;
	} else if (o.attachEvent) {
		return o.attachEvent("on"+type, fn);
	} else {
		return false;
	}
}

var popup = new Array();
//-->
</script>
<?php
	}
	echo "<script type='text/javascript'>\n";
	echo "<!--\n";
	for ($i = $ue1_js_popup_start; $i < count($popup_html); $i++) {
		echo "popup[$i] = '" . addslashes($popup_html[$i]) . "';\n";
	}
	$ue1_js_popup_start = $i;
?>
//-->
</script>

<?php
	if ( get_option("ue1_show_powered") ) {
		echo '<ul style="list-style-type:none;list-style-image:none;backgound:none;"><li style="background:none;"><small>Powered by ';
		echo "<a href='$ue1_url'>Upcoming Events v$ue1_version</a></small></li></ul>\n";
	}

	$exec_end = microtime_float();
	$exec_time = round($exec_end - $exec_start, 3);
	echo "<!-- Upcoming Event Sidebar v$ue1_version - $ue1_url -->\n";
	echo "<!-- Generated in $exec_time seconds -->\n";
}

