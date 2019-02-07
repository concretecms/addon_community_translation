<?php

defined('C5_EXECUTE') or die('Access Denied.');

/* @var string $siteName */
/* @var string $siteUrl */
/* @var string $recipientName */
/* @var League\URL\URLInterface $recipientAccountUrl */
/* @var CommunityTranslation\Service\User $usersHelper */

/* @var string $localeName */
/* @var Concrete\Core\User\UserInfo|null $applicant */
/* @var Concrete\Core\User\UserInfo|null $rejectedBy */
/* @var string $teamsUrl */

$subject = "[$siteName] User denied for the team $localeName";

$applicantHTML = $usersHelper->format($applicant);
$rejectedByHTML = $usersHelper->format($rejectedBy);

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The request of $applicantHTML to join the <a href="$teamsUrl">$localeName</a> translation group has been denied by $rejectedByHTML.</p>

EOT
;
