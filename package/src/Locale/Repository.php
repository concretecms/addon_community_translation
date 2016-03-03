<?php
namespace Concrete\Package\CommunityTranslation\Src\Locale;

use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\Application;
use Doctrine\ORM\EntityRepository;

class Repository extends EntityRepository implements ApplicationAwareInterface
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
     * Get some stats about one or more packages and one or more locales.
     *
     * @param Package|Packages[]|array|array[array] $packages
     * @param Locale|Locale[]|string|string[] $locales
     *
     * @return Stats[]
     */
    public function getApprovedLocales()
    {
        $locales = $this->findBy(array('lIsSource' => false, 'lIsApproved' => true));
        usort($locales, function (Locale $a, Locale $b) {
            return strcasecmp($a->getDisplayName(), $a->getDisplayName());
        });

        return $locales;
    }
}
