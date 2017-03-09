<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New locale requested: $localeName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>
<p>The user <i>$requestedBy</i> has requested the creation of a new translation team for $localeName.</p>
<p>You can accept/refuse this new team <a href="$teamsUrl">here</a>.</p>
EOT
;

if ($notes) {
    $bodyHTML .= <<<EOT
<p>The applicant specified these notes:<br />
<blockquote>$notes</blockquote>
</p>
EOT
    ;
}
