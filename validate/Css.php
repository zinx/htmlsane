<?php

include_once('debug.php');
include_once('util.php');
include_once('Lexer.php');

abstract class _hs_CS_token {}
class _hs_CS_PropSep extends _hs_CS_token {}
class _hs_CS_PropEnd extends _hs_CS_token {}
class _hs_CS_ArgBegin extends _hs_CS_token {}
class _hs_CS_ArgEnd extends _hs_CS_token {}
class _hs_CS_ArgSep extends _hs_CS_token {}

abstract class _hs_CS_value extends _hs_CS_token {
	public $value, $unit;
	function __construct($value) {$this->value = $value;$this->unit = NULL;}
	abstract public function __toString();
	public function op($op,$b) {
		switch ($op) {
		case '?':
			if (!$this->check_basic_op($b)) return FALSE;
			$cmp = $this->do_op($op, $b, NULL);
			return ($cmp<0)?-1:($cmp>0?1:0);
		case '+': case '-': case '|': case '^': case '&':
			if (!$this->check_basic_op($b)) return FALSE;
			return $this->do_op($op, $b, $this->unit);
		case '*':
			if (!$this->check_mul_op($b,$unit)) return FALSE;
			return $this->do_op($op, $b, $unit);
		case '/':
			if (!$this->check_div_op($b,$unit)) return FALSE;
			return $this->do_op($op, $b, $unit);
		case '%':
			if (!$this->check_mod_op($b)) return FALSE;
			return $this->do_op($op, $b, $this->$unit);
		case 'N': case '~':
			return $this->do_op($op, NULL, $this->unit);
		case 'T':
			return $this->do_op($op, NULL, NULL);
		case 'V':
			return $this->value?$this->do_op($op, NULL, NULL):FALSE;
		}
		return FALSE;
	}
	abstract protected function do_op($op,$b,$unit);
	abstract protected function check_basic_op($b);
	abstract protected function check_mul_op($b,&$unit);
	abstract protected function check_div_op($b,&$unit);
	abstract protected function check_mod_op($b,&$unit);
}

class _hs_CS_String extends _hs_CS_value {
	public function __toString() {
		return '"'.strtr($this->value, array(
			'\\'=>'\\\\',
			'"'=>'\\"',
			"\n"=>'\\00000a',
			"\f"=>'\\00000c',
			"\r"=>'\\00000d',
		)).'"';
	}
	protected function do_op($op,$b,$unit) {
		switch ($op) {
			case '?': return strcasecmp($this->value, $b->value);
			case '+': return new _hs_CS_String($this->value . $b->value);
			case '-': case '%': case '&': return strcasecmp($this->value, $b->value)==0?new _hs_CS_String(NULL):$this;
			case '*': case '/': return strcasecmp($this->value, $b->value)==0?$this:new _hs_CS_String(NULL);
			case 'T': return $this->value != NULL && $this->value != '';
			case 'V': return $this->value != NULL;
			default: return FALSE;
		}
	}
	protected function check_basic_op($b) {return ($b instanceof _hs_CS_String) && $this->value != NULL && $b->value != NULL;}
	protected function check_mul_op($b,&$unit) {return FALSE;}
	protected function check_div_op($b,&$unit) {return FALSE;}
	protected function check_mod_op($b,&$unit) {return FALSE;}
}

class _hs_CS_Name extends _hs_CS_String {
	public function __toString() {
		return strtr($this->value, array(
			'\\'=>'\\\\',
			'"'=>'\\"',
			"'"=>"\\'",
			"\n"=>'\\00000a',
			"\f"=>'\\00000c',
			"\r"=>'\\00000d',
		));
	}
}

class _hs_CS_URL extends _hs_CS_String {
	public function __toString() {
		$url = parent::__toString();
		return 'url('.$url.')';
	}
}

class _hs_CS_Numeric extends _hs_CS_value {
	public $value, $unit;
	function __construct($value,$unit) {
		$this->value = (float)$value;
		$this->unit = strtolower($unit);
		switch ($this->unit) {
			case '%': $this->value /= 100; break;
			case 'in': $this->value *= 72; $this->unit = 'pt'; break;
			case 'cm': $this->value *= 2.54*72; $this->unit = 'pt'; break;
			case 'mm': $this->value *= 254*72; $this->unit = 'pt'; break;
			case 'pc': $this->value /= 12.0; $this->unit = 'pt'; break;
			case 'rad': $this->value = $this->value*180.0/M_PI; $this->unit = 'deg'; break;
			case 'grad': $this->value = $this->value*200.0/180.0; $this->unit = 'deg'; break;
			case 's': $this->value = $this->value*1000; $this->unit = 'ms'; break;
			case 'khz': $this->value = $this->value*1000; $this->unit = 'hz'; break;
		}
	}

