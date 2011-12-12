<<?php ?>?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>htmlsane ALPHA</title>
</head>
<body>
<?php
ini_set('error_reporting', E_ALL|E_STRICT);

include_once('htmlsane.php');

if (isset($_POST['text'])) {
	$config = new _hs_Config();
	$config->base_uri = NULL;
	if (isset($_SERVER['HTTP_HOST']))
		$config->base_uri = _hs_parse_uri('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	if (!isset($config->base_uri) || $config->base_uri === FALSE)
		$config->base_uri = _hs_parse_uri('http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI']);

	if (get_magic_quotes_gpc()) {
		$_POST['text'] = stripslashes($_POST['text']);
		trigger_error("Magic quotes is enabled.  Please disable them.");
	}
	$validated = htmlsane_validate($_POST['text'], $config);
} else {
	$validated = 'Break me!<br />';
}
?>
	<ul>
		<li>Beware that the "style" attribute is not yet validated, but is allowed.</li>
	</ul>
	<form method="post" action="" accept-charset="UTF-8">
		<textarea rows="20" cols="100" name="text" style="display:block;"><?php print(_hs_htmlentities($validated)); ?></textarea>
		<input type="submit" style="margin:1em;" />
	</form>
	<div style="border:1px solid black;margin:2em;padding:1em;"><?php print($validated);?></div>
	<div style="background:#aaa;">Some text after that...</div>
</body>
</html>
