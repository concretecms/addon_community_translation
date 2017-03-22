<?php
namespace Concrete\Package\CommunityTranslation\Block\FillTranslations;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Parser\ParserInterface;
use CommunityTranslation\Repository\Locale;
use CommunityTranslation\Repository\LocaleStats;
use CommunityTranslation\Service\IPControlLog;
use CommunityTranslation\Service\RateLimit;
use CommunityTranslation\Service\VolatileDirectory;
use CommunityTranslation\Translation\Exporter;
use CommunityTranslation\UserException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Support\Facade\Application;
use DateTime;
use Exception;
use Gettext\Generators\Mo as MOGenerator;
use Illuminate\Filesystem\Filesystem;
use Throwable;
use ZipArchive;

class Controller extends BlockController
{
    public $helpers = [];

    protected $btTable = 'btCTFillTranslations';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 520;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;
    protected $btCacheBlockOutputOnPost = false;
    protected $btCacheBlockOutputForRegisteredUsers = false;

    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    public $rateLimit_maxRequests;
    public $rateLimit_timeWindow;
    public $maxFileSize;
    public $maxLocalesCount;
    public $maxStringsCount;

    public function getBlockTypeName()
    {
        return t('Fill Translations');
    }

    public function getBlockTypeDescription()
    {
        return t('Allow users to get translations for their own files.');
    }

    /**
     * @param string|numeric $size
     *
     * @return int|null
     */
    protected function parseSize($size)
    {
        $result = null;
        $size = (string) $size;
        if (preg_match('/^\s*(\d+(?:\.\d*)?)\s*(?:([bkmgtpezy])b?)?\s*/i', $size, $matches)) {
            $value = (float) $matches[1];
            $unit = empty($matches[2]) ? 'b' : strtolower($matches[2]);
            if ($unit === 'b') {
                $result = (int) $value;
            } else {
                $result = (int) round($value * pow(1024, strpos('bkmgtpezy', $unit)));
            }
        }

        return $result;
    }

    /**
     * @return int|null
     */
    protected function getPostLimit()
    {
        $result = $this->parseSize(@ini_get('post_max_size'));
        $r = $this->parseSize(@ini_get('upload_max_filesize'));
        if ($result === null) {
            $result = $r;
        } elseif ($r !== null) {
            $result = min($r, $result);
        }

        return $result;
    }

    public function add()
    {
        $this->rateLimit_timeWindow = 3600;
        $this->edit();
    }

