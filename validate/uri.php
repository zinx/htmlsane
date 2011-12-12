<?php

define('_HS_URI_GENDELIMS', ':/?#[]@');
define('_HS_URI_SUBDELIMS', '!$&\'()*+,;=');
define('_HS_IRI_UCSCHAR', '\xA0-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}');
define('_HS_IRI_PRIVATE', '\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}');
define('_HS_URI_UNRESERVED', '-A-Za-z0-9._~');
define('_HS_IRI_UNRESERVED', _HS_URI_UNRESERVED._HS_IRI_UCSCHAR);
define('_HS_URI_SCHEME_START', 'A-Za-z');
define('_HS_URI_SCHEME_CHARS', '-A-Za-z0-9+.');
define('_HS_URI_PCT_ENCODED', '%[A-Fa-f0-9]{2}');

/* You must supply the proper value for $chars, the characters to not encode. */
function _hs_uriencode($str, $chars) {
	if (!preg_match_all('`[^'.$chars.']+`u', $str, $matches, PREG_OFFSET_CAPTURE))
		return $str;

	$offset = 0;
	$out = '';
	foreach ($matches[0] as $match) {
		$out .= substr($str, $offset, $match[1] - $offset);

		$r = '';
		for ($len = strlen($match[0]), $i = 0; $i < $len; ++$i) {
			$ord = ord($match[0][$i]);
			if ($ord <= 0) return FALSE;
			$r .= '%'.dechex($ord);
		}

		$out .= $r;
		$offset = $match[1] + strlen($match[0]);
	}

	$out .= substr($str, $offset);
	return $out;
}

function _hs_uridecode($str, $ignore = '%') {
	if (!preg_match_all('`(?:%[A-Fa-f0-9]{2})+`u', $str, $matches, PREG_OFFSET_CAPTURE))
		return $str;

	$out = '';
	$offset = 0;
	foreach ($matches[0] as $match) {
		$out .= substr($str, $offset, $match[1] - $offset);

		$r = '';
		$hexes = explode('%',$match[0]);
		while ($hex = next($hexes)) {
			$chr = chr(hexdec($hex));
			if (strpos($ignore, $chr) === FALSE)
				$r .= $chr;
			else
				$r .= '%'.strtoupper($hex);
		}

		$out .= $r;
		$offset = $match[1] + strlen($match[0]);
	}

	$out .= substr($str, $offset);

	$out = _hs_utf8_cleanse($out, $iserr);
	if ($iserr) return FALSE;

	return $out;
}

/* TODO: Implement RFC 3490 (IDNA) */
define('_HS_RE_OCTET', '(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])');
define('_HS_RE_IPV4', '(?:'._HS_RE_OCTET.'\.){3}'._HS_RE_OCTET);
define('_HS_RE_H16', '[A-Fa-f0-9]{1,4}');
define('_HS_RE_LS32', '(?:'._HS_RE_H16.':'._HS_RE_H16.'|'._HS_RE_IPV4.')');
define('_HS_RE_H16_C', '(?:'._HS_RE_H16.':)');
define('_HS_RE_IPV6', '(?:'.
		_HS_RE_H16_C.'{6}'._HS_RE_LS32.'|'.
		'::'._HS_RE_H16_C.'{5}'._HS_RE_LS32.'|'.
		_HS_RE_H16.'::'._HS_RE_H16_C.'{4}'._HS_RE_LS32.'|'.
		_HS_RE_H16_C.'?'._HS_RE_H16.'::'._HS_RE_H16_C.'{3}'._HS_RE_LS32.'|'.
		_HS_RE_H16_C.'{,2}'._HS_RE_H16.'::'._HS_RE_H16_C.'{2}'._HS_RE_LS32.'|'.
		_HS_RE_H16_C.'{,3}'._HS_RE_H16.'::'._HS_RE_H16_C._HS_RE_LS32.'|'.
		_HS_RE_H16_C.'{,4}'._HS_RE_H16.'::'._HS_RE_LS32.'|'.
		_HS_RE_H16_C.'{,5}'._HS_RE_H16.'::'._HS_RE_H16.'|'.
		_HS_RE_H16_C.'{,6}'._HS_RE_H16.'::'.')');
