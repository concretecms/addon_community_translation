<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $siteName
 * @var string $siteUrl
 * @var CommunityTranslation\Service\User $userService
 * @var string $recipientAccountUrl
 * @var string $recipientName
 * @var array $packageVersions
 */

$subject = "[{$siteName}] Updated translatable packages";

$bodyHTML = '<p>Hi ' . $recipientName . ',</p>';

if (count($packageVersions) === 1) {
    $htmlUrl = h($packageVersions[0]['url']);
    $htmlName = h($packageVersions[0]['name']);
    $bodyHTML .= "<p>A version of a translatable package has been updated: <a href=\"{$htmlUrl}\">{$htmlName}</a>.</p>";
} else {
    $bodyHTML .= '<p>The following package versions have been updated:</p><ul>';
    foreach ($packageVersions as $packageVersion) {
        $htmlUrl = h($packageVersion['url']);
        $htmlName = h($packageVersion['name']);
        $bodyHTML .= "<li><a href=\"{$htmlUrl}\">{$htmlName}</a></li>";
    }
    $bodyHTML .= '</ul>';
}

$bodyHTML .= '<p>You can disable notifications about new translatable package versions in the online editor.</p>';

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
