<?php
namespace Concrete\Package\CommunityTranslation\Job;

use Concrete\Core\Job\Job as AbstractJob;

class ParseGitRepositories extends AbstractJob
{
    public function getJobName()
    {
        return t('Parse Git Repositories');
    }

    public function getJobDescription()
    {
        return t('Fetch and all defined Git Repositories and extract translatable strings');
    }

    protected $startTime;

    protected function getLogPrefix()
    {
        $delta = id(new \DateTime())->diff($this->startTime);
        $totMinutes = (($delta->d * 24) + $delta->h) * 60 + $delta->m;

        return sprintf('%02d.%02d ', $totMinutes, $delta->s);
    }

    public function run()
    {
        $app = \Core::make('app');

        $this->startTime = new \DateTime();
        $result = '';
        $repositories = $app->make('community_translation/git')->findAll();
        if (empty($repositories)) {
            $result .= t('No Git Repositories defined.');
        } else {
            foreach ($repositories as $repository) {
                $result .= $this->workOnGitRepository($repository);
            }
        }

        return nl2br(h(trim($result)));
    }

    protected function workOnGitRepository(\Concrete\Package\CommunityTranslation\Src\Git\Repository $repository)
    {
        $me = $this;
        $result = '';
        $result .= $this->getLogPrefix().t("Processing repository '%s'", $repository->getName())."\n";
        try {
            $importer = \Core::make('community_translation/git/importer');
            $importer->setLogger(function ($text) use ($me, &$result) {
                $result .= $me->getLogPrefix().$text."\n";
            });
            $importer->import($repository);
        } catch (\Exception $x) {
            $result .= t('ERROR: %s', $x->getMessage())."\n";
        }

        return $result;
    }
}
