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
 * @var Concrete\Core\User\UserInfo|null $rejectedBy
 * @var string $teamsUrl
 */

$subject = "[{$siteName}] User denied for the team {$localeName}";

$applicantHTML = $userService->format($applicant);
$rejectedByHTML = $userService->format($rejectedBy);
$teamsUrlHTML = h($teamsUrl);

$bodyHTML = <<<EOT
<p>Hi {$recipientName},</p>

<p>The request of {$applicantHTML} to join the <a href="{$teamsUrlHTML}">{$localeName}</a> translation group has been denied by {$rejectedByHTML}.</p>

EOT
;

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
