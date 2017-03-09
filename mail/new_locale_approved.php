<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New locale approved: $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user <i>$approvedBy</i> has just approved the creation of the new translation team for $localeName that was requested by $requestedBy.</p>

<p>You can view the details of this new team <a href="$teamsUrl">here</a>.</p>

EOT
;
