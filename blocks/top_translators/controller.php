<?php

namespace Concrete\Package\CommunityTranslation\Block\TopTranslators;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\User;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Utility\Service\Validation\Numbers;
use PDO;

class Controller extends BlockController
{
    public $helpers = [];

    protected $btTable = 'btCTTopTranslators';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 520;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    protected $btCacheBlockOutputForRegisteredUsers = true;

    protected $btCacheBlockOutputLifetime = 3600; // 1 hour

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$supportSavingNullValues
     */
    protected $supportSavingNullValues = true;

    /**
     * @var int|null
     */
    public $numTranslators;

    /**
     * @var string
     */
    public $limitToLocale;

    /**
     * @var bool
     */
    public $allTranslations;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeName()
     */
    public function getBlockTypeName()
    {
        return t('Top Translators');
    }

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
        $this->set('form', $this->app->make('helper/form'));
        $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $localeOptions = ['' => t('Every translation team')];
        foreach ($locales as $locale) {
            $localeOptions[$locale->getID()] = $locale->getDisplayName();
        }
        $this->set('numTranslators', $this->numTranslators ? (int) $this->numTranslators : null);
        $this->set('localeOptions', $localeOptions);
        $this->set('limitToLocale', (string) $this->limitToLocale);
        $this->set('allTranslations', (bool) $this->allTranslations);
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $args += [
            'numTranslators' => '',
            'limitToLocale' => '',
        ];
        $valn = $this->app->make(Numbers::class);
        $normalized = [
            'numTranslators' => $valn->integer($args['numTranslators'], 1) ? (int) $args['numTranslators'] : null,
            'allTranslations' => empty($args['allTranslations']) ? 0 : 1,
            'limitToLocale' => '',
        ];
        if ($args['limitToLocale'] !== '') {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($args['limitToLocale']);
            if ($locale !== null) {
                $normalized['limitToLocale'] = $locale->getID();
            }
        }

        return $normalized;
    }

    public function view()
    {
        $counters = $this->getCounters();
        $this->set('counters', $counters);
        $this->set('userService', $this->app->make(User::class));
    }

    /**
     * @return array[]
     */
    private function getCounters()
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
        if ($this->limitToLocale) {
            $qb->andWhere($expr->eq('t.locale', $qb->createNamedParameter($this->limitToLocale)));
        }
        $remainingGroups = $this->numTranslators ? (int) $this->numTranslators : null;
        $rs = $qb->execute();
        $result = [];
        while (($row = $rs->fetch(PDO::FETCH_NUM)) !== false) {
            $numTranslations = (int) $row[1];
            if (!isset($result[$numTranslations])) {
                if ($remainingGroups !== null) {
                    --$remainingGroups;
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
