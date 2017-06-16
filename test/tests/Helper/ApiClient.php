<?php

namespace CommunityTranslation\Tests\Helper;

use Exception;

class ApiClient
{
    /**
     * The root URL of the APIs.
     *
     * @var string
     */
    protected $rootURL;

    /**
     * The API entry point.
     *
     * @var string
     */
    protected $entryPoint;

    /**
     * The query string.
     *
     * @var string
     */
    protected $queryString;

    /**
     * The API token.
     *
     * @var string
     */
    protected $token;

    /**
     * The fields to post.
     *
     * @var array
     */
    protected $postFields;

    /**
     * The files to post.
     *
     * @var array
     */
    protected $postFiles;

    /**
     * Data from the last response.
     *
     * @var null|array
     */
    protected $lastResponse;

    /**
     * Initializes the instance.
     *
     * @param string $rootURL the root URL for the API calls
     */
    public function __construct($rootURL)
    {
        $this->rootURL = rtrim((string) $rootURL . '/');
        $this->reset(false);
    }

    /**
     * Set the API entry point.
     *
     * @param string $value
     *
     * @return static
     */
    public function setEntryPoint($value)
    {
        $this->entryPoint = (string) $value;

        return $this;
    }

    /**
     * Set the query string.
     *
     * @param string $value
     *
     * @return static
     */
    public function setQueryString($value)
    {
        $value = (string) $value;
        if ($value !== '' && $value[0] !== '?') {
            $value = '?' . $value;
        }
        $this->queryString = $value;

        return $this;
    }

    /**
     * Set the API token.
     *
     * @param string $value
     *
     * @return static
     */
    public function setToken($value)
    {
        $this->token = (string) $value;

        return $this;
    }

    /**
     * Set a field to be sent via POST.
     *
     * @param string $fieldName
     * @param string $value
     *
     * @return static
     */
    public function setPostField($fieldName, $value)
    {
        $this->postFields[$fieldName] = $value;

        return $this;
    }

    /**
     * (Re)Set all the fields to be sent via POST.
     *
     * @param array $postFields
     *
     * @return static
     */
    public function setPostFields(array $postFields)
    {
        $this->postFields = $postFields;

        return $this;
    }

    /**
     * Set a file to be sent via POST.
     *
     * @param string $fieldName
     * @param string $path
     *
     * @return static
     */
    public function setPostFile($fieldName, $path)
    {
        $this->postFiles[$fieldName] = $path;

        return $this;
    }

    /**
     * (Re)Set all the files to be sent via POST.
     *
     * @param array $postFiles
     *
     * @return static
     */
    public function setPostFiles(array $postFiles)
    {
        $this->postFiles = $postFiles;

        return $this;
    }

    /**
     * Reset all the data.
     *
     * @param bool $keepToken Keep the API token?
     *
     * @return static
     */
    public function reset($keepToken = true)
    {
        $this->lastResponse = null;
        $this->entryPoint = null;
        $this->queryString = '';
        if (!$keepToken) {
            $this->token = '';
        }
        $this->postFields = [];
        $this->postFiles = [];

        return $this;
    }

    /**
     * Perform the API call.
     * Retrieve the response details with getLastResponseCode, getLastResponseType, getLastResponseData.
     *
     * @throws Exception
     *
     * @return static
     */
    public function exec()
    {
        $this->lastResponse = null;
        if ($this->entryPoint === null) {
            throw new Exception('API entry point not set.');
        }
        $ch = curl_init();
        $url = rtrim($this->rootURL, '/') . '/' . ltrim($this->entryPoint, '/') . $this->queryString;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        if (empty($this->postFields) && empty($this->postFiles)) {
            curl_setopt($ch, CURLOPT_POST, false);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
            }
            if (empty($this->postFiles)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postFields);
            } else {
                $fields = $this->postFields;
                if (class_exists('CURLFile')) {
                    foreach ($this->postFiles as $fieldName => $path) {
                        $fields[$fieldName] = new CURLFile($path);
                    }
                } else {
                    if (defined('CURLOPT_SAFE_UPLOAD')) {
                        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
                    }
                    foreach ($this->postFiles as $fieldName => $path) {
                        $fields[$fieldName] = '@' . $path;
                    }
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            }
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = [
            'Expect: ',
        ];
        if ($this->token !== '') {
            $headers[] = 'API-Token: ' . $this->token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $responseBody = curl_exec($ch);
        if (!is_string($responseBody)) {
            $err = @curl_error($ch) ?: 'Unknown cURL error';
            @curl_close($ch);
            throw new Exception($err);
        }
        $info = curl_getinfo($ch);
        curl_close($ch);
        if (!is_array($info)) {
            throw new Exception('Failed to get response info');
        }
        if (!isset($info['http_code'])) {
            throw new Exception('Failed to get HTTP response code');
        }
        $responseCode = @(int) ($info['http_code']);
        $contentType = isset($info['content_type']) ? (string) $info['content_type'] : '';
        $contentLength = (isset($info['size_download']) && is_numeric($info['size_download'])) ? @(int) ($info['size_download']) : null;
        if ($responseCode < 200) {
            throw new Exception('Invalid HTTP response code received: ' . $info['http_code']);
        }
        if ($responseCode >= 400) {
            if (strpos($contentType, 'text/plain') !== 0) {
                throw new Exception('Wrong content type of error ' . $responseCode . ': ' . $contentType, $responseCode);
            } else {
                throw new ApiClientResponseException($responseBody, $responseCode);
            }
        }
        if ($contentLength !== null && strlen($responseBody) !== $contentLength) {
            throw new Exception("Wrong response size (expected: $contentLength, received: " . strlen($responseBody) . ')');
        }
        if (strpos($contentType, 'application/json') === 0) {
            if (strcasecmp(trim($responseBody), 'null') === 0) {
                $result = null;
            } else {
                $result = @json_decode($responseBody, true);
                if ($result === null) {
                    throw new Exception('Failed to decode JSON response');
                }
            }
        } else {
            $result = $responseBody;
        }

        $this->lastResponse = [
            'code' => $responseCode,
            'type' => $contentType,
            'data' => $result,
        ];
    }

    /**
     * Return the HTTP code of the last good API call.
     *
     * @throws Exception
     *
     * @return int
     */
    public function getLastResponseCode()
    {
        if ($this->lastResponse === null) {
            throw new Exception('No last good API call data.');
        }

        return $this->lastResponse['code'];
    }

    /**
     * Return the Content Type of the last good API call.
     *
     * @throws Exception
     *
     * @return string
     */
    public function getLastResponseType()
    {
        if ($this->lastResponse === null) {
            throw new Exception('No last good API call data.');
        }

        return $this->lastResponse['type'];
    }

    /**
     * Return the last response data.
     *
     * @throws Exception
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
