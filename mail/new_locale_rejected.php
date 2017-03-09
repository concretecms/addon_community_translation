<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New locale rejected: $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user <i>$deniedBy</i> has just refused to create a new translation team for $localeName that was requested by $requestedBy.</p>

<p>You can view the list of currently available teams <a href="$teamsUrl">here</a>.</p>

EOT
;
