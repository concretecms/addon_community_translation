<?php

defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New locale approved: $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user <i>$approverName</i> has just approved the creation of the new translation team for $localeName that was requested by $requestedBy on $requestedOn.</p>

<p>You can view the details of this new team <a href="$teamUrl">here</a>.</p>

EOT
;
