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
 * @var array $translations
 * @var string $approvalURL
 */

$subject = "[{$siteName}] New translations need review for {$localeName}";

$bodyHTML = "<p>Hi {$recipientName},</p>";
if (count($translations) === 1) {
    $userHTML = $userService->format($translations[0]['user']);
    $bodyHTML .= "<p>The user {$userHTML} submitted {$translations[0]['numTranslations']} translations that need review.</p>";
} else {
    $bodyHTML .= '<p>The following users submitted some translations that need review:</p>';
    $bodyHTML .= '<ul>';
    foreach ($translations as $t) {
        $userHTML = $userService->format($t['user']);
        $bodyHTML .= "<li>{$userHTML}: {$t['numTranslations']} translations</li>";
    }
    $bodyHTML .= '</ul>';
}

$bodyHTML .= "<p>You can view review these translations <a href=\"{$approvalURL}\">here</a>.</p>";

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
