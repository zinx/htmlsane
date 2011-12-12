<?php

require_once('RelaxElements.php');
require_once('RelaxTree.php');
require_once('RelaxTreeLut.php');

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->load('xhtml11-flat.rng', LIBXML_COMPACT|LIBXML_NOCDATA);

$tree = new RelaxTree($dom);
RelaxTreeLut::build($tree->defines);

$names = array();
$counts = array();
foreach ($tree->defines as $def) {
	$def->write($tree->defines, $counts, $names, TRUE);
}

$index = 0; foreach ($counts as $key => &$count) {
	unset($counts[$key]);
	if ($count <= 1) continue;
	$idxval = base_convert($index, 10, 36);
	$names[$key] = "\$_hs$idxval";
	++$index;
}

$defs = array();
print("<?php\n");
foreach ($tree->defines as $name => $def) {
	$defs[] = "'$name'=>".$def->write($tree->defines, $counts, $names);
//	print("// '$name'=>$def\n");
}

print("\$_hs_spec = array(\n".implode(",\n",$defs)."\n);\n");
print("\$_hs_spec_elems = array('".implode("','",RelaxElement::$names)."');\n");
print("\$_hs_spec_attrs = array('".implode("','",RelaxAttribute::$names)."');\n");
