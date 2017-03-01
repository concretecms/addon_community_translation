<?php

defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] User accepted for the team $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user <i>$applicant</i> has been accepted by <i>$operator</i> as a translator of <a href="$teamUrl">$localeName</a>.</p>

EOT
;
