<?php

defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New translation strings need review for $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user <i>$translatorName</i> submitted $numTranslations translations that need review <a href="$packageUrl">for package $packageName</a>.</p>

<p>You can view all the translations awaiting approval <a href="$allUnreviewedUrl">here</a>.</p>

EOT
;
