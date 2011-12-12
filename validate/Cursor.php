<?php
/* Keeps track of the position in the lexical representation,
   depth in the parser, and other miscellaneous global things. */
class _hs_Cursor {
	public $sym;
	public $comments;
	public $config;

	protected $lexed;
	protected $offset;
	protected $tags, $ignore, $terminated;
	protected $attr;

	function __construct($config = NULL) {
		if (!isset($config)) $config = new _hs_Config();
		$this->config = $config;
	}

	/* Prepare for a new document/clear data */
	public function reset() {
		$this->offset = 0;
		$this->ignore = array();
		$this->tags = array();
		$this->comments = '';
		$this->terminated = FALSE;
		$this->lexed = array();
	}

	/* Load a new document */
	public function load($doc) {
		$this->reset();
		$this->lexed = _hs_Lexer::lex($doc, $this->config);
		$this->_sym();
		$this->translate();
	}

	/* Internal: symbol translation.  Ideally this would be a separate class, but
	   it needs access to Cursor internals, and there are no friend classes in PHP. */
	private function replace_symbol($sym, $offset, $replacement) {
		array_splice($this->lexed, $offset, 1, $replacement);
		$this->offset += count($replacement) - 1;

		$this->translate_content();

		/* We are now at the end symbol for this symbol */
		if ($sym instanceof _hs_S_Element) {
			if ($this->sym->value == $sym->value) {
				/* It's for this element; replace it */
				$this->lexed[$this->offset] = new _hs_S_ElementEnd($replacement[0]->value);
			} else if ($this->sym->value == $replacement[0]->value) {
				/* If it's for the replacement element, add a new one */
				array_splice($this->lexed, $this->offset, 0, array(new _hs_S_ElementEnd($replacement[0]->value)));
			}
		} else /* ($sym instanceof _hs_S_Attribute) */ {
			assert($this->sym instanceof _hs_S_AttributeEnd && $this->sym->value == $sym->value);
			$this->lexed[$this->offset] = new _hs_S_AttributeEnd($replacement[0]->value);
		}
	}

	private function translate_inner($offset) {
		$sym = $this->lexed[$offset];
		if ($sym instanceof _hs_S_Element) {
			if (isset($this->config->elem_trans[$sym->value])) {
				$trans = &$this->config->elem_trans[$sym->value];
				if (!is_array($trans))
					$trans = _hs_Lexer::lex($trans, $this->config);
				$this->replace_symbol($sym, $offset, $trans);
				return;
			}
		} else if ($sym instanceof _hs_S_Attribute) {
			if (isset($this->config->attr_trans[$sym->value])) {
				$trans = &$this->config->attr_trans[$sym->value];
			} else {
				$k = $this->tags[1].'.'.$sym->value;
				if (isset($this->config->attr_trans[$k]))
					$trans = &$this->config->attr_trans[$k];
			}
			if (isset($trans)) {
				if (!is_array($trans))
					$trans = array(new _hs_S_Attribute($trans));
				$this->replace_symbol($sym, $offset, $trans);
				return;
			}
		}
		$this->translate_content();
	}

	private function translate_content() {
		while (!$this->ended()) {
			if ($this->sym instanceof _hs_S_Start) {
				$offset = $this->offset;
				$this->enter();
				$this->translate_inner($offset);
				$this->leave();
			} else {
				$this->next();
			}
		}
	}

	protected function translate() {
		$saved = $this->save();
		$this->translate_content();
		$this->restore($saved);
	}

	/* Internal; process a symbol: skip comments and invalid end tags */
	private function _sym($comments = TRUE) {
		for (;;) {
			if (!isset($this->lexed[$this->offset])) {
				$this->sym = NULL;
				return;
			}
			$this->sym = $this->lexed[$this->offset];
			if ($this->sym instanceof _hs_S_Comment) {
				if ($comments)
					$this->comments .= $this->sym->validate($this);
				/* fallthrough */
			} else if ($this->sym instanceof _hs_S_AttributeEnd) {
				if ($this->tags[0] === NULL)
					break;
				/* fallthrough */
			} else if ($this->sym instanceof _hs_S_ElementEnd) {
				$depth = array_search($this->sym->value, $this->tags);
				if ($depth !== FALSE) {
					if ($depth || !$this->ignore[0])
						break;
					/* embedded element end; recursive call. */
					$this->leave();
					return;
				}
				/* fallthrough */
			} else {
				break;
			}
			++$this->offset;
		}
	}

