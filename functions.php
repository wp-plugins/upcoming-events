<?php
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


function ue1_retreive_ics($feed_codes) {
	global $wpdb;
	$feeds = get_option("ue1_feeds");
	$ret = array();

	$table = $wpdb->prefix . "ue1_cache";
	$sql = "SELECT code_name, last_update, ics_data FROM $table WHERE ";
	$arr_where = array();
	foreach ($feed_codes as $f) {
		array_push($arr_where, "code_name = '" . $wpdb->escape($f) . "'");
	}
        $sql .= implode(" OR ", $arr_where);
	$feeds_db = $wpdb->get_results($sql);
	foreach($feeds_db as $feed_db) {
		foreach ($feeds as $feed) {
			if ($feed_db->code_name == $feed["code_name"]) {
				$update_freq = ($feed["update_freq"] == "Default") ? get_option("ue1_update_freq") : $feed["update_freq"];
				if ( time() > strtotime($update_freq, strtotime($feed_db->last_update)) ) {
					$ret[$feed["code_name"]] = ue1_update_ics($feed["code_name"]);
				} else {
					$ret[$feed["code_name"]] = $feed_db->ics_data;
				}
				break;
			}
		}
	}
	return $ret;
}

function ue1_update_ics($feed_code) {
	global $wpdb;
	$table = $wpdb->prefix . "ue1_cache";
	$feeds = get_option("ue1_feeds");

	foreach ($feeds as $feed) {
	    /* Only Update if feed returns value, else leave old data */
		if ( $feed_code == $feed["code_name"] &&
			 $ics = file_get_contents($feed["url"])) {
			    $wpdb->query("UPDATE $table SET
					last_update = now(),
					ics_data = '" . $wpdb->escape($ics) . "'
					WHERE code_name = '" . $wpdb->escape($feed_code) . "'");
			return $ics;
		}
	}
	return false;
}

function sort_event_date($a, $b) {
	if ( $a->start_time == $b->start_time ) {
		return 0;
	}
	return ($a->start_time < $b->start_time) ? -1 : 1;
}

function microtime_float() {
	list ($msec, $sec) = explode(" ", microtime());
	return ((float)$sec + (float)$msec);
}

/* need to strip out cr/lf from some fields */
function strip_newlines($str) {
	return str_replace(array("\r\n","\r","\n") , ' ' , $str);
}
?>