define('_HS_RE_IPVFuture', '(?<ipvf>[Vv][A-Fa-f0-9]+\.['._HS_URI_UNRESERVED._HS_URI_SUBDELIMS.':]+)');
/* Technically, a host name can contain any character.  We only allow FDH. */
define('_HS_RE_DN_LABEL', '[A-Za-z0-9]+(?:[-A-Za-z0-9]*[A-Za-z0-9])?');
define('_HS_RE_DN_LAST_LABEL', '(?:(?<ll>[0-9]+)|'._HS_RE_DN_LABEL.')');
define('_HS_RE_DN', '(?<dn>'._HS_RE_DN_LABEL.'\.)*'._HS_RE_DN_LAST_LABEL);
define('_HS_RE_FQDN', '(?<dn>'._HS_RE_DN_LABEL.'\.)+'._HS_RE_DN_LAST_LABEL);
define('_HS_RE_IP_OR_DN', '(?:'._HS_RE_IPV4.'|\[(?:'._HS_RE_IPV6.'|'._HS_RE_IPVFuture.')\]|'._HS_RE_DN.')');

function _hs_check_host($host) {
	$len = strlen($host);
	if (!$len)
		return FALSE;

	if (!preg_match('`^'._HS_RE_IP_OR_DN.'$`u', $host, $m))
		return FALSE;

	if (isset($m['ll']) && $m['ll'] != '')
		return FALSE;

	if (isset($m['dn']) && $m['dn'] != '')
		return $host;

	if (isset($m['ipvf']) && $m['ipvf'] != '') {
		assert($host[1] == 'v' || $host[1] == 'V');
		$host[1] = 'v';
		return $host;
	}

	return strtolower($host);
}

/* doesn't handle broken Microsoft style paths */
function _hs_decode_path($path, $ignore = '') {
	if ($path == '')
		return array();

	$path = explode('/', $path);
	$len = count($path);

	for ($i = 0; $i < $len; ++$i) {
		$path[$i] = _hs_uridecode($path[$i], $ignore);
		if ($path[$i]===FALSE) return FALSE;
	}

	return _hs_normalize_path($path);
}

function _hs_normalize_path($path) {
	$len = count($path);
	$isabs = $isdir = FALSE;
	if ($len > 0 && ($isabs = ($path[0] == ''))) {
		array_shift($path);
		--$len;
	}
	if (!$len) {
		$isdir = TRUE;
	} else if ($isdir = ($path[$len-1] == '')) {
		array_pop($path);
		--$len;
	} else {
		$isdir = ($path[$len-1] == '.' || $path[$len-1] == '..');
	}

	for ($i = 0; $i < $len; ) {
		if ($path[$i] == '' || $path[$i] == '.') {
			array_splice($path, $i, 1);
			--$len;
			continue;
		}
		if ($path[$i] == '..' && $i != 0) {
			array_splice($path, $i - 1, 2);
			$len -= 2;
			--$i;
			continue;
		}
		++$i;
	}

	if (!$len) {
		$path = $isabs?array(''):($isdir?array('.'):array());
	} else if ($isabs) {
		if ($path[0] == '..')
			return FALSE;
		array_unshift($path, '');
	}

	if ($isdir) {
		$path[] = '';
	}

	return $path;
}

