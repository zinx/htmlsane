<?php
/* Turns tag soup in to symbol soup */

define('_HS_S',"\x20\x9\xD\xA");
/* Valid XML chars minus "compatibility characters", and minus '&' */
define('_HS_CHARS','\x9\xA\xD\x20-\x25\x27-\x7E\x85\xA0-\x{D7FF}\x{E000}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E0000}-\x{EFFFD}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}');
define('_HS_HEX','0123456789ABCDEFabcdef');
define('_HS_DEC','0123456789');

class _hs_Buffer {
	public $offset = 0;
	public $str;
	public $length;

	public function __construct($str) {
		$this->str = $str;
		$this->length = strlen($this->str);
	}

	public function tokenis($chars) {
		$len = strspn($this->str, $chars, $this->offset);
		$tok = substr($this->str, $this->offset, $len);
		$this->offset += $len;
		return $tok;
	}

	public function tokennot($chars) {
		$len = strcspn($this->str, $chars, $this->offset);
		$tok = substr($this->str, $this->offset, $len);
		$this->offset += $len;
		return $tok;
	}

	public function tokenuntil($str) {
		$pos = strpos($this->str, $str, $this->offset);
		if ($pos === FALSE) return FALSE;
		$tok = substr($this->str, $this->offset, $pos - $this->offset);
		$this->offset = $pos;
		return $tok;
	}

	public function consume($count) {
		if ($this->offset + $count > $this->length)
			$count = $this->length - $this->offset;
		$this->offset += $count;
		return substr($this->str, $this->offset - $count, $count);
	}

	public function consumestr($str) {
		$len = strlen($str);
		if (strcmp(substr($this->str, $this->offset, $len), $str) == 0) {
			$this->offset += $len;
			return TRUE;
		}
		return FALSE;
	}

	public function getchar() {
		return ($this->offset<$this->length)?$this->str[$this->offset++]:NULL;
	}

	public function char($n = 0) {
		return $this->str[$this->offset + $n];
	}

	public function consumechar($chars) {
		if ($this->offset>=$this->length)
			return FALSE;
		if (strpos($chars, $this->str[$this->offset]) !== FALSE)
			return ++$this->offset; /* TRUE */
		return FALSE;
	}

	public function consumegetchar($chars) {
		if ($this->offset>=$this->length)
			return '';
		$chr = $this->str[$this->offset];
		if (strpos($chars, $chr) !== FALSE)
			++$this->offset;
		return $chr;
	}

	public function consumenotchar($chars) {
		if ($this->offset>=$this->length)
			return FALSE;
		if (strchr($chars, $this->str[$this->offset]) === FALSE)
			return ++$this->offset; /* TRUE */
		return FALSE;
	}

	public function preg_consume($pattern, &$matches = array(), $flags = 0) {
		if (($ret = preg_match($pattern, substr($this->str, $this->offset), $matches, $flags)))
			$this->offset += strlen(($flags&PREG_OFFSET_CAPTURE)?$matches[0][0]:$matches[0]);
		return $ret;
	}

	public function preg_consume_all($pattern, &$matches = array(), $flags = 0) {
		if (($ret = preg_match_all($pattern, substr($this->str, $this->offset), $matches, $flags|PREG_OFFSET_CAPTURE))) {
			/* PREG_SET_ORDER not supported */
			$end = end($matches[0]);
			$this->offset = strlen($end[0]) + $end[1];
		}
		return $ret;
	}

	public function ended() {
		return $this->offset >= $this->length;
	}
}

abstract class _hs_Symbol_base {
	public $value;
	function __construct($text) { $this->value = strtolower($text); /*ASSume element/attribute names are lowercase*/}
	abstract public function lut(&$lut);
}

abstract class _hs_S_Text_base extends _hs_Symbol_base {
	function __construct($text) {
		$this->value = self::clean($text);
	}

	protected static function clean($text) {
		global $_hs_ents;
		$buf = new _hs_Buffer($text);
		$text = '';
		while (!$buf->ended()) {
			if ($buf->preg_consume('@^['._HS_CHARS.']+@u', $match))
				$text .= $match[0];
			if ($buf->getchar() == '&') {
				if ($buf->consumechar('#')) {
					if ($buf->consumechar('xX')) {
						$ent = $buf->tokenis(_HS_HEX);
						$ent = hexdec($ent);
					} else {
						$ent = $buf->tokenis(_HS_DEC);
						$ent = (int)$ent;
					}
					$consumed = $buf->consumechar(';');

					if ($ent) {
						$ent = utf8_chr($ent);
						if (preg_match('@^[&'._HS_CHARS.']$@u', $ent, $match)) {
							$ent = $match[0];
						} else {
							$ent = "&$ent".($consumed?';':'');
						}
					}
				} else {
					$ent = $buf->tokennot(_HS_S.'&<>;"\'');
					$consumed = $buf->consumechar(';');
					if (!isset($_hs_ents[$ent]))
						$ent = strtolower($ent);
					if (!isset($_hs_ents[$ent])) {
						if (preg_match_all('@[&'._HS_CHARS.']+$@u', $ent, $match))
							$ent = '&'.implode('',$match[0]).($consumed?';':'');
						else
							$ent = '&'.($consumed?';':'');
					} else {
						$ent = $_hs_ents[$ent];
						if ($ent) $ent = utf8_chr($ent);
						else $ent = "&$ent;";
					}
				}
				if ($ent)
					$text .= $ent;
			}
			$buf->preg_consume('@^[^&'._HS_CHARS.']+@u');
		}
		return $text;
	}

