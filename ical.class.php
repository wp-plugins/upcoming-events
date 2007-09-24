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


class ical {
	var $prodid;
        var $prodver;
        var $calscale;
	var $method;
	var $events = array();

	var $lines;

	function parse_ics($file) {
		$this->lines = explode("\n", $file);

		while($line = array_shift($this->lines)) {
			switch ( rtrim(strtolower($line)) ) {
			case "begin:vcalendar":
				ical::parse_vcal();
				break;
			case "begin:vtimezone":
				ical::parse_tzone();
				break;
			case "begin:vevent":
				ical::parse_event();
				break;
			default:
			}
		}
		# The ICS file has been fully parsed. Now let's sort the events
		usort($this->events, Array("ical", "sort_date"));
	}

	function parse_vcal() {
		while($line = array_shift($this->lines)) {
			if (! strpos($line, ":") ) { continue; }
			list($p, $v) = explode(":", rtrim($line));
			switch ( strtolower($p) ) {
			case "prodid":
				$this->prodid = $v;
				break;
			case "version":
				$this->prodver = $v;
				break;
			case "calscale":
				$this->calscale = $v;
				break;
			case "method":
				$this->method = $v;
				break;
			case "begin":
				array_unshift($this->lines, $line);
				break 2;
			}
		}
	}

	function parse_tzone() {
		# TODO: Deal with the timezone section... maybe
		while($line = array_shift($this->lines)) {
			list($p, $v) = explode(":", rtrim($line));
			switch ( strtolower($p) ) {
			case "end":
				if ( strtolower($v) == "vtimezone" ) {
					break 2;
				}
			}
		}
	}

	function parse_event() {
		$event_data = array();
		# Gather data to pass to the event parsing class
		while($line = array_shift($this->lines)) {
			switch ( rtrim(strtolower($line)) ) {
			# All we're really looking for is "END:VEVENT"
			case "end:vevent":
				$event = new ical_event();
				$event->parse($event_data);
				array_push($this->events, $event);
				break 2;
			# Gather everything else into the $event_data array
			default:
				if ( preg_match("/^\s/", $line) ) {
					$i = count($event_data) - 1;
					$event_data[$i] .= trim($line);
				} else {
					array_push($event_data, rtrim($line));
				}
			}
		}
	}

	function sort_date($a, $b) {
		if ( $a->start_time == $b->start_time ) {
			return 0;
		}
		return ($a->start_time < $b->start_time) ? -1 : 1;
	}
}

class ical_event {
	var $summary;
	var $location;
	var $desc;
	var $start_tz;
	var $start_time;
	var $start_date;
	var $end_tz;
	var $end_time;
	var $end_date;
	var $all_day;
	var $same_day;		// Does this event start and end on one day?
	var $recurs;		// Does this event have recurrence rules?
	var $r_freq;
	var $r_interval;
	var $r_until;
	var $r_bymonth;
	var $r_byday;

	function parse($data) {
		while($line = array_shift($data)) {
			if ( preg_match("/^summary:(.*)$/i", $line, $m) ) {
				$this->summary = stripslashes($m[1]);
			} elseif ( preg_match("/^location:(.*)$/i", $line, $m) ) {
				$this->location = stripslashes($m[1]);
			} elseif ( preg_match("/^description:(.*)$/i", $line, $m)) {
				$tmp = preg_replace("/\\\\n/", "\n", $m[1]);
				$this->desc = stripslashes($tmp);
			} elseif ( preg_match("/^dtstart;tzid=(.+):([0-9]{4})([0-9]{2})([0-9]{2})t([0-9]{2})([0-9]{2})([0-9]{2})$/i", $line, $m) ) {
				$this->start_tz = $m[1];
				$tmp = mktime($m[5], $m[6], $m[7], $m[3], $m[4], $m[2]);
				$this->start_time = $tmp;
				$this->start_date = date("Ymd", $tmp);
				$this->all_day = false;
			} elseif ( preg_match("/^dtend;tzid=(.+):([0-9]{4})([0-9]{2})([0-9]{2})t([0-9]{2})([0-9]{2})([0-9]{2})$/i", $line, $m) ) {
				$this->end_tz = $m[1];
				$tmp = mktime($m[5], $m[6], $m[7], $m[3], $m[4], $m[2]);
				$this->end_time = $tmp;
				$this->end_date = date("Ymd", $tmp);
				$this->all_day=false;
			} elseif ( preg_match("/^dtstart;value=date:(.+)$/i", $line, $m) ) {
				$this->start_time = strtotime($m[1]);
				$this->start_date = $m[1];
				$this->all_day=true;
			} elseif ( preg_match("/^dtend;value=date:(.+)$/i", $line, $m) ) {
				$this->end_time = strtotime($m[1]);
				$this->end_date = date("Ymd", strtotime($m[1] . " -1 day"));
				$this->all_day=true;
			} elseif ( preg_match("/^duration:(.+)$/i", $line, $m) ) {
				$dur = array();
				if ( preg_match("/T(\d+)H/i", $m[1], $p) ) {
					array_push($dur, "+".$p[1]." hours");
				}
				$this->end_time = strtotime(implode(" ", $dur), $this->start_time);
				$this->end_date = date("Ymd", $this->end_time);
			} elseif (preg_match("/^rrule:(.+)$/i", $line, $m) ) {
				$this->recurs = true;
				$rinfo = explode(";", $m[1]);
				foreach ($rinfo as $info) {
					if ( preg_match("/^freq=(.+)$/i", $info, $m) ) {
						$this->r_freq = strtolower($m[1]);
					} elseif (preg_match("/^interval=(\\d+)$/i", $info, $m) ) {
						$this->r_interval = $m[1];
					} elseif (preg_match("/^until=(\\d+)$/i", $info, $m) ) {
						$this->r_until = strtotime($m[1]);
					} elseif (preg_match("/^bymonth=(\\d+)$/i", $info, $m) ) {
						$this->r_bymonth = $m[1];
					} elseif (preg_match("/^byday=(.+)$/i", $info, $m) ) {
						$this->r_byday = $m[1];
					}
				}
				# Unix can't use a timestamp past 2038
				if ( empty($this->r_until) ) {
					$this->r_until = strtotime("20380118");
				}
				# If we never defined an interval, make it '1'
				if ( empty($this->r_interval) ) {
					$this->r_interval = 1;
				}
			}
		}
		if ( $this->start_date == $this->end_date ) {
			$this->same_day = true;
		}
	}