define('_HS_RE_SCHEME', '(?<scheme>['._HS_URI_SCHEME_START.']['._HS_URI_SCHEME_CHARS.']*):');
define('_HS_RE_USERINFO', '(?:(?<userinfo>['._HS_IRI_UNRESERVED._HS_URI_SUBDELIMS.':%]+)@)');
define('_HS_RE_HOST', '(?<host>[^:/[][^:/]*|\[(?:.*?)\])');
define('_HS_RE_PORT', '(?:\:(?<port>[^/?#]*))');
define('_HS_RE_SEGMENT', '(?:/[^/?#]*)');
define('_HS_RE_PATH_ABEMPTY', '(?<authpath>'._HS_RE_SEGMENT.'*)');
define('_HS_RE_AUTHORITY', _HS_RE_USERINFO.'?'._HS_RE_HOST._HS_RE_PORT.'?');
define('_HS_RE_AUTHORITY_PATH_ABEMPTY', '//'._HS_RE_AUTHORITY._HS_RE_PATH_ABEMPTY);
define('_HS_RE_PATH_ABSOLUTE', '(?:/|/[^/][^/?#]*'._HS_RE_SEGMENT.'*)');
define('_HS_RE_PATH_ROOTLESS', '[^/?#]+'._HS_RE_SEGMENT.'*');
define('_HS_RE_PATH_NOSCHEME', '[^:/?#]+'._HS_RE_SEGMENT.'*');
define('_HS_RE_PATH', '(?<path>'._HS_RE_AUTHORITY_PATH_ABEMPTY.'|'._HS_RE_PATH_ABSOLUTE.'|'._HS_RE_PATH_ROOTLESS.'|)');
define('_HS_RE_PATH_REL', '(?<path>'._HS_RE_AUTHORITY_PATH_ABEMPTY.'|'._HS_RE_PATH_ABSOLUTE.'|'._HS_RE_PATH_NOSCHEME.'|)');
define('_HS_RE_QUERY','(?:\?(?<query>[^#]*))');
define('_HS_RE_FRAGMENT','(?:\#(?<fragment>.*))');
define('_HS_RE_URI', _HS_RE_SCHEME._HS_RE_PATH._HS_RE_QUERY.'?'._HS_RE_FRAGMENT.'?');
define('_HS_RE_URI_REL', _HS_RE_PATH_REL._HS_RE_QUERY.'?'._HS_RE_FRAGMENT.'?');

function _hs_parse_uri_1_path($o) {
	if (count($o['path']) != 1)
		return FALSE;
	return $o;
}

function _hs_parse_uri_mailto_path($o) {
	if (count($o['path']) != 1)
		return FALSE;

	$at = strpos($o['path'][0], '@');
	if ($at === FALSE)
		return FALSE;

	$host = substr($o['path'][0], $at + 1);
	$host = _hs_check_host($host);
	if ($host === FALSE)
		return FALSE;

	$o['path'][0] = substr($o['path'][0], 0, $at + 1) . $host;

	return $o;
}

function _hs_parse_uri_sip_path($o) {
	if (count($o['path']) != 1)
		return FALSE;

	$sections = explode(';', $o['path'][0]);

	$userathost = array_shift($sections);

	$at = strpos($userathost, '@');
	if ($at === FALSE)
		return FALSE;

	$hostport = substr($userathost, $at + 1);
	$colon = strpos($hostport, ':');
	$rcolon = strrpos($hostport, ':');
	if ($colon !== FALSE) {
		if ($rcolon !== $colon)
			return FALSE;
		$host = substr($hostport, 0, $colon);
		$port = (int)substr($hostport, $colon + 1);
		if (!$port)
			$port = NULL;
	} else {
		$host = $hostport;
		$port = NULL;
	}
	$host = _hs_check_host($host);
	if ($host === FALSE)
		return FALSE;
	if ($port && $port < 1024)
		return FALSE;

	foreach ($sections as $section) {
		if (strpos($section, '=') === FALSE)
			return FALSE;
	}

	array_unshift($sections, $host . ($port?':'.$port:''));

	$o['path'][0] = substr($userathost, 0, $at + 1) . implode(';', $sections);

	return $o;
}

