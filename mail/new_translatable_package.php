<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $siteName
 * @var string $siteUrl
 * @var CommunityTranslation\Service\User $userService
 * @var string $recipientAccountUrl
 * @var string $recipientName
 * @var array $packages
 */

$subject = "[{$siteName}] New translatable packages";

$bodyHTML = "<p>Hi {$recipientName},</p>";

if (count($packages) === 1) {
    $htmlUrl = h($packages[0]['url']);
    $htmlName = h($packages[0]['name']);
    $bodyHTML .= <<<EOT
<p>A new package is ready to be translated: {$htmlName}</p>
<p>Click <a href="{$htmlUrl}">here</a> to view the package details.</p>
EOT
    ;
} else {
    $bodyHTML .= '<p>The following new packages are ready to be translated:</p><ul>';
    foreach ($packages as $package) {
        $htmlUrl = h($package['url']);
        $htmlName = h($package['name']);
        $bodyHTML .= "<li><a href=\"{$htmlUrl}\">{$htmlName}</a></li>";
    }
    $bodyHTML .= '</ul>';
}
$htmlRecipientAccountUrl = h($recipientAccountUrl);
$bodyHTML .= "<p>You can disable notifications about new translatable packages <a href=\"{$htmlRecipientAccountUrl}\">here</a>.</p>";

// Let's avoid IDE warnings
if (false) return [$subject, $bodyHTML];