	public function __toString() {
		$val = $this->unit=='%'?100*$this->value:$this->value;
		switch ($this->unit) {
			case 'em': case 'ex': case 'pt':
				$prec = 2;
				break;
			default:
				$prec = 0;
				break;
		}
		$val = round($val, $prec);
		return $val.$this->unit;
	}

	protected function do_op($op,$b,$unit) {
		switch ($op) {
			case '+': return new _hs_CS_Numeric($this->value + $b->value, $unit);
			case '-': return new _hs_CS_Numeric($this->value - $b->value, $unit);
			case '*': return new _hs_CS_Numeric($this->value * $b->value, $unit);
			case '/': return new _hs_CS_Numeric($this->value / $b->value, $unit);
			case '%': return new _hs_CS_Numeric($this->value % $b->value, $unit);
			case '|': return new _hs_CS_Numeric($this->value | $b->value, $unit);
			case '^': return new _hs_CS_Numeric($this->value ^ $b->value, $unit);
			case '&': return new _hs_CS_Numeric($this->value & $b->value, $unit);
			case 'N': return new _hs_CS_Numeric(-$this->value, $unit);
			case '~': return new _hs_CS_Numeric(~$this->value, $unit);
			case 'T': return abs($this->value)>=0.000001;
			case 'V': return TRUE;
			default: return FALSE;
		}
	}

	protected function check_basic_op($b) {return ($b instanceof _hs_CS_Numeric) && $this->unit == $b->unit;}
	protected function check_mul_op($b,&$unit) {
		if (!($b instanceof _hs_CS_Numeric)) return FALSE;
		if ($this->unit && $b->unit && $this->unit != '%' && $b->unit != '%') return FALSE;
		if ($this->unit == '%') $unit = $b->unit;
		else $unit = $this->unit;
		return TRUE;
	}
	protected function check_div_op($b,&$unit) {
		if (!($b instanceof _hs_CS_Numeric)) return FALSE;
		if (abs($b->value)<=0.000001) return FALSE;
		if ($this->unit && $b->unit && $this->unit != '%' && $b->unit != '%' && $this->unit != $b->unit) return FALSE;
		if ($this->unit == $b->unit) $unit = ($this->unit=='%')?'%':'';
		else $unit = ($this->unit&&$this->unit!='%')?$this->unit:$b->unit;
		return TRUE;
	}
	protected function check_mod_op($b,&$unit) {
		if (!($b instanceof _hs_CS_Numeric)) return FALSE;
		if (abs($b->value)<=0.000001) return FALSE;
		if ($b->unit) return FALSE;
		$unit = $this->unit;
		return TRUE;
	}
}

