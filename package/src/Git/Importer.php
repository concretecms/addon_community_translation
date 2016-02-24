<?php
namespace Concrete\Package\CommunityTranslation\Src\Git;

use Concrete\Core\Application\Application;

class Importer implements \Concrete\Core\Application\ApplicationAwareInterface
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Set the application object.
     *
     * @param Application $application            
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Import the strings from the git repository.
     */
    public function import(Repository $repository)
    {
        $importer = $this->app->make('community_translation/translatable/importer');
        /* @var \Concrete\Package\CommunityTranslation\Src\Translatable\Importer $importer */
        $fetcher = $this->app->make('community_translation/git/fetcher', array($repository));
        /* @var Fetcher $fetcher */
        $placeRepo = $this->app->make('community_translation/translatable/place');
        /* @var \Doctrine\ORM\EntityRepository $placeRepo */
        $fetcher->update();
        if ($repository->getDevBranch() !== '') {
            $importer->importDirectory($fetcher->getWebDirectory(), $repository->getPackage(), $repository->getDevVersion());
        }
        foreach ($fetcher->getTaggedVersions() as $tag => $version) {
            if ($placeRepo->findOneBy(array(
                'tpPackage' => $repository->getPackage(),
                'tpVersion' => $tag,
            )) === null) {
                $fetcher->switchToTag($tag);
                $importer->importDirectory($fetcher->getWebDirectory(), $repository->getPackage(), $version);
            }
        }
    }
}
