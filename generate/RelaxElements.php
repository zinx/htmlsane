<?php

abstract class RelaxBase {
	public $toplevel = FALSE; // hack

	public function __construct() {
	}

	public function __toString() {
		return $this->name() . '()';
	}

	public function toKey() {
		if ($this->toplevel)
			return "_hs_r('".$this->toplevel."')";
		return $this->name();
	}

	private static function childToKey($child) {
		if (is_a($child, 'RelaxBase')) return $child->toKey();
		if (is_array($child)) return self::arrayToKey($child);
		if (is_string($child)) return "'$child'";
		if (is_numeric($child)) return $child;
		if (is_bool($child)) return $child?1:0;
		trigger_error("Unknown child element type: ".gettype($child));
	}

	protected static function arrayToKey(array $array) {
		$children = array();
		if (isset($array[0])) {
			foreach ($array as $child)
				$children[] = self::childToKey($child);
		} else {
			foreach ($array as $key => $child)
				$children[] = "'$key'=".self::childToKey($child);
		}
		return '('.implode(',',$children).')';
	}

	private function write_child($child, &$defines, &$counts, &$names, $generate, $parent = NULL) {
		if (is_a($child, 'RelaxBase')) return $child->write($defines, $counts, $names, $generate, $parent);
		if (is_array($child)) return self::write_array($child, $defines, $counts, $names, $generate, $parent);
		if (is_string($child)) return "'$child'";
		if (is_numeric($child)) return $child;
		if (is_bool($child)) return $child?1:0;
		trigger_error("Unknown child element type: ".gettype($child));
	}

	private function output_array(array $array, &$defines, &$counts, &$names, $generate, $parent = NULL) {
		$children = array();
		if (isset($array[0])) {
			foreach ($array as $child)
				$children[] = self::write_child($child, $defines, $counts, $names, $generate, $parent);
		} else {
			foreach ($array as $key => $child)
				$children[] = "'$key'=>".self::write_child($child, $defines, $counts, $names, $generate, $parent);
		}
		return "array(".implode(',',$children).")";
	}

	final protected function write_array(array $array, &$defines, &$counts, &$names, $generate, $parent = NULL) {
		$key = self::arrayToKey($array);
		if (isset($names[$key])) {
			$name = $names[$key];
			if (!$counts[$name]++)
				return "$name=".self::output_array($array, $defines, $counts, $names, $generate, $parent);
			return "&$name";
		} else if ($generate) {
			if ($counts[$key]++)
				return;
		}
		return self::output_array($array, $defines, $counts, $names, $generate, $parent);
	}

	final public function write(&$defines, &$counts, &$names, $generate = FALSE, $parent = NULL) {
		$key = $this->toKey();
		if (isset($names[$key])) {
			$name = $names[$key];
			if (!$counts[$name]++)
				return "$name=".$this->output($defines, $counts, $names, $generate, $parent);
			return "&$name";
		} else if ($generate) {
			if ($counts[$key]++)
				return;
		}
		return $this->output($defines, $counts, $names, $generate, $parent);
	}

	protected function output(&$defines = array(), &$counts = array(), &$names = array(), $generate = FALSE) {
		$name = $this->name();
		return "new {$name}()";
	}

	abstract public function lutval();

	public function name() {
		$class = get_class($this);
		$len = strlen($class);
		$len = 5 + strlen($len) + $len; /* O:$len:"$class" */
		$obj = unserialize('a'.substr(serialize($this),$len));
		return $obj["\0$class\0name"];
	}
}

/**/

abstract class RelaxGroupBase extends RelaxBase {
	public $children;
	public $lut;

	function __construct($children) {$this->children = &$children;}
	public static function nest($children) {
		if (count($children) < 1)
			return NULL;

		$class = RelaxGroupBase::get_called_nest();
		return new $class(&$children);
	}

