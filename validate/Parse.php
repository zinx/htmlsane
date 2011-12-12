<?php
/* Symbols -> Tree -> sanitized output */
/* classes for generated Relax NG based tree */

abstract class _hs_base {
	/* Returns FALSE if invalid, NULL if invalid but optional.
	   otherwise, advances the cursor and returns an array with one or more of:
		'attr' => array('attribute name' => 'attribute value', ...),
		'content' => array('content value', ...),
		'content' => FALSE // empty element,
		'value' => 'value value',
	*/
	abstract public function validate(&$cursor);
	protected function repeatable() {return FALSE;}
	protected function optional() {return FALSE;}
}

abstract class _hs_value extends _hs_base {
	public $value;
	function __construct($value){$this->value=&$value;}
}

abstract class _hs_group extends _hs_base {
	public $rules, $lut;
	function __construct($rules,$lut){$this->rules=&$rules;$this->lut=&$lut;}
	/* abstract public function validate(&$cursor, $parentslut = array()); */
}

class _hs_t /*text*/ extends _hs_base {
	public function validate(&$cursor) {
		if (!$cursor->sym instanceof _hs_S_Text_base)
			return FALSE;
		$val = $cursor->sym->validate($cursor);
		if ($val===FALSE) return FALSE;
		$cursor->next();
		return array('content' => array($val));
	}
}

class _hs_x /*empty*/ extends _hs_base {
	public function validate(&$cursor) {
		if (!$cursor->ended())
			$cursor->terminate();
		return array('content' => FALSE);
	}
}

class _hs_v /*value*/ extends _hs_value {
	public function validate(&$cursor) {
		if (!$cursor->sym instanceof _hs_S_Text_base)
			return FALSE;
		$val = $cursor->sym->validate($cursor);
		if ($val===FALSE || $val != $this->value)
			return FALSE;
		$cursor->next();
		return array('value' => $val);
	}
}

class _hs_c /*choice*/ extends _hs_group {
	public function validate(&$cursor, $parentslut = array()) {
		foreach ($cursor->sym->lut($this->lut) as $index) {
			$ret = $this->rules[$index]->validate($cursor, array_merge($parentslut, $this->lut));
			if ($ret!==FALSE) return $ret;
		}
		return FALSE;
	}
}

class _hs_i /*interleave*/ extends _hs_group {
	protected function repeatable() {return TRUE;}
	protected function optional() {return TRUE;}
	protected static function merge_comments(&$ret, &$cursor) {
		if ($cursor->comments!=='' && isset($ret['content']) && $ret['content']!==FALSE) {
			$ret['content'][] = $cursor->comments;
			$cursor->comments = '';
		}
	}

	protected function validate_lut(&$cursor, &$parentslut, $lut, &$children, &$empty, &$done, $ordered, $comments = TRUE) {
		$ret = FALSE;
		$set = FALSE;
		if (isset($lut)) {
			$done = 0;
			foreach ($lut as $index) {
				$rule = $this->rules[$index];
				$set = isset($children[$index]);
	
				if ($ordered && $set && !$rule->repeatable()) {
					++$done;
					continue;
				}
	
				$ret = $rule->validate($cursor, $parentslut);
	
				if ($ret===FALSE) continue;
				if ($ret===NULL) {
					if ($ordered && !$set)
						$children[$index] = NULL;
					continue;
				}
	
				if ($comments) self::merge_comments($ret, $cursor);
	
				if ($ordered) {
					if ($set)
						$children[$index] = array_merge_recursive($children[$index], $ret);
					else
						$children[$index] = $ret;
				} else {
					$children[] = $ret;
				}
	
				$empty = (isset($ret['content'])&&$ret['content']!==FALSE)?FALSE:$empty;
				break;
			}
			$done = ($ordered&&$done==count($lut))?TRUE:FALSE;
		} else {
			$done = FALSE;
		}

		return $ret;
	}

