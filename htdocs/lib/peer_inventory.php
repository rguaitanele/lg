<?php

/**
 * Load the peer inventory. Missing or invalid files are treated as empty.
 */
function peer_inventory_load()
{
	global $_CONFIG;

	$path = $_CONFIG['peerinventorypath'];
	if (!is_readable($path))
	{
		return array('version' => 1, 'routers' => array(), 'asn_cache' => array());
	}

	$data = json_decode(@file_get_contents($path), TRUE);
	if (!is_array($data))
	{
		return array('version' => 1, 'routers' => array(), 'asn_cache' => array());
	}

	$data += array('version' => 1, 'routers' => array(), 'asn_cache' => array());
	return $data;
}

/**
 * Store a successful peer snapshot and refresh stale ASN names.
 */
function peer_inventory_store_snapshot($router, $protocol, $peers)
{
	global $_CONFIG;

	if (empty($peers))
	{
		return FALSE;
	}

	$path = $_CONFIG['peerinventorypath'];
	$directory = dirname($path);
	if (!is_dir($directory) AND !@mkdir($directory, 0770, TRUE))
	{
		error_log('LG cannot create peer inventory directory: '.$directory);
		return FALSE;
	}

	$lock_path = $path.'.lock';
	$lock = @fopen($lock_path, 'c');
	if (!$lock OR !flock($lock, LOCK_EX))
	{
		error_log('LG cannot lock peer inventory: '.$lock_path);
		if ($lock) fclose($lock);
		return FALSE;
	}

	$data = peer_inventory_load();
	$now = time();
	$cache_ttl = 604800;

	foreach ($peers as $peer_ip => $peer)
	{
		$asn = isset($peer['asn']) ? preg_replace('/\D/', '', $peer['asn']) : '';
		$name = '';

		if ($asn !== '' AND isset($data['asn_cache'][$asn])
			AND isset($data['asn_cache'][$asn]['updated_at'])
			AND ($now - (int) $data['asn_cache'][$asn]['updated_at']) < $cache_ttl)
		{
			$name = $data['asn_cache'][$asn]['name'];
		}
		else if ($asn !== '')
		{
			$asinfo = get_asinfo('AS'.$asn);
			if ($asinfo)
			{
				$name = !empty($asinfo['description']) ? $asinfo['description'] : $asinfo['asname'];
				$data['asn_cache'][$asn] = array('name' => $name, 'updated_at' => $now);
			}
		}

		$peers[$peer_ip]['name'] = $name;
	}

	$data['routers'][$router][$protocol] = array(
		'updated_at' => date(DATE_ATOM, $now),
		'peers' => $peers,
	);

	$temporary = $path.'.tmp.'.getmypid();
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	$written = $json !== FALSE AND @file_put_contents($temporary, $json."\n") !== FALSE
		AND @rename($temporary, $path);

	if (file_exists($temporary)) @unlink($temporary);
	flock($lock, LOCK_UN);
	fclose($lock);

	if (!$written)
	{
		error_log('LG cannot write peer inventory: '.$path);
	}

	return $written;
}

/**
 * Return peer suggestions grouped by configured router and protocol.
 */
function peer_inventory_suggestions()
{
	$data = peer_inventory_load();
	$return = array();

	foreach ($data['routers'] as $router => $protocols)
	{
		foreach ($protocols as $protocol => $snapshot)
		{
			$return[$router][$protocol] = array(
				'updated_at' => isset($snapshot['updated_at']) ? $snapshot['updated_at'] : NULL,
				'peers' => isset($snapshot['peers']) ? $snapshot['peers'] : array(),
			);
		}
	}

	return $return;
}