	public function validate(&$cursor, $res = NULL, $pre = '', $post = '') {
		if (!isset($res)) $res = $cursor->config->allow_value;
		$tag = $cursor->tag();
		if ($tag == NULL) $tag = '<TOPLEVEL>';
		if ($cursor->attr() !== NULL) {
			$attr = '.'.$cursor->attr();
			$tagattr = $tag.$attr;
			if (isset($res[$tagattr]))
				$tag = $tagattr;
			else if (isset($res[$attr]))
				$tag = $attr;
		}
		if (!isset($res[$tag])) $tag = '';
		if (isset($res[$tag])) {
			$re = $res[$tag];

			if (is_array($re)) {
				foreach ($re as $match => $replaces) {
					$match = strtr($match, array('`'=>'\\`'));
					if (!preg_match("`$match`u", $this->value, $matches))
						continue;

					$val = $this->value;
					foreach ($replaces as $pat => $rep) {
						$pat = strtr($pat, array('`'=>'\\`'));
						$val = preg_replace("`$pat`u", $rep, $val);
					}

					return $pre.$val.$post;
				}
				return FALSE;
			}

			$re = strtr($re, array('`'=>'\\`'));
			if (preg_match_all("`$re`u", $this->value, $matches)) {
				return $pre.implode('', $matches[0]).$post;
			}
		}
		return FALSE;
	}
}

class _hs_S_Text extends _hs_S_Text_base {
	public $whitespace = FALSE;
	public function lut(&$lut) {return isset($lut['t'])?$lut['t']:NULL;}

	function __construct($text) {
		parent::__construct($text);
		if (strspn($this->value, _HS_S) == strlen($this->value))
			$this->whitespace = TRUE;
	}

	public function validate(&$cursor, $res = NULL, $pre = '', $post = '') {
		if (!isset($res)) $res = $cursor->config->allow_text;
		$val = parent::validate($cursor, $res, $pre, $post);
		if ($val===FALSE) return FALSE;
		return _hs_htmlentities($val);
	}

	public function append($text) {
		$text = self::clean($text);
		$this->value .= $text;
		if (strspn($text, _HS_S) != strlen($text))
			$this->whitespace = FALSE;
	}
}

class _hs_S_CDATA extends _hs_S_Text {
	function __construct($text) {
		$this->value = $text; /* No clean. */
	}

	public function validate(&$cursor, $res = NULL, $pre = '<![CDATA[', $post = ']]>') {
		if (!isset($res)) $res = $cursor->config->allow_cdata;
		$val = _hs_S_Text_base::validate($cursor, $res, $pre, $post);
		if ($val === FALSE) return _hs_S_Text::validate($cursor);
		return $val;
	}
}

class _hs_S_Comment extends _hs_S_Text_base {
	public function validate(&$cursor, $res = NULL, $pre = '<!--', $post = '-->') {
		if (!isset($res)) $res = $cursor->config->allow_comment;
		return parent::validate($cursor, $res, $pre, $post);
	}
	public function lut(&$lut) {return array();}
}

class _hs_S_Decl extends _hs_S_Comment {
	public function validate(&$cursor, $res = NULL, $pre = '<!', $post = '>') {
		if (!isset($res)) $res = $cursor->config->allow_decl;
		return parent::validate($cursor, $res, $pre, $post);
	}
}

class _hs_S_XmlProc extends _hs_S_Comment {
	public function validate(&$cursor, $res = NULL, $pre = '<?', $post = '?>') {
		if (!isset($res)) $res = $cursor->config->allow_xmlproc;
		return parent::validate($cursor, $res, $pre, $post);
	}
}

abstract class _hs_S_Start extends _hs_Symbol_base {}
abstract class _hs_S_End extends _hs_Symbol_base {}

class _hs_S_Attribute extends _hs_S_Start {
	public function lut(&$lut) { $lv='a'.$this->value; return isset($lut[$lv])?$lut[$lv]:NULL; }
}

class _hs_S_AttValue extends _hs_S_Text_base {
	public function lut(&$lut) {
		$lutval = 'v'.$this->value;
		if (!isset($lut[$lutval])) return isset($lut['s'])?$lut['s']:NULL;
		return $lut[$lutval];
	}
}