	public function validate(&$cursor, $parentslut = array(), $ordered = FALSE) {
		$children = array();
		if (isset($this->lut['-'])) foreach ($this->lut['-'] as $index)
			$children[$index] = NULL; /* !isset, but counts in count() */

		$alllut = array_merge($parentslut, $this->lut);

		$empty = TRUE;
		if ($ordered) { $goodcursor = $failcursor = $cursor->save(); }

		for (;;) {
			$ret = FALSE;
			$set = FALSE;

			while (!$cursor->ended() && $cursor->sym instanceof _hs_S_Element) {
				if (!isset($cursor->config->elements[$cursor->sym->value]))
					$cursor->embed();
				else break;
			}

			while (!$cursor->ended() && $cursor->sym instanceof _hs_S_Attribute) {
				if (!isset($cursor->config->attrs[$cursor->sym->value])) {
					$cursor->next();
				}
				else break;
			}

			if ($cursor->ended()) break;

			$lut = $cursor->sym->lut($this->lut);

			$ret = $this->validate_lut($cursor, $alllut, $lut, $children, $empty, $set, $ordered);

			if ($ret===FALSE||$ret===NULL) {
				/* Always skip bad attributes */
				if ($cursor->sym instanceof _hs_S_Attribute) {
					if (!$cursor->sym->lut($parentslut)) {
						$cursor->next();
						continue;
					}
					break;
				}

				/* If only attributes are valid, we're done */
				if (isset($this->lut['a'])) {
					break;
				}

				/* Always skip whitespace */
				if ($cursor->sym instanceof _hs_S_Text && $cursor->sym->whitespace) {
					$cursor->next();
					continue;
				}

				/* If this element isn't valid anywhere above us, just embed it */
				if (!$cursor->sym->lut($parentslut)) {
					if ($cursor->embed()) {
						continue;
					}
				}

				/* Don't try any harder if this is an optional element */
				if ($this->optional()) {
					break;
				}

				/* Skip any text */
				if ($cursor->sym instanceof _hs_S_Text_base) {
					$cursor->next();
					continue;
				}

				/* If this isn't an ordered group, give up. (Missing end tag?) */
				if (!$ordered) {
					break;
				}

				if ($set) {
					break;
				}

				/* Try the contents of the cursor element */
				if ($cursor->embed()) {
					continue;
				}

				/* If we have everything required, bail. */
				if (count($children) == count($this->rules)) {
					break;
				}

				/* Try skipping the cursor */
				$cursor->next();
				continue;
			} else {
				if ($ordered && !$this->optional()) $goodcursor = $cursor->save();
			}
		}

		if ($ordered && !$this->optional()) $cursor->restore($goodcursor);

		if ($empty && isset($this->lut['x'])) {
			$ret = $this->validate_lut($cursor, $alllut, $this->lut['x'], $children, $empty, $set, $ordered, FALSE);
			if (!$empty) {
				$cursor->restore($failcursor);
				return FALSE;
			}
		}

		if ($ordered && count($children) < count($this->rules)) {
			$cursor->restore($failcursor);
			return FALSE;
		}

		$content = array();
		foreach ($children as $child) {
			if (!isset($child)) continue;
			$content = array_merge_recursive($content, $child);
		}

		return empty($content)?NULL:$content;
	}
}

class _hs_g /*group*/ extends _hs_i {
	protected function repeatable() {return FALSE;}
	protected function optional() {return FALSE;}
	public function validate(&$cursor, $parentslut = array()) {
		return _hs_i::validate($cursor, $parentslut, TRUE);
	}
}

class _hs_0 /*zeroOrMore*/ extends _hs_g {
	protected function repeatable() {return TRUE;}
	protected function optional() {return TRUE;}
	public function validate(&$cursor, $parentslut = array(), $combined = array()) {
		while (($ret = _hs_g::validate($cursor, $parentslut)) !== FALSE)
			$combined = array_merge_recursive($combined, $ret);
		return empty($combined)?NULL:$combined;
	}
}

