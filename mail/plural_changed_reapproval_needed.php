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
 * @var int $numTranslations
 * @var string $approvalURL
 */

$subject = "[{$siteName}] Plural rules changed for {$localeName}";

$bodyHTML = <<<EOT
<p>Hi {$recipientName},</p>

<p>The rules to determine plurals have changed for the language {$localeName}.</p>

<p>Because of that, we've had to update {$numTranslations} strings with plural forms.<br />
Since this is an automated process, we marked those translations as to be reviwed: you <a href=\"{$approvalURL}\">should review and approve them</a></p>
EOT
;

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
