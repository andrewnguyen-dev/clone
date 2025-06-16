<?php
	$nowSql = 'NOW()';
	$_connection = null;

	if (!function_exists('logError')) {
		function logError($string) {
			echo $string."\n";
			throw new Exception("$string");
	}}

	function doDbConnect() {
		global $db_host, $db_user, $db_pass, $db_name, $nowSql, $_connection;
		// Connect to the database, and return any errors
		$_connection = mysqli_connect($db_host, $db_user, $db_pass, $db_name) or logError("Cannot connect to database host: $db_host");
		date_default_timezone_set('Australia/Adelaide');
		mysqli_query($_connection, "SET time_zone='+0:00'");
		putenv("TZ=Australia/Adelaide");
		mysqli_set_charset($_connection, 'utf8mb4');
		$nowSql = "DATE_ADD(NOW(), INTERVAL " . date('Z') . " SECOND)";
	}
	doDbConnect();

	// Define error-handling sql-query function
	if (!function_exists('db_query')) {
		function db_query($query) {
			global $_connection;
			$r = mysqli_query($_connection, $query) or logError($query . "<BR><BR>" . mysqli_error($_connection));
			return $r;
	}}

	// sql query function to return a single row
	if (!function_exists('db_getVal')) {
		function db_getVal($query) {
			$r = db_query($query);
			if (mysqli_num_rows($r) == 1) {
				$data = mysqli_fetch_row($r);
				if (count($data) == 1) list($data) = $data;
			} else $data = '';
			return $data;
	}}

	// sql function to get/set config values
	if (!function_exists('db_getConfig')) {
		function db_getConfig($config) {
			return db_getVal("SELECT value FROM configs WHERE name='$config' LIMIT 1");
	}}
	if (!function_exists('db_setConfig')) {
		function db_setConfig($config, $value) {
			$config = mysqli_escape_string($config);
			$value = mysqli_escape_string($value);
			db_query("REPLACE INTO configs SET name='{$config}', value='{$value}'");
	}}
?>
