<?php // @todo

defined('C5_EXECUTE') or die('Access Denied.');

$subject = "[$siteName] Error extracting translations from Git Repository $repositoryName";

$bodyHTML = <<<EOT
<p>Hi $recipientName,</p>

<p>An error occurred while extracting translatable strings from the Git Repository named <i>$repositoryName</i>.</p>

<p>Error: <strong>$errorMessage</strong></p>

<p>Stack trace:<br /><code>$stackTrace</code></p>
EOT
;
