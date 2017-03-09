<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New locale approved: $localeName";

$approvedByHTML = $usersHelper->format($approvedBy);
$requestedByHTML = $usersHelper->format($requestedBy);
$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user $approvedByHTML has just approved the creation of the new translation team for $localeName that was requested by $requestedByHTML.</p>

<p>You can view the details of this new team <a href="$teamsUrl">here</a>.</p>

EOT
;
