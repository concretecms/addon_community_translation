<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New translations need review for $localeName";

$bodyHTML = "<p>Hi $recipientName,</p>";
if (count($translations) === 1) {
    $bodyHTML .= '<p>The user ' . $usersHelper->format($translations[0]['user']) . " submitted {$translations[0]['numTranslations']} translations that need review.</p>";
} else {
    $bodyHTML .= '<p>The following users submitted some translations that need review:</p>';
    $bodyHTML .= '<ul>';
    foreach ($translations as $t) {
        $bodyHTML .= '<li>' . $usersHelper->format($t['user']) . ": {$t['numTranslations']} translations</li>";
    }
    $bodyHTML .= '</ul>';
}

$bodyHTML .= "<p>You can view review these translations <a href=\"$approvalURL\">here</a>.</p>";
