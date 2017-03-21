<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New translatable packages";

$bodyHTML = '<p>Hi ' . $recipientName . ',</p>';

if (count($packages) === 1) {
    $packageURL = key($packages);
    $packageName = $packages[$packageURL];
    $bodyHTML .= '
<p>A new package is ready to be translated: ' . h($packageName) . '</p>
<p>Click <a href="' . $packageURL . '">here</a> to view the package details.</p>';
} else {
    $bodyHTML .= '<p>The following new packages are ready to be translated:</p><ul>';
    foreach ($packages as $packageURL => $packageName) {
        $bodyHTML .= '<li><a href="' . $packageURL . '">' . h($packageName) . '</a></li>';
    }
    $bodyHTML .= '</ul>';
}

$bodyHTML .= '<p>You can disable notifications about new translatable packages <a href="' . $recipientAccountUrl . '">here</a>.';
