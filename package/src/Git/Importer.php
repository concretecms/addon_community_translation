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
     * A callback function that receive messages.
     *
     * @var callable
     */
    protected $logger = null;

    /**
     * Set a callback function that receive messages.
     *
     * @param callable|null $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger ?: null;
    }

    /**
     * Call the logger (if it's set) passing it a status string.
     *
     * @param string $line
     */
    protected function log($line)
    {
        if ($this->logger !== null) {
            call_user_func($this->logger, $line);
        }
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
        $package = $this->app->make('community_translation/package');
        /* @var \Doctrine\ORM\EntityRepository $package */
        $this->log(t("Initializing/fetching local repository clone"));
        $fetcher->update();
        foreach ($repository->getDevBranches() as $branch => $version) {
            $this->log(t("Checking out development branch '%s'", $branch));
            $fetcher->switchToBranch($branch);
            $this->log(t("Extracting strings for version '%s'", $version));
            $importer->importDirectory($fetcher->getWebDirectory(), $repository->getPackage(), $version);
        }
        $this->log(t("Listing tags"));
        $taggedVersions = $fetcher->getTaggedVersions();
        $skippedTags = array();
        foreach ($taggedVersions as $tag => $version) {
            if ($package->findOneBy(array(
                'pHandle' => $repository->getPackage(),
                'pVersion' => $version,
            )) !== null) {
                $skippedTags[] = $tag;
            } else {
                $this->log(t("Checking out tag '%s'", $tag));
                $fetcher->switchToTag($tag);
                $this->log(t("Extracting strings for version '%s'", $version));
                $importer->importDirectory($fetcher->getWebDirectory(), $repository->getPackage(), $version);
            }
        }
        if (!empty($skippedTags)) {
            $this->log(t("Tags skipped since already parsed: %s", implode(', ', $skippedTags)));
        }
    }
}
