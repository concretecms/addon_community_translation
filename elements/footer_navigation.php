<?php

/**
 * @project:   ConcreteCMS Docs
 *
 * @copyright  (C) 2021 Portland Labs (https://www.portlandlabs.com)
 * @author     Fabian Bitter (fabian@bitter.de)
 */

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Cache\Level\ExpensiveCache;
use Concrete\Core\Support\Facade\Application;

$app = Application::getFacadeApplication();
/** @var ExpensiveCache $expensiveCache */
$expensiveCache = $app->make(ExpensiveCache::class);
$cacheObject = $expensiveCache->getItem("Footer/ContainerHtml");

if ($cacheObject->isMiss()) {
    // Use CURL to fetch the footer from the main site.
    // Guzzle is not working because of the AWS balancer.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://community.concretecms.com");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $html = curl_exec($ch);
    curl_close($ch);

    // Disable error report for libxml to prevent loading issues because of missing html validation etc.
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    // Enable error report for libxml again
    libxml_use_internal_errors(false);

    // Extract the html for the footer
    $xpath = new DOMXPath($doc);
    $footerNode = $doc->getElementsByTagName('footer')->item(0);
    $footerContainerNode = $xpath->query('div/div[1]', $footerNode)->item(0);
    $footerContainerHtml = $doc->saveHTML($footerContainerNode);

    // Store to cache
    $expensiveCache->save($cacheObject->set($footerContainerHtml)->expiresAfter(24 * 60 * 60));
} else {
    // Get from cache
    $footerContainerHtml = $cacheObject->get();
}

echo $footerContainerHtml;