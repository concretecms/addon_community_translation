<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents a git repository to fetch the translatables strings from.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\GitRepository",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationGitRepositories",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="IDX_CTGitRepositoriesName", columns={"name"})
 *     },
 *     options={
 *         "comment": "Git repositories containing translatable strings"
 *     }
 * )
 */
class GitRepository
{
    public const DEFAULT_TAGTOVERSION_REGEXP = '/^(?:v(?:er(?:s(?:ion)?)?)?[.\s]*)?(\d+(?:\.\d+)*)$/';

    /**
     * Git repository ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Git repository ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * Mnemonic name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=100, nullable=false, options={"comment": "Mnemonic name"})
     */
    protected string $name;

    /**
     * Package handle.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=64, nullable=false, options={"comment": "Package handle"})
     */
    protected string $packageHandle;

    /**
     * Package name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Package name"})
     */
    protected string $packageName;

    /**
     * Repository remote URL.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Repository remote URL"})
     */
    protected string $url;

    /**
     * Development branches (keys are the branch name, values are the version - they should start with Package\Version::DEV_PREFIX).
     *
     * @Doctrine\ORM\Mapping\Column(type="array", nullable=false, options={"comment": "Development branches (keys are the branch name, values are the version - they should start with Package\Version::DEV_PREFIX)"})
     */
    protected array $devBranches;

    /**
     * Path to the directory to be parsed.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Path to the directory to be parsed"})
     */
    protected string $directoryToParse;

    /**
     * Base directory for places.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Base directory for places"})
     */
    protected string $directoryForPlaces;

    /**
     * Detected versions.
     *
     * @Doctrine\ORM\Mapping\Column(type="array", nullable=false, options={"comment": "Repository detected versions"})
     */
    protected array $detectedVersions;

    /**
     * Tag-to-version regular expression.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Tag-to-version regular expression"})
     */
    protected string $tagToVersionRegexp;

    /**
     * Repository tag filters.
     *
     * @Doctrine\ORM\Mapping\Column(type="simple_array", nullable=false, options={"comment": "Repository tag filters"})
     *
     * @var string[]
     */
    protected array $tagFilters;

    public function __construct()
    {
        $this->id = null;
        $this->name = '';
        $this->packageHandle = '';
        $this->packageName = '';
        $this->url = '';
        $this->devBranches = [];
        $this->directoryToParse = '';
        $this->directoryForPlaces = '';
        $this->detectedVersions = [];
        $this->tagToVersionRegexp = static::DEFAULT_TAGTOVERSION_REGEXP;
        $this->setTagFilters(null);
    }

    /**
     * Get the git repository ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Set the mnemonic name.
     *
     * @return $this
     */
    public function setName(string $value): self
    {
        $this->name = $value;

        return $this;
    }

    /**
     * Get the mnemonic name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the package handle.
     *
     * @return $this
     */
    public function setPackageHandle(string $value): self
    {
        $this->packageHandle = $value;

        return $this;
    }

    /**
     * Get the package handle.
     */
    public function getPackageHandle(): string
    {
        return $this->packageHandle;
    }

    /**
     * Set the package name.
     *
     * @return $this
     */
    public function setPackageName(string $value): self
    {
        $this->packageName = $value;

        return $this;
    }

    /**
     * Get the package name.
     */
    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * Set the repository remote URL.
     *
     * @return $this
     */
    public function setURL(string $value): self
    {
        $this->url = $value;

        return $this;
    }

    /**
     * Get the repository remote URL.
     */
    public function getURL(): string
    {
        return $this->url;
    }

    /**
     * Set the development branches (keys are the branch name, values are the version).
     *
     * @return $this
     */
    public function setDevBranches(array $value): self
    {
        $this->devBranches = $value;

        return $this;
    }

    /**
     * Get the development branches (keys are the branch name, values are the version).
     */
    public function getDevBranches(): array
    {
        return $this->devBranches;
    }

