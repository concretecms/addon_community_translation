<?php

defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New translation strings need review for $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user <i>$translatorName</i> submitted $numTranslations translations that <a href="$pageUrl">need review</a>.</p>

EOT
;
