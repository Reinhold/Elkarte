<?php

/**
 * This Plugin file contains all the functions that allow for ElkArte to interface
 * with Bad Behavior.  Bad Behavior is
 * Copyright (C) 2005,2006,2007,2008,2009,2010,2011,2012 Michael Hampton
 * License: LGPLv3
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

define('BB2_CWD', dirname(__FILE__));

// Calls inward to Bad Behavior itself.
require_once(BB2_CWD . '/bad-behavior/core.inc.php');

/**
 * Return current time in the format preferred by your database.
 *
 * @return string
 */
function bb2_db_date()
{
	return time();
}

/**
 * Return affected rows from most recent query.
 *
 * @return int
 */
function bb2_db_affected_rows()
{
	$db = database();

	return $db->affected_rows();
}

/**
 * Escape a string for database usage
 *
 * @param string $string
 * @return string
 */
function bb2_db_escape($string)
{
	$db = database();

	return $db->escape_string($string);
}

/**
 * Return the number of rows in a particular query.
 *
 * @param object $result
 * @return int
 */
function bb2_db_num_rows($result)
{
	$db = database();

	return $db->num_rows($result);
}

/**
 * Run a query and return the results, if any.
 * Should return FALSE if an error occurred.
 * Bad Behavior will use the return value here in other callbacks.
 *
 * @param string $query
 * @return bool or int
 */
function bb2_db_query($query)
{
	$db = database();

	// First fix the horrors caused by bb's support of only mysql
	// ok they are right its my horror :P
	if (strpos($query, 'DATE_SUB') !== false)
		$query = 'DELETE FROM {db_prefix}log_badbehavior WHERE date < ' . (bb2_db_date() - 7 * 86400);
	elseif (strpos($query, '@@session.wait_timeout') !== false)
		return true;

	// Run the query, return success, failure or the actual results
	$result = $db->query('', $query, array());

	if (!$result)
		return false;
	elseif ($result === true)
		return (bb2_db_affected_rows() !== 0);
	elseif (bb2_db_num_rows($result) === 0)
		return false;

	return bb2_db_rows($result);
}

/**
 * Return all rows in a particular query.
 * Should contain an array of all rows generated by calling mysql_fetch_assoc()
 * or equivalent and appending the result of each call to an array.
 *
 * @param object $result
 * @return array
 */
function bb2_db_rows($result)
{
	$db = database();

	$temp = array();
	while ($row = $db->fetch_assoc($result))
		$temp[] = $row;
	$db->free_result($result);

	return $temp;
}

/**
 * Return emergency contact email address.
 *
 * @return string (email address)
 */
function bb2_email()
{
	global $webmaster_email;

	return $webmaster_email;
}

/**
 * Create the query for inserting a record in to the database.
 * This is the main logging function for logging and verbose levels.
 *
 * @param array $settings
 * @param array $package
 * @param string $key
 * @return string
 */
function bb2_insert($settings, $package, $key)
{
	global $user_info, $sc;

	// Logging not enabled
	if (!$settings['logging'])
		return '';

	// Clean the data that bb sent us
	$ip = bb2_db_escape($package['ip']);
	$date = (int) bb2_db_date();
	$request_method = bb2_db_escape($package['request_method']);
	$request_uri = bb2_db_escape($package['request_uri']);
	$server_protocol = bb2_db_escape($package['server_protocol']);
	$user_agent = bb2_db_escape($package['user_agent']);
	$member_id = (int) !empty($user_info['id']) ? $user_info['id'] : 0;
	$session = !empty($sc) ? (string) $sc : '';

	// Prepare the headers etc for db insertion
	// We are passed ...  Host. User-Agent, Accept, Accept-Language, Accept-Encoding, DNT, Connection, Referer, Cookie, Authorization
	$headers = '';
	$skip = array('User-Agent');
	foreach ($package['headers'] as $h => $v)
	{
		if (!in_array($h, $skip))
			$headers .= bb2_db_escape($h . ': ' .  $v . "\n");
	}

	$request_entity = '';
	if (!strcasecmp($request_method, "POST"))
	{
		foreach ($package['request_entity'] as $h => $v)
			$request_entity .= bb2_db_escape("$h: $v\n");
	}

	// Add it
	return "INSERT INTO {db_prefix}log_badbehavior
		(`ip`, `date`, `request_method`, `request_uri`, `server_protocol`, `http_headers`, `user_agent`, `request_entity`, `valid`, `id_member`, `session`) VALUES
		('$ip', '$date', '$request_method', '$request_uri', '$server_protocol', '$headers', '$user_agent', '$request_entity', '$key', '$member_id' , '$session')";
}

/**
 * Retrieve whitelist
 *
 * @todo
 * @return type
 */
