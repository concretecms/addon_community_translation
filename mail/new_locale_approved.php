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
 * @var Concrete\Core\Entity\User\User|null $approvedBy
 * @var Concrete\Core\Entity\User\User|null $requestedBy
 * @var string $teamsUrl
 */

$approvedByHTML = $userService->format($approvedBy);
$requestedByHTML = $userService->format($requestedBy);

$subject = "[{$siteName}] New locale approved: {$localeName}";

$bodyHTML = <<<EOT
<p>Hi {$recipientName},</p>

<p>The user {$approvedByHTML} has just approved the creation of the new translation team for {$localeName} that was requested by {$requestedByHTML}.</p>

<p>You can view the details of this new team <a href="{$teamsUrl}">here</a>.</p>

EOT
;

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
