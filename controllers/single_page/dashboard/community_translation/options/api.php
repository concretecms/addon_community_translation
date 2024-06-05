<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\Options;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Api\UserControl;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Entity\Permission\IpAccessControlCategory;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Api extends DashboardPageController
{
    public function view(): ?Response
    {
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $config = $this->app->make(Repository::class);
        $this->set('accessDenylistUrl', $this->getIpAccessControlUrl('community_translation_api_access'));
        $this->set('rateLimitDenylistUrl', $this->getIpAccessControlUrl('community_translation_api_ratelimit'));
        $this->set('accessControlAllowOrigin', (string) $config->get('community_translation::api.accessControlAllowOrigin'));
        $accessChecks = [];
        foreach ($this->getAccessChecks() as $key => $info) {
            $value = $config->get("community_translation::api.access.{$key}");
            $accessChecks[$key] = [
                'label' => $info['name'],
                'value' => is_string($value) ? $value : '',
                'values' => $this->getAccessOptions($info['localeDepentent']),
            ];
        }
        $this->set('accessChecks', $accessChecks);

        return null;
    }

    public function submit(): ?Response
    {
        if (!$this->token->validate('ct-options-save-api')) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view();
        }
        $accessControlAllowOrigin = $this->parseAccessControlAllowOrigin();
        $apiAccess = $this->parseApiAccess();
        if ($this->error->has()) {
            return $this->view();
        }
        $config = $this->app->make(Repository::class);
        $config->save('community_translation::api.accessControlAllowOrigin', $accessControlAllowOrigin);
        foreach ($apiAccess as $key => $value) {
            $config->save('community_translation::api.access.' . $key, $value);
        }
        $this->flash('message', t('Comminity Translation options have been saved.'));

        return $this->buildRedirect([$this->request->getCurrentPage()]);
    }

    private function getAccessChecks(): array
    {
        return [
            EntryPoint\GetRateLimit::ACCESS_KEY => [
                'name' => t('Get the current status of the rate limit'),
                'localeDepentent' => false,
            ],
            EntryPoint\GetLocales::ACCESS_KEY => [
                'name' => t('Get the list of approved locales'),
                'localeDepentent' => false,
            ],
            EntryPoint\GetPackages::ACCESS_KEY => [
                'name' => t('Get the list of available packages'),
                'localeDepentent' => false,
            ],
            EntryPoint\GetPackageVersions::ACCESS_KEY => [
                'name' => t('Get the version list of packages'),
                'localeDepentent' => false,
            ],
            EntryPoint\GetPackageVersionLocales::ACCESS_KEY => [
                'name' => t('Get the translation progress of package versions'),
                'localeDepentent' => true,
            ],
            EntryPoint\GetPackageVersionTranslations::ACCESS_KEY => [
                'name' => t('Get the translations of a specific package version'),
                'localeDepentent' => true,
            ],
            EntryPoint\FillTranslations::ACCESS_KEY => [
                'name' => t('Fill-in known translations for users usage'),
                'localeDepentent' => true,
            ],
            EntryPoint\ImportPackage::ACCESS_KEY => [
                'name' => t('Import translatable strings from a remote package'),
                'localeDepentent' => false,
            ],
            EntryPoint\ImportPackageVersionTranslatables::ACCESS_KEY => [
                'name' => t('Set the source strings of a specific package version'),
                'localeDepentent' => false,
            ],
            EntryPoint\ImportTranslations::ACCESS_KEY_WITHOUTAPPROVE => [
                'name' => t('Add translations (without approval)'),
                'localeDepentent' => true,
            ],
            EntryPoint\ImportTranslations::ACCESS_KEY_WITHAPPROVE => [
                'name' => t('Add translations (with approval)'),
                'localeDepentent' => true,
            ],
        ];
    }

    private function getAccessOptions(bool $localeDepentent): array
    {
        return $localeDepentent ?
            [
                // 'localeadmins-all-locales', 'localeadmins-own-locales', 'globaladmins', 'nobody'
                UserControl::ACCESSOPTION_EVERYBODY => t('Everybody (no authentication required)'),
                UserControl::ACCESSOPTION_REGISTEREDUSERS => t('Registered users'),
                UserControl::ACCESSOPTION_TRANSLATORS_ALLLOCALES => t('Translators (access to all locales)'),
                UserControl::ACCESSOPTION_TRANSLATORS_OWNLOCALES => t('Translators (access to own locales only)'),
                UserControl::ACCESSOPTION_LOCALEADMINS_ALLLOCALES => t('Language team coordinators (access to all locales)'),
                UserControl::ACCESSOPTION_LOCALEADMINS_OWNLOCALES => t('Language team coordinators (access to own locales only)'),
                UserControl::ACCESSOPTION_GLOBALADMINS => t('Global localization administrators'),
                UserControl::ACCESSOPTION_SITEADMINS => t('Site administrators'),
                UserControl::ACCESSOPTION_ROOT => t('Site administrator'),
                UserControl::ACCESSOPTION_MARKET => t('Concrete Marketplace'),
                UserControl::ACCESSOPTION_NOBODY => t('Nobody'),
            ]
            :
            [
                UserControl::ACCESSOPTION_EVERYBODY => t('Everybody (no authentication required)'),
                UserControl::ACCESSOPTION_REGISTEREDUSERS => t('Registered users'),
                UserControl::ACCESSOPTION_TRANSLATORS => t('Translators (of any locale)'),
                UserControl::ACCESSOPTION_LOCALEADMINS => t('Language team coordinators (of any locale)'),
                UserControl::ACCESSOPTION_GLOBALADMINS => t('Global localization administrators'),
                UserControl::ACCESSOPTION_SITEADMINS => t('Site administrators'),
                UserControl::ACCESSOPTION_ROOT => t('Site administrator'),
                UserControl::ACCESSOPTION_MARKET => t('Concrete Marketplace'),
                UserControl::ACCESSOPTION_NOBODY => t('Nobody'),
            ]
        ;
    }

    private function getIpAccessControlUrl(string $handle): string
    {
        $repo = $this->entityManager->getRepository(IpAccessControlCategory::class);
        $category = $repo->findOneBy(['handle' => $handle]);

        return (string) $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/permissions/denylist/configure', $category->getIpAccessControlCategoryID()]);
    }

    private function parseAccessControlAllowOrigin(): string
    {
        $result = $this->request->request->get('accessControlAllowOrigin');
        $result = is_string($result) ? trim($result) : '';
        if ($result === '') {
            $this->error->add(t(/*i18n: %s is an HTTP header name*/'Please specify the value of the %s header', 'Access-Control-Allow-Origin'));
        }

        return $result;
    }

    private function parseApiAccess(): array
    {
        $result = [];
        foreach ($this->getAccessChecks() as $key => $info) {
            $validValues = $this->getAccessOptions($info['localeDepentent']);
            $value = $this->request->request->get('apiAccess-' . $key);
            if (!is_string($value) || $value === '') {
                $this->error->add(t('Please specify the API access for: %s', $info['name']));
            } elseif (!isset($validValues[$value])) {
                $this->error->add(t('Unrecognized value for the API access for: %s', $info['name']));
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
