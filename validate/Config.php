<?php

class _hs_Config {
	public $base_uri = array(
		'scheme' => 'http',
		'host' => 'zenthought.org',
		'path' => array('', 'tmp', ''),
	);
	public $url_force = 1; /* Force absolute */

	public $xhtml_end_tag = TRUE;

	public $medias = array(
		'screen'=>1,'tty'=>1,'tv'=>1,'projection'=>1,'handheld'=>1,
		'print'=>1,'braille'=>1,'aural'=>1,'all'=>1
	);
	public $charsets = array(
		'BIG5'=>'BIG5',
		'BIG5HKSCS'=>'BIG5-HKSCS',
		'CP1251'=>'CP1251',
		'EUCKR'=>'EUC-KR',
		'EUCJP'=>'EUC-JP',
		'EUCTW'=>'EUC-TW',
		'GB2312'=>'GB2312',
		'GB18030'=>'GB18030',
		'GBK'=>'GBK',
		'ISO88591'=>'ISO-8859-1',
		'ISO88592'=>'ISO-8859-2',
		'ISO88593'=>'ISO-8859-3',
		'ISO88594'=>'ISO-8859-4',
		'ISO88595'=>'ISO-8859-5',
		'ISO88596'=>'ISO-8859-6',
		'ISO88597'=>'ISO-8859-7',
		'ISO88598'=>'ISO-8859-8',
		'ISO88599'=>'ISO-8859-9',
		'ISO885910'=>'ISO-8859-10',
		'ISO885911'=>'ISO-8859-11',
		'ISO885912'=>'ISO-8859-12',
		'ISO885913'=>'ISO-8859-13',
		'ISO885914'=>'ISO-8859-14',
		'ISO885915'=>'ISO-8859-15',
		'ISO885916'=>'ISO-8859-16',
		'KOI8R'=>'KOI8-R',
		'KOI8U'=>'KOI8-U',
		'TIS620'=>'TIS-620',
		'USASCII'=>'US-ASCII',
		'UTF8'=>'UTF-8',
		'UTF16'=>'UTF-16',
		'UTF16BE'=>'UTF-16BE',
		'UTF16LE'=>'UTF-16LE',
	);
	public $linktypes = array(
		'alternate'=>1,'stylesheet'=>1,'start'=>1,'next'=>1,'prev'=>1,
		'contents'=>1,'index'=>1,'glossary'=>1,'copyright'=>1,
		'chapter'=>1,'section'=>1,'subsection'=>1,'appendix'=>1,
		'help'=>1,'bookmark'=>1,
	);

	public $scripts = FALSE;
	public $stylesheets = FALSE;

	public $attrvs = array(
		'style' => array('_hs_av', 'a_style'),
		'class' => array('_hs_av', 'a_class'),
	);

	public $styles = array();
	public $classes = array();

	public $elements = array(
		'br'=>1,'span'=>1,'abbr'=>1,'acronym'=>1,'cite'=>1,'code'=>1,
		'dfn'=>1,'em'=>1,'kbd'=>1,'q'=>1,'samp'=>1,'strong'=>1,
		'var'=>1,'div'=>1,'p'=>1,'address'=>1,'blockquote'=>1,'pre'=>1,
		'h1'=>1,'h2'=>1,'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,'a'=>1,'dl'=>1,
		'dt'=>1,'dd'=>1,'ol'=>1,'ul'=>1,'li'=>1,'ins'=>1,'del'=>1,
		'bdo'=>1,'ruby'=>1,'rbc'=>1,'rtc'=>1,'rb'=>1,'rt'=>1,'rp'=>1,
		'b'=>1,'big'=>1,'i'=>1,'small'=>1,'sub'=>1,'sup'=>1,'tt'=>1,
		'hr'=>1,'img'=>1,'table'=>1,'caption'=>1,'thead'=>1,'tfoot'=>1,
		'tbody'=>1,'colgroup'=>1,'col'=>1,'tr'=>1,'th'=>1,'td'=>1,
	);

	public $attrs = array(
		'style'=>1,'id'=>1,'class'=>1,'title'=>1,'xml:lang'=>1,
		'dir'=>1,'cite'=>1,'href'=>1,'charset'=>1,'type'=>1,
		'hreflang'=>1,'rel'=>1,'rev'=>1,'datetime'=>1,'rbspan'=>1,
		'media'=>1,'src'=>1,'alt'=>1,'longdesc'=>1,'height'=>1,
		'width'=>1,'frame'=>1,'rules'=>1,'align'=>1,'char'=>1,
		'charoff'=>1,'valign'=>1,'scope'=>1,'summary'=>1,'border'=>1,
		'cellspacing'=>1,'cellpadding'=>1,'span'=>1,'abbr'=>1,
		'axis'=>1,'headers'=>1,'rowspan'=>1,'colspan'=>1,
	);

	public $ids = array();
	public $id_prefix = 'hs-';

	public $elem_trans = array(
		'u' => '<span style="text-decoration:underline;">',
		's' => '<span style="text-decoration:strike-through;">',
		'strike' => '<span style="text-decoration:strike-through;">',
		'center' => '<div style="text-align:center;">',
	);

	public $attr_trans = array(
		'a.name' => 'id',
		'applet.name' => 'id',
		'form.name' => 'id',
		'frame.name' => 'id',
		'iframe.name' => 'id',
		'img.name' => 'id',
		'map.name' => 'id',
		'lang' => 'xml:lang',
	);

	public $allow_value = array(''=>'.*');
	public $allow_text = array(''=>'.*');
	public $allow_cdata = array();
	public $allow_comment = array(
		'' => array('^break$'=>array(),''=>array('[][]'=>'.')),
	);
	public $allow_decl = array();
	public $allow_xmlproc = array();

	public $uri_schemes = array(
		'http'=>1,'https'=>1,'ftp'=>1,'gopher'=>1,'news'=>1,'tel'=>1,
		'mailto'=>1,'sip'=>1,'sips'=>1,'sftp'=>1,'irc'=>1
	);
	public $uri_unverified = FALSE;
	public $uri_unverified_port = FALSE;
}
