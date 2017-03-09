<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] User accepted for the team $localeName";

$bodyHTML = "<p>Hi $recipientName,</p>";

$applicantHTML = $usersHelper->format($applicant);

if ($automatic) {
    $bodyHTML .= "<p>The user $applicantHTML has been automatically accepted as a translator of <a href=\"$teamsUrl\">$localeName</a>.</p>";
} else {
    $bodyHTML .= "<p>The user $applicantHTML has been accepted by " . $usersHelper->format($approvedBy) . " as a translator of <a href=\"$teamsUrl\">$localeName</a>.</p>";
}
