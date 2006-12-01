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


function ue1_install() {
	global $table_prefix, $wpdb;

	$table_name = $table_prefix . "ue1_cache";

	if ( $wpdb->get_var("show tables like '$table_name'") != $table_name ) {
		require_once(ABSPATH . "wp-admin/upgrade-functions.php");

		$sql = "CREATE TABLE $table_name (
				code_name VARCHAR(30) NOT NULL,
				last_update DATETIME,
				ics_data MEDIUMTEXT,

				PRIMARY KEY (code_name)
			)";
		dbDelta($sql);
	}
	add_option("ue1_show_powered", true, "Wether or not to show the 'Powered By' line in the sidebar");
	add_option("ue1_update_freq", "1 Week", "Default update frequency for Upcoming Events plugin");
	add_option("ue1_show_num", "10", "Number of (Days|Events) to show");
	add_option("ue1_show_type", "Events", "What ue1_show_num refers to");
	add_option("ue1_feeds", array( array("url"=>"")));

}

function ue1_options() {
	if ( function_exists('add_options_page') ) {
		add_options_page(
			'Upcoming Events Options',
			'Upcoming Events',
			6,
			'ue1',
			'ue1_options_subpanel');
	}
}

function ue1_widget($args) {
	extract($args);
	echo $before_widget;
	echo $before_title . 'Upcoming Events' . $after_title;
	ue1_get_events();
	echo $after_widget;
}

