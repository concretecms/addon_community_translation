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
     * Search an approved locale given its ID (excluding the source one - en_US).
     *
     * @return Locale|null
     */
    public function findApproved($localeID)
    {
        $result = null;
        if (is_string($localeID) && $localeID !== '') {
            $l = $this->find($localeID);
            if ($l !== null && $l->isApproved() && !$l->isSource()) {
                $result = $l;
            }
        }

        return $result;
    }

    /**
     * Get the list of the approved locales (excluding the source one - en_US).
     *
     * @return Locale[]
     */
    public function getApprovedLocales()
    {
        $locales = $this->findBy(array('lIsSource' => false, 'lIsApproved' => true));
        usort($locales, function (Locale $a, Locale $b) {
            return strcasecmp($a->getDisplayName(), $b->getDisplayName());
        });

        return $locales;
    }
}