class _hs_CS_Array extends _hs_CS_value implements Iterator {
	public $commas;
	function __construct($values,$commas=FALSE) {$this->value = $values;$this->commas = $commas;}
	public function __toString() {
		$v = array();
		foreach ($this->value as $av)
			$v[] = ''.$av;
		return implode($this->commas?', ':' ',$v);
	}
	protected function apply_op($op, $b = NULL) {
		$v = array();
		if ($b instanceof _hs_CS_Array) {
			if (count($this->value) != count($b->value))
				return FALSE;
			$v = array();
			foreach ($this->value as $k => $av) {
				if ($av instanceof _hs_CS_value)
					$val = $av->op($op, $b->value[$k]);
				else
					$val = FALSE;
				$v[] = $val;
			}
		} else {
			foreach ($this->value as $av) {
				if ($av instanceof _hs_CS_value)
					$val = $av->op($op, $b);
				else
					$val = FALSE;
				$v[] = $val;
			}
		}
		return $v;
	}
	public function op($op, $b) {
		switch ($op) {
			case '?':
				$vals = $this->apply_op($op, $b);
				if (!$vals) return FALSE;
				foreach ($vals as $v) {
					if ($v===FALSE) return FALSE;
					if ($v!=0) return $v;
				}
				return 0;
			case '+': case '-': case '*': case '/': case '%':
			case '|': case '^': case '&': case 'N': case '~':
				$vals = apply_op($op, $b);
				if (!$vals) return FALSE;
				$class = get_class($this);
				return new $$class($vals, $this->commas);
			case 'T':
				$vals = $this->apply_op($op,$b);
				if (!$vals) return FALSE;
				foreach ($vals as $v) if ($v) return TRUE;
				return FALSE;
			case 'V':
				$vals = $this->apply_op($op,$b);
				if (!$vals) return FALSE;
				foreach ($vals as $v) if (!$v) return FALSE;
				return TRUE;
			case 'A':// unary &
				$vals = $this->apply_op('V',NULL);
				if (!$vals) return FALSE;
				$vvals = array();
				foreach ($vals as $i=>$v)
					if ($v) $vvals[] = $this->value[$i];
				$class = get_class($this);
				return new $$class($vvals, $this->commas);
			default: return FALSE;
		}
	}
	protected function do_op($op,$b,$unit) {return FALSE;}
	protected function check_basic_op($b) {return FALSE;}
	protected function check_mul_op($b,&$unit) {return FALSE;}
	protected function check_div_op($b,&$unit) {return FALSE;}
	protected function check_mod_op($b,&$unit) {return FALSE;}

	public function rewind() { reset($this->value); }
	public function current() { return current($this->value); }
	public function key() { return key($this->value); }
	public function next() { return next($this->value); }
	public function valid() { return $this->value !== FALSE && current($this->value) !== FALSE; }
}

class _hs_CS_Color extends _hs_CS_Array {
	static $luma = array(0.21, 0.72, 0.07);
	public $alpha = TRUE;
	public function __toString() {
		$c = array();
		for ($i = 0; $i < 3; ++$i) {
			$c[$i] = (int)round(255 * $this->value[$i]->value);
			if ($c[$i] < 0) $c[$i] = 0;
			if ($c[$i] > 255) $c[$i] = 255;
		}
		if (!$this->alpha || $this->value[3]->value > (254.5/255.0)) {
			foreach ($c as &$v) $v = str_pad(dechex($v), 2, '0', STR_PAD_LEFT);
			return '#'.implode('',$c);
		}
		$c[] = round($this->value[3]->value, 3);
		return 'rgba('.implode(',',$c).')';
	}
	function __construct($vals, $size, $count) {
		if (is_string($vals)) {
			$vals = str_split($vals, $size);
			for ($i = 0; $i < $count; ++$i) {
				$vals[$i] = new _hs_CS_Numeric(
					hexdec($vals[$i].($size==1?$vals[$i]:''))*100/255,
					'%'
				);
			}
			if ($count == 4)
				array_push($vals, array_shift($vals));
			else
				$vals[3] = new _hs_CS_Numeric(100, '%');
		} else {
			for ($i = 0; $i < 3; ++$i) {
				if (!($vals[$i] instanceof _hs_CS_Numeric) || ($vals[$i]->unit != '' && $vals[$i]->unit != '%')) {
					$vals[$i] = FALSE;
					continue;
				}

				if ($vals[$i]->unit == '') {
					$vals[$i]->value = $vals[$i]->value/255.0;
					$vals[$i]->unit = '%';
				}
			}
			if ($count == 4) {
				if (!($vals[$i] instanceof _hs_CS_Numeric) || $vals[$i]->unit != '')
					$vals[$i] = FALSE;
				else
					$vals[$i]->unit = '%';
			} else {
				$vals[$i] = new _hs_CS_Numeric(100, '%');
			}
		}
		$this->value = $vals;
	}

	public function op($op,$b) {
		switch ($op) {
		case 'A': return FALSE;
		case '?':
			$vals = $this->apply_op('-',$b);
			if (!($vals instanceof _hs_CS_Color)||count($ret->value)!=4) return FALSE;
			$luma = 0;
			for ($i = 0; $i < 3; ++$i) {
				if (!$vals[$i] instanceof _hs_CS_Numeric) return FALSE;
				if ($vals[$i]->unit != '%') return FALSE;
				$luma += $this->luma[$i] * $vals[$i]->value;
			}
			$luma *= $vals[3]->value;
			return ($luma<0)?-1:($luma>0?1:0);
		case 'V':
			if (count($this->value)!=4) return FALSE;
			if (!parent::op($op,$b)) return FALSE;
			foreach ($this->value as $v) {
				if (!($v instanceof _hs_CS_Numeric)) return FALSE;
				if ($v->unit != '%') return FALSE;
			}
			return TRUE;
		default:
			$ret = parent::op($op,$b);
			if (!($ret instanceof _hs_CS_Color)||count($ret->value)!=4) return FALSE;
			return $ret;
		}
	}
}

