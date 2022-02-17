<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $siteName
 * @var string $siteUrl
 * @var CommunityTranslation\Service\User $userService
 * @var string $recipientAccountUrl
 * @var string $recipientName
 * @var string|null $specificForLocale
 * @var array $comments
 */

$subject = "[{$siteName}] " . ($specificForLocale === null ? 'New comments about translatable strings' : "New comments about translations for {$specificForLocale}");

$bodyHTML = <<<EOT
<p>Hi {$recipientName},</p>

<p>The following new comments about translations have been posted:</p>

EOT
;
foreach ($comments as $comment) {
    $authorHTML = $userService->format($comment['author']);
    $translatableHTML = h($comment['translatable']);
    $bodyHTML .= <<<EOT
<p>
    <b>{$comment['date']} by {$authorHTML} about string <a href="{$comment['link']}">{$translatableHTML}</a></b><br />
    <blockquote>{$comment['messageHtml']}</blockquote><br />
</p>
EOT
    ;
}

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
