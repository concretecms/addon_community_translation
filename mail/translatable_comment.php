<?php
defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] " . ($specificForLocale === null ? 'New comments about translatable strings' : "New comments about translations for $specificForLocale");

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>The following new comments about translations have been posted:</p>

EOT
;
foreach ($comments as $comment) {
    $authorHTML = $usersHelper->format($comment['author']);
    $translatableHTML = h($comment['translatable']);
    $bodyHTML .= "<p><b>{$comment['date']} by $authorHTML about string <a href=\"{$comment['link']}\">$translatableHTML</a></b><br />";
    $bodyHTML .= "<blockquote>{$comment['messageHtml']}</blockquote>";
    $bodyHTML .= '<br /></p>';
}
