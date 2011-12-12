<?php

include_once('debug.php');
include_once('util.php');
include_once('uri.php');
include_once('Config.php');
include_once('Lexer.php');
include_once('Cursor.php');
include_once('Parse.php');
include_once('ParseData.php');
include_once('xhtml-ents.php');
include_once('xhtml11.php');

class htmlsane {
	protected $cursor;
	function __construct($config = NULL) {
		$this->cursor = new _hs_Cursor($config);
	}

	protected static function lookup_ref_element($elem) {
		global $_hs_spec;
		$ref = $_hs_spec[$elem];
		while (!($ref instanceof _hs_e) && ($indexes = $ref->lut['e'.$elem])) {
			if (count($indexes)!=1) return FALSE;
			$ref = $ref->rules[$indexes[0]];
		}
		return ($ref instanceof _hs_e)?$ref:FALSE;
	}

	function validate($doc, $parent_element = 'div') {
		$this->cursor->load(_hs_utf8_cleanse($doc));
		$rule = self::lookup_ref_element($parent_element);
		if ($rule===FALSE) return FALSE;
		$ret = $rule->validate_content($this->cursor);
		$this->cursor->reset();
		return $ret;
	}

	function config($config = NULL) {
		if (isset($config)) $this->cursor->config = $config;
		return $this->cursor->config;
	}
}

function htmlsane_validate($doc, $config = NULL, $parent_element = 'div') {
	static $htmlsane;
	if (!isset($htmlsane)) $htmlsane = new htmlsane($config);
	else $htmlsane->config($config);
	return $htmlsane->validate($doc, $parent_element);
}