	function next_recurrence ( $after = "") {
		if ( empty($after) ) {
			$after = time();
		}

		if ( $this->r_until < $after ) {
			# Event is done. No more recurrences.
			return null;
		}
		if ( isset($this->r_byday) ) {
			$save_start_time = $this->start_time;
			switch ($this->r_freq) {
			case "yearly":
				# If we're doing this "BYDAY", which we are
				# because we're here, we should also have
				# something that tells us what month we're in,
				# such as "BYMONTH"
				if ( empty($this->r_bymonth) ) {
					# I think this is an error condition
					return null;
				}
				while (1) {
					# Get year for this recurrence
					$e_year = date("Y", $this->start_time);
					$e_month = $this->r_bymonth;
					if ( strlen($e_month) == 1 ) {
						$e_month = "0$e_month";
					}
					if ( preg_match("/^(\\d)(.+)$/", $this->r_byday, $m) ) {
						# We're dealing with something
						# like 1st sunday, etc. Start
						# at the 1st of the month
						$this->start_time = strtotime($e_year.$e_month."01");
						# We're going to step forward
						# a little
						$i = $m[1] - 1;
						$d = $m[2]; # Day of week
						if (! empty($i)) {
							$this->start_time = strtotime("$i week", $this->start_time);
						}
						$dow = "";
						switch (strtolower($d)) {
							case "su":
								$dow = "Sunday";
								break;
							case "mo":
								$dow = "Monday";
								break;
							case "tu":
								$dow = "Tuesday";
								break;
							case "we":
								$dow = "Wednesday";
								break;
							case "th":
								$dow = "Thursday";
								break;
							case "fr":
								$dow = "Friday";
								break;
							case "sa":
								$dow = "Saturday";
								break;
						}
						if ( date("l", $this->start_time) != $dow ) {
							# This isn't the day of
							# the week in question.
							# Forward to it.
							$this->start_time = strtotime("Next $dow", $this->start_time);
						}
						if ($this->start_time > $after) {
							# This is it
							break;
						} else {
							# Advance int years and
							# try again
							$this->start_time = strtotime($this->r_interval." years", $this->start_time);
						}
					} else {
						# Negative day. Don't support yet.
						return null;
					}
				}
				break;
			default:
				# We don't handle this yet
				return null;
			}
			$time_diff = $this->start_time - $save_start_time;
			$this->end_time = $this->end_time + $time_diff;
		# End of $this->r_byday
		} else {
			# We haven't been told specifics of what day this
			# should be on, so simply add time to the start_time
			# until we make it past $after.
			while ($this->start_time <= $after) {
				# Figure out what we're adding (years, etc).
				$add = "";
				switch ($this->r_freq) {
				case "yearly":
					$s_add = " year";
					break;
				default:
					# We don't handle this yet
					return null;
				}
				# Add the same amount of time to the start and
				# end of the event.
				$this->start_time = strtotime($this->r_interval . $s_add, $this->start_time);
				$this->end_time = strtotime($this->r_interval . $s_add, $this->end_time);
			}
		}
		# We've figured out when the next recurrance would be. Let's
		# make sure it makes sense, set the *_date variables and return
		# the next recurrance.
                if ( $this->r_until < $this->start_time ) {
			# What would be the next scheduled recurrence is after
			# the last date of this event
                        return null;
                }

		$this->start_date = date("Ymd", $this->start_time);
		$this->end_date = date("Ymd", $this->end_time);
		return date("Ymd", $this->start_time);
	}
}

?>