    public function edit()
    {
        $this->set('rateLimitHelper', $this->app->make(RateLimit::class));
        $this->set('rateLimit_maxRequests', $this->rateLimit_maxRequests);
        $this->set('rateLimit_timeWindow', $this->rateLimit_timeWindow);
        $postLimit = $this->getPostLimit();
        if ($postLimit === null) {
            $s = '';
        } else {
            $nh = $this->app->make('helper/number');
            $s = $nh->formatSize($postLimit);
        }
        $this->set('postLimit', $s);
        $maxFileSizeValue = '';
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
        $this->set('maxLocalesCount', $this->maxLocalesCount);
        $this->set('maxStringsCount', $this->maxStringsCount);
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        $normalized = [];

        try {
            /* @var \CommunityTranslation\Service\RateLimit $rateLimitHelper */
            list($normalized['rateLimit_maxRequests'], $normalized['rateLimit_timeWindow']) = $this->app->make(RateLimit::class)->fromWidgetHtml('rateLimit', $this->rateLimit_timeWindow ?: 3600);
        } catch (UserException $x) {
            $error->add($x->getMessage());
        }

        $normalized['maxFileSize'] = null;
        if (isset($args['maxFileSizeValue']) && (is_int($args['maxFileSizeValue']) || (is_string($args['maxFileSizeValue']) && is_numeric($args['maxFileSizeValue'])))) {
            $maxFileSizeValue = (int) $args['maxFileSizeValue'];
            if ($maxFileSizeValue > 0) {
                $maxFileSizeUnit = $this->post('maxFileSizeUnit');
                $p = is_string($maxFileSizeUnit) ? array_search($maxFileSizeUnit, ['b', 'KB', 'MB', 'GB'], true) : false;
                if ($p === false) {
                    $error->add(t('Please specify the unit of the max size of uploaded files'));
                } else {
                    $maxFileSize = (int) $maxFileSizeValue * pow(1024, $p);
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
        if (isset($args['maxLocalesCount']) && (is_int($args['maxLocalesCount']) || (is_string($args['maxLocalesCount']) && is_numeric($args['maxLocalesCount'])))) {
            $i = (int) $args['maxLocalesCount'];
            if ($i > 0) {
                $normalized['maxLocalesCount'] = $i;
            }
        }
        $normalized['maxStringsCount'] = null;
        if (isset($args['maxStringsCount']) && (is_int($args['maxStringsCount']) || (is_string($args['maxStringsCount']) && is_numeric($args['maxStringsCount'])))) {
            $i = (int) $args['maxStringsCount'];
            if ($i > 0) {
                $normalized['maxStringsCount'] = $i;
            }
        }

        return $error->has() ? $error : $normalized;
    }

    public function view()
    {
        $this->set('token', $this->app->make('helper/validation/token'));
        $locales = $this->app->make(Locale::class)->getApprovedLocales();
        $translatedLocales = [];
        $untranslatedLocales = [];
        $threshold = (int) $this->app->make('community_translation/config')->get('options.translatedThreshold', 90);
        $statsRepo = $this->app->make(LocaleStats::class);
        foreach ($locales as $locale) {
            if ($statsRepo->getByLocale($locale)->getPercentage() >= $threshold) {
                $translatedLocales[] = $locale;
            } else {
                $untranslatedLocales[] = $locale;
            }
        }
        if (empty($translatedLocales) || empty($untranslatedLocales)) {
            $translatedLocales = $locales;
            $untranslatedLocales = [];
        }
        $this->set('translatedLocales', $translatedLocales);
        $this->set('untranslatedLocales', $untranslatedLocales);
        $displayLimits = [];
        if ($this->rateLimit_maxRequests && $this->rateLimit_timeWindow) {
            $displayLimits[t('Requests limit')] = $this->app->make(RateLimit::class)->describeRate($this->rateLimit_maxRequests, $this->rateLimit_timeWindow);
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
            $displayLimits[t('Maximum number of languages')] = $this->maxLocalesCount;
        }
        if ($this->maxStringsCount) {
            $displayLimits[t('Maximum number of strings')] = $this->maxStringsCount;
        }
        $this->set('displayLimits', $displayLimits);
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::isControllerTaskInstanceSpecific()
     */
    protected function isControllerTaskInstanceSpecific($method)
    {
        return true;
    }

    /**
     * @throws UserException
     *
     * $return \Symfony\Component\HttpFoundation\File\UploadedFile
     */
    private function getPostedFile()
    {
        $file = $this->request->files->get('file');
        if ($file === null) {
            throw new UserException(t('Please specify the file to be analyzed'));
        }
        if (!$file->isValid()) {
            throw new UserException($file->getErrorMessage());
        }
        if ($this->maxFileSize) {
            $filesize = @filesize($file->getPathname());
            if ($filesize !== false && $filesize > $this->maxFileSize) {
                throw new UserException(t('The uploaded file is too big'));
            }
        }

        return $file;
    }

    /**
     * @throws UserException
     *
     * $return \CommunityTranslation\Entity\Locale[]
     */
    private function getPostedLocales()
    {
        $localeIDs = [];
        $list = $this->post('translatedLocales');
        if (is_array($list)) {
            $localeIDs = array_merge($localeIDs, $list);
        }
        $list = $this->post('untranslatedLocales');
        if (is_array($list)) {
            $localeIDs = array_merge($localeIDs, $list);
        }
        $repo = $this->app->make(Locale::class);
        $locales = $repo->getApprovedLocales();
        $result = [];
        foreach ($locales as $locale) {
            if (in_array($locale->getID(), $localeIDs, true)) {
                $result[] = $locale;
            }
        }

        $count = count($result);
        if ($count === 0) {
            throw new UserException(t('Please specify the languages of the .po/.mo files to generate'));
        }
        if ($this->maxLocalesCount && $count > (int) $this->maxLocalesCount) {
            throw new UserException(t('Please specify up to %s languages (you requested %2$s languages)', $this->maxLocalesCount, $count));
        }

        return $result;
    }

    /**
     * @throws UserException
     */
    private function checkRateLimit()
    {
        $maxRequests = (int) $this->rateLimit_maxRequests;
        if ($maxRequests > 0) {
            $timeWindow = (int) $this->rateLimit_timeWindow;
            if ($timeWindow > 0) {
                $ipControlLog = $this->app->make(IPControlLog::class);
                /* @var IPControlLog $ipControlLog */
                $visits = $ipControlLog->countVisits('fill-trans', new DateTime("-$timeWindow seconds"));
                if ($visits >= $maxRequests) {
                    throw new UserException(t('You reached the rate limit (%1$s requests every %2$s seconds)', $maxRequests, $timeWindow));
                }
                $ipControlLog->addVisit('fill-trans');
            }
        }
    }

    public function action_fill_in()
    {
        $responseFactory = $this->app->make(ResponseFactoryInterface::class);
        /* @var ResponseFactoryInterface $responseFactory */
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-fill-translations')) {
                throw new UserException($valt->getErrorMessage());
            }

            $file = $this->getPostedFile();

            $writePOT = (bool) $this->post('include-pot');
            $writePO = (bool) $this->post('include-po');
            $writeMO = (bool) $this->post('include-mo');
            if (!($writePOT || $writePO || $writeMO)) {
                throw new UserException(t('You need to specify at least one kind of file to generate'));
            }

            $locales = ($writePO || $writeMO) ? $this->getPostedLocales() : [];

            $this->checkRateLimit();

            $parsed = $this->app->make(ParserInterface::class)->parseFile('', '', $file->getPathname());
            if ($parsed === null) {
                throw new UserException(t('No translatable string found in the uploaded file'));
            }

            /* @var \CommunityTranslation\Parser\Parsed $parsed */

            if ($this->maxStringsCount) {
                $n = count($parsed->getSourceStrings(true));
                if ($n > (int) $this->maxStringsCount) {
                    throw new UserException(t('Please specify up to %1$s strings (your file contains %2$s strings)', $this->maxStringsCount, $n));
                }
            }
            $tmp = $this->app->make(VolatileDirectory::class);
            $zipName = $tmp->getPath() . '/out.zip';
            $zip = new ZipArchive();
            try {
                if ($zip->open($zipName, ZipArchive::CREATE) !== true) {
                    throw new UserException(t('Failed to create destination ZIP file'));
                }
                $zip->addEmptyDir('languages');
                if ($writePOT) {
                    $zip->addFromString('languages/messages.pot', $parsed->getSourceStrings(true)->toPoString());
                }
                if ($writePO || $writeMO) {
                    MOGenerator::$includeEmptyTranslations = true;
                    $exporter = $this->app->make(Exporter::class);
                    /* @var Exporter $exporter */
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
            } catch (Exception $x) {
                try {
                    $zip->close();
                } catch (Exeption $foo) {
                }
                unset($zip);
                unset($tmp);
                throw $x;
            }
            unset($zip);
            $contents = (new Filesystem())->get($zipName);
            unset($tmp);

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
        } catch (UserException $x) {
            $message = $x->getMessage();
        } catch (Exception $x) {
            $message = t('An unspecified error occurred');
        } catch (Throwable $x) {
            $message = t('An unspecified error occurred');
        }

        return $this->app->make('helper/concrete/ui')->buildErrorResponse(
            t('An unexpected error occurred.'),
            nl2br(h($message))
        );
    }
}