	public function __toString() {
		$children = array();

		foreach ($this->children as &$child)
			$children[] = ''.$child;

		$luts = array();
		if (isset($this->lut)) {
			foreach ($this->lut as $lutval => $indexes) {
				if (is_array($indexes))
					$luts[] = "'$lutval'=>(".implode(',',$indexes).")";
				else
					$luts[] = "'$lutval'=>$indexes";
			}
		}

		return $this->name().'(('.implode(',',$children).'),('.implode(',',$luts).'))';
	}

	public function toKey() {
		if ($this->toplevel)
			return "_hs_r('".$this->toplevel."')";
		return $this->name() . self::arrayToKey($this->children);
	}

	protected function output(&$defines = array(), &$counts = array(), &$names = array(), $generate = FALSE) {
		$name = $this->name();
		return "new {$name}("
			.self::write_array($this->children, $defines, $counts, $names, $generate, $this).','
			.self::write_array($this->lut, $defines, $counts, $names, $generate, $this)
			.")";
	}

	public function lutval() {
		$lutvals = array();
		foreach ($this->children as &$child) {
			$lutvals = array_merge($lutvals, $child->lutval());
		}
		return $lutvals;
	}

	protected static function get_called_nest() {
		$bt = debug_backtrace();
		list(,$last_frame) = each($bt);
		while (list(,$frame) = each($bt)) {
			if ($frame['function'] == 'call_user_func' && count($frame['args'][0]) == 2) {
				list($frame['class'],$frame['function']) = $frame['args'][0];
				if (is_string($frame['class']) && substr($frame['class'],0,1) != '$')
					$frame['type'] = '::';
				$frame['USER_FUNCTION'] = TRUE;
			}
			if ($frame['type'] !== '::' || $frame['function'] !== 'nest')
				break;
			$last_frame = $frame;
		}

		if (!$last_frame['USER_FUNCTION']) {
			$lines = file($last_frame['file']);
			$line = $last_frame['line'];
			while (--$line > 0 && stripos($lines[$line], '::nest') === FALSE)
				;
			$line = $lines[$line];

			if (stripos($line, $last_frame['class']) === FALSE) {
				preg_match('/([a-zA-Z0-9\_]+)::nest\(/', $line, $matches);
				if (!isset($matches[1])) {
					print("\$last_frame:\n");
					print_r($last_frame);
					print("BACKTRACE:\n");
					print_r($bt);
					trigger_error("Unable to get called class of ::nest in $line\n");
					return FALSE;
				}
				$last_frame['class'] = $matches[1];
			}
		}

		return $last_frame['class'];
	}

	public static function subnest($type, $children) {
		return call_user_func(array($type,'nest'), &$children);
	}
}

class RelaxInterleave extends RelaxGroupBase {
	private $name = '_hs_i';
	public static function nest(&$children) {
		if (count($children) < 1)
			return NULL;

		foreach ($children as $order => $child) {
			switch (get_class($child)) {
			case 'RelaxOptional':
				array_splice($children, $order, 1, array(self::subnest('RelaxGroup',&$child->children)));
				break;
			case 'RelaxZeroOrMore':
			case 'RelaxOneOrMore':
				if (count($child->children) > 1) {
					array_splice($children, $order, 1, array(self::subnest('RelaxGroup',&$child->children)));
					break;
				}
				/* fallthrough */
			case 'RelaxInterleave':
				array_splice($children, $order, 1, &$child->children);
				break;
			}
		}

		$class = RelaxGroupBase::get_called_nest();
		return new $class(&$children);
	}

	public function lutval() {
		$lutval = parent::lutval();
		$lutval[] = '-';
		return $lutval;
	}
}

class RelaxGroup extends RelaxGroupBase {
	private $name = '_hs_g';
	public static function nest($children) {
		if (count($children) < 1)
			return NULL;

		$class = RelaxGroupBase::get_called_nest();

		if (count($children) == 1 && $class === 'RelaxGroup') {
			$child = &$children[0];
			return $child;
		}

		foreach ($children as $order => &$child) {
			switch (get_class($child)) {
			case 'RelaxGroup':
				array_splice($children, $order, 1, &$child->children);
				break;
			}
		}

		return new $class(&$children);
	}
}

