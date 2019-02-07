<?php

defined('C5_EXECUTE') or die('Access Denied.');

/* @var string $siteName */
/* @var string $siteUrl */
/* @var string $recipientName */
/* @var League\URL\URLInterface $recipientAccountUrl */
/* @var CommunityTranslation\Service\User $usersHelper */

/* @var array $packages */

$subject = "[$siteName] New translatable packages";

$bodyHTML = '<p>Hi ' . $recipientName . ',</p>';

if (count($packages) === 1) {
    $bodyHTML .= '
<p>A new package is ready to be translated: ' . h($packages[0]['name']) . '</p>
<p>Click <a href="' . $packages[0]['url'] . '">here</a> to view the package details.</p>';
} else {
    $bodyHTML .= '<p>The following new packages are ready to be translated:</p><ul>';
    foreach ($packages as $package) {
        $bodyHTML .= '<li><a href="' . $package['url'] . '">' . h($package['name']) . '</a></li>';
    }
    $bodyHTML .= '</ul>';
}

$bodyHTML .= '<p>You can disable notifications about new translatable packages <a href="' . $recipientAccountUrl . '">here</a>.</p>';
