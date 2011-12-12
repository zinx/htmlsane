<?php

class RelaxTreeLut {
	static private $unresolved = array();

	public static function build($defines) {
		foreach ($defines as &$def)
			self::build_lut($def, $defines);
		self::resolve(&$defines);
	}

	private static function build_lut(&$def, &$defines) {
		if (!is_a($def, 'RelaxGroupBase'))
			return;

		$luts = array();
		foreach ($def->children as &$child) {
			self::build_lut($child, $defines);
			$lutvals = $child->lutval();
			if ($child instanceof RelaxRef && !$defines[substr($lutvals[0],1)]) {
				unset($child);
				continue;
			}
			$lutvals = array_unique($lutvals);
			sort($lutvals);
			$luts[] = $lutvals;
		}
		$def->children = array_values($def->children);

		$def->lut = array();
		$un = FALSE;
		$attr = TRUE;
		foreach ($luts as $index => $lutvals) {
			foreach ($lutvals as $lutval) {
				if ($lutval[0]=='r')
					$un = TRUE;
				else if ($lutval != '-' && $lutval[0] != 'a' && $lutval != 'x')
					$attr = FALSE;
				$def->lut[$lutval][] = $index;
			}
		}

		if ($un) self::$unresolved[] = &$def;
		else if ($attr) $def->lut['a'] = TRUE;
	}

	private static function resolve($defines) {
		while (!empty(self::$unresolved)) {
			foreach (self::$unresolved as $key => &$def) {
				if (self::resolve_lut($defines, &$def->lut, $def->group_name))
					unset(self::$unresolved[$key]);
			}
		}
	}

	private static function resolve_lut($defines, $lut, $ignore) {
		$un = FALSE;
		$attr = TRUE;
		foreach ($lut as $lutval => $indexes) {
			if ($lutval != '-' && $lutval[0] != 'a' && $lutval[0] != 'x')
				$nattr = TRUE;
			else
				$nattr = FALSE;
			if ($lutval[0]!='r') {$attr=$nattr?FALSE:$attr;continue;}
			unset($lut[$lutval]);
			$defname = substr($lutval,1);
			if (!$defines[$defname]) continue;
			$lutvals = $defines[$defname]->lutval();
			$lutvals = array_unique($lutvals);
			sort($lutvals);
			foreach ($lutvals as $lutval) {
				if ($lutval[0]=='r')
					$un = TRUE;
				else if ($lutval != '-' && $lutval[0] != 'a' && $lutval[0] != 'x')
					$attr = FALSE;

				if (!isset($lut[$lutval]))
					$lut[$lutval] = $indexes;
				else
					$lut[$lutval] = array_merge($lut[$lutval], $indexes);

				$lut[$lutval] = array_unique($lut[$lutval]);
			}
		}
		if (!$un && $attr) $lut['a'] = TRUE;
		ksort($lut);
		return !$un;
	}
}
