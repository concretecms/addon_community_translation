<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New versions of translatable packages";

$bodyHTML = '<p>Hi ' . $recipientName . ',</p>';

if (count($packageVersions) === 1) {
    $bodyHTML .= '<p>A new version of a translatable package is ready to be translated: <a href="' . $packageVersions[0]['url'] . '">' . h($packageVersions[0]['name']) . '</a>.</p>';
} else {
    $bodyHTML .= '<p>The following new package versions are ready to be translated:</p><ul>';
    foreach ($packageVersions as $packageVersion) {
        $bodyHTML .= '<li><a href="' . $packageVersion['url'] . '">' . h($packageVersion['name']) . '</a></li>';
    }
    $bodyHTML .= '</ul>';
}

$bodyHTML .= '<p>You can disable notifications about new translatable package versions in the online editor.</p>';