function bb2_read_whitelist()
{
	global $modSettings;

	// Current whitelist data
	$whitelist = array('badbehavior_ip_wl', 'badbehavior_useragent_wl', 'badbehavior_url_wl');
	foreach ($whitelist as $list)
	{
		$whitelist[$list] = array();
		if (!empty($modSettings[$list]))
		{
			$whitelist[$list] = unserialize($modSettings[$list]);
			$whitelist[$list] = array_filter($whitelist[$list]);
		}
	}

	// Nothing in the whitelist
	if (empty($whitelist['badbehavior_ip_wl']) && empty($whitelist['badbehavior_useragent_wl']) && empty($whitelist['badbehavior_url_wl']))
		return false;

	// Build up the whitelist array so badbehavior can use it
	return array_merge(
		array('ip' => $whitelist['badbehavior_ip_wl']),
		array('url' => $whitelist['badbehavior_useragent_wl']),
		array('useragent' => $whitelist['badbehavior_url_wl'])
	);
}

/**
 * Retrieve bad behavior settings from database and supply them to
 * bad behavior so it knows to not behave badly
 *
 * @return array
 */
function bb2_read_settings()
{
	global $modSettings;

	$badbehavior_reverse_proxy = !empty($modSettings['badbehavior_reverse_proxy']);

	// Make sure that the proxy addresses are split into an array, and if it's empty - make sure reverse proxy is disabled
	if (!empty($modSettings['badbehavior_reverse_proxy_addresses']))
		$badbehavior_reverse_proxy_addresses = explode('|', trim($modSettings['badbehavior_reverse_proxy_addresses']));
	else
	{
		$badbehavior_reverse_proxy_addresses = array();
		$badbehavior_reverse_proxy = false;
	}

	// If they supplied a http:BL API Key lets see if it looks correct before we use it
	$invalid_badbehavior_httpbl_key = empty($modSettings['badbehavior_httpbl_key']) || (!empty($modSettings['badbehavior_httpbl_key']) && (strlen($modSettings['badbehavior_httpbl_key']) !== 12 || !ctype_lower($modSettings['badbehavior_httpbl_key'])));

	// Return the settings so BadBehavior can use them
	return array(
		'log_table' => '{db_prefix}log_badbehavior',
		'display_stats' => !empty($modSettings['badbehavior_display_stats']),
		'strict' => !empty($modSettings['badbehavior_strict']),
		'verbose' => !empty($modSettings['badbehavior_verbose']),
		'logging' => !empty($modSettings['badbehavior_logging']),
		'httpbl_key' => $invalid_badbehavior_httpbl_key ? '' : $modSettings['badbehavior_httpbl_key'],
		'httpbl_threat' => $modSettings['badbehavior_httpbl_threat'],
		'httpbl_maxage' => $modSettings['badbehavior_httpbl_maxage'],
		'eu_cookie' => !empty($modSettings['badbehavior_eucookie']),
		'offsite_forms' => !empty($modSettings['badbehavior_offsite_forms']),
		'reverse_proxy' => $badbehavior_reverse_proxy,
		'reverse_proxy_header' => $modSettings['badbehavior_reverse_proxy_header'],
		'reverse_proxy_addresses' => $badbehavior_reverse_proxy_addresses
	);
}

/**
 * Insert this into the <head> section of your HTML through a template call
 * or whatever is appropriate. This is optional we'll fall back to cookies
 * if you don't use it.
 */
function bb2_insert_head()
{
	global $bb2_javascript;

	// Prepare it so we can use addInlineJavascript by removing the script tags hats its pre wrapped in
	$temp = str_replace('<script type="text/javascript">' . "\n" . '<!--' . "\n", '', $bb2_javascript);
	$temp = str_replace('// --></script>', '', $temp);

	return "\n" . trim($temp);
}

/**
 * Display Statistics (default off)
 * Enabling this option will return a string to add a blurb to your site footer
 * advertising Bad Behavior’s presence and the number of recently blocked requests.
 *
 * This option is not available or has no effect when logging is not in use.
 *
 * @param bool $force
 */
function bb2_insert_stats($force = false)
{
	global $txt;

	$settings = bb2_read_settings();

	if ($force || $settings['display_stats'])
	{
		// Get the blocked count for the last 7 days ... cache this as well
		if (($bb2_blocked = cache_get_data('bb2_blocked', 900)) === null)
		{
			$bb2_blocked = bb2_db_query('SELECT COUNT(*) FROM {db_prefix}log_badbehavior WHERE `valid` NOT LIKE \'00000000\'');
			cache_put_data('bb2_blocked', $bb2_blocked, 900);
		}

		if ($bb2_blocked !== false)
			return sprintf($txt['badbehavior_blocked'], $bb2_blocked[0]['COUNT(*)']);
	}
}

/**
 * Return the top-level relative path of wherever we are (for cookies)
 *
 * @return string
 */
function bb2_relative_path()
{
	global $boardurl;

	return $boardurl;
}