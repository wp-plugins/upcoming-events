<?php

class ical {
	public $prodid;
        public $prodver;
        public $calscale;
	public $method;
	public $events = array();

	private $lines;

	public function parse_ics($file) {
		$this->lines = explode("\n", $file);

		while($line = array_shift($this->lines)) {
			switch ( rtrim(strtolower($line)) ) {
			case "begin:vcalendar":
				self::parse_vcal();
				break;
			case "begin:vtimezone":
				self::parse_tzone();
				break;
			case "begin:vevent":
				self::parse_event();
				break;
			default:
			}
		}
		# The ICS file has been fully parsed. Now let's sort the events
		usort($this->events, Array("self", "sort_date"));
	}

	function parse_vcal() {
		while($line = array_shift($this->lines)) {
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
	public $summary;
	public $location;
	public $desc;
	public $start_tz;
	public $start_time;
	public $start_date;
	public $end_tz;
	public $end_time;
	public $end_date;
	public $all_day;

	function parse($data) {
		while($line = array_shift($data)) {
			if ( preg_match("/^summary:(.*)$/i", $line, $m) ) {
				$this->summary = stripslashes($m[1]);
			} elseif ( preg_match("/^location:(.*)$/i", $line, $m) ) {
				$this->location = stripslashes($m[1]);
			} elseif ( preg_match("/^description:(.*)$/i", $line, $m)) {
				$tmp = preg_replace("/\\\\n/", "\n", $m[1]);
				$this->desc = stripslashes($tmp);
			} elseif ( preg_match("/^dtstart;tzid=(.+):(.+)$/i", $line, $m) ) {
				$this->start_tz = $m[1];
				$this->start_time = strtotime($m[2]);
				$this->start_date = date("Ymd", strtotime($m[2]));
				$this->all_day = false;
			} elseif ( preg_match("/^dtend;tzid=(.+):(.+)$/i", $line, $m) ) {
				$this->end_tz = $m[1];
				$this->end_time = strtotime($m[2]);
				$this->end_date = date("Ymd", strtotime($m[2]));
				$this->all_day=false;
			} elseif ( preg_match("/^dtstart;value=date:(.+)$/i", $line, $m) ) {
				$this->start_time = strtotime($m[1]);
				$this->start_date = $m[1];
				$this->all_day=true;
			} elseif ( preg_match("/^dtend;value=date:(.+)$/i", $line, $m) ) {
				$this->end_time = strtotime($m[1]);
				$this->end_date = $m[1];
				$this->all_day=true;
			}
		}
	}
}

?>