class _hs_1 /*oneOrMore*/ extends _hs_0 {
	protected function optional() {return FALSE;}
	public function validate(&$cursor, $parentslut = array()) {
		$combined = _hs_g::validate($cursor, $parentslut);
		if ($combined===FALSE) return FALSE;
		if ($combined===NULL) return NULL;
		return _hs_0::validate($cursor, $parentslut, $combined);
	}
}

class _hs_o /*optional*/ extends _hs_g {
	protected function optional() {return TRUE;}
	public function validate(&$cursor, $parentslut = array()) {
		$ret = _hs_g::validate($cursor, $parentslut, TRUE);
		if ($ret!==FALSE) return $ret;
		return NULL;
	}
}

abstract class _hs_named_group extends _hs_g {
	public $name;
	function __construct($name,$rules,$lut){$this->name=$name;$this->rules=&$rules;$this->lut=&$lut;}
	public function validate(&$cursor, $parentslut = array()) {
		if ($cursor->sym->value !== $this->name)
			return FALSE;
		$failcursor = $cursor->save();
		$cursor->enter();
		$comments = $cursor->comments;
		$cursor->comments = '';
		$ret = _hs_g::validate($cursor, $parentslut);
		if ($ret===FALSE) {
			$cursor->restore($failcursor);
			return FALSE;
		}

		if ($comments!=='' && isset($ret['content']) && $ret['content']!==FALSE)
			array_unshift($ret['content'], $comments);

		if (!$cursor->ended()) {
			$cursor->terminate();
		}

		$cursor->leave();

		return $ret;
	}
}

class _hs_e /*element*/ extends _hs_named_group {
	protected static function _filter($val) {
		return $val[0]!='a';
	}

	public function validate(&$cursor, $parentslut = array()) {
		$parentslut = array_filter($parentslut, array('_hs_e', '_filter'));
		$ret = _hs_named_group::validate($cursor, $parentslut);
		if ($ret===FALSE) return FALSE;

		$content = '<'.$this->name;
		if (isset($ret['attr']))
		foreach ($ret['attr'] as $attname => $attvalue) {
			$attvalue = _hs_htmlentities($attvalue);
			$content .= " {$attname}=\"{$attvalue}\"";
		}
		
		if (isset($ret['content']) && $ret['content'] === FALSE) {
			$content .= ' />';
		} else {
			$content .= '>';
			if (isset($ret['content'])) foreach ($ret['content'] as $child)
				$content .= $child;
			$content .= "</{$this->name}>";
		}

		return array('content' => array($content));
	}

	public function validate_content(&$cursor) {
		$failcursor = $cursor->save();
		$ret = _hs_g::validate($cursor, array());
		if ($ret===FALSE) {
			$cursor->restore($failcursor);
			return FALSE;
		}
		$content = '';
		if ($ret['content']) foreach ($ret['content'] as $child)
			$content .= $child;
		return $content;
	}
}

class _hs_a /*attribute*/ extends _hs_named_group {
	static $default_attr_rule;
	static $default_attr_lut;

	function __construct($name,$rules,$lut) {
		parent::__construct($name, $rules, $lut);
		if (empty($this->rules)) {
			if (!isset(self::$default_attr_rule)) {
				self::$default_attr_rule = array(new _hs_d_string());
				self::$default_attr_lut = array('s'=>array(0),'a'=>1);
			}

			$this->rules = &self::$default_attr_rule;
			$this->lut = &self::$default_attr_lut;
		}
	}
	public function validate(&$cursor, $parentslut = array()) {
		$ret = _hs_named_group::validate($cursor, $parentslut);
		if ($ret===FALSE || !isset($ret['value'])) return FALSE;
		return array('attr' => array($this->name => $ret['value']));
	}
}
