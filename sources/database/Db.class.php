<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This class is the abstract base class for database drivers implementations.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

abstract class Database
{
	/**
	 * Fix up the prefix so it doesn't require the database to be selected.
	 *
	 * @param string &db_prefix
	 * @param string $db_name
	 */
	abstract function fix_prefix(&$db_prefix, $db_name);

	/**
	 * Callback for preg_replace_callback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from $user_info.
	 * In addition, it performs checks and sanitization on the values sent to the database.
	 *
	 * @param $matches
	 */
	abstract function replacement__callback($matches);

	/**
	 * Just like the db_query, escape and quote a string, but not executing the query.
	 *
	 * @param string $db_string
	 * @param array $db_values
	 * @param resource $connection = null
	 */
	abstract function quote($db_string, $db_values, $connection = null);

	/**
	 * Do a query.  Takes care of errors too.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param array $db_values = array()
	 * @param resource $connection = null
	 */
	abstract function query($identifier, $db_string, $db_values = array(), $connection = null);

	/**
	 * Affected rows from previous operation
	 * @param resource $connection
	 */
	abstract function affected_rows($connection = null);

	/**
	 * insert_id
	 *
	 * @param string $table
	 * @param string $field = null
	 * @param resource $connection = null
	 */
	abstract function insert_id($table, $field = null, $connection = null);

	/**
	 * Do a transaction.
	 *
	 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param resource $connection = null
	 */
	abstract function do_transaction($type = 'commit', $connection = null);

	/**
	 * Database error!
	 * Backtrace, log, try to fix.
	 *
	 * @param string $db_string
	 * @param resource $connection = null
	 */
	abstract function error($db_string, $connection = null);

	/**
	 * insert
	 *
	 * @param string $method - options 'replace', 'ignore', 'insert'
	 * @param $table
	 * @param $columns
	 * @param $data
	 * @param $keys
	 * @param bool $disable_trans = false
	 * @param resource $connection = null
	 */
	abstract function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null);

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param $error_message
	 * @param $log_message
	 * @param $error_type
	 * @param $file
	 * @param $line
	 */
	abstract function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null);

	/**
	 * Escape the LIKE wildcards so that they match the character and not the wildcard.
	 *
	 * @param $string
	 * @param bool $translate_human_wildcards = false, if true, turns human readable wildcards into SQL wildcards.
	 */
	abstract function escape_wildcard_string($string, $translate_human_wildcards=false);

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return bool
	 */
	abstract function support_ignore();

	/**
	 * Return last error string from the database server
	 *
	 * @param resource $connection = null
	 */
	abstract function last_error($connection = null);
}