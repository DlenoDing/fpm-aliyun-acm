<?php
/**
 * Handles all HTTP requests using cURL and manages the responses.
 * @license http://opensource.org/licenses/bsd-license.php Simplified BSD License
 */

namespace Dleno\AliYunAcm;


class RequestCore
{
    /**
     * The URL being requested.
     */
    public $requestUrl;

    /**
     * The headers being sent in the request.
     */
    public $requestHeaders;

    /**
     * The body being sent in the request.
     */
    public $requestBody;

    /**
     * The response returned by the request.
     */
    public $response;

    /**
     * The headers returned by the request.
     */
    public $responseHeaders;

    /**
     * The body returned by the request.
     */
    public $responseBody;

    /**
     * The HTTP status code returned by the request.
     */
    public $responseCode;

    /**
     * Additional response data.
     */
    public $responseInfo;

    /**
     * The handle for the cURL object.
     */
    public $curlHandle;

    /**
     * The method by which the request is being made.
     */
    public $method;

    /**
     * Stores the proxy settings to use for the request.
     */
    public $proxy = null;

    /**
     * The username to use for the request.
     */
    public $username = null;

    /**
     * The password to use for the request.
     */
    public $password = null;

    /**
     * Custom CURLOPT settings.
     */
    public $curlopts = null;

    /**
     * The state of debug mode.
     */
    public $debugMode = false;

    /**
     * The default class to use for HTTP Requests (defaults to <RequestCore>).
     */
    public $requestClass = '\\Dleno\\AliYunAcm\\RequestCore';

    /**
     * The default class to use for HTTP Responses (defaults to <ResponseCore>).
     */
    public $responseClass = '\\Dleno\\AliYunAcm\\ResponseCore';

    /**
     * Default useragent string to use.
     */
    public $useragent = 'RequestCore/1.4.3';

    /**
     * File to read from while streaming up.
     */
    public $readFile = null;

    /**
     * The resource to read from while streaming up.
     */
    public $readStream = null;

    /**
     * The size of the stream to read from.
     */
    public $readStreamSize = null;

    /**
     * The length already read from the stream.
     */
    public $readStreamRead = 0;

    /**
     * File to write to while streaming down.
     */
    public $writeFile = null;

    /**
     * The resource to write to while streaming down.
     */
    public $writeStream = null;

    /**
     * Stores the intended starting seek position.
     */
    public $seekPosition = null;

    /**
     * The location of the cacert.pem file to use.
     */
    public $cacertLocation = false;

    /**
     * The state of SSL certificate verification.
     */
    public $sslVerification = true;

    /**
     * The user-defined callback function to call when a stream is read from.
     */
    public $registeredStreamingReadCallback = null;

    /**
     * The user-defined callback function to call when a stream is written to.
     */
    public $registeredStreamingWriteCallback = null;


    /*%******************************************************************************************%*/
    // CONSTANTS

    /**
     * GET HTTP Method
     */
    const HTTP_GET = 'GET';

    /**
     * POST HTTP Method
     */
    const HTTP_POST = 'POST';

    /**
     * PUT HTTP Method
     */
    const HTTP_PUT = 'PUT';

    /**
     * DELETE HTTP Method
     */
    const HTTP_DELETE = 'DELETE';

    /**
     * HEAD HTTP Method
     */
    const HTTP_HEAD = 'HEAD';


    /*%******************************************************************************************%*/
    // CONSTRUCTOR/DESTRUCTOR

    /**
     * Constructs a new instance of this class.
     *
     * @param string $url (Optional) The URL to request or service endpoint to query.
     * @param string $proxy (Optional) The faux-url to use for proxy settings. Takes the following format: `proxy://user:pass@hostname:port`
     * @param array $helpers (Optional) An associative array of classnames to use for request, and response functionality. Gets passed in automatically by the calling class.
     * @return $this A reference to the current instance.
     */
    public function __construct($url = null, $proxy = null, $helpers = null)
    {
        // Set some default values.
        $this->requestUrl = $url;
        $this->method = self::HTTP_GET;
        $this->requestHeaders = array();
        $this->requestBody = '';

        // Set a new Request class if one was set.
        if (isset($helpers['request']) && !empty($helpers['request'])) {
            $this->requestClass = $helpers['request'];
        }

        // Set a new Request class if one was set.
        if (isset($helpers['response']) && !empty($helpers['response'])) {
            $this->responseClass = $helpers['response'];
        }

        if ($proxy) {
            $this->setProxy($proxy);
        }

        return $this;
    }

    /**
     * Destructs the instance. Closes opened file handles.
     *
     * @return $this A reference to the current instance.
     */
    public function __destruct()
    {
        if (isset($this->readFile) && isset($this->readStream)) {
            fclose($this->readStream);
        }

        if (isset($this->writeFile) && isset($this->writeStream)) {
            fclose($this->writeStream);
        }

        return $this;
    }


    /*%******************************************************************************************%*/
    // REQUEST METHODS

    /**
     * Sets the credentials to use for authentication.
     *
     * @param string $user (Required) The username to authenticate with.
     * @param string $pass (Required) The password to authenticate with.
     * @return $this A reference to the current instance.
     */
    public function setCredentials($user, $pass)
    {
        $this->username = $user;
        $this->password = $pass;
        return $this;
    }

    /**
     * Adds a custom HTTP header to the cURL request.
     *
     * @param string $key (Required) The custom HTTP header to set.
     * @param mixed $value (Required) The value to assign to the custom HTTP header.
     * @return $this A reference to the current instance.
     */
    public function addHeader($key, $value)
    {
        $this->requestHeaders[$key] = $value;
        return $this;
    }

    /**
     * Removes an HTTP header from the cURL request.
     *
     * @param string $key (Required) The custom HTTP header to set.
     * @return $this A reference to the current instance.
     */
    public function removeHeader($key)
    {
        if (isset($this->requestHeaders[$key])) {
            unset($this->requestHeaders[$key]);
        }
        return $this;
    }

    /**
     * Set the method type for the request.
     *
     * @param string $method (Required) One of the following constants: <HTTP_GET>, <HTTP_POST>, <HTTP_PUT>, <HTTP_HEAD>, <HTTP_DELETE>.
     * @return $this A reference to the current instance.
     */
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Sets a custom useragent string for the class.
     *
     * @param string $ua (Required) The useragent string to use.
     * @return $this A reference to the current instance.
     */
    public function setUseragent($ua)
    {
        $this->useragent = $ua;
        return $this;
    }

    /**
     * Set the body to send in the request.
     *
     * @param string $body (Required) The textual content to send along in the body of the request.
     * @return $this A reference to the current instance.
     */
    public function setBody($body)
    {
        $this->requestBody = $body;
        return $this;
    }

    /**
     * Set the URL to make the request to.
     *
     * @param string $url (Required) The URL to make the request to.
     * @return $this A reference to the current instance.
     */
    public function setRequestUrl($url)
    {
        $this->requestUrl = $url;
        return $this;
    }

    /**
     * Set additional CURLOPT settings. These will merge with the default settings, and override if
     * there is a duplicate.
     *
     * @param array $curlopts (Optional) A set of key-value pairs that set `CURLOPT` options. These will merge with the existing CURLOPTs, and ones passed here will override the defaults. Keys should be the `CURLOPT_*` constants, not strings.
     * @return $this A reference to the current instance.
     */
    public function setCurlopts($curlopts)
    {
        $this->curlopts = $curlopts;
        return $this;
    }

    /**
     * Sets the length in bytes to read from the stream while streaming up.
     *
     * @param integer $size (Required) The length in bytes to read from the stream.
     * @return $this A reference to the current instance.
     */
    public function setReadStreamSize($size)
    {
        $this->readStreamSize = $size;

        return $this;
    }