class _hs_CS_Function extends _hs_CS_token {
	public $name, $args;
	function __construct($name,$args) {$this->name = $name;$this->args = $args;}
	public function __toString() {
		return $this->name.'('.implode(', ',$this->args).')';
	}
}

define('_HS_CSS_S', " \t\r\n\f");

class _hs_Css {
	static $props = array(
		'azimuth' => array('urad'),
		'background-attachment' => array('iscroll', 'ifixed', 'iinherit'),
		'background-color' => array('c', 'itransparent', 'iinherit'),
		'background-image' => array('U', 'inone', 'iinherit'),
		'background-position' => array(
			array('Lo', array('l', 'ileft', 'icenter', 'iright'), array('l', 'itop', 'icenter', 'ibottom')),
			array('Lu', array('ileft', 'icenter', 'iright'), array('itop', 'imiddle', 'ibottom')),
			'iinherit'
		),
		'background-repeat' => array('irepeat-x', 'irepeat-y', 'ino-repeat', 'iinherit'),
		'border-collapse' => array('icollapse', 'iseparate', 'iinherit'),
		'border-spacing' => array('LO', 'l', 'l'),
		'border-top-color' => array('c', 'itransparent', 'iinherit'),
		'border-right-color' => array('c', 'itransparent', 'iinherit'),
		'border-bottom-color' => array('c', 'itransparent', 'iinherit'),
		'border-left-color' => array('c', 'itransparent', 'iinherit'),
		'border-color' => array('LO', 'Rborder-top-color', 'Rborder-right-color', 'Rborder-bottom-color', 'Rborder-left-color'),
		'border-top-style' => array('inone', 'ihidden', 'idotted', 'idashed', 'isolid', 'idouble', 'igroove', 'iridge', 'iinset', 'ioutset'),
		'border-right-style' => array('inone', 'ihidden', 'idotted', 'idashed', 'isolid', 'idouble', 'igroove', 'iridge', 'iinset', 'ioutset'),
		'border-bottom-style' => array('inone', 'ihidden', 'idotted', 'idashed', 'isolid', 'idouble', 'igroove', 'iridge', 'iinset', 'ioutset'),
		'border-left-style' => array('inone', 'ihidden', 'idotted', 'idashed', 'isolid', 'idouble', 'igroove', 'iridge', 'iinset', 'ioutset'),
		'border-style' => array('LO', 'Rborder-top-style', 'Rborder-right-style', 'Rborder-bottom-style', 'Rborder-left-style'),
		'border-top-width' => array('l', 'ithin', 'imedium', 'ithick'),
		'border-right-width' => array('l', 'ithin', 'imedium', 'ithick'),
		'border-bottom-width' => array('l', 'ithin', 'imedium', 'ithick'),
		'border-left-width' => array('l', 'ithin', 'imedium', 'ithick'),
		'border-style' => array('LO', 'Rborder-top-width', 'Rborder-right-width', 'Rborder-bottom-width', 'Rborder-left-width'),
		'border' => array('Lu', array('l', 'ithin', 'imedium', 'ithick'), array('inone', 'ihidden', 'idotted', 'idashed', 'isolid', 'idouble', 'igroove', 'iridge', 'iinset', 'ioutset'), array('c', 'itransparent', 'iinherit')),
	);
	static $vprops = array(
		'background',
		'border-color',
		'border-style',
		'border-top',
		'border-right',
		'border-bottom',
		'border-left',
	);

