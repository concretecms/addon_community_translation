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
 * @var Concrete\Core\User\UserInfo|null $requestedBy
 * @var Concrete\Core\User\UserInfo|null $deniedBy
 * @var string $teamsUrl
 */

$deniedByHTML = $userService->format($deniedBy);
$requestedByHTML = $userService->format($requestedBy);

$subject = "[{$siteName}] New locale rejected: {$localeName}";

$bodyHTML = <<<EOT
<p>Hi {$recipientName},</p>

<p>The user {$deniedByHTML} has just refused to create a new translation team for {$localeName} that was requested by {$requestedByHTML}.</p>

<p>You can view the list of currently available teams <a href="{$teamsUrl}">here</a>.</p>

EOT
;

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
