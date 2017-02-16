<?php
defined('C5_EXECUTE') or die('Access Denied.');

$this->inc('elements/header.php');
echo $html->css($view->getStylesheet('full_page.less'));

?></head>
<body>
	<div class="<?php echo $c->getPageWrapperClass(); ?>"><?php echo $innerContent; ?></div>

<?php

$this->inc('elements/footer.php');