    /**
     * Sets the resource to read from while streaming up. Reads the stream from its current position until
     * EOF or `$size` bytes have been read. If `$size` is not given it will be determined by <php:fstat()> and
     * <php:ftell()>.
     *
     * @param resource $resource (Required) The readable resource to read from.
     * @param integer $size (Optional) The size of the stream to read.
     * @return $this A reference to the current instance.
     */
    public function setReadStream($resource, $size = null)
    {
        if (!isset($size) || $size < 0) {
            $stats = fstat($resource);

            if ($stats && $stats['size'] >= 0) {
                $position = ftell($resource);

                if ($position !== false && $position >= 0) {
                    $size = $stats['size'] - $position;
                }
            }
        }

        $this->readStream = $resource;

        return $this->setReadStreamSize($size);
    }

    /**
     * Sets the file to read from while streaming up.
     *
     * @param string $location (Required) The readable location to read from.
     * @return $this A reference to the current instance.
     */
    public function setReadFile($location)
    {
        $this->readFile = $location;
        $readFileHandle = fopen($location, 'r');

        return $this->setReadStream($readFileHandle);
    }

    /**
     * Sets the resource to write to while streaming down.
     *
     * @param resource $resource (Required) The writeable resource to write to.
     * @return $this A reference to the current instance.
     */
    public function setWriteStream($resource)
    {
        $this->writeStream = $resource;

        return $this;
    }

    /**
     * Sets the file to write to while streaming down.
     *
     * @param string $location (Required) The writeable location to write to.
     * @return $this A reference to the current instance.
     */
    public function setWriteFile($location)
    {
        $this->writeFile = $location;
        $writeFileHandle = fopen($location, 'w');

        return $this->setWriteStream($writeFileHandle);
    }

    /**
     * Set the proxy to use for making requests.
     *
     * @param string $proxy (Required) The faux-url to use for proxy settings. Takes the following format: `proxy://user:pass@hostname:port`
     * @return $this A reference to the current instance.
     */
    public function setProxy($proxy)
    {
        $proxy = parse_url($proxy);
        $proxy['user'] = isset($proxy['user']) ? $proxy['user'] : null;
        $proxy['pass'] = isset($proxy['pass']) ? $proxy['pass'] : null;
        $proxy['port'] = isset($proxy['port']) ? $proxy['port'] : null;
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * Set the intended starting seek position.
     *
     * @param integer $position (Required) The byte-position of the stream to begin reading from.
     * @return $this A reference to the current instance.
     */
    public function setSeekPosition($position)
    {
        $this->seekPosition = isset($position) ? (integer)$position : null;

        return $this;
    }

    /**
     * Register a callback function to execute whenever a data stream is read from using
     *
     * @return $this A reference to the current instance.
     */
    public function registerStreamingReadCallback($callback)
    {
        $this->registeredStreamingReadCallback = $callback;

        return $this;
    }

    /**
     * Register a callback function to execute whenever a data stream is written to using
     * <CFRequest::streamingWriteCallback()>.
     *
     * The user-defined callback function should accept two arguments:
     * @param string|array|function $callback (Required) The callback function is called by <php:call_user_func()>, so you can pass the following values: <ul>
     * @return $this A reference to the current instance.
     */
    public function registerStreamingWriteCallback($callback)
    {
        $this->registeredStreamingWriteCallback = $callback;

        return $this;
    }


    /*%******************************************************************************************%*/
    // PREPARE, SEND, AND PROCESS REQUEST

    /**
     * A callback function that is invoked by cURL for streaming up.
     *
     * @param resource $curlHandle (Required) The cURL handle for the request.
     * @param resource $fileHandle (Required) The open file handle resource.
     * @param integer $length (Required) The maximum number of bytes to read.
     * @return binary Binary data from a stream.
     */
    public function streamingReadCallback($curlHandle, $fileHandle, $length)
    {
        // Once we've sent as much as we're supposed to send...
        if ($this->readStreamRead >= $this->readStreamSize) {
            // Send EOF
            return '';
        }

        // If we're at the beginning of an upload and need to seek...
        if ($this->readStreamRead == 0 && isset($this->seekPosition) && $this->seekPosition !== ftell($this->readStream)) {
            if (fseek($this->readStream, $this->seekPosition) !== 0) {
                throw new Exception\RequestCoreException('The stream does not support seeking and is either not at the requested position or the position is unknown.');
            }
        }

        $read = fread($this->readStream, min($this->readStreamSize - $this->readStreamRead, $length)); // Remaining upload data or cURL's requested chunk size
        $this->readStreamRead += strlen($read);

        $out = $read === false ? '' : $read;

        // Execute callback function
        if ($this->registeredStreamingReadCallback) {
            call_user_func($this->registeredStreamingReadCallback, $curlHandle, $fileHandle, $out);
        }

        return $out;
    }

    /**
     * A callback function that is invoked by cURL for streaming down.
     *
     * @param resource $curlHandle (Required) The cURL handle for the request.
     * @param binary $data (Required) The data to write.
     * @return integer The number of bytes written.
     */
    public function streamingWriteCallback($curlHandle, $data)
    {
        $length = strlen($data);
        $writtenTotal = 0;
        $writtenLast = 0;

        while ($writtenTotal < $length) {
            $writtenLast = fwrite($this->writeStream, substr($data, $writtenTotal));

            if ($writtenLast === false) {
                return $writtenTotal;
            }

            $writtenTotal += $writtenLast;
        }

        // Execute callback function
        if ($this->registeredStreamingWriteCallback) {
            call_user_func($this->registeredStreamingWriteCallback, $curlHandle, $writtenTotal);
        }

        return $writtenTotal;
    }

    /**
     * Prepares and adds the details of the cURL request. This can be passed along to a <php:curl_multi_exec()>
     * function.
     *
     * @return resource The handle for the cURL object.
     */
    public function prepRequest()
    {
        $curlHandle = curl_init();

        // Set default options.
        curl_setopt($curlHandle, CURLOPT_URL, $this->requestUrl);
        curl_setopt($curlHandle, CURLOPT_FILETIME, true);
        curl_setopt($curlHandle, CURLOPT_FRESH_CONNECT, false);
        curl_setopt($curlHandle, CURLOPT_MAXREDIRS, 5);
        curl_setopt($curlHandle, CURLOPT_HEADER, true);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5184000);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($curlHandle, CURLOPT_NOSIGNAL, true);
        curl_setopt($curlHandle, CURLOPT_REFERER, $this->requestUrl);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, $this->useragent);
        curl_setopt($curlHandle, CURLOPT_READFUNCTION, array($this, 'streamingReadCallback'));

