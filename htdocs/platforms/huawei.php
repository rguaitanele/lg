<?php

/**
 * Commands supported by Huawei VRP routers.
 */
function huawei_queries()
{
	return array
	(
		'ipv4' => array
		(
			'bgp' => 'display bgp routing-table %s',
			'advertised-routes' => 'display bgp routing-table peer %s advertised-routes',
			'received-routes' => 'display bgp routing-table peer %s received-routes',
			'routes' => 'display bgp routing-table peer %s received-routes active',
			'summary' => 'display bgp peer',
			'ping' => 'ping %s',
			'trace' => 'tracert %s',
		),
		'ipv6' => array
		(
			'bgp' => 'display bgp ipv6 routing-table %s',
			'advertised-routes' => 'display bgp ipv6 routing-table peer %s advertised-routes',
			'received-routes' => 'display bgp ipv6 routing-table peer %s received-routes',
			'routes' => 'display bgp ipv6 routing-table peer %s received-routes active',
			'summary' => 'display bgp ipv6 peer',
			'ping' => 'ping ipv6 %s',
			'trace' => 'tracert ipv6 %s',
		)
	);
}

/**
 * Hide Huawei SSH/VTY session messages that are not part of command output.
 */
function huawei_should_ignore_output_line($output)
{
	return preg_match('/^\s*User Authentication\s*$/i', $output)
		OR preg_match('/^\s*Info:\s+The max number of VTY users\b/i', $output)
		OR preg_match('/^\s*The current login time is\b/i', $output);
}

/**
 * Enrich Huawei BGP peer output with AS, IP and received-route links.
 * Returns NULL when the current command is handled by the generic parser.
 */
function huawei_parse_output_line($output, $exec)
{
	global $lastip;

	if (!preg_match('/^display bgp(?: ipv6)? peer/', $exec))
	{
		return NULL;
	}

	$output = preg_replace_callback(
		'/( 4 )([ ]*)([0-9]{0,6})/',
		function ($matches) {
			return $matches[1].$matches[2].link_as($matches[3]);
		},
		$output
	);

	$output = preg_replace_callback(
		'/^(  )([0-9\.A-Fa-f:]+)( )/',
		function ($matches) {
			global $lastip;
			$lastip = $matches[2];
			return $matches[1].link_whois($matches[2]).$matches[3];
		},
		$output
	);

	$output = preg_replace_callback(
		'/( Established )([ ]* )([0-9]{1,6})/',
		function ($matches) use ($lastip) {
			return $matches[1].$matches[2].link_command('received-routes', $lastip, $matches[3]);
		},
		$output
	);

	return $output;
}

/**
 * Extract AS paths from detailed Huawei BGP route output for GraphViz.
 */
function huawei_parse_bgp_path($output)
{
	$best = FALSE;
	$pathes = array();

	if (!preg_match_all(
		'/^\s*AS-path\s+([^,\r\n]+),\s*([^\r\n]*)/im',
		$output,
		$matches,
		PREG_SET_ORDER
	))
	{
		return FALSE;
	}

	foreach ($matches as $match)
	{
		$raw_path = trim($match[1]);
		$attributes = $match[2];

		// A local route has no external AS path to draw.
		if (strcasecmp($raw_path, 'Nil') == 0)
		{
			continue;
		}

		$path = parse_as_path($raw_path);

		if (empty($path))
		{
			continue;
		}

		$path_id = count($pathes);
		$pathes[] = $path;

		if (preg_match('/(?:^|,\s*)best(?:,|\s|$)/i', $attributes)
			AND preg_match('/(?:^|,\s*)select(?:,|\s|$)/i', $attributes))
		{
			$best = $path_id;
		}
	}

	if (empty($pathes))
	{
		return array
		(
			'best' => FALSE,
			'pathes' => array(),
		);
	}

	// Some VRP releases omit the selection attributes when only one path exists.
	if ($best === FALSE AND count($pathes) == 1)
	{
		$best = 0;
	}

	return array
	(
		'best' => $best,
		'pathes' => $pathes,
	);
}
