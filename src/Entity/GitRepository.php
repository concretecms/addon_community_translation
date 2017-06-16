<?php

namespace CommunityTranslation\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a git repository to fetch the translatables strings from.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\GitRepository",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationGitRepositories",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="IDX_CTGitRepositoriesName", columns={"name"})
 *     },
 *     options={
 *         "comment": "Git repositories containing translatable strings"
 *     }
 * )
 */
class GitRepository
{
    /**
     * Create a new instance.
     *
     * @return static
     */
    public static function create()
    {
        $result = new static();
        $result->devBranches = [];
        $result->directoryToParse = '';
        $result->directoryForPlaces = '';
        $result->detectedVersions = [];
        $result->tagToVersionRegexp = '/^(?:v(?:er(?:s(?:ion)?)?)?[.\s]*)?(\d+(?:\.\d+)*)$/';
        $result->setTagFilters(null);

        return $result;
    }

    protected function __construct()
    {
    }

    /**
     * Git repository ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Git repository ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * Get the git repository ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Mnemonic name.
     *
     * @ORM\Column(type="string", length=100, nullable=false, options={"comment": "Mnemonic name"})
     *
     * @var string
     */
    protected $name;

    /**
     * Set the mnemonic name.
     *
     * @param string $value
     *
     * @return static
     */
    public function setName($value)
    {
        $this->name = (string) $value;

        return $this;
    }

    /**
     * Get the mnemonic name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Package handle.
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"comment": "Package handle"})
     *
     * @var string
     */
    protected $packageHandle;

    /**
     * Set the package handle.
     *
     * @param string $value
     *
     * @return static
     */
    public function setPackageHandle($value)
    {
        $this->packageHandle = (string) $value;

        return $this;
    }

    /**
     * Get the package handle.
     *
     * @return string
     */
    public function getPackageHandle()
    {
        return $this->packageHandle;
    }

    /**
     * Repository remote URL.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Repository remote URL"})
     *
     * @var string
     */
    protected $url;

    /**
     * Set the repository remote URL.
     *
     * @param string $value
     *
     * @return static
     */
    public function setURL($value)
    {
        $this->url = (string) $value;

        return $this;
    }

    /**
     * Get the repository remote URL.
     *
     * @return string
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * Development branches (keys are the branch name, values are the version - they should start with Package\Version::DEV_PREFIX).
     *
     * @ORM\Column(type="array", nullable=false, options={"comment": "Development branches (keys are the branch name, values are the version - they should start with Package\Version::DEV_PREFIX)"})
     *
     * @var array
     */
    protected $devBranches;

    /**
     * Set the development branches (keys are the branch name, values are the version).
     *
     * @param array $value
     *
     * @return static
     */
    public function setDevBranches(array $value)
    {
        $this->devBranches = $value;

        return $this;
    }

    /**
     * Get the development branches (keys are the branch name, values are the version).
     *
     * @return array
     */
    public function getDevBranches()
    {
        return $this->devBranches;
    }

    /**
     * Path to the directory to be parsed.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Path to the directory to be parsed"})
     *
     * @var string
     */
    protected $directoryToParse;

    /**
     * Set the path to the directory to be parsed.
     *
     * @param string $value
     *
     * @return static
     */
    public function setDirectoryToParse($value)
    {
        $this->directoryToParse = trim(str_replace(DIRECTORY_SEPARATOR, '/', trim((string) $value)), '/');

        return $this;
    }

    /**
     * Get the path to the directory to be parsed.
     *
     * @return string
     */
    public function getDirectoryToParse()
    {
        return $this->directoryToParse;
    }

    /**
     * Base directory for places.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Base directory for places"})
     *
     * @var string
     */
    protected $directoryForPlaces;

    /**
     * Set the base directory for places.
     *
     * @param string $value
     *
     * @return static
     */
    public function setDirectoryForPlaces($value)
    {
        $this->directoryForPlaces = trim(str_replace(DIRECTORY_SEPARATOR, '/', trim((string) $value)), '/');

        return $this;
    }

    /**
     * Get the base directory for places.
     *
     * @return string
     */
    public function getDirectoryForPlaces()
    {
        return $this->directoryForPlaces;
    }

    /**
     * Detected versions.
     *
     * @ORM\Column(type="array", nullable=false, options={"comment": "Repository detected versions"})
     *
     * @var array
     */
    protected $detectedVersions;

    /**
     * Add a repository detected version.
     *
     * @param string $version
     * @param string $kind
     * @param string $repoName
     *
     * @return static
     */
    public function addDetectedVersion($version, $kind, $repoName)
    {
        $this->detectedVersions[$version] = [
            'kind' => $kind,
            'repoName' => $repoName,
        ];

        return $this;
    }

    /**
     * Get the repository tag filters.
     *
     * @param string $version
     *
     * @return array|null
     */
    public function getDetectedVersion($version)
    {
        return isset($this->detectedVersions[$version]) ? $this->detectedVersions[$version] : null;
    }

    /**
     * Tag-to-version regular expression.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Tag-to-version regular expression"})
     *
     * @var string
     */
    protected $tagToVersionRegexp;

    /**
     * Set the tag-to-version regular expression.
     *
     * @param string $value
     *
     * @return static
     */
    public function setTagToVersionRegexp($value)
    {
        $this->tagToVersionRegexp = (string) $value;

        return $this;
    }

    /**
     * Get the tag-to-version regular expression.
     *
     * @return string
     */
    public function getTagToVersionRegexp()
    {
        return $this->tagToVersionRegexp;
    }

    /**
     * Repository tag filters.
     *
     * @ORM\Column(type="simple_array", nullable=false, options={"comment": "Repository tag filters"})
     *
     * @var string[]
     */
    protected $tagFilters;

    /**
     * Set the repository tag filters.
     *
     * @param string[]|null $value Null for no tags, an array otherwise (empty means all tags)
     *
     * @return static
     */
    public function setTagFilters(array $value = null)
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
    public function getTagFilters()
    {
        if ($this->tagFilters === ['none']) {
            $result = null;
        } elseif ($this->tagFilters === ['all']) {
            $result = [];
        } else {
            $result = $this->tagFilters;
        }

        return $result;
    }

    /**
     * Extracts the info for tag filter.
     *
     * @return null|array(array('operator' => string, 'version' => string))
     */
    public function getTagFiltersExpanded()
    {
        $tagFilters = $this->getTagFilters();
        if ($tagFilters === null) {
            $result = null;
        } else {
            $result = [];
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
        }

        return $result;
    }

    /**
     * Get a visual representation of the tag filters.
     *
     * @return string
     */
    public function getTagFiltersDisplayName()
    {
        $expanded = $this->getTagFiltersExpanded();
        if ($expanded === null) {
            $result = tc('Tags', 'none');
        } elseif (count($expanded) === 0) {
            $result = tc('Tags', 'all');
        } else {
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
                $list[] = "$op {$x['version']}";
            }
            $result = implode(' ∧ ', $list);
        }

        return $result;
    }
}
