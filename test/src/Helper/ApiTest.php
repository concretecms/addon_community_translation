<?php

declare(strict_types=1);

namespace CommunityTranslation\Tests\Helper;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Entity\Permission\IpAccessControlCategory;
use Concrete\Core\Permission\IpAccessControlService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

abstract class ApiTest extends TestCase
{
    protected static Repository $config;

    protected static IpAccessControlCategory $ipAccessControlApiAccess;

    protected static IpAccessControlCategory $ipAccessControlRateLimit;

    protected static EntityManager $em;

    private static string $apiRootURL;

    /**
     * @var string[]
     */
    private static array $configSections;

    private array $originalConfig;

    private static IpAccessControlCategory $originalIPAccessControlApiAccess;

    private static IpAccessControlCategory $originalIPAccessControlRateLimit;

    /**
     * {@inheritdoc}
     *
     * @see \PHPUnit\Framework\TestCase::setUpBeforeClass()
     */
    public static function setUpBeforeClass(): void
    {
        $apiRootURL = rtrim((string) ($_ENV['CT_TEST_API_ROOTURL'] ?? ''), '/');
        if ($apiRootURL === '') {
            $apiRootURL = rtrim((string) getenv('CT_TEST_API_ROOTURL'), '/');
            if ($apiRootURL === '') {
                self::markTestSkipped('CT_TEST_API_ROOTURL environment variable is missing: set it to the URL of a running concrete5 instance with Community Translation installed.');
            }
        }
        self::$apiRootURL = $apiRootURL;
        self::$configSections = array_values(
            array_map(
                static function (string $filename): string {
                    return substr($filename, 0, -strlen('.php'));
                },
                array_filter(
                    scandir(CT_ROOT_DIR . '/config'),
                    static function (string $filename): bool {
                        return (bool) preg_match('/^\w.*\.php/', $filename);
                    }
                )
            )
        );
        self::$config = app(Repository::class);
        self::$em = app(EntityManager::class);
        self::$ipAccessControlApiAccess = self::$em->getRepository(IpAccessControlCategory::class)->findOneBy(['handle' => 'community_translation_api_access']);
        self::$originalIPAccessControlApiAccess = clone self::$ipAccessControlApiAccess;
        self::$ipAccessControlRateLimit = self::$em->getRepository(IpAccessControlCategory::class)->findOneBy(['handle' => 'community_translation_api_ratelimit']);
        self::$originalIPAccessControlRateLimit = clone self::$ipAccessControlRateLimit;
    }

    /**
     * {@inheritdoc}
     *
     * @see \PHPUnit\Framework\TestCase::tearDownAfterClass()
     */
    public static function tearDownAfterClass(): void
    {
        self::revertIPAccessControl(self::$originalIPAccessControlApiAccess, self::$ipAccessControlApiAccess);
        self::revertIPAccessControl(self::$originalIPAccessControlRateLimit, self::$ipAccessControlRateLimit);
        self::$em->flush();
        self::deleteIPs(self::$ipAccessControlApiAccess);
        self::deleteIPs(self::$ipAccessControlRateLimit);
    }

    /**
     * {@inheritdoc}
     *
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp(): void
    {
        $this->originalConfig = array_map(
            function (string $configSection): array {
                return [
                    'section' => $configSection,
                    'values' => self::$config->get("community_translation::{$configSection}"),
                ];
            },
            self::$configSections,
        );
        self::$ipAccessControlApiAccess
            ->setEnabled(false)
            ->setMaxEvents(3)
            ->setTimeWindow(30)
            ->setBanDuration(600)
        ;
        self::$ipAccessControlRateLimit
            ->setEnabled(false)
            ->setMaxEvents(3)
            ->setTimeWindow(30)
            ->setBanDuration(600)
        ;
        self::$em->flush();
        self::deleteIPs(self::$ipAccessControlApiAccess);
        self::deleteIPs(self::$ipAccessControlRateLimit);
    }

    /**
     * {@inheritdoc}
     *
     * @see \PHPUnit\Framework\TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        array_map(
            function (array $originalConfig): void {
                self::$config->save("community_translation::{$originalConfig['section']}", $originalConfig['values']);
            },
            $this->originalConfig
        );
    }

    protected function buildApiClient(): ApiClient
    {
        return new ApiClient(self::$apiRootURL . self::$config->get('community_translation::paths.api'));
    }

    private static function revertIPAccessControl(IpAccessControlCategory $original, IpAccessControlCategory $entity): void
    {
        $entity
            ->setEnabled($original->isEnabled())
            ->setMaxEvents($original->getMaxEvents())
            ->setTimeWindow($original->getTimeWindow())
            ->setBanDuration($original->getBanDuration())
        ;
    }

    private static function deleteIPs(IpAccessControlCategory $category): void
    {
        $service = app(IpAccessControlService::class, ['category' => $category]);
        $service->deleteEvents();
        foreach ([
            IpAccessControlService::IPRANGETYPE_BLACKLIST_AUTOMATIC,
            IpAccessControlService::IPRANGETYPE_BLACKLIST_MANUAL,
            IpAccessControlService::IPRANGETYPE_WHITELIST_MANUAL,
        ] as $rangeType) {
            foreach ($service->getRanges($rangeType) as $range) {
                $service->deleteRange($range);
            }
        }
    }
}
