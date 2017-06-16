<?php

defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] User requested to join the team for $localeName";

$applicantHTML = $usersHelper->format($applicant);

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user $applicantHTML has asked to join the translation team for $localeName.</p>

<p>Visit the page with the <a href="$teamsUrl">members of the $localeName</a> translation team to accept or reject this request.</p>

EOT
;
