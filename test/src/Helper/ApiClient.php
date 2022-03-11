<?php

declare(strict_types=1);

namespace CommunityTranslation\Tests\Helper;

use CURLFile;
use Exception;

class ApiClient
{
    /**
     * The root URL of the APIs.
     */
    private string $rootURL;

    /**
     * The API entry point.
     */
    private string $entryPoint;

    /**
     * The query string.
     */
    private string $queryString;

    /**
     * The API token.
     */
    private string $token;

    /**
     * The fields to post.
     */
    private array $postFields;

    /**
     * The files to post.
     */
    private array $postFiles;

    /**
     * Data from the last response.
     */
    private ?array $lastResponse;

    /**
     * Initializes the instance.
     *
     * @param string $rootURL the root URL for the API calls
     */
    public function __construct(string $rootURL)
    {
        $this->rootURL = rtrim($rootURL, '/');
        $this->reset(false);
    }

    /**
     * Set the API entry point.
     *
     * @return $this
     */
    public function setEntryPoint(string $value): self
    {
        $this->entryPoint = ltrim($value, '/');

        return $this;
    }

    /**
     * Set the query string.
     *
     * @return $this
     */
    public function setQueryString(string $value): self
    {
        if ($value !== '' && $value[0] !== '?') {
            $value = '?' . $value;
        }
        $this->queryString = $value;

        return $this;
    }

    /**
     * Set the API token.
     *
     * @return $this
     */
    public function setToken(string $value): self
    {
        $this->token = $value;

        return $this;
    }

    /**
     * Set a field to be sent via POST.
     *
     * @return $this
     */
    public function setPostField(string $fieldName, string $value): self
    {
        $this->postFields[$fieldName] = $value;

        return $this;
    }

    /**
     * (Re)Set all the fields to be sent via POST.
     *
     * @return $this
     */
    public function setPostFields(array $postFields): self
    {
        $this->postFields = $postFields;

        return $this;
    }

    /**
     * Set a file to be sent via POST.
     *
     * @return $this
     */
    public function setPostFile(string $fieldName, string $path): self
    {
        $this->postFiles[$fieldName] = $path;

        return $this;
    }

    /**
     * (Re)Set all the files to be sent via POST.
     *
     * @return $this
     */
    public function setPostFiles(array $postFiles): self
    {
        $this->postFiles = $postFiles;

        return $this;
    }

    /**
     * Reset all the data.
     *
     * @param bool $keepToken Keep the API token?
     *
     * @return $this
     */
    public function reset(bool $keepToken = true): self
    {
        $this->entryPoint = '';
        $this->queryString = '';
        if (!$keepToken) {
            $this->token = '';
        }
        $this->postFields = [];
        $this->postFiles = [];
        $this->lastResponse = null;

        return $this;
    }

    /**
     * Perform the API call.
     * Retrieve the response details with getLastResponseCode, getLastResponseType, getLastResponseData.
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function exec(): self
    {
        $this->lastResponse = null;
        if ($this->entryPoint === '') {
            throw new Exception('API entry point not set');
        }
        $headers = [
            'Expect: ',
        ];
        if ($this->token !== '') {
            $headers[] = 'API-Token: ' . $this->token;
        }
        $url = "{$this->rootURL}/{$this->entryPoint}{$this->queryString}";
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($this->postFields === [] && $this->postFiles === []) {
            $curlOptions[CURLOPT_POST] = false;
        } else {
            $curlOptions[CURLOPT_POST] = true;
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                $curlOptions[CURLOPT_SAFE_UPLOAD] = true;
            }
            if ($this->postFiles === []) {
                $curlOptions[CURLOPT_POSTFIELDS] = $this->postFields;
            } else {
                $fields = $this->postFields;
                if (class_exists(CURLFile::class)) {
                    foreach ($this->postFiles as $fieldName => $path) {
                        $fields[$fieldName] = new CURLFile($path);
                    }
                } else {
                    if (defined('CURLOPT_SAFE_UPLOAD')) {
                        $curlOptions[CURLOPT_SAFE_UPLOAD] = false;
                    }
                    foreach ($this->postFiles as $fieldName => $path) {
                        $fields[$fieldName] = '@' . $path;
                    }
                }
                $curlOptions[CURLOPT_POSTFIELDS] = $fields;
            }
        }
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception('curl_init() failed');
        }
        try {
            if (curl_setopt_array($ch, $curlOptions) === false) {
                $err = curl_error($ch) ?: 'Unknown cURL error';
                throw new Exception("curl_setopt_array() failed: {$err}");
            }
            $responseBody = curl_exec($ch);
            if (!is_string($responseBody)) {
                $err = curl_error($ch) ?: 'Unknown cURL error';
                throw new Exception("curl_exec() failed: {$err}");
            }
            $info = curl_getinfo($ch);
            if (!is_array($info)) {
                $err = curl_error($ch) ?: 'Unknown cURL error';
                throw new Exception("curl_getinfo() failed: {$err}");
            }
        } finally {
            curl_close($ch);
        }
        if (!isset($info['http_code'])) {
            throw new Exception('Failed to get HTTP response code');
        }
        $responseCode = (int) $info['http_code'];
        $contentType = (string) ($info['content_type'] ?? '');
        $contentLength = is_numeric($info['size_download'] ?? null) ? (int) $info['size_download'] : null;
        if ($responseCode < 200) {
            throw new Exception("Invalid HTTP response code received: {$info['http_code']}");
        }
        if ($responseCode >= 400) {
            if (strpos($contentType, 'text/plain') !== 0) {
                throw new Exception("'{$contentType}' is a wrong content type for error {$responseCode} when calling {$url}.\n\nResponse body:\n{$responseBody}", $responseCode);
            }
            throw new ApiClientResponseException($responseBody, $responseCode);
        }
        $responseBodyLength = strlen($responseBody);
        if ($contentLength !== null && $responseBodyLength !== $contentLength) {
            throw new Exception("Wrong response size (expected: {$contentLength}, received: {$responseBodyLength})");
        }
        if (strpos($contentType, 'application/json') === 0) {
            $result = json_decode($responseBody, true, JSON_THROW_ON_ERROR);
        } else {
            $result = $responseBody;
        }
        $this->lastResponse = [
            'code' => $responseCode,
            'type' => $contentType,
            'data' => $result,
        ];

        return $this;
    }

    /**
     * Return the HTTP code of the last good API call.
     *
     * @throws \Exception
     */
    public function getLastResponseCode(): int
    {
        if ($this->lastResponse === null) {
            throw new Exception('No last good API call data.');
        }

        return $this->lastResponse['code'];
    }

    /**
     * Return the Content Type of the last good API call.
     *
     * @throws \Exception
     */
    public function getLastResponseType(): string
    {
        if ($this->lastResponse === null) {
            throw new Exception('No last good API call data.');
        }

        return $this->lastResponse['type'];
    }

    /**
     * Return the last response data.
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function getLastResponseData()
    {
        if ($this->lastResponse === null) {
            throw new Exception('No last good API call data.');
        }

        return $this->lastResponse['data'];
    }
}
