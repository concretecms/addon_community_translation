<?php

defined('C5_EXECUTE') or die('Access Denied.');

/* @var string $siteName */
/* @var string $siteUrl */
/* @var string $recipientName */
/* @var League\URL\URLInterface $recipientAccountUrl */
/* @var CommunityTranslation\Service\User $usersHelper */

/* @var Concrete\Core\Entity\User\User|null $requestedBy */
/* @var string $localeName */
/* @var string $teamsUrl */
/* @var string $notes */

$subject = "[$siteName] New locale requested: $localeName";

$requestedByHTML = $usersHelper->format($requestedBy);

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>
<p>The user $requestedByHTML has requested the creation of a new translation team for $localeName.</p>
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
