<?php

	require_once	'../AcCSS.php';
	require_once	'../AcCSSCollection.php';
	
	$css	=	file_get_contents('css/index.css');
	$accss	=	new AcCSS;
	$accss->addString($css);
	
?>
<!DOCTYPE html> 
<html xmlns="http://www.w3.org/1999/xhtml" id="nojs"> 
<head> 
	<meta charset="utf-8" /> 
	<title></title>
	<script type="text/javascript">document.documentElement.id='js';</script>
	<style>
		<?php echo $accss ?>
	</style>
</head> 
<body>
	<ul>
		<li>Index</li>
		<li><a href="#">Limit css scope</a></li>
	</ul>
</body>
</html>