<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] Updated translatable packages";

$bodyHTML = '<p>Hi ' . $recipientName . ',</p>';

if (count($packageVersions) === 1) {
    $bodyHTML .= '<p>A version of a translatable package has been updated: <a href="' . $packageVersions[0]['url'] . '">' . h($packageVersions[0]['name']) . '</a>.</p>';
} else {
    $bodyHTML .= '<p>The following package versions have been updated:</p><ul>';
    foreach ($packageVersions as $packageVersion) {
        $bodyHTML .= '<li><a href="' . $packageVersion['url'] . '">' . h($packageVersion['name']) . '</a></li>';
    }
    $bodyHTML .= '</ul>';
}

$bodyHTML .= '<p>You can disable notifications about new translatable package versions in the online editor.</p>';
