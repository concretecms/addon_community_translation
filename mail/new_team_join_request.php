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
 * @var string $teamsUrl
 */

$applicantHTML = $userService->format($applicant);

$subject = "[{$siteName}] User requested to join the team for {$localeName}";

$bodyHTML = <<<EOT
<p>Hi {$recipientName},</p>

<p>The user {$applicantHTML} has asked to join the translation team for {$localeName}.</p>

<p>Visit the page with the <a href="{$teamsUrl}">members of the {$localeName}</a> translation team to accept or reject this request.</p>

EOT
;

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
