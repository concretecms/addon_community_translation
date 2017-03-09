<?php // @todo

defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] New translation strings need review for $localeName";

$sPlural = ($numTranslaions > 1) ? 's' : '';

$bodyHTML = "<p>Hi $recipientName,</p>";

if (isset($packageUrl)) {
    $bodyHTML .= <<<EOT
<p>The user <i>$translatorName</i> submitted $numTranslations translation$sPlural that need review <a href="$packageUrl">for package $packageName</a>.</p>
<p>You can view all the translations awaiting approval for $localeName <a href="$allUnreviewedUrl">here</a>.</p>
EOT
    ;
} else {
    $bodyHTML .= <<<EOT
<p>The user <i>$translatorName</i> submitted $numTranslations translation$sPlural that need review.</p>
<p>You can view all the translations awaiting approval for $localeName <a href="$allUnreviewedUrl">here</a>.</p>
EOT
    ;
}