        // Verification of the SSL cert
        if ($this->sslVerification) {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
        }

        // chmod the file as 0755
        if ($this->cacertLocation === true) {
            curl_setopt($curlHandle, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
        } elseif (is_string($this->cacertLocation)) {
            curl_setopt($curlHandle, CURLOPT_CAINFO, $this->cacertLocation);
        }

        // Debug mode
        if ($this->debugMode) {
            curl_setopt($curlHandle, CURLOPT_VERBOSE, true);
        }

        // Handle open_basedir & safe mode
        if (!ini_get('safe_mode') && !ini_get('open_basedir')) {
            curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        }

        // Enable a proxy connection if requested.
        if ($this->proxy) {
            curl_setopt($curlHandle, CURLOPT_HTTPPROXYTUNNEL, true);

            $host = $this->proxy['host'];
            $host .= ($this->proxy['port']) ? ':' . $this->proxy['port'] : '';
            curl_setopt($curlHandle, CURLOPT_PROXY, $host);

            if (isset($this->proxy['user']) && isset($this->proxy['pass'])) {
                curl_setopt($curlHandle, CURLOPT_PROXYUSERPWD, $this->proxy['user'] . ':' . $this->proxy['pass']);
            }
        }

        // Set credentials for HTTP Basic/Digest Authentication.
        if ($this->username && $this->password) {
            curl_setopt($curlHandle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($curlHandle, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        // Handle the encoding if we can.
        if (extension_loaded('zlib')) {
            curl_setopt($curlHandle, CURLOPT_ENCODING, '');
        }

        // Process custom headers
        if (isset($this->requestHeaders) && count($this->requestHeaders)) {
            $tempHeaders = array();

            foreach ($this->requestHeaders as $k => $v) {
                $tempHeaders[] = $k . ': ' . $v;
            }

            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $tempHeaders);
        }

        switch ($this->method) {
            case self::HTTP_PUT:
                //unset($this->read_stream);
                curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (isset($this->readStream)) {
                    if (!isset($this->readStreamSize) || $this->readStreamSize < 0) {
                        throw new Exception\RequestCoreException('The stream size for the streaming upload cannot be determined.');
                    }

                    curl_setopt($curlHandle, CURLOPT_INFILESIZE, $this->readStreamSize);
                    curl_setopt($curlHandle, CURLOPT_UPLOAD, true);
                } else {
                    curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->requestBody);
                }
                break;

            case self::HTTP_POST:
                curl_setopt($curlHandle, CURLOPT_POST, true);
                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->requestBody);
                break;

            case self::HTTP_HEAD:
                curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, self::HTTP_HEAD);
                curl_setopt($curlHandle, CURLOPT_NOBODY, 1);
                break;

            default: // Assumed GET
                curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $this->method);
                if (isset($this->writeStream)) {
                    curl_setopt($curlHandle, CURLOPT_WRITEFUNCTION, array($this, 'streamingWriteCallback'));
                    curl_setopt($curlHandle, CURLOPT_HEADER, false);
                } else {
                    curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->requestBody);
                }
                break;
        }

        // Merge in the CURLOPTs
        if (isset($this->curlopts) && sizeof($this->curlopts) > 0) {
            foreach ($this->curlopts as $k => $v) {
                curl_setopt($curlHandle, $k, $v);
            }
        }

        return $curlHandle;
    }

    /**
     * Take the post-processed cURL data and break it down into useful header/body/info chunks. Uses the
     * data stored in the `curlHandle` and `response` properties unless replacement data is passed in via
     * parameters.
     *
     * @param resource $curlHandle (Optional) The reference to the already executed cURL request.
     * @param string $response (Optional) The actual response content itself that needs to be parsed.
     * @return ResponseCore A <ResponseCore> object containing a parsed HTTP response.
     */
    public function processResponse($curlHandle = null, $response = null)
    {
        if ($response) {
            $this->response = $response;
        }
        // As long as this came back as a valid resource...
        if (is_resource($curlHandle)) {
            // Determine what's what.
            $headerSize = curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);
            $this->responseHeaders = substr($this->response, 0, $headerSize);
            $this->responseBody = substr($this->response, $headerSize);
            $this->responseCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            $this->responseInfo = curl_getinfo($curlHandle);

            // Parse out the headers
            $this->responseHeaders = explode("\r\n\r\n", trim($this->responseHeaders));
            $this->responseHeaders = array_pop($this->responseHeaders);
            $this->responseHeaders = explode("\r\n", $this->responseHeaders);
            array_shift($this->responseHeaders);

            // Loop through and split up the headers.
            $header_assoc = array();
            foreach ($this->responseHeaders as $header) {
                $kv = explode(': ', $header);
                $header_assoc[strtolower($kv[0])] = isset($kv[1]) ? $kv[1] : '';
            }

            // Reset the headers to the appropriate property.
            $this->responseHeaders = $header_assoc;
            $this->responseHeaders['_info'] = $this->responseInfo;
            $this->responseHeaders['_info']['method'] = $this->method;

            if ($curlHandle && $this->response) {
                return new $this->responseClass($this->responseHeaders, $this->responseBody, $this->responseCode, $curlHandle);
            }
        }

        // Return false
        return false;
    }

    /**
     * Sends the request, calling necessary utility functions to update built-in properties.
     *
     * @param boolean $parse (Optional) Whether to parse the response with ResponseCore or not.
     * @return string The resulting unparsed data from the request.
     */
    public function sendRequest($parse = false)
    {
        set_time_limit(0);

        $curlHandle = $this->prepRequest();
        $this->response = curl_exec($curlHandle);

        if ($this->response === false) {
            throw new Exception\RequestCoreException('cURL resource: ' . (string)$curlHandle . '; cURL error: ' . curl_error($curlHandle) . ' (' . curl_errno($curlHandle) . ')');
        }

        $parsedResponse = $this->processResponse($curlHandle, $this->response);

        curl_close($curlHandle);

        if ($parse) {
            return $parsedResponse;
        }

        return $this->response;
    }

    /**
     * Sends the request using <php:curl_multi_exec()>, enabling parallel requests. Uses the "rolling" method.
     *
     * @param array $handles (Required) An indexed array of cURL handles to process simultaneously.
     * @param array $opt (Optional) An associative array of parameters that can have the following keys: <ul>
     *    <li><code>callback</code> - <code>string|array</code> - Optional - The string name of a function to pass the response data to. If this is a method, pass an array where the <code>[0]</code> index is the class and the <code>[1]</code> index is the method name.</li>
     *    <li><code>limit</code> - <code>integer</code> - Optional - The number of simultaneous requests to make. This can be useful for scaling around slow server responses. Defaults to trusting cURLs judgement as to how many to use.</li></ul>
     * @return array Post-processed cURL responses.
     */
    public function sendMultiRequest($handles, $opt = null)
    {
        set_time_limit(0);

        // Skip everything if there are no handles to process.
        if (count($handles) === 0) return array();

        if (!$opt) $opt = array();

        // Initialize any missing options
        $limit = isset($opt['limit']) ? $opt['limit'] : -1;

        // Initialize
        $handleList = $handles;
        $http = new $this->requestClass();
        $multiHandle = curl_multi_init();
        $handlesPost = array();
        $added = count($handles);
        $lastHandle = null;
        $count = 0;
        $i = 0;

        // Loop through the cURL handles and add as many as it set by the limit parameter.
        while ($i < $added) {
            if ($limit > 0 && $i >= $limit) break;
            curl_multi_add_handle($multiHandle, array_shift($handles));
            $i++;
        }

        do {
            $active = false;

            // Start executing and wait for a response.
            while (($status = curl_multi_exec($multiHandle, $active)) === CURLM_CALL_MULTI_PERFORM) {
                // Start looking for possible responses immediately when we have to add more handles
                if (count($handles) > 0) break;
            }

            // Figure out which requests finished.
            $toProcess = array();

            while ($done = curl_multi_info_read($multiHandle)) {
                // Since curl_errno() isn't reliable for handles that were in multirequests, we check the 'result' of the info read, which contains the curl error number, (listed here http://curl.haxx.se/libcurl/c/libcurl-errors.html )
                if ($done['result'] > 0) {
                    throw new Exception\RequestCoreException('cURL resource: ' . (string)$done['handle'] . '; cURL error: ' . curl_error($done['handle']) . ' (' . $done['result'] . ')');
                } // Because curl_multi_info_read() might return more than one message about a request, we check to see if this request is already in our array of completed requests
                elseif (!isset($toProcess[(int)$done['handle']])) {
                    $toProcess[(int)$done['handle']] = $done;
                }
            }

            // Actually deal with the request
            foreach ($toProcess as $pkey => $done) {
                $response = $http->processResponse($done['handle'], curl_multi_getcontent($done['handle']));
                $key = array_search($done['handle'], $handleList, true);
                $handlesPost[$key] = $response;

                if (count($handles) > 0) {
                    curl_multi_add_handle($multiHandle, array_shift($handles));
                }

                curl_multi_remove_handle($multiHandle, $done['handle']);
                curl_close($done['handle']);
            }
        } while ($active || count($handlesPost) < $added);

        curl_multi_close($multiHandle);

        ksort($handlesPost, SORT_NUMERIC);
        return $handlesPost;
    }


    /*%******************************************************************************************%*/
    // RESPONSE METHODS

    /**
     * Get the HTTP response headers from the request.
     *
     * @param string $header (Optional) A specific header value to return. Defaults to all headers.
     * @return string|array All or selected header values.
     */
    public function getResponseHeader($header = null)
    {
        if ($header) {
            return $this->responseHeaders[strtolower($header)];
        }
        return $this->responseHeaders;
    }

    /**
     * Get the HTTP response body from the request.
     *
     * @return string The response body.
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * Get the HTTP response code from the request.
     *
     * @return string The HTTP response code.
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }
}
