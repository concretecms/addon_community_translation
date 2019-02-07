<?php

defined('C5_EXECUTE') or die('Access Denied.');

/* @var string $siteName */
/* @var string $siteUrl */
/* @var string $recipientName */
/* @var League\URL\URLInterface $recipientAccountUrl */
/* @var CommunityTranslation\Service\User $usersHelper */

/* @var string $localeName */
/* @var Concrete\Core\Entity\User\User|null $approvedBy */
/* @var Concrete\Core\Entity\User\User|null $requestedBy */
/* @var string $teamsUrl */

$subject = "[$siteName] New locale approved: $localeName";

$approvedByHTML = $usersHelper->format($approvedBy);
$requestedByHTML = $usersHelper->format($requestedBy);
$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The user $approvedByHTML has just approved the creation of the new translation team for $localeName that was requested by $requestedByHTML.</p>

<p>You can view the details of this new team <a href="$teamsUrl">here</a>.</p>

EOT
;