$_hs_parse_uri_schemes = array(
	'http' => array(
		'ports' => array(80),
		'require_host' => TRUE,
		'allow_host' => TRUE,
		'allow_query' => TRUE,
		'allow_fragment' => TRUE,
		'path_special' => '',
	),
	'https' => array(
		'ports' => array(443),
		'require_host' => TRUE,
		'allow_host' => TRUE,
		'allow_query' => TRUE,
		'allow_fragment' => TRUE,
		'path_special' => '',
	),
	'irc' => array(
		'ports' => array(6667,194),
		'require_host' => FALSE,
		'allow_host' => TRUE,
		'allow_query' => FALSE,
		'allow_fragment' => FALSE,
		'path_special' => '%+&#,',
	),
	'ftp' => array(
		'ports' => array(21),
		'require_host' => TRUE,
		'allow_host' => TRUE,
		'allow_query' => FALSE,
		'allow_fragment' => FALSE,
		'path_special' => '',
	),
	'sftp' => array(
		'ports' => array(115),
		'require_host' => TRUE,
		'allow_host' => TRUE,
		'allow_query' => FALSE,
		'allow_fragment' => FALSE,
		'path_special' => '',
	),
	'file' => array(
		'require_host' => FALSE,
		'allow_host' => TRUE,
		'allow_query' => FALSE,
		'allow_fragment' => TRUE,
		'path_special' => '',
	),
	'news' => array(
		'ports' => array(119),
		'require_host' => FALSE,
		'allow_host' => TRUE,
		'allow_query' => FALSE,
		'allow_fragment' => TRUE,
		'path_special' => '',
	),
	'nntp' => array(
		'ports' => array(119),
		'require_host' => TRUE,
		'allow_host' => TRUE,
		'allow_query' => FALSE,
		'allow_fragment' => TRUE,
		'path_special' => '',
	),
	'rtsp' => $_hs_parse_uri_scheme_rtsp = array(
		'ports' => array(554),
		'require_host' => TRUE,
		'allow_host' => TRUE,
		'allow_query' => FALSE,
		'allow_fragment' => TRUE,
		'path_special' => '',
	),
	'rtspu' => &$_hs_parse_uri_scheme_rtsp,
	'mailto' => array(
		'require_host' => FALSE,
		'allow_host' => FALSE,
		'allow_query' => TRUE,
		'allow_fragment' => FALSE,
		'path_special' => '%@',
		'callback' => '_hs_parse_uri_mailto_path',
	),
	'tel' => array(
		'require_host' => FALSE,
		'allow_host' => FALSE,
		'allow_query' => FALSE,
		'allow_fragment' => FALSE,
		'path_special' => '',
		'callback' => '_hs_parse_uri_1_path',
	),
	'sip' => array(
		'ports' => array(5060),
		'require_host' => FALSE,
		'allow_host' => FALSE,
		'allow_query' => TRUE,
		'allow_fragment' => FALSE,
		'path_special' => '%@;:',
		'callback' => '_hs_parse_uri_sip_path',
	),
	'sips' => array(
		'ports' => array(5061),
		'require_host' => FALSE,
		'allow_host' => FALSE,
		'allow_query' => TRUE,
		'allow_fragment' => FALSE,
		'path_special' => '%@;:',
		'callback' => '_hs_parse_uri_sip_path',
	),
	/* other schemes may be added/removed via _hs_parse_uri_set_scheme */
);

function _hs_parse_uri_set_scheme($scheme, $settings) {
	global $_hs_parse_uri_schemes;
	$old = $_hs_parse_uri_schemes[$scheme];
	$_hs_parse_uri_schemes[$scheme] = $settings;
	return $old;
}