	protected static function csstoken(&$buf, &$forceid, $dots = FALSE, $end = ",:;(){} \t", $consume = '') {
		$tok = '';
		$forceid = NULL;
		if ($dots) {
			if ($buf->consumestr('..'))
				return '..';
			$dots = '.';
		} else	$dots = '';
		while (!$buf->ended()) {
			$tok .= $buf->tokennot("\\\r\n\f<-".$end.$dots);
			$peek = $buf->char();
			if ($peek == '<') {
				if (!$buf->consumestr('<!--'))
					$tok .= $buf->getchar();
				continue;
			} else if ($peek == '-') {
				if (!$buf->consumestr('-->'))
					$tok .= $buf->getchar();
				continue;
			} else if ($peek == '.') {
				if (!$buf->consumechar('..')) {
					$tok .= $buf->getchar();
					continue;
				}
				return $tok;
			}
			if ($forceid === NULL && $tok != '') $forceid = FALSE;
			$ch = $buf->consumechar("\\\r\n\f".$consume);
			if ($ch == '\\') {
				if ($forceid === NULL) $forceid = TRUE;
				if ($buf->preg_consume('`^[0-9a-fA-F]{1,6}`u', $m)) {
					$ch = utf8_chr(hexdec($m[0]));
					if ($ch !== FALSE)
						$tok .= $ch;
				} else {
					$ch = $buf->consume(1);
					if ($ch == '\r') {
						$buf->consumechar('\n');
						continue;
					}
					if ($ch == '\n' || $ch == '\f')
						continue;
					$tok .= $ch;
				}
			} else {
				break;
			}
		}

		return $tok;
	}

	static public function lex_rule($props, $dots = FALSE) {
		$buf = new _hs_Buffer($props);
		$lexed = array();
		while (!$buf->ended()) {
			$buf->tokenis(_HS_CSS_S);

			if ($buf->consumestr('/*')) {
				if (!$buf->tokenuntil('*/')) break;
				continue;
			}

			if ($buf->consumechar('\'"')) {
				$quot = $buf->char(-1);
				$tok = self::csstoken($buf, $forceid, FALSE, $quot, $quot);
				$lexed[] = new _hs_CS_String($tok);
				continue;
			}

			$tok = self::csstoken($buf, $forceid, $dots);
			if ($tok != '') do {
				if ($dots && $tok == '..') {
					$lexed[] = new _hs_CS_RangeSep();
					break;
				}
				if (!$forceid) {
					$digits = strspn($tok, '0123456789.-+');
					if ($digits) {
						$lexed[] = new _hs_CS_Numeric(
							substr($tok, 0, $digits),
							substr($tok, $digits)
						);
						break;
					}
					if ($tok[0] == '#') {
						$tok = substr($tok, 1);
						$colors = strspn($tok, '0123456789abcdefABCDEF');
						if ($colors == 8) {
							$lexed[] = new _hs_CS_Color($tok, 2, 4);
							break;
						} else if ($colors >= 6) {
							$lexed[] = new _hs_CS_Color($tok, 2, 3);
							break;
						} else if ($colors == 4) {
							$lexed[] = new _hs_CS_Color($tok, 1, 4);
							break;
						} else if ($colors >= 3) {
							$lexed[] = new _hs_CS_Color($tok, 1, 3);
							break;
						}
					}
				}
				$lexed[] = new _hs_CS_Name($tok);
			} while (0);

			switch ($buf->consume(1)) {
				case '{': case '}': break;
				case ':': $lexed[] = new _hs_CS_PropSep(); break;
				case ';': $lexed[] = new _hs_CS_PropEnd(); break;
				case '(': $lexed[] = new _hs_CS_ArgBegin(); break;
				case ')': $lexed[] = new _hs_CS_ArgEnd(); break;
				case ',': $lexed[] = new _hs_CS_ArgSep(); break;
			}
		}
		return $lexed;
	}

	static public function parse_value(&$lexed) {
		$sym = current($lexed);
		if ($sym instanceof _hs_CS_ArgSep)
			next($lexed);

		if ($sym instanceof _hs_CS_ArgEnd || $sym instanceof _hs_CS_PropEnd ||
		    $sym instanceof _hs_CS_ArgSep || $sym instanceof _hs_CS_PropSep ||
		    $sym instanceof _hs_CS_RangeSep)
			return FALSE;

		if ($sym instanceof _hs_CS_Name) {
			$next = next($lexed);
			if (!($next instanceof _hs_CS_ArgBegin))
				return $sym;

			next($lexed);
			$arglist = self::parse_values($lexed, $t, TRUE);
			if (!$arglist) return FALSE;
			switch ($sym->value) {
			case 'url':
				if (count($arglist) != 1) return FALSE;
				if (!($arglist[0] instanceof _hs_CS_String)) return FALSE;
				return new _hs_CS_Function($sym->value, array(new _hs_CS_String($arglist[0]->value)));
			case 'rgb':
				if (count($arglist) != 3) return FALSE;
				$val = new _hs_CS_Color($arglist,0,3);
				if (!$val->op('V')) return FALSE;
				return $val;
			case 'rgba':
				if (count($arglist) != 4) return FALSE;
				$val = new _hs_CS_Color($arglist,0,4);
				if (!$val->op('V',NULL)) return FALSE;
				return $val;
			}
			//return new _hs_CS_Function($sym->value, $arglist);
			return FALSE;
		} else if ($sym instanceof _hs_CS_ArgBegin) {
			return FALSE;
		}

		next($lexed);
		return $sym;
	}

