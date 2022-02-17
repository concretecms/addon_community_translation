<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\Options;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Parser\ParserInterface;
use CommunityTranslation\Parser\Provider as ParserProvider;
use CommunityTranslation\Service\SourceLocale;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Translate extends DashboardPageController
{
    public function view(): ?Response
    {
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $config = $this->app->make(Repository::class);
        $parsers = [];
        foreach ($this->app->make(ParserProvider::class)->getRegisteredParsers() as $parser) {
            $parsers[get_class($parser)] = $parser->getDisplayName();
        }
        $this->set('sourceLocale', $this->app->make(SourceLocale::class)->getSourceLocaleID());
        $this->set('translatedThreshold', (int) $config->get('community_translation::translate.translatedThreshold', 90));
        $this->set('parsers', $parsers);
        $this->set('defaultParser', $config->get('community_translation::translate.parser'));

        return null;
    }

    public function submit(): ?Response
    {
        if (!$this->token->validate('ct-options-save-translate')) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view();
        }
        try {
            $newSourceLocale = $this->parseSourceLocale();
        } catch (UserMessageException $x) {
            $this->error->add($x);
        }
        $translatedThreshold = $this->parseTranslatedThreshold();
        $parser = $this->parseParser();
        if ($this->error->has()) {
            return $this->view();
        }
        $config = $this->app->make(Repository::class);
        $this->app->make(SourceLocale::class)->switchSourceLocale($newSourceLocale);
        $config->save('community_translation::translate.translatedThreshold', $translatedThreshold);
        $config->save('community_translation::translate.parser', $parser);
        $this->flash('message', t('Comminity Translation options have been saved.'));

        return $this->buildRedirect([$this->request->getCurrentPage()]);
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function parseSourceLocale(): LocaleEntity
    {
        $sourceLocaleID = $this->request->request->get('sourceLocale');
        if (!is_string($sourceLocaleID) || $sourceLocaleID !== '') {
            throw new UserMessageException(t('Please specify a valid source locale'));
        }
        $newSourceLocale = new LocaleEntity($sourceLocaleID);
        $this->app->make(SourceLocale::class)->checkEligible($newSourceLocale);

        return $newSourceLocale;
    }

    private function parseTranslatedThreshold(): ?int
    {
        $raw = $this->request->request->get('translatedThreshold');
        if (is_string($raw) && is_numeric($raw)) {
            $int = (int) $raw;
            if ($int >= 0 && $int <= 100) {
                return $int;
            }
        }
        $this->error->add(t('Please specify the translation thresold used to consider a language as translated'));

        return null;
    }

    private function parseParser(): string
    {
        $result = $this->request->request->get('parser');
        $result = is_string($result) ? ltrim(trim($result), '\\') : '';
        if ($result !== '' && class_exists("\\{$result}") && is_a($result, ParserInterface::class, true)) {
            return $result;
        }
        $this->error->add(t('Please specify strings parser.'));

        return '';
    }
}
