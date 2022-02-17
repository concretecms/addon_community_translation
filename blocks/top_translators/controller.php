<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Block\TopTranslators;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Utility\Service\Validation\Numbers;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access denied.');

class Controller extends BlockController
{
    /**
     * @var int|string|null
     */
    public $numTranslators;

    /**
     * @var string|null
     */
    public $limitToLocale;

    /**
     * @var bool|string|null
     */
    public $allTranslations;

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
    protected $btTable = 'btCTTopTranslators';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceWidth
     */
    protected $btInterfaceWidth = 400;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceHeight
     */
    protected $btInterfaceHeight = 380;

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
    protected $btCacheBlockOutput = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputOnPost
     */
    protected $btCacheBlockOutputOnPost = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputForRegisteredUsers
     */
    protected $btCacheBlockOutputForRegisteredUsers = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputLifetime
     */
    protected $btCacheBlockOutputLifetime = 3600; // 1 hour

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

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeName()
     */
    public function getBlockTypeName()
    {
        return t('Top Translators');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeDescription()
     */
    public function getBlockTypeDescription()
    {
        return t('Display a list with the translators that contributed the most.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $localeOptions = ['' => t('Every translation team')];
        foreach ($locales as $locale) {
            $localeOptions[$locale->getID()] = $locale->getDisplayName();
        }
        $this->set('form', $this->app->make('helper/form'));
        $this->set('numTranslators', $this->numTranslators ? (int) $this->numTranslators : null);
        $this->set('localeOptions', $localeOptions);
        $this->set('limitToLocale', (string) $this->limitToLocale);
        $this->set('allTranslations', (bool) $this->allTranslations);
    }

    public function view(): ?Response
    {
        $this->set('counters', $this->getCounters());
        $this->set('userService', $this->getUserService());

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Controller\BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $valn = $this->app->make(Numbers::class);
        $normalized = [
            'numTranslators' => $valn->integer($args['numTranslators'] ?? null, 1) ? (int) $args['numTranslators'] : null,
            'allTranslations' => empty($args['allTranslations']) ? 0 : 1,
            'limitToLocale' => '',
        ];
        if (is_string($args['limitToLocale'] ?? null) && $args['limitToLocale'] !== '') {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($args['limitToLocale']);
            if ($locale !== null) {
                $normalized['limitToLocale'] = $locale->getID();
            }
        }

        return $normalized;
    }

    private function getCounters(): array
    {
        $cn = $this->app->make(Connection::class);
        $qb = $cn->createQueryBuilder();

        $expr = $qb->expr();
        $plat = $cn->getDatabasePlatform();
        $qb
            ->select(['t.createdBy', $plat->getCountExpression('*')])
            ->from('CommunityTranslationTranslations', 't')
            ->where($expr->neq('t.createdBy', $qb->createNamedParameter(USER_SUPER_ID)))
            ->groupBy(['t.createdBy'])
            ->orderBy($plat->getCountExpression('*'), 'DESC')
        ;
        if (!$this->allTranslations) {
            $qb->andWhere($expr->isNotNull('t.currentSince'));
        }
        if ((string) $this->limitToLocale !== '') {
            $qb->andWhere($expr->eq('t.locale', $qb->createNamedParameter($this->limitToLocale)));
        }
        $remainingGroups = $this->numTranslators ? (int) $this->numTranslators : null;
        $rs = $qb->execute();
        $result = [];
        while (($row = $rs->fetchNumeric()) !== false) {
            $numTranslations = (int) $row[1];
            if (!isset($result[$numTranslations])) {
                if ($remainingGroups !== null) {
                    $remainingGroups--;
                    if ($remainingGroups < 0) {
                        break;
                    }
                }
                $result[$numTranslations] = [];
            }
            $result[$numTranslations][] = (int) $row[0];
        }

        return $result;
    }
}