    /**
     * Set the path to the directory to be parsed.
     *
     * @return $this
     */
    public function setDirectoryToParse(string $value): self
    {
        $this->directoryToParse = trim(str_replace(DIRECTORY_SEPARATOR, '/', trim($value)), '/');

        return $this;
    }

    /**
     * Get the path to the directory to be parsed.
     */
    public function getDirectoryToParse(): string
    {
        return $this->directoryToParse;
    }

    /**
     * Set the base directory for places.
     *
     * @return $this
     */
    public function setDirectoryForPlaces(string $value): self
    {
        $this->directoryForPlaces = trim(str_replace(DIRECTORY_SEPARATOR, '/', trim($value)), '/');

        return $this;
    }

    /**
     * Get the base directory for places.
     */
    public function getDirectoryForPlaces(): string
    {
        return $this->directoryForPlaces;
    }

    /**
     * Reset the detected versions.
     *
     * @return $this
     */
    public function resetDetectedVersions(): self
    {
        $this->detectedVersions = [];

        return $this;
    }

    /**
     * Add a repository detected version.
     *
     * @return $this
     */
    public function addDetectedVersion(string $version, string $kind, string $repoName): self
    {
        $this->detectedVersions[$version] = [
            'kind' => $kind,
            'repoName' => $repoName,
        ];

        return $this;
    }

    /**
     * Get the repository tag filters.
     */
    public function getDetectedVersion(string $version): ?array
    {
        return $this->detectedVersions[$version] ?? null;
    }

    /**
     * Set the tag-to-version regular expression.
     *
     * @return $this
     */
    public function setTagToVersionRegexp(string $value): self
    {
        $this->tagToVersionRegexp = $value;

        return $this;
    }

    /**
     * Get the tag-to-version regular expression.
     */
    public function getTagToVersionRegexp(): string
    {
        return $this->tagToVersionRegexp;
    }

    /**
     * Set the repository tag filters.
     *
     * @param string[]|null $value NULL for no tags, an array otherwise ([] means all tags)
     *
     * @return $this
     */
    public function setTagFilters(?array $value = null): self
    {
        if ($value === null) {
            $this->tagFilters = ['none'];
        } elseif (empty($value)) {
            $this->tagFilters = ['all'];
        } else {
            $this->tagFilters = $value;
        }

        return $this;
    }

    /**
     * Get the repository tag filters.
     *
     * @return string[]|null
     */
    public function getTagFilters(): ?array
    {
        if ($this->tagFilters === ['none']) {
            return null;
        }
        if ($this->tagFilters === ['all']) {
            return [];
        }

        return $this->tagFilters;
    }

    /**
     * Extracts the info for tag filter.
     *
     * @return null|array(array('operator' => string, 'version' => string))
     */
    public function getTagFiltersExpanded(): ?array
    {
        $tagFilters = $this->getTagFilters();
        if ($tagFilters === null) {
            return null;
        }
        $result = [];
        $m = null;
        foreach ($tagFilters as $tagFilter) {
            if (preg_match('/^\s*([<>=]+)\s*(\d+(?:\.\d+)?)\s*$/', $tagFilter, $m)) {
                switch ($m[1]) {
                    case '<=':
                    case '<':
                    case '=':
                    case '>=':
                    case '>':
                        $result[] = ['operator' => $m[1], 'version' => $m[2]];
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * Get a visual representation of the tag filters.
     */
    public function getTagFiltersDisplayName(): string
    {
        $expanded = $this->getTagFiltersExpanded();
        if ($expanded === null) {
            return tc('Tags', 'none');
        }
        if ($expanded === []) {
            return tc('Tags', 'all');
        }
        $list = [];
        foreach ($expanded as $x) {
            switch ($x['operator']) {
                case '<=':
                    $op = '≤';
                    break;
                case '>=':
                    $op = '≥';
                    break;
                default:
                    $op = $x['operator'];
                    break;
            }
            $list[] = "{$op} {$x['version']}";
        }

        return implode(' ∧ ', $list);
    }
}