class _hs_S_AttributeEnd extends _hs_S_End {
	public function lut(&$lut) { return array(); }
}

class _hs_S_Element extends _hs_S_Start {
	public function lut(&$lut) { $lv='e'.$this->value; return isset($lut[$lv])?$lut[$lv]:NULL; }
}

class _hs_S_ElementEnd extends _hs_S_End {
	public function lut(&$lut) { return array(); }
}

class _hs_Lexer {
	private static function addtext($text, &$lexed) {
		if (!strlen($text)) return;
		if (($tok = end($lexed)) && $tok instanceof _hs_S_Text)
			$tok->append($text);
		else
			array_push($lexed, new _hs_S_Text($text));
		$tok = end($lexed);
		if (!strlen($tok->value)) array_pop($lexed);
	}

	static public function lex($doc, &$config) {
		$lexed = array();
		$buf = new _hs_Buffer($doc);
		while (!$buf->ended()) {
			/* text */
			self::addtext($buf->tokennot('<'), $lexed);
			if ($buf->ended()) break;
			$buf->getchar(); /* < */

			switch ($buf->consumegetchar('!?/')) {
			case '!':
				if ($buf->consumestr('--')) {
					/* comment */
					$comment = $buf->tokenuntil('-->');
					if ($comment === FALSE) {
						$comment = $buf->tokennot('>');
						$endc = $buf->consumegetchar('>');
					} else {
						$endc = $buf->consume(3);
					}
					if (strpos($comment, '--') !== FALSE) {
						array_push($lexed, new _hs_S_Text('<!--'.$comment.$endc));
					} else {
						array_push($lexed, new _hs_S_Comment($comment));
					}
				} else if ($buf->consumestr('[CDATA[')) {
					/* CDATA */
					$cdata = $buf->tokenuntil(']]>');
					if ($cdata == FALSE) {
						$cdata = $buf->tokennot('>');
						$buf->consumechar('>');
					} else {
						$buf->consume(3);
					}
					array_push($lexed, new _hs_S_CDATA($cdata));
					continue;
				} else {
					$decl = $buf->tokenuntil('>');
					$buf->consumechar('>');
					$cstart = strpos($decl, '--');
					if ($cstart !== FALSE) {
						$comment = substr($decl, $cstart + 2);
						$decl = substr($decl, 0, $cstart);
						if (substr($comment, -2)!='--') {
							$append = $buf->tokenuntil('-->');
							if ($append !== FALSE) $comment .= $append;
						} else {
							$comment = substr($comment, 0, -2);
						}
						array_push($lexed, new _hs_S_Decl($decl));
						array_push($lexed, new _hs_S_Comment($comment));
					} else {
						array_push($lexed, new _hs_S_Decl($decl));
					}
				}
				break;
			case '?':
				/* xml processing instruction */
				$proc = $buf->tokennot('>');
				$buf->consumechar('>');
				if (substr($decl, -1)=='?')
					$decl = substr($decl, 0, -1);
				array_push($lexed, new _hs_S_XmlProc($proc));
				break;
			case '/':
				/* endtag */
				$buf->tokenis(_HS_S);
				$name = $buf->tokennot(_HS_S.'<>');
				$buf->tokennot('<>');
				$buf->consumechar('>');
				array_push($lexed, new _hs_S_ElementEnd($name));
				break;
			default:
				/* starttag */
				$name = $buf->tokennot(_HS_S.'<>');
				if (strlen($name) == 0) {
					self::addtext('<', $lexed);
					continue;
				}
				array_push($lexed, new _hs_S_Element($name));
				while (!$buf->ended()) {
					$buf->tokenis(_HS_S);
					$attname = $buf->tokennot(_HS_S.'<>=\'"');
					$next = $buf->consumegetchar('>=');
					if ($next == '<') break;
					if ($next == '>') {
						if ($config->xhtml_end_tag && $buf->char(-2) == '/')
							array_push($lexed, new _hs_S_ElementEnd($name));
						break;
					}
					if ($next != '=') { $buf->getchar(); continue; }
					$buf->tokenis(_HS_S);
					$next = $buf->consumegetchar('>"\'');
					if ($next == '<') break;
					if ($next == '>') {
						if ($config->xhtml_end_tag && $buf->char(-2) == '/')
							array_push($lexed, new _hs_S_ElementEnd($name));
						break;
					}
					if ($next == "'" || $next == '"') {
						$attvalue = $buf->tokennot($next.'<>');
						$buf->consumechar($next);
						$buf->tokennot($doc, _HS_S.'<>');
					} else {
						$attvalue = $buf->tokennot(_HS_S.'<>');
					}
					array_push($lexed, new _hs_S_Attribute($attname));
					array_push($lexed, new _hs_S_AttValue($attvalue));
					array_push($lexed, new _hs_S_AttributeEnd($attname));
				}
				break;
			}
		}
		return $lexed;
	}
}