class RelaxOptional extends RelaxGroup {
	private $name = '_hs_o';
	public static function nest($children) {
		if (count($children) < 1)
			return NULL;

		if (count($children) == 1) {
			$child = &$children[0];
			switch (get_class($child)) {
			case 'RelaxGroup':
				$class = RelaxGroupBase::get_called_nest();
				return call_user_func(array($class,'nest'),&$child->children);
			case 'RelaxOneOrMore':
				return RelaxZeroOrMore::nest(&$child->children);
			case 'RelaxOptional':
			case 'RelaxZeroOrMore':
				return $child;
			}
		}

		return RelaxGroup::nest(&$children);
	}
	public function lutval() {
		$lutval = parent::lutval();
		$lutval[] = '-';
		return $lutval;
	}
}

class RelaxZeroOrMore extends RelaxOptional {
	private $name = '_hs_0';
	public static function nest($children) {
		if (count($children) < 1)
			return NULL;

		if (count($children) == 1)
			return self::subnest('RelaxInterleave',&$children);

		$all_optional = TRUE;
		foreach ($children as $order => &$child) {
			switch (get_class($child)) {
			case 'RelaxOptional':
			case 'RelaxZeroOrMore':
			case 'RelaxInterleave':
				continue;
			}
			$all_optional = FALSE;
			break;
		}
		if ($all_optional)
			return self::subnest('RelaxInterleave',&$children);

		return RelaxOptional::nest(&$children);
	}
}

class RelaxOneOrMore extends RelaxGroup {
	private $name = '_hs_1';
	public static function nest($children) {
		if (count($children) == 1) {
			$child = &$children[0];
			switch (get_class($child)) {
			case 'RelaxOptional':
			case 'RelaxZeroOrMore':
			case 'RelaxOneOrMore':
			case 'RelaxInterleave':
				return $child;
			}
		}

		return RelaxGroup::nest(&$children);
	}
}

class RelaxChoice extends RelaxGroupBase {
	private $name = '_hs_c';
	public static function nest($children) {
		if (count($children) < 1)
			return NULL;

		$class = RelaxGroupBase::get_called_nest();
		if (count($children) == 1 && $class === 'RelaxChoice') {
			$child = &$children[0];
			return $child;
		}

		foreach ($children as $order => &$child) {
			switch (get_class($child)) {
			case 'RelaxChoice':
				array_splice($children, $order, 1, &$child->children);
				break;
			}
		}

		return new $class(&$children);
	}
}

/**/

class RelaxEmpty extends RelaxBase {
	private $name = '_hs_x';
	public function lutval() {return array('x');}
}

class RelaxText extends RelaxBase {
	private $name = '_hs_t';
	public function lutval() {return array('t');}
}

abstract class RelaxValueBase extends RelaxBase {
	public $value;
	function __construct($value) {parent::__construct();$this->value = $value;}
	public function __toString() {
		return $this->name() . "('".$this->value."')";
	}

	public function toKey() {
		if ($this->toplevel)
			return "_hs_r('".$this->toplevel."')";
		return $this->name() . "('".$this->value."')";
	}

	protected function output(&$defines = array(), &$counts = array(), &$names = array(), $generate = FALSE) {
		$name = $this->name();
		return "new {$name}('{$this->value}')";
	}
}

class RelaxData extends RelaxValueBase {
	private $name = '_hs_d';
	public function lutval() {return array('s');}
	protected function output(&$defines = array(), &$counts = array(), &$names = array(), $generate = FALSE) {
		$name = $this->name();
		return "new {$name}_{$this->value}()";
	}
}

class RelaxValue extends RelaxValueBase {
	private $name = '_hs_v';
	public function lutval() {return array('v'.$this->value);}
}

