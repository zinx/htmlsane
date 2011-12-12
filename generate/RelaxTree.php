<?php

class RelaxTree {
	public $defines;

	function __construct(DOMDocument $dom, $datatyperefs = array()) {
		$this->build_root($dom, $datatyperefs);
	}

	private function build_root(DOMDocument $dom, $datatyperefs) {
		for ($length = $dom->childNodes->length, $i = 0; $i < $length; ++$i) {
			if (is_a($dom->childNodes->item($i), 'DOMElement')) {
				$root = $dom->childNodes->item($i);
				break;
			}
		}

		for ($length = $root->childNodes->length, $i = 0; $i < $length; ++$i) {
			$def = $root->childNodes->item($i);
			if (!is_a($def, 'DOMElement')) continue;
			if ($def->tagName != 'define') continue;
			$name = $def->getAttribute('name');
			if (in_array($name, $datatyperefs)) continue;
			$this->defines[$name] = NULL;
		}

		for ($length = $root->childNodes->length, $i = 0; $i < $length; ++$i) {
			$def = $root->childNodes->item($i);
			if (!is_a($def, 'DOMElement')) continue;
			if ($def->tagName != 'define') continue;
			$name = $def->getAttribute('name');
			if (in_array($name, $datatyperefs)) continue;
			$combine = $def->getAttribute('combine');
			if (empty($combine)) $combine = 'group';
			$define = $this->build($combine, $def, $datatyperefs, $name);
			if ($define) {
				if (substr($name,-9)=='.datatype') {
					$this->defines[$name] = RelaxDataType::nest($name, array($define));
				} else {
					$this->defines[$name] = RelaxGroup::nest(array($define));
				}
				$this->defines[$name]->toplevel = $name;
			}
			else unset($this->defines[$name]);
		}

	}

	private function build($type, $element, $datatyperefs, $name = '') {
		$children = array();
		if (($length = $element->childNodes->length)) {
			for ($i = 0; $i < $length; ++$i) {
				$ce = $element->childNodes->item($i);
				if (!is_a($ce, 'DOMElement')) continue;
				$child = $this->build($ce->tagName, $ce, $datatyperefs);
				if ($child) $children[] = $child;
			}
		}
		if (isset($this->defines[$name])) {
			assert(substr($name,-9) != '.datatype');
			$children = array_merge($this->defines[$name]->children, $children);
		}

		$name = $element->getAttribute('name');
		$child = $element->childNodes->item(0);
		if (is_a($child, 'DOMText'));
			$value = $child->wholeText;
		$datatype = $element->getAttribute('type');

		if ($type == 'ref' && in_array($name, $datatyperefs)) {
			$type = 'data';
			$datatype = $name;
		}

		switch ($type) {
			case 'notAllowed': return NULL;

			case 'empty': return new RelaxEmpty();
			case 'text': return new RelaxText();
			case 'data': return new RelaxData($datatype);
			case 'value': return new RelaxValue($value);
			case 'ref':
				if (array_key_exists($name, $this->defines)) {
					return new RelaxRef($name);
				}
				return NULL;
			case 'optional': return RelaxOptional::nest($children);
			case 'choice': return RelaxChoice::nest($children);
			case 'interleave': return RelaxInterleave::nest($children);
			case 'group': return RelaxGroup::nest($children);
			case 'zeroOrMore': return RelaxZeroOrMore::nest($children);
			case 'oneOrMore': return RelaxOneOrMore::nest($children);

			case 'element': return RelaxElement::nest($name, $children);
			case 'attribute': return RelaxAttribute::nest($name, $children);
		}

		trigger_error("Unknown element type $type\n");
		return NULL;
	}
}