function _hs_parse_uri($uri) {
	$m = array();
	if (!preg_match('`^'._HS_RE_URI.'$`u', $uri, $m)) {
		$m = array();
		if (!preg_match('`^'._HS_RE_URI_REL.'$`u', $uri, $m))
			return FALSE;
	}

	$o = array();

	if (isset($m['host']) && $m['host']!='') {
		$m['path'] = $m['authpath'];
		if ($m['path'] == '')
			$m['path'] = '/';
	}

	if (isset($m['scheme'])) {
		$o['scheme'] = strtolower($m['scheme']);
		global $_hs_parse_uri_schemes;
		if (isset($_hs_parse_uri_schemes[$o['scheme']]))
			$scheme = &$_hs_parse_uri_schemes[$o['scheme']];
	}

	/* verify userinfo = *(iunreserved / pct-encoded / sub-delims / ":") */
	if (isset($m['userinfo']) && $m['userinfo']!='') {
		if (!preg_match('`^(?:['._HS_IRI_UNRESERVED._HS_URI_SUBDELIMS.':]|'._HS_URI_PCT_ENCODED.')+$`u', $m['userinfo']))
			return FALSE;

		$o['userinfo'] = _hs_uridecode($m['userinfo'], '%@');
		if ($o['userinfo']===FALSE) return FALSE;

		if (empty($o['userinfo'])) unset($o['userinfo']);
	}

	if (isset($m['host']) && $m['host']!='') {
		/* verify host */
		$o['host'] = _hs_uridecode($m['host'], '%:/?#');
		if ($o['host']===FALSE) return FALSE;
		$o['host'] = _hs_check_host($o['host']);
		if ($o['host']===FALSE) return FALSE;
	}

	if (isset($m['port'])) {
		/* verify port */
		$o['port'] = _hs_uridecode($m['port'], '%:/?#');
		if ($o['port']===FALSE) return FALSE;
		$o['port'] = (int)$o['port'];
		if (!$o['port']) unset($o['port']);
		else if ($o['port'] < 1024) {
			if (isset($scheme) && isset($scheme['ports'])) {
				if (!in_array($o['port'], $scheme['ports']))
					return FALSE;
			} else {
				$o['unverified_port'] = TRUE;
			}
		} else if ($o['port'] >= 65536) {
			return FALSE;
		}
		if (isset($o['port']) && isset($scheme) && isset($scheme['ports']) &&
		    $o['port'] === $scheme['ports'][0]) {
			unset($o['port']);
		}
	}

	if (isset($m['query']) && $m['query']!='') {
		$o['query'] = _hs_uridecode($m['query'], '%&+#');
		if ($o['query']===FALSE) return FALSE;
	}

	if (isset($m['fragment']) && $m['fragment']!='') {
		$o['fragment'] = _hs_uridecode($m['fragment'], '%');
		if ($o['fragment']===FALSE) return FALSE;
	}

	/* verify scheme-specific stuff */
	if (isset($o['scheme'])) {
		if (isset($scheme)) {
			$o['path'] = _hs_decode_path($m['path'], $scheme['path_special']);
			if ($o['path']===FALSE)
				return FALSE;
			if ($scheme['require_host'] && !isset($o['host']))
				return FALSE;
			if (!$scheme['allow_host'] && isset($o['host']))
				return FALSE;
			if (!$scheme['allow_query'] && isset($o['query']))
				return FALSE;
			if (!$scheme['allow_fragment'] && isset($o['fragment']))
				return FALSE;
			if (isset($scheme['callback'])) {
				$o = call_user_func($scheme['callback'], $o);
				if ($o === FALSE)
					return FALSE;
			}
		} else {
			$o['path'] = explode('/', $m['path']);
			$o['unverified'] = TRUE;
		}
	} else {
		/* Relative URI */
		$scheme = &$_hs_parse_uri_schemes['http'];
		$o['path'] = _hs_decode_path($m['path'], $scheme['path_special']);
		if ($o['path']===FALSE)
			return FALSE;
		$o['relative'] = TRUE;
	}

	return $o;
}

