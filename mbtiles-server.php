<?php
/**
 * Micro MBTiles Server using PHP
 * This is part of the KD2 tools available at http://dev.kd2.org/kd2fw/
 * Copyleft (C) 2015 BohwaZ <bohwaz.net>
 * Released under the GNU AGPLv3
 */

/**
 * Sets the MBTiles file to read from
 */
define('LOCAL_MBTILES_FILE', __DIR__ . '/test.mbtiles');

/**
 * Set to true if you want to use Apache or Lighttpd X-SendFile feature
 * (it is faster!)
 * Make sure the X-SendFile module is installed and enabled or you will
 * get empty requests results.
 * Will only work if ENABLE_LOCAL_CACHE is set to true.
 */
define('ENABLE_X_SENDFILE', false);

/**
 * Set to true to cache tiles locally (faster)
 */
define('ENABLE_LOCAL_CACHE', true);

/**
 * Default cache dir
 */
define('LOCAL_CACHE_DIR', __DIR__ . '/cache');

///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////

if (!file_exists(LOCAL_MBTILES_FILE))
{
	error('MBTiles file not found.');
}

if (!empty($_SERVER['REQUEST_URI']))
{
	$url = $_SERVER['REQUEST_URI'];
}
elseif (!empty($_SERVER['QUERY_STRING']))
{
	$url = preg_replace('/&.*$/', '', $_SERVER['QUERY_STRING']);
}
else
{
	error('No valid URL found.');
}

if (!preg_match('!(\d+)/(\d+)/(\d+)(?:\.\w+)?$!', $url, $match))
{
	error('Invalid tile URL.');
}

$z = (int)$match[1];
$x = (int)$match[2];
$y = (int)$match[3];

$y = pow(2, $z) - 1 - $y;

$request = $z . '/' . $x . '/' . $y;

if (ENABLE_LOCAL_CACHE)
{
	$local_cache_file = LOCAL_CACHE_DIR . '/' . $request;
	$format = false;

	if (file_exists($local_cache_file . '.png'))
	{
		$format = 'png';
	}
	elseif (file_exists($local_cache_file . '.jpg'))
	{
		$format = 'jpg';
	}

	if ($format)
	{
		if (ENABLE_X_SENDFILE)
		{
			header('X-Sendfile: ' . $local_cache_file . '.' . $format);
		}
		else if (ENABLE_X_ACCEL)
		{
			// Relative path for nginx, we assume the vhost is 
			$file = str_replace(LOCAL_CACHE_DIR, '', $local_cache_file);
			header('X-Accel-Redirect: ' . $file . '.' . $format);
		}
		else
		{
			img_header($format);
			readfile($local_cache_file . '.' . $format);
		}
		exit;
	}
}

$not_found = false;

$db = new \SQLite3(LOCAL_MBTILES_FILE, SQLITE3_OPEN_READONLY);

$rowid = $db->querySingle('SELECT rowid FROM tiles 
	WHERE zoom_level = ' . $z . ' AND tile_column = ' . $x . ' 
	AND tile_row = ' . $y . ';');

if (!$rowid)
{
	header('HTTP/1.1 404 Not Found', true, 404);
	echo "The requested tile can not be found." . PHP_EOL;
	exit;
}
else
{
	$format = $db->querySingle('SELECT value FROM metadata WHERE name = \'format\';');
	$format = strtolower($format) == 'png' ? 'png' : 'jpg';

	$blob = $db->openBlob('tiles', 'tile_data', $rowid);
}

if (ENABLE_LOCAL_CACHE)
{
	if (!file_exists(LOCAL_CACHE_DIR . '/' . $z . '/' . $x))
	{
		mkdir(LOCAL_CACHE_DIR . '/' . $z . '/' . $x, 0777, true);
	}

	$local_cache_file = LOCAL_CACHE_DIR . '/' . $request . '.' . $format;

	$out = fopen($local_cache_file, 'w');

	if ($not_found)
	{
		fwrite($out, $not_found);
		fclose($out);

		img_header('png');
		echo $not_found;
		exit;
	}

	stream_copy_to_stream($blob, $out);
	
	fclose($out);
	fclose($blob);
	$db->close();

	if (ENABLE_X_SENDFILE)
	{
		header('X-Sendfile: ' . $local_cache_file);
	}
	else
	{
		img_header($format);
		readfile($local_cache_file);
	}

	exit;
}

while (!feof($blob))
{
	echo fread($blob, 8192);
}

fclose($blob);
$db->close();

/**
 * Returns an error to the HTTP client
 * @param  string $msg Error message
 * @return void
 */
function error($msg)
{
	header('HTTP/1.1 400 Bad Request', true, 400);
	echo $msg . PHP_EOL;
	exit;
}

function img_header($format)
{
	// 7 days browser cache
	$expires = 7 * 3600 * 24;

	header('HTTP/1.1 200 OK', true, 200);
	header('Content-type: image/' . $format);
	header('Pragma: cache');
	header('X-Powered-By: KD2.MBTiles.Server', true);
	header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $expires));
	header('Cache-Control: max-age=' . $expires);

	ob_clean();
	flush();
}