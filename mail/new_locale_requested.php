<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $siteName
 * @var string $siteUrl
 * @var CommunityTranslation\Service\User $userService
 * @var string $recipientAccountUrl
 * @var string $recipientName
 * @var Concrete\Core\Entity\User\User|null $requestedBy
 * @var string $localeName
 * @var string $teamsUrl
 * @var string $notes
 */

$subject = "[{$siteName}] New locale requested: {$localeName}";

$requestedByHTML = $userService->format($requestedBy);

$bodyHTML = <<<EOT
<p>Hi {$recipientName},</p>
<p>The user {$requestedByHTML} has requested the creation of a new translation team for {$localeName}.</p>
<p>You can accept/refuse this new team <a href="{$teamsUrl}">here</a>.</p>
EOT
;

if ((string) $notes !== '') {
    $bodyHTML .= <<<EOT
<p>The applicant specified these notes:<br />
<blockquote>{$notes}</blockquote>
</p>
EOT
    ;
}

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