class RelaxRef extends RelaxValueBase {
	private $name = '_hs_r';
	public function lutval() {return array('r'.$this->value);}
	protected function output(&$defines = array(), &$counts = array(), &$names = array(), $generate = FALSE, $parent) {
		$child = $defines[$this->value];
		while (count($child->children) == 1) {
			if (get_class($child)=='RelaxGroup') {
				$child = &$child->children[0];
				continue;
			}

			switch (get_class($parent)) {
			case 'RelaxInterleave':
			case 'RelaxZeroOrMore':
				switch (get_class($child)) {
				case 'RelaxOptional':
				case 'RelaxChoice':
				case 'RelaxInterleave':
				case 'RelaxZeroOrMore':
				case 'RelaxOneOrMore':
					$parent = NULL;
					$child = &$child->children[0];
				}
				break;
			case 'RelaxOneOrMore':
				switch (get_class($child)) {
				case 'RelaxOneOrMore':
					$parent = NULL;
					$child = &$child->children[0];
				}
				break;
			case 'RelaxOptional':
				switch (get_class($child)) {
				case 'RelaxOptional':
				case 'RelaxInterleave':
				case 'RelaxZeroOrMore':
					$parent = NULL;
					$child = &$child->children[0];
				}
				break;
			}
			break;
		}
		return $child->output($defines, $counts, $names, $generate, $this);
	}
}

/**/

abstract class RelaxNamedGroup extends RelaxGroup {
	public $group_name;
	function __construct($name, $children) {
		parent::__construct(&$children);
		$this->group_name = &$name;
	}
	public static function nest($name, $children) {
		$class = RelaxGroupBase::get_called_nest();
		$child = self::subnest('RelaxGroup',&$children);
		if (get_class($child) === 'RelaxGroup')
			return new $class(&$name, &$child->children);
		return new $class(&$name, $child?array(&$child):array());
	}

	public function __toString() {
		$children = array();

		foreach ($this->children as &$child)
			$children[] = ''.$child;

		$luts = array();
		if (isset($this->lut)) {
			foreach ($this->lut as $lutval => $indexes)
				if (is_array($indexes))
					$luts[] = "'$lutval'=>(".implode(',',$indexes).")";
				else
					$luts[] = "'$lutval'=>$indexes";
		}

		return $this->name()."('{$this->group_name}',(".implode(',',$children).'),('.implode(',',$luts).'))';
	}

	public function toKey() {
		if ($this->toplevel)
			return "_hs_r('".$this->toplevel."')";
		return $this->name().':'.$this->group_name.self::arrayToKey($this->children);
	}

	protected function output(&$defines = array(), &$counts = array(), &$names = array(), $generate = FALSE) {
		$name = $this->name();
		return "new {$name}('{$this->group_name}',"
			.self::write_array($this->children, $defines, $counts, $names, $generate).','
			.self::write_array($this->lut, $defines, $counts, $names, $generate)
			.")";
	}
}

class RelaxElement extends RelaxNamedGroup {
	private $name = '_hs_e';
	static public $names = array();
	function __construct($name, $children) {
		parent::__construct($name, &$children);
		if (!in_array($name, self::$names)) self::$names[] = $name;
	}
	public function lutval() {return array('e'.$this->group_name);}
}

class RelaxAttribute extends RelaxNamedGroup {
	private $name = '_hs_a';
	static public $names = array();
	function __construct($name, $children) {
		parent::__construct($name, &$children);
		if (!in_array($name, self::$names)) self::$names[] = $name;
	}
	public function lutval() {return array('a'.$this->group_name);}
}

class RelaxDataType extends RelaxNamedGroup {
	private $name = '_hs_dt';
	static public $names = array();
	function __construct($name, $children) {
		parent::__construct($name, &$children);
		if (!in_array($name, self::$names)) self::$names[] = $name;
	}
	protected function output(&$defines = array(), &$counts = array(), &$names = array(), $generate = FALSE) {
		$type = substr($this->group_name,0,-9);
		$name = $this->name();
		return "new {$name}_{$type}("
			.self::write_array($this->children, $defines, $counts, $names, $generate).','
			.self::write_array($this->lut, $defines, $counts, $names, $generate)
			.")";
	}
}
