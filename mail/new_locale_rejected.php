<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New locale rejected: $localeName";

$deniedByHTML = $usersHelper->format($deniedBy);
$requestedByHTML = $usersHelper->format($requestedBy);

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user $deniedByHTML has just refused to create a new translation team for $localeName that was requested by $requestedByHTML.</p>

<p>You can view the list of currently available teams <a href="$teamsUrl">here</a>.</p>

EOT
;
