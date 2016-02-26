<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] User denied for the team $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The request of <i>$applicant</i> to join the <a href="$teamUrl">$localeName</a> translation group has been denied by <i>$operator</i>.</p>

EOT
;