function _hs_build_uri($a) {
	$out = '';

	if (isset($a['scheme']))
		$out .= $a['scheme'].':';

	if (isset($a['host'])) {
		$out .= '//';
		if (isset($a['userinfo']))
			$out .= _hs_uriencode($a['userinfo'], _HS_IRI_UNRESERVED._HS_URI_SUBDELIMS.':%');

		$out .= _hs_uriencode($a['host'], _HS_IRI_UNRESERVED._HS_URI_SUBDELIMS.'%');

		if (isset($a['port']))
			$out .= ':'.$a['port'];
	}

	if (isset($a['unverified'])) {
		$path = $a['path'];
	} else {
		$path = array();
		foreach ($a['path'] as $dir)
			$path[] = _hs_uriencode($dir, _HS_IRI_UNRESERVED._HS_URI_SUBDELIMS.':@%');
	}
	$out .= implode('/', $path);

	if (isset($a['query']))
		$out .= '?'._hs_uriencode($a['query'], _HS_IRI_UNRESERVED._HS_URI_SUBDELIMS._HS_IRI_PRIVATE.':/@%?');

	if (isset($a['fragment']))
		$out .= '#'._hs_uriencode($a['fragment'], _HS_IRI_UNRESERVED._HS_URI_SUBDELIMS.':/@%?');

	return $out;
}

/* $uri and $base should both already be parsed */
function _hs_uri_relative_to($uri, $base) {
	if (isset($uri['relative']))
		return TRUE;
	if (!isset($uri['host']) || strcasecmp($uri['host'], $base['host']) != 0)
		return FALSE;
	if ($uri['scheme'] != $base['scheme'])
		return FALSE;
	if (isset($uri['port']) != isset($base['port']))
		return FALSE;
	if (isset($uri['port']) && $uri['port'] != $base['port'])
		return FALSE;
	if (isset($uri['userinfo']) != isset($host['userinfo']))
		return FALSE;
	if (isset($uri['userinfo']) && $uri['userinfo'] != $host['userinfo'])
		return FALSE;
	return TRUE;
}

/* $uri and $base should both already be parsed */
function _hs_uri_rel($uri, $base) {
	if (isset($uri['relative'])) {
		$uc = count($uri['path']);
		$bc = count($base['path']);
		if ($base['path'][$bc-1] == '' && $uc > 0 && $uri['path'][0] == '.') {
			array_shift($uri['path']);
			if ($uc > 1 && $uri['path'][0] == '')
				array_shift($uri['path']);
		}
		return $uri;
	}
	if (!_hs_uri_relative_to($uri, $base))
		return $uri;

	$uc = count($uri['path']);
	$bc = count($base['path']);
	$max = max($uc, $bc);
	for ($i = 1; $i < $max; ++$i) {
		if ($uri['path'][$i] != $base['path'][$i])
			break;
	}
	if ($i != 1) {
		$diff = $bc - $uc;
		array_splice($uri['path'], 0, $i, ($diff>0)?array_fill(0, $diff, '..'):array());
	}

	unset($uri['scheme']);
	if (isset($uri['userinfo'])) unset($uri['userinfo']);
	unset($uri['host']);
	if (isset($uri['port'])) unset($uri['port']);
	$uri['relative'] = TRUE;

	return $uri;
}

/* $uri and $base should both already be parsed */
function _hs_uri_abs($uri, $base) {
	if (!isset($uri['relative']))
		return $uri;

	if (empty($uri['path']) || $uri['path'][0] != '') {
		$base_dir = $base['path'];
		if (count($base_dir) > 1 && !empty($uri['path']))
			array_pop($base_dir);

		$uri['path'] = _hs_normalize_path(array_merge($base_dir, $uri['path']));
	}

	$uri['scheme'] = $base['scheme'];
	if (isset($base['userinfo'])) $uri['userinfo'] = $base['userinfo'];
	$uri['host'] = $base['host'];
	if (isset($base['port'])) $uri['port'] = $base['port'];
	unset($uri['relative']);

	return $uri;
}