	/* enter an element/attribute */
	public function enter($comments = TRUE) {
		if (!($this->sym instanceof _hs_S_Start))
			trigger_error("Nothing to enter", E_USER_ERROR);

		if ($this->sym instanceof _hs_S_Element) {
			array_unshift($this->tags, $this->sym->value);
		} else /* $this->sym instanceof _hs_S_Attribute */ {
			array_unshift($this->tags, NULL);
			$this->attr = $this->sym->value;
		}
		array_unshift($this->ignore, FALSE);
		++$this->offset;$this->_sym($comments);
	}

	/* Like enter, but skips attributes and no 'leave' should be used;
	   turns: <tag attr="val"> "foo" </tag> " bar"
	   in to: "foo" " bar" */
	public function embed() {
		if (!($this->sym instanceof _hs_S_Element)) return FALSE;
		$this->enter();
		$this->ignore[0] = TRUE;
		while ($this->sym instanceof _hs_S_Attribute)
			$this->next();
		return TRUE;
	}

	/* End the current enter(), even if we haven't reached its end tag. */
	/* Use for cases where there isn't an end tag in the proper spot. */
	/* You still have to call leave() after this. */
	public function terminate() {
		if ($this->sym instanceof _hs_S_AttValue) {
			/* Inside an attribute; just terminate the attribute */
			$this->next();
			$this->leave();
			return;
		}

		/* Skip any attributes to avoid integrating them with the parent element */
		while ($this->sym instanceof _hs_S_Attribute)
			$this->next();

		if ($this->sym instanceof _hs_S_ElementEnd) {
			/* Check to see if we reached the end of the element after skipping attributes */
			$depth = array_search($this->sym->value, $this->tags);
			if ($depth===0) {
				++$this->offset;$this->_sym();
			}
		}

		$this->terminated = TRUE;
	}

	/* Must have a matching enter(), must be called after terminate() too. */
	/* Can only be called when $this->ended() is true; use $this->next() until $this->ended(). */
	public function leave($comment_depth = NULL) {
		if ($this->terminated) {
			array_shift($this->tags);
			array_shift($this->ignore);
			$this->terminated = FALSE;
			$this->_sym(!isset($comment_depth) || count($this->tags) <= $comment_depth);
			return;
		}

		if ($this->sym && !($this->sym instanceof _hs_S_End)) {
			trigger_error('Not ended', E_USER_ERROR);
		}

		if ($this->sym) {
			if ($this->tags[0] !== NULL) {
				$depth = array_search($this->sym->value, $this->tags);
			} else {
				$depth = 0;
				$this->attr = NULL;
			}
		} else {
			$depth = 1;
		}
		array_shift($this->tags);
		array_shift($this->ignore);
		if (!$depth)
			++$this->offset;
		$this->_sym(!isset($comment_depth) || count($this->tags) <= $comment_depth);
	}

	/* Returns TRUE if the current enter() (or toplevel) has ended */
	public function ended() {
		return !$this->sym || $this->sym instanceof _hs_S_End || $this->terminated;
	}

	/* Advance to the next symbol that is at the current level */
	public function next() {
		if ($this->ended())
			return;

		if ($this->sym instanceof _hs_S_Start) {
			/* Skip an entire element/attribute, based on the symbols */
			$depth = count($this->tags);
			$this->enter(FALSE);
			do {
				if ($this->sym instanceof _hs_S_Start) {
					$this->enter(FALSE);
				} else {
					++$this->offset;$this->_sym(FALSE);
				}
				while ($this->ended() && count($this->tags) > $depth)
					$this->leave($depth);
			} while (count($this->tags) > $depth);
			return;
		}

		++$this->offset;$this->_sym();
	}

	/* Save/restore cursor state */
	/* WARNING: Only valid for the current depth and depths below it.  Do not $this->leave(). */
	public function save() {
		return array($this->offset, count($this->tags), $this->comments, $this->terminated);
	}

	public function restore(&$saved) {
		list($this->offset, $depth, $this->comments, $this->terminated) = $saved;
		$depth = count($this->tags) - $depth;
		$this->tags = array_slice($this->tags, $depth);
		$this->ignore = array_slice($this->ignore, $depth);
		$this->sym = isset($this->lexed[$this->offset])?$this->lexed[$this->offset]:NULL;
	}

	public function tag() {
		$len = count($this->tags);
		$i = 0;
		if ($len && $this->tags[0] === NULL) ++$i;
		for (; $i < $len && $this->ignore[$i]; ++$i)
			;
		if ($i >= $len) return NULL;
		return $this->tags[$i];
	}

	public function attr() {
		return $this->attr;
	}
}