function ue1_update_options(&$feeds) {
	global $table_prefix, $wpdb;

	$validation_errors = array();

	$show = (isset($_POST['ue1_show_powered'])) ? true : false;
	update_option("ue1_show_powered", $show);
	update_option("ue1_update_freq", $_POST['ue1_update_freq']);
	update_option("ue1_show_num", $_POST['ue1_show_num']);
	update_option("ue1_show_type", $_POST['ue1_show_type']);
	$c = 0;
	while(1) {
		$c++;
		$f = 'ue1_feed'.$c;
		if ( isset($_POST[$f.'_update_freq']) ) {
			array_push($feeds, array(
				"display" => $_POST[$f.'_display'],
				"code_name" => $_POST[$f.'_code_name'],
				"url" => $_POST[$f.'_url'],
				"update_freq" => $_POST[$f.'_update_freq'],
				"show" => isset($_POST[$f.'_show']) ? true : false));
			$t = $table_prefix . "ue1_cache";
			$wpdb->query("INSERT IGNORE $t (code_name)
				VALUES ('" . $wpdb->escape($_POST[$f.'_code_name']) . "')");
		} else {
			break;
		}
	}
	update_option("ue1_feeds", $feeds);
	?>
<div class="updated">Upcoming Events Options have been updated.</div>
	<?php
	return $validation_errors;
}

function ue1_options_subpanel() {
	global $table_prefix, $wpdb;

	$feeds = array();
	$validation_errors = array();

	if ( isset($_POST['ue1_update']) || isset($_POST['ue1_add_feed']) ) {
		$validation_errors = ue1_update_options($feeds);
	}

	if ( isset($_POST['ue1_add_feed']) ) {
		array_push($feeds, array("update_freq"=>"Default"));
		echo '<div class="updated">Options have been added for an <a href="#feed' . count($feeds) . '">additional feed below</a></div>';
	}

	if ( isset($_POST['ue1_man_refresh']) ) {
		$validation_errors = ue1_update_options($feeds);
		$feeds = get_option("ue1_feeds");
		foreach ($feeds as $feed) {
			ue1_update_ics($feed["code_name"]);
		}
	?>
<div class="updated">The cached feeds have been updated</div>
	<?php
	}
	$c = 0;
	while (1) {
		$c++;
		$f = 'ue1_feed'.$c;
		if ( empty($_POST[$f."_update_freq"]) ) {
			# Update Freq is pretty much guarnteed to be set as
			# it's a select box
			break;
		}
		if ( isset($_POST[$f."_update"]) ) {
			$validation_errors = ue1_update_options($feeds);
			ue1_update_ics($_POST[$f."_code_name"]);
			echo '<div class="updated">Feed "<tt>'.$_POST[$f."_code_name"].'</tt>" has been updated</div>';
		}
	}
	?>
  <div class="wrap">
  <form method="post">
    <h2>Upcoming Events Options</h2>
    <table width="100%" cellspacing="2" cellpadding="5" class="editform"> 

      <tr valign="top"> 
       <th width="33%" scope="row">Show Powered By:</th> 
       <td>
         <?php $checked = (get_option("ue1_show_powered")) ? 'checked="checked"' : ''; ?>
         <input type="checkbox" name="ue1_show_powered" <?php echo $checked; ?> />
         <em>Uncheck this if you would rather not have the "Powered By" in the sidebar box. The author would appreciate it if this were left on, but understands people not wanting their UI cluttered</em>
      <tr valign="top"> 
       <th width="33%" scope="row">Default Update Frequency:</th> 
       <td>
         <?php ue1_echo_update_freq_select("ue1_update_freq", get_option("ue1_update_freq")); ?>
         <br />
           <em>The local cache of an iCal feed is refreshed if it is older than the update frequency. This frequency can be indvidually overridden by each feed.</em>
       </td>
      </tr>
      <tr valign="top"> 
       <th width="33%" scope="row">Amount to show:</th> 
       <td>
         Show <select name="ue1_show_num">
<?php
$def = get_option("ue1_show_num");
for ($i = 1; $i < 20; $i++) {
	$sel = ($i == $def) ? " selected" : "";
	echo "<option$sel>$i</option>\n";
}
?>
         </select> <select name="ue1_show_type">
           <option<?php echo (get_option("ue1_show_type") == "Events") ? " selected" : ""; ?>>Events</option>
           <option<?php echo (get_option("ue1_show_type") == "Days") ? " selected" : ""; ?>>Days</option>
           <option<?php echo (get_option("ue1_show_type") == "Weeks") ? " selected" : ""; ?>>Weeks</option>
         </select>
       </td>
      </tr>
      <tr valign="top"> 
       <th width="33%" scope="row">Manual Refresh:</th> 
       <td>
         <input type="submit" name="ue1_man_refresh" class="edit" value="Update all feeds now">
       </td>
      </tr>
    </table>
    <div class="submit">
      <input type="submit" name="ue1_update" value="Update Options &raquo;" />
    </div>

    <h3>iCal Feeds</h3>
    <em>Dispaly Name, Code Name, and Path are required for each feed. Each feed must have a unique Code Name</em>

<?php
if ( ! isset($feeds[0]["update_freq"]) ) {
	$feeds = get_option('ue1_feeds');
}

$c = 0;
foreach ($feeds as $feed) {
	$c++;
	echo "<a name='feed$c'></a>\n";
	echo '<h4 id="ue1_feed' . $c . '_lbl">' . htmlentities($feed["display"]) . "</h4>\n";
	?>
<table width="100%" cellspacing="2" cellpadding="5" class="editform">

<tr valign="top"> 
  <th width="33%" scope="row">Display Name:</th>
  <td>
    <input type="text" name="ue1_feed<?php echo $c; ?>_display" size="60" value="<?php echo htmlentities($feed["display"]); ?>">
    <br /><em>The Display Name is used any time a reference to this iCal feed 
    must be displayed. This includes here in the admin panel.</em>
  </td>
</tr>
<tr valign="top"> 
  <th width="33%" scope="row">Code Name:</th>
  <td>
    <input type="text" name="ue1_feed<?php echo $c; ?>_code_name" size="60" value="<?php echo htmlentities($feed["code_name"]); ?>">
    <br /><em>The Code Name can be used to selectively display iCal feeds
    in seperate sidebar boxes.</em>
  </td>
</tr>
<tr valign="top"> 
  <th width="33%" scope="row">Path:</th>
  <td>
    <input type="text" name="ue1_feed<?php echo $c; ?>_url" size="60" value="<?php echo htmlentities($feed["url"]); ?>">
    <br /><em>The path can either be a local ics file or a URL to a live ICS feed (such as Google Calendar or Apple's iCal)</em>
  </td>
</tr>
<tr valign="top">
  <th width="33%" scope="row">Update Frequency:</th>
  <td>
<?php ue1_echo_update_freq_select("ue1_feed" . $c . "_update_freq", $feed["update_freq"]); ?>
  </td>
</tr>
<tr valign="top">
  <th width="33%" scope="row">Show This Feed:</th>
  <td>
<?php $checked = ($feed["show"]) ? 'checked="checked"' : ''; ?>
    <input name="ue1_feed<?php echo $c; ?>_show" id="ue1_feed<?php echo $c; ?>_show" type="checkbox" value="1" <?php echo $checked; ?>/> <label for="ue1_feed<?php echo $c; ?>_show">Display in default sidebar</label>
  </td>
</tr>
<?php
	if (! empty($feed["code_name"]) ) {
?>
<tr valign="top">
  <th width="33%" scope="row">Feed Update:</th>
  <td>
    <input type="submit" name="ue1_feed<?php echo $c; ?>_update" value="Update Now">
    Last Updated:
<?php
        $table = $table_prefix . "ue1_cache";
        $sql = "SELECT last_update FROM $table WHERE code_name = '" . $wpdb->escape($feed["code_name"]) . "'";
        $last_update = $wpdb->get_var($sql);
	echo $last_update;
?>
  </td>
</tr>
<?php
	}
?>
</table>
<div class="submit">
  <input type="submit" name="ue1_update" value="Update Options &raquo;" />
</div>

<?php
}
?>

<div>
  <input type="submit" name="ue1_add_feed" value="Add another feed" />
</div>

  </form>

<h3>Sample Sidebar Code</h3>

<p>If you use the <a href="http://automattic.com/code/widgets/">WordPress Widget</a> plugin, you can add Upcoming Events to the sidebar using the Widget options on the Presentation tab. Currently this adds the same thing as the "Default settings" option below.<p>

<h4>Use default settings</h4>
<pre class="code">
&lt;li&gt;
  &lt;h2&gt;Upcoming Events&lt;h2&gt;
  &lt;?php ue1_get_events()?&gt;
&lt;/li&gt;
</pre>

<h4>Display a feed with the Code Name of <tt>feed3</tt></h4>
<pre class="code">
&lt;li&gt;
  &lt;h2&gt;Upcoming Events&lt;h2&gt;
  &lt;?php ue1_get_events(array("feed3"))?&gt;
&lt;/li&gt;
</pre>
<em>Note: This will ignore the "Show This Feed" option</em>

<h4>Display 5 aggregated events from <tt>feed2</tt> and <tt>feed3</tt></h4>
<pre class="code">
&lt;li&gt;
  &lt;h2&gt;Upcoming Events&lt;h2&gt;
  &lt;?php ue1_get_events(array("feed2", "feed3"), 5, "Events")?&gt;
&lt;/li&gt;
</pre>

<h4>Dispaly 6 weeks worth of events from default feeds</h4>
<pre class="code">
&lt;li&gt;
  &lt;h2&gt;Upcoming Events&lt;h2&gt;
  &lt;?php ue1_get_events(null, 6, "Weeks")?&gt;
&lt;/li&gt;
</pre>
<em>Note: This will use the "Show This Feed" option to determine which feeds to aggregate</em>

  </div>

<?php
}

function ue1_echo_update_freq_select($name, $def) {
	$options = array(
		"1 Hour",
		"2 Hours",
		"3 Hours",
		"4 Hours",
		"5 Hours",
		"6 Hours",
		"9 Hours",
		"12 Hours",
		"15 Hours",
		"18 Hours",
		"1 Day",
		"2 Days",
		"3 Days",
		"4 Days",
		"5 Days",
		"1 Week",
		"2 Weeks",
		"3 Weeks",
		"1 Month",
		"2 Months",
		"3 Months",
		"4 Months",
		"5 Months",
		"6 Months",
		"1 Year");
	echo "<select name='$name' id='$name'>\n";
	if ( preg_match("/_feed\\d/", $name) ) {
		array_unshift($options, "Default");
	}
	foreach ($options as $option) {
		$sel = ($option == $def) ? " selected" : "";
		echo "  <option$sel>$option</option>\n";
	}
	echo "</select>\n";
}

function ue1_widget_init() {
	if ( function_exists('register_sidebar_widget') ) {
		register_sidebar_widget('Upcoming Events', 'ue1_widget');
	}
}

add_action('admin_menu', 'ue1_options');
add_action('activate_upcoming-events/upcoming-events.php', 'ue1_install');
add_action('widgets_init', 'ue1_widget_init');

