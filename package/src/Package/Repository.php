<?php

namespace Concrete\Package\CommunityTranslation\Src\Package;

use Concrete\Core\Application\Application;
use Concrete\Core\Application\ApplicationAwareInterface;
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
     * Get the latest version of a translated package.
     *
     * @return string|null
     */
    public function getLatestVersion($packageOrHandle)
    {
        if ($packageOrHandle instanceof Package) {
            $handle = $packageOrHandle->getHandle;
        } else {
            $handle = (string) $packageOrHandle;
        }
        $result = null;
        foreach ($this->findBy(array('pHandle' => $handle)) as $package) {
            $v = $package->getVersion();
            if (strpos($v, Package::DEV_PREFIX) !== 0) {
                if ($result === null) {
                    $result = $v;
                } elseif (version_compare($v, $result) > 0) {
                    $result = $v;
                }
            }
        }

        return $result;
    }
}
