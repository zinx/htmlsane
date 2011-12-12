<?php

$_debug_indent = 0;
function denter($msg) {dprint('ENTER '.$msg);++$GLOBALS['_debug_indent'];}
function dleave($msg) {--$GLOBALS['_debug_indent'];dprint('LEAVE '.$msg);}
function dprint($msg) {
	$indent = ($GLOBALS['_debug_indent']>0)?str_repeat(' ', $GLOBALS['_debug_indent']):'';
	print($indent.$msg."\n");
}
function dfmt($obj) {
	if ($obj instanceof _hs_base) {
		if ($obj instanceof _hs_named_group) {
			return get_class($obj).' '.$obj->name;
		}
		return get_class($obj);
	}
	if ($obj instanceof _hs_Cursor) {
		return get_class($obj->sym).' '.$obj->sym->value;
	}
	if ($obj instanceof _hs_Symbol_base) {
		return substr(get_class($obj),4).' '.$obj->value;
	}
	if ($obj instanceof _hs_CS_token) {
		$append = ($obj instanceof _hs_CS_value)?' '.dfmt($obj->value):'';
		$append .= ($obj instanceof _hs_CS_Numeric)?dfmt($obj->unit):'';
		$append .= ($obj instanceof _hs_CS_Function)?' '.dfmt($obj->name).'('.dfmt($obj->args).')':'';
		return substr(get_class($obj),4).$append;
	}
	if (is_array($obj)) {
		if (is_numeric(key($obj))) {
			$a = array();
			foreach ($obj as $key => $val)
				$a[] = dfmt($val);
			return '('.implode(',',$a).')';
		} else {
			$a = array();
			foreach ($obj as $key => $val)
				$a[] = "'$key'=>".dfmt($val);
			return '('.implode(',',$a).')';
		}
	}
	if (is_object($obj)) return get_class($obj);
	if (is_string($obj)) return "'$obj'";
	if (is_null($obj)) return 'NULL';
	if (is_bool($obj)) return $obj?'TRUE':'FALSE';
	return "$obj";
}
