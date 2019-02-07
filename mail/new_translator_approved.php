<?php

defined('C5_EXECUTE') or die('Access Denied.');

/* @var string $siteName */
/* @var string $siteUrl */
/* @var string $recipientName */
/* @var League\URL\URLInterface $recipientAccountUrl */
/* @var CommunityTranslation\Service\User $usersHelper */

/* @var string $localeName */
/* @var Concrete\Core\User\UserInfo|null $applicant */
/* @var Concrete\Core\User\UserInfo|null $approvedBy */
/* @var bool $automatic */
/* @var string $teamsUrl */

$subject = "[$siteName] User accepted for the team $localeName";

$bodyHTML = "<p>Hi $recipientName,</p>";

$applicantHTML = $usersHelper->format($applicant);

if ($automatic) {
    $bodyHTML .= "<p>The user $applicantHTML has been automatically accepted as a translator of <a href=\"$teamsUrl\">$localeName</a>.</p>";
} else {
    $bodyHTML .= "<p>The user $applicantHTML has been accepted by " . $usersHelper->format($approvedBy) . " as a translator of <a href=\"$teamsUrl\">$localeName</a>.</p>";
}
