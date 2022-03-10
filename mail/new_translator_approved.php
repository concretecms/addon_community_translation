<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $siteName
 * @var string $siteUrl
 * @var CommunityTranslation\Service\User $userService
 * @var string $recipientAccountUrl
 * @var string $recipientName
 * @var string $localeName
 * @var Concrete\Core\User\UserInfo|null $applicant
 * @var Concrete\Core\User\UserInfo|null $approvedBy
 * @var bool $automatic
 * @var string $teamsUrl
 */

$subject = "[{$siteName}] User accepted for the team {$localeName}";

$bodyHTML = "<p>Hi {$recipientName},</p>";

$applicantHTML = $userService->format($applicant);

if ($automatic) {
    $bodyHTML .= "<p>The user {$applicantHTML} has been automatically accepted as a translator of <a href=\"{$teamsUrl}\">{$localeName}</a>.</p>";
} else {
    $htmlApprover = $userService->format($approvedBy);
    $bodyHTML .= "<p>The user {$applicantHTML} has been accepted by {$htmlApprover} as a translator of <a href=\"{$teamsUrl}\">{$localeName}</a>.</p>";
}

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
