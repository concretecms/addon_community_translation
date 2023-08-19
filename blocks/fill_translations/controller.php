<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Block\FillTranslations;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Parser\ParserInterface;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\LocaleStats as LocaleStatsRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Service\VolatileDirectoryCreator;
use CommunityTranslation\Translation\Exporter;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Entity\Permission\IpAccessControlCategory;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Permission\IpAccessControlService;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gettext\Generators\Mo as MOGenerator;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    /**
     * @var int|string|null
     */
    public $maxFileSize;

    /**
     * @var int|string|null
     */
    public $maxLocalesCount;

    /**
     * @var int|string|null
     */
    public $maxStringsCount;

    /**
     * @var string|null
     */
    public $statsFromPackage;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$helpers
     */
    protected $helpers = [];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btTable
     */
    protected $btTable = 'btCTFillTranslations';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceWidth
     */
    protected $btInterfaceWidth = 600;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceHeight
     */
    protected $btInterfaceHeight = 580;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockRecord
     */
    protected $btCacheBlockRecord = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutput
     */
    protected $btCacheBlockOutput = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputOnPost
     */
    protected $btCacheBlockOutputOnPost = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputForRegisteredUsers
     */
    protected $btCacheBlockOutputForRegisteredUsers = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputLifetime
     */
    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btSupportsInlineEdit
     */
    protected $btSupportsInlineEdit = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btSupportsInlineAdd
     */
    protected $btSupportsInlineAdd = false;

    private ?IpAccessControlCategory $ipAccessControlCategory = null;

    private ?IpAccessControlService $ipAccessControlService = null;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeName()
     */
    public function getBlockTypeName()
    {
        return t('Fill Translations');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeDescription()
     */
    public function getBlockTypeDescription()
    {
        return t('Allow users to get translations for their own files.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->set('form', $this->app->make('helper/form'));
        $this->set('rateLimitControlUrl', (string) $this->app->make(ResolverManagerInterface::class)->resolve([
            '/dashboard/system/permissions/denylist/configure',
            $this->getIpAccessControlCategory()->getIpAccessControlCategoryID(),
        ]));
        $this->set('statsFromPackage', (string) $this->statsFromPackage);
        $postLimit = $this->getPostLimit();
        $this->set('postLimit', $postLimit === null ? '' : $this->app->make('helper/number')->formatSize($postLimit));
        $maxFileSizeValue = null;
        $maxFileSizeUnit = 'MB';
        if ($this->maxFileSize) {
            $maxFileSizeValue = (int) $this->maxFileSize;
            $maxFileSizeUnit = 'b';
            if ($maxFileSizeValue > 0 && ($maxFileSizeValue % 1024) === 0) {
                $maxFileSizeValue = (int) ($maxFileSizeValue / 1024);
                $maxFileSizeUnit = 'KB';
                if ($maxFileSizeValue > 0 && ($maxFileSizeValue % 1024) === 0) {
                    $maxFileSizeValue = (int) ($maxFileSizeValue / 1024);
                    $maxFileSizeUnit = 'MB';
                    if ($maxFileSizeValue > 0 && ($maxFileSizeValue % 1024) === 0) {
                        $maxFileSizeValue = (int) ($maxFileSizeValue / 1024);
                        $maxFileSizeUnit = 'GB';
                    }
                }
            }
        }
        $this->set('maxFileSizeValue', $maxFileSizeValue);
        $this->set('maxFileSizeUnit', $maxFileSizeUnit);
        $this->set('maxLocalesCount', $this->maxLocalesCount ? (int) $this->maxLocalesCount : null);
        $this->set('maxStringsCount', $this->maxStringsCount ? (int) $this->maxStringsCount : null);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::delete()
     */
    public function delete()
    {
        parent::delete();
        $category = $this->getIpAccessControlCategory();
        $em = $this->app->make(EntityManagerInterface::class);
        $em->remove($category);
        $em->flush($category);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::registerViewAssets()
     */
    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset('javascript', 'jquery');
    }

    public function view(): ?Response
    {
        $this->set('token', $this->app->make('helper/validation/token'));
        $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $translatedLocales = [];
        $untranslatedLocales = [];
        $threshold = (int) $this->app->make(Repository::class)->get('community_translation::translate.translatedThreshold', 90);
        $statsForVersion = null;
        if ((string) $this->statsFromPackage !== '') {
            $package = $this->app->make(PackageRepository::class)->getByHandle($this->statsFromPackage);
            if ($package !== null) {
                $statsForVersion = $package->getLatestVersion();
            }
        }
        if ($statsForVersion === null) {
            $localeStatsRepo = $this->app->make(LocaleStatsRepository::class);
            foreach ($locales as $locale) {
                if ($localeStatsRepo->getByLocale($locale)->getRoundedPercentage() >= $threshold) {
                    $translatedLocales[] = $locale;
                } else {
                    $untranslatedLocales[] = $locale;
                }
            }
        } else {
            $statsRepo = $this->app->make(StatsRepository::class);
            $stats = $statsRepo->get($statsForVersion, $locales);
            foreach ($locales as $locale) {
                $isTranslated = false;
                foreach ($stats as $stat) {
                    if ($stat->getLocale() === $locale) {
                        if ($stat->getRoundedPercentage() >= $threshold) {
                            $isTranslated = true;
                        }
                        break;
                    }
                }
                if ($isTranslated === true) {
                    $translatedLocales[] = $locale;
                } else {
                    $untranslatedLocales[] = $locale;
                }
            }
        }
        if ($translatedLocales === [] || $untranslatedLocales === []) {
            $translatedLocales = $locales;
            $untranslatedLocales = [];
        }
        $this->set('translatedLocales', $translatedLocales);
        $this->set('untranslatedLocales', $untranslatedLocales);
        $displayLimits = [];
        $ipAccessControlCategory = $this->getIpAccessControlCategory();
        if ($ipAccessControlCategory->isEnabled()) {
            $limit = $ipAccessControlCategory->describeTimeWindow(false);
            if ($limit !== '') {
                $displayLimits[t('Requests limit')] = $limit;
            }
        }
        $maxFileSize = $this->maxFileSize ? (int) $this->maxFileSize : null;
        $r = $this->getPostLimit();
        if ($maxFileSize === null) {
            $maxFileSize = $r;
        } elseif ($r !== null) {
            $maxFileSize = min($r, $maxFileSize);
        }
        if ($maxFileSize !== null) {
            $displayLimits[t('Maximum file size')] = $this->app->make('helper/number')->formatSize($maxFileSize);
        }
        if ($this->maxLocalesCount) {
            $displayLimits[t('Maximum number of languages')] = (string) $this->maxLocalesCount;
        }
        if ($this->maxStringsCount) {
            $displayLimits[t('Maximum number of strings')] = (string) $this->maxStringsCount;
        }
        $this->set('displayLimits', $displayLimits);

        return null;
    }

    public function action_fill_in(): Response
    {
        $responseFactory = $this->app->make(ResponseFactoryInterface::class);
        $message = '';
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-fill-translations')) {
                throw new UserMessageException($valt->getErrorMessage());
            }

            $file = $this->getPostedFile();

            $writePOT = $this->request->request->get('include-pot') ? true : false;
            $writePO = $this->request->request->get('include-po') ? true : false;
            $writeMO = $this->request->request->get('include-mo') ? true : false;
            if (!($writePOT || $writePO || $writeMO)) {
                throw new UserMessageException(t('You need to specify at least one kind of file to be generated.'));
            }
            $this->checkRateLimit();

            $locales = ($writePO || $writeMO) ? $this->getPostedLocales() : [];

            $parsed = $this->app->make(ParserInterface::class)->parseFile('', '', $file->getPathname());
            if ($parsed === null) {
                throw new UserMessageException(t('No translatable string found in the uploaded file'));
            }

            if ($this->maxStringsCount) {
                $n = count($parsed->getSourceStrings(true));
                if ($n > (int) $this->maxStringsCount) {
                    throw new UserMessageException(t('Please specify up to %1$s strings (your file contains %2$s strings)', $this->maxStringsCount, $n));
                }
            }
            $tmp = $this->app->make(VolatileDirectoryCreator::class)->createVolatileDirectory();
            $zipName = $tmp->getPath() . '/out.zip';
            $zip = new ZipArchive();
            try {
                if ($zip->open($zipName, ZipArchive::CREATE) !== true) {
                    throw new UserMessageException(t('Failed to create destination ZIP file'));
                }
                $zip->addEmptyDir('languages');
                if ($writePOT) {
                    $zip->addFromString('languages/messages.pot', $parsed->getSourceStrings(true)->toPoString());
                }
                if ($writePO || $writeMO) {
                    MOGenerator::$includeEmptyTranslations = true;
                    $exporter = $this->app->make(Exporter::class);
                    foreach ($locales as $locale) {
                        $dir = 'languages/' . $locale->getID();
                        $zip->addEmptyDir($dir);
                        $dir .= '/LC_MESSAGES';
                        $zip->addEmptyDir($dir);
                        $po = $parsed->getTranslations($locale);
                        $po = $exporter->fromPot($po, $locale);
                        if ($writePO) {
                            $zip->addFromString($dir . '/messages.po', $po->toPoString());
                        }
                        if ($writeMO) {
                            \Gettext\Generators\Mo::$includeEmptyTranslations = true;
                            $zip->addFromString($dir . '/messages.mo', $po->toMoString());
                        }
                    }
                }
                $zip->close();
                $zip = null;
                $contents = (new Filesystem())->get($zipName);
            } finally {
                if (isset($zip)) {
                    try {
                        $zip->close();
                    } catch (Throwable $foo) {
                    }
                    unset($zip);
                }
                unset($tmp);
            }

            return $responseFactory->create(
                $contents,
                200,
                [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename=translations.zip',
                    'Content-Transfer-Encoding' => 'binary',
                    'Content-Length' => strlen($contents),
                    'Expires' => '0',
                ]
            );
        } catch (UserMessageException $x) {
            $message = $x->getMessage();
        } catch (Throwable $x) {
            $message = t('An unspecified error occurred');
        }

        $jsonMessage = json_encode($message);
        $charset = APP_CHARSET;

        return $responseFactory->create(
            <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset={$charset}" />
    <script>
        (window.parent || window).alert({$jsonMessage});
    </script>
</head>
</html>
EOT
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Controller\BlockController::isControllerTaskInstanceSpecific()
     */
    protected function isControllerTaskInstanceSpecific(string $method): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Controller\BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        $normalized = [];
        $normalized['maxFileSize'] = null;
        if (is_numeric($args['maxFileSizeValue'] ?? null)) {
            $maxFileSizeValue = (int) $args['maxFileSizeValue'];
            if ($maxFileSizeValue > 0) {
                $maxFileSizeUnit = $this->request->request->get('maxFileSizeUnit');
                $p = is_string($maxFileSizeUnit) ? array_search($maxFileSizeUnit, ['b', 'KB', 'MB', 'GB'], true) : false;
                if ($p === false) {
                    $error->add(t('Please specify the unit of the max size of uploaded files'));
                } else {
                    $maxFileSize = (int) ($maxFileSizeValue * 1024 ** $p);
                    $postLimit = $this->getPostLimit();
                    if ($postLimit !== null && $maxFileSize > $postLimit) {
                        $nh = $this->app->make('helper/number');
                        $error->add(
                            t(
                                'You can\'t set the max file size to %1$s since the current limit imposed by PHP is %2$s',
                                $nh->formatSize($maxFileSize),
                                $nh->formatSize($postLimit)
                            )
                        );
                    } else {
                        $normalized['maxFileSize'] = $maxFileSize;
                    }
                }
            }
        }
        $normalized['maxLocalesCount'] = null;
        if (is_numeric($args['maxLocalesCount'] ?? null)) {
            $maxLocalesCount = (int) $args['maxLocalesCount'];
            if ($maxLocalesCount > 0) {
                $normalized['maxLocalesCount'] = $maxLocalesCount;
            }
        }
        $normalized['maxStringsCount'] = null;
        if (is_numeric($args['maxStringsCount'] ?? null)) {
            $maxStringsCount = (int) $args['maxStringsCount'];
            if ($maxStringsCount > 0) {
                $normalized['maxStringsCount'] = $maxStringsCount;
            }
        }
        $normalized['statsFromPackage'] = '';
        if (is_string($args['statsFromPackage'] ?? null)) {
            $normalized['statsFromPackage'] = trim($args['statsFromPackage']);
            if ($normalized['statsFromPackage'] !== '') {
                $package = $this->app->make(PackageRepository::class)->getByHandle($normalized['statsFromPackage']);
                if ($package === null) {
                    $error->add(t('Unable to find a package with handle "%s"', $normalized['statsFromPackage']));
                } else {
                    $normalized['statsFromPackage'] = $package->getHandle();
                    if ($package->getLatestVersion() === null) {
                        $error->add(t('The package with handle "%s" does not have a latest version', $normalized['statsFromPackage']));
                    }
                }
            }
        }

        return $error->has() ? $error : $normalized;
    }

    private function getPostLimit(): ?int
    {
        set_error_handler(static function () {}, -1);
        try {
            $iniValues = [ini_get('post_max_size'), ini_get('upload_max_filesize')];
        } finally {
            restore_error_handler();
        }
        $result = null;
        foreach ($iniValues as $iniValue) {
            $bytes = $this->parseSize($iniValue);
            if ($bytes === null) {
                continue;
            }
            $result = $result === null ? $bytes : min($result, $bytes);
        }

        return $result;
    }

    /**
     * @param string|int $size
     */
    private function parseSize($size): ?int
    {
        $matches = null;
        if (!preg_match('/^\s*(?<value>\d+(?:\.\d*)?)\s*(?:(?<unit>[bkmgtpezy])b?)?\s*/i', (string) $size, $matches)) {
            return null;
        }
        $value = (float) $matches['value'];
        $unit = strtolower($matches['unit'] ?? 'b');
        if ($unit === 'b') {
            return (int) $value;
        }

        return (int) round($value * 1024 ** strpos('bkmgtpezy', $unit));
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getPostedFile(): UploadedFile
    {
        $file = $this->request->files->get('file');
        if ($file === null) {
            throw new UserMessageException(t('Please specify the file to be analyzed'));
        }
        if (!$file->isValid()) {
            throw new UserMessageException($file->getErrorMessage());
        }
        if ($this->maxFileSize) {
            set_error_handler(static function () {}, -1);
            try {
                $filesize = $file->getSize();
            } finally {
                restore_error_handler();
            }
            if (is_int($filesize) && $filesize > (int) $this->maxFileSize) {
                throw new UserMessageException(t('The uploaded file is too big'));
            }
        }

        return $file;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \CommunityTranslation\Entity\Locale[]
     */
    private function getPostedLocales(): array
    {
        $localeIDs = [];
        $list = $this->request->request->get('translatedLocales');
        if (is_array($list)) {
            $localeIDs = array_merge($localeIDs, $list);
        }
        $list = $this->request->request->get('untranslatedLocales');
        if (is_array($list)) {
            $localeIDs = array_merge($localeIDs, $list);
        }
        $repo = $this->app->make(LocaleRepository::class);
        $locales = $repo->getApprovedLocales();
        $result = [];
        foreach ($locales as $locale) {
            if (in_array($locale->getID(), $localeIDs, true)) {
                $result[] = $locale;
            }
        }
        if ($result === []) {
            throw new UserMessageException(t('Please specify the languages of the .po/.mo files to be generated.'));
        }
        if ($this->maxLocalesCount) {
            $count = count($result);
            if ($count > (int) $this->maxLocalesCount) {
                throw new UserMessageException(t('Please specify up to %s languages (you requested %2$s languages)', $this->maxLocalesCount, $count));
            }
        }

        return $result;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function checkRateLimit(): void
    {
        $service = $this->getIpAccessControlService();
        $range = $service->getRange();
        if ($range !== null) {
            if ($range->getType() & IpAccessControlService::IPRANGEFLAG_WHITELIST) {
                return;
            }
            if ($range->getType() === IpAccessControlService::IPRANGETYPE_BLACKLIST_AUTOMATIC) {
                throw new UserMessageException(t('You reached the rate limit (%s)', $service->getCategory()->describeTimeWindow()));
            }
            throw new UserMessageException(t('You have been blocked'));
        }
        if ($service->isThresholdReached()) {
            $service->addToDenylistForThresholdReached();
            throw new UserMessageException(t('You reached the API rate limit (%s)', $service->getCategory()->describeTimeWindow()));
        }
        $service->registerEvent();
    }

    private function getIpAccessControlCategory(): IpAccessControlCategory
    {
        if ($this->ipAccessControlCategory === null) {
            $em = $this->app->make(EntityManagerInterface::class);
            $categoryRepository = $em->getRepository(IpAccessControlCategory::class);
            $this->ipAccessControlCategory = $categoryRepository->findOneBy(['handle' => 'community_translation_bt_fill_translations']);
        }

        return $this->ipAccessControlCategory;
    }

    private function getIpAccessControlService(): IpAccessControlService
    {
        if ($this->ipAccessControlService === null) {
            $this->ipAccessControlService = $this->app->make(IpAccessControlService::class, ['category' => $this->getIpAccessControlCategory()]);
        }

        return $this->ipAccessControlService;
    }
}
