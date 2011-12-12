<?php
function utf8_chr($val) {
	if ($val <= 0) {
		return FALSE;
	} elseif ($val <= 0x007f) {
		return chr($val);
	} elseif ($val <= 0x07ff) {
		return chr(0xc0 | ($val >> 6)) .
			chr(0x80 | ($val & 0x003f));
	} elseif ($val == 0xfeff) {
		return '';
	} elseif ($val >= 0xd800 && $val <= 0xdfff) {
		return FALSE;
	} elseif ($val <= 0xffff) {
		return chr(0xe0 | ($val >> 12)).
			chr(0x80 | (($val >> 6) & 0x003f)).
			chr(0x80 | ($val & 0x003f));
	} elseif ($val <= 0x10ffff) {
		return chr(0xf0 | ($val >> 18)).
			chr(0x80 | (($val >> 12) & 0x3f)).
			chr(0x80 | (($val >> 6) & 0x3f)).
			chr(0x80 | ($val & 0x3f));
	}
	return FALSE;
}

function utf8_ord($chr, &$len = 0) {
	$ord = ord($chr{0});
	if ($ord < 0x7f) {$len = 1; return $ord;}
	if (($ord & 0xe0) == 0xc0) {$len = 2; $val=$ord&~0xe0;}
	else if (($ord & 0xf0) == 0xe0) {$len = 3; $val=$ord&~0xf0;}
	else if (($ord & 0xf8) == 0xf0) {$len = 4; $val=$ord&~0xf8;}
	else return FALSE;
	if ($len > strlen($chr)) return FALSE;
	for ($i = 1; $i < $len; ++$i) {
		$ord = ord($chr{$i});
		if (($ord & 0xC0) != 0x80) return FALSE;
		$val = ($val<<6)|($ord&~0xC0);
	}
	return $val;
}

/* iconv spits out warnings for invalid characters. */
$_hs_utf8_cleanse_error = TRUE;
function _hs_utf8_cleanse_handler($errno, $error) {
	global $_hs_utf8_cleanse_error;
	$is_iconv_err = ($errno==8)&&(substr($error,0,8)=='iconv():');
	if ($is_iconv_err)
		$_hs_utf8_cleanse_error = $error;
	return $is_iconv_err;
}
function _hs_utf8_cleanse($str, &$error = NULL) {
	global $_hs_utf8_cleanse_error;
	$_hs_utf8_cleanse_error = FALSE;
	set_error_handler('_hs_utf8_cleanse_handler', E_NOTICE);
	$clean = iconv('UTF-8', 'UTF-8//IGNORE', $str);
	restore_error_handler();
	$error = $_hs_utf8_cleanse_error;
	return $clean;
}

function _hs_htmlentities($str) {
	global $_hs_rents;
	if (!preg_match_all('@[^\x9\xA\xD\x20-\x21\x23-\x25\x27-\x3b\x3d\x3f-\x7E]@u', $str, $matches, PREG_OFFSET_CAPTURE))
		return $str;

	$offset = 0;
	$out = '';
	foreach ($matches[0] as $match) {
		$out .= substr($str, $offset, $match[1] - $offset);
		$ord = utf8_ord($match[0]);
		if (array_key_exists($ord, $_hs_rents))
			$out .= "&{$_hs_rents[$ord]};";
		else
			$out .= "&{$ord};";
		$offset = $match[1] + strlen($match[0]);
	}
	$out .= substr($str, $offset);
	return $out;
}