	static public function parse_values(&$lexed, &$islist = FALSE, $args = FALSE) {
		$plist = array();
		$islist = $args;
		$namebegin = 0;
		while ($sym = current($lexed)) {
			if ($sym instanceof _hs_CS_ArgEnd) {
				next($lexed);
				if (!$args) return FALSE;
				break;
			}

			if ($sym instanceof _hs_CS_PropSep)
				return FALSE;

			if ($sym instanceof _hs_CS_ArgSep || ($islist && $sym instanceof _hs_CS_PropEnd)) {
				$nameend = count($plist) - 1;
				if ($namebegin < $nameend) {
					$names = array();
					foreach (range($namebegin, $nameend) as $i) {
						if (!($plist[$i] instanceof _hs_CS_Name))
							return FALSE;
						$names[] = $plist[$i]->value;
					}
					array_splice($plist, $namebegin, $nameend - $namebegin + 1,
						array(new _hs_CS_String(implode(' ',$names))));
				}
				$namebegin = count($plist);
				$islist = TRUE;
				if ($sym instanceof _hs_CS_ArgSep) {
					next($lexed);
					continue;
				}
			}

			if ($sym instanceof _hs_CS_PropEnd) {
				if ($args) return FALSE;
				break;
			}

			if ($sym instanceof _hs_CS_RangeSep) {
				if ($args) return FALSE;
				next($lexed);
				$rval = self::parse_value($lexed, $islist);
				if (!$rval) return FALSE;
				$lval = array_pop($plist);
				$plist[] = new _hs_CS_Range($lval, $pval);
				continue;
			}

			$pval = self::parse_value($lexed, $islist);
			if (!$pval) return FALSE;
			$plist[] = $pval;
		}
		if (count($plist)) return $plist;
		return FALSE;
	}

	static public function parse_rule($lexed) {
		$props = array();
		for (reset($lexed); $sym = current($lexed); next($lexed)) {
			do {
				if (!$sym instanceof _hs_CS_Name)
					break; /* goto skip; */

				$sep = next($lexed);
				if (!($sep instanceof _hs_CS_PropSep))
					break; /* goto skip; */

				next($lexed);
				$plist = self::parse_values($lexed, $islist);
				if (!$plist)
					break; /* goto skip; */

				$end = current($lexed);
				if ($end && !($end instanceof _hs_CS_PropEnd))
					break; /* goto skip; */

				if (count($plist) > 1)
					$props[][$sym->value] = new _hs_CS_Array($plist, $islist);
				else
					$props[][$sym->value] = $plist[0];

				/* fallthrough to skip: */
			} while (0);

		/*skip:*/
			while (($sym = current($lexed)) && !($sym instanceof _hs_CS_PropEnd))
				next($lexed);
		}

		return $props;
	}
}

header('Content-Type: text/plain');

$rule =
'border-style: 1px solid black;
font-family: Arial, "Arial Unicode MS", sans;
font-family: Arial, Big Black Font, "Arial Unicode MS", sans;
background: url(foobar/baz.jpg);
background-image: "foobar/baz.jpg";
background-color: rgba(1,2,3,0.5);
font-color: #123;
border-color: #12345678;
border-top-color: #123456;
line-height: 1.3em !important;
width: 35in;
frequency: 1kHz;
time: 3s;
'
;

$lexed = _hs_Css::lex_rule($rule);
dprint(dfmt($lexed));

$parsed = _hs_Css::parse_rule($lexed);
dprint(dfmt($parsed));

foreach ($parsed as $key => $value) {
	print dfmt($value)."\n";
}
