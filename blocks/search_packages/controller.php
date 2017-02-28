<?php
namespace Concrete\Package\CommunityTranslation\Block\SearchPackages;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\UserException;
use Concrete\Core\Http\ResponseFactoryInterface;

class Controller extends BlockController
{
    const MIN_SEARCH_LENGTH = 3;
    const MAX_SEARCH_RESULTS = 10;
    public $helpers = [];

    protected $btTable = 'btCTSearchPackages';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 200;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;
    protected $btCacheBlockOutputOnPost = false;
    protected $btCacheBlockOutputForRegisteredUsers = false;

    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    public $preloadPackageHandle;

    public function getBlockTypeName()
    {
        return t('Search Packages');
    }

    public function getBlockTypeDescription()
    {
        return t('Allow users to search translated packages and access their translations.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->set('preloadPackageHandle', (string) $this->preloadPackageHandle);
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        $normalized = [
            'preloadPackageHandle' => '',
        ];
        if (isset($args['preloadPackageHandle']) && is_string($args['preloadPackageHandle'])) {
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $args['preloadPackageHandle']]);
            if ($package === null) {
                $error->add(t('No package with handle "%s"', h($args['preloadPackageHandle'])));
            } else {
                $normalized['preloadPackageHandle'] = $package->getHandle();
            }
        }

        return $error->has() ? $error : $normalized;
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::getInstanceSpecificTasks()
     */
    protected function getInstanceSpecificTasks()
    {
        return '*';
    }

    public function view()
    {
        $this->requireAsset('selectize');
        $this->set('token', $this->app->make('token'));
        $this->set('initialData', null);
        if ($this->preloadPackageHandle !== '') {
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $this->preloadPackageHandle]);
            if ($package !== null) {
                $this->set('initialData', $this->resultToJSON($package));
            }
        }
    }

    public function action_search()
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_search-package')) {
                new UserException($token->getErrorMessage());
            }
            $search = $this->post('search');
            $search = is_string($search) ? trim($search) : '';
            if ($search === '') {
                new UserException(t('Please specify what you would like to search'));
            }
            if (strlen($search) < self::MIN_SEARCH_LENGTH) {
                new UserException(t('Please be more specific with your search'));
            }
            $repo = $this->app->make(PackageRepository::class);
            $packages = $repo->createQueryBuilder('p')
                ->where('p.name like :search')
                ->orWhere('p.handle like :search')
                ->setParameter('search', '%' . $search . '%')
                ->setMaxResults(self::MAX_SEARCH_RESULTS)
                ->getQuery()->execute();
            $result = [];
            foreach ($packages as $package) {
                $r = $this->resultToJSON($package);
                if ($r !== null) {
                    $result[] = $r;
                }
            }

            return $rf->json($result);
        } catch (UserException $x) {
            return $rf->json([
                'error' => true,
                'errors' => [$x->getMessage()],
            ]);
        }
    }

    private function resultToJSON(PackageEntity $package)
    {
        $result = null;
        $versions = $package->getSortedVersions(true);
        if (!empty($versions)) {
            $result = [
                'handle' => $package->getHandle(),
                'name' => $package->getDisplayName(),
                'versions' => [],
            ];
            foreach ($versions as $version) {
                $result['versions'][] = [
                    'id' => $version->getID(),
                    'name' => $version->getDisplayVersion(),
                ];
            }
        }

        return $result;
    }
}
