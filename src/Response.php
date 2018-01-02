<?php

namespace Ions\Http;

use Ions\Std\ResponseInterface;

/**
 * Class Response
 * @package Ions\Http
 */
class Response extends Message implements ResponseInterface
{
    const HTTP_CONTINUE = 100;
    const HTTP_SWITCHING_PROTOCOLS = 101;
    const HTTP_PROCESSING = 102;
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    const HTTP_NO_CONTENT = 204;
    const HTTP_RESET_CONTENT = 205;
    const HTTP_PARTIAL_CONTENT = 206;
    const HTTP_MULTI_STATUS = 207;
    const HTTP_ALREADY_REPORTED = 208;
    const HTTP_IM_USED = 226;
    const HTTP_MULTIPLE_CHOICES = 300;
    const HTTP_MOVED_PERMANENTLY = 301;
    const HTTP_FOUND = 302;
    const HTTP_SEE_OTHER = 303;
    const HTTP_NOT_MODIFIED = 304;
    const HTTP_USE_PROXY = 305;
    const HTTP_RESERVED = 306;
    const HTTP_TEMPORARY_REDIRECT = 307;
    const HTTP_PERMANENTLY_REDIRECT = 308;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_PAYMENT_REQUIRED = 402;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_NOT_ACCEPTABLE = 406;
    const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    const HTTP_REQUEST_TIMEOUT = 408;
    const HTTP_CONFLICT = 409;
    const HTTP_GONE = 410;
    const HTTP_LENGTH_REQUIRED = 411;
    const HTTP_PRECONDITION_FAILED = 412;
    const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    const HTTP_REQUEST_URI_TOO_LONG = 414;
    const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const HTTP_EXPECTATION_FAILED = 417;
    const HTTP_I_AM_A_TEAPOT = 418;
    const HTTP_MISDIRECTED_REQUEST = 421;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const HTTP_LOCKED = 423;
    const HTTP_FAILED_DEPENDENCY = 424;
    const HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL = 425;
    const HTTP_UPGRADE_REQUIRED = 426;
    const HTTP_PRECONDITION_REQUIRED = 428;
    const HTTP_TOO_MANY_REQUESTS = 429;
    const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_NOT_IMPLEMENTED = 501;
    const HTTP_BAD_GATEWAY = 502;
    const HTTP_SERVICE_UNAVAILABLE = 503;
    const HTTP_GATEWAY_TIMEOUT = 504;
    const HTTP_VERSION_NOT_SUPPORTED = 505;
    const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;
    const HTTP_INSUFFICIENT_STORAGE = 507;
    const HTTP_LOOP_DETECTED = 508;
    const HTTP_NOT_EXTENDED = 510;
    const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;
    const HTTP_NETWORK_CONNECT_TIMEOUT_ERROR = 599;

    const STATUS_CODE = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error'
    ];

    /**
     * @var int
     */
    protected $statusCode = 200;

    /**
     * @var string
     */
    protected $reasonPhrase;

    /**
     * @param $string
     * @return static
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public static function create($string)
    {
        $lines = explode("\r\n", $string);

        if (!is_array($lines) || count($lines) === 1) {
            $lines = explode("\n", $string);
        }

        $firstLine = array_shift($lines);

        $response = new static();

        $regex = '/^HTTP\/(?P<version>1\.[01]) (?P<status>\d{3})(?:[ ]+(?P<reason>.*))?$/';

        $matches = [];

        if (!preg_match($regex, $firstLine, $matches)) {
            throw new \InvalidArgumentException('A valid response status line was not found in the provided string');
        }

        $response->version = $matches['version'];

        $response->setStatusCode($matches['status']);

        $response->setReasonPhrase((isset($matches['reason']) ? $matches['reason'] : ''));

        if (count($lines) === 0) {
            return $response;
        }

        $isHeader = true;
        $headers = $content = [];

        foreach ($lines as $line) {

            if ($isHeader && $line === '') {
                $isHeader = false;
                continue;
            }

            if ($isHeader) {
                if (preg_match("/[\r\n]/", $line)) {
                    throw new \RuntimeException('CRLF injection detected');
                }
                $headers[] = $line;
                continue;
            }

            if (empty($content) && preg_match('/^[a-z0-9!#$%&\'*+.^_`|~-]+:$/i', $line)) {
                throw new \RuntimeException('CRLF injection detected');
            }

            $content[] = $line;
        }

        if ($headers) {
            $response->headers = implode("\r\n", $headers);
        }

        if ($content) {
            $response->setContent(implode("\r\n", $content));
        }

        return $response;
    }

    /**
     * @return bool|mixed
     */
    public function getCookie()
    {
        return $this->getHeaders()->get('Set-Cookie');
    }

    /**
     * @param $code
     * @return Response
     * @throws \InvalidArgumentException
     */
    public function setStatusCode($code)
    {
        if (!is_numeric($code) || !array_key_exists($code, static::STATUS_CODE)) {
            throw new \InvalidArgumentException(sprintf('Invalid status code provided: "%s"', $code));
        }

        return $this->saveStatusCode($code);
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param $code
     * @return Response
     * @throws \InvalidArgumentException
     */
    public function setCustomStatusCode($code)
    {
        if (!is_numeric($code)) {
            throw new \InvalidArgumentException(sprintf('Invalid status code provided: "%s"', $code));
        }

        return $this->saveStatusCode($code);
    }

    /**
     * @param $code
     * @return $this
     */
    protected function saveStatusCode($code)
    {
        $this->reasonPhrase = null;

        $this->statusCode = (int)$code;

        return $this;
    }

    /**
     * @param $reasonPhrase
     * @return $this
     */
    public function setReasonPhrase($reasonPhrase)
    {
        $this->reasonPhrase = trim($reasonPhrase);

        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getReasonPhrase()
    {
        if (null === $this->reasonPhrase && array_key_exists($this->statusCode, static::STATUS_CODE)) {
            $this->reasonPhrase = static::STATUS_CODE[$this->statusCode];
        }

        return $this->reasonPhrase;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $body = (string)$this->getContent();

        $transferEncoding = $this->getHeaders()->get('Transfer-Encoding');

        if (!empty($transferEncoding)) {
            if (strtolower($transferEncoding->getValue()) === 'chunked') {
                $body = $this->decodeChunkedBody($body);
            }
        }

        $contentEncoding = $this->getHeaders()->get('Content-Encoding');

        if (!empty($contentEncoding)) {
            $contentEncoding = $contentEncoding->getValue();
            if ($contentEncoding === 'gzip') {
                $body = $this->decodeGzip($body);
            } elseif ($contentEncoding === 'deflate') {
                $body = $this->decodeDeflate($body);
            }
        }

        return $body;
    }

    /**
     * @return bool
     */
    public function isClientError()
    {
        $code = $this->getStatusCode();
        return ($code < 500 && $code >= 400);
    }

    /**
     * @return bool
     */
    public function isForbidden()
    {
        return (403 === $this->getStatusCode());
    }

    /**
     * @return bool
     */
    public function isInformational()
    {
        $code = $this->getStatusCode();
        return ($code >= 100 && $code < 200);
    }

    /**
     * @return bool
     */
    public function isNotFound()
    {
        return (404 === $this->getStatusCode());
    }

    /**
     * @return bool
     */
    public function isGone()
    {
        return (410 === $this->getStatusCode());
    }

    /**
     * @return bool
     */
    public function isOk()
    {
        return (200 === $this->getStatusCode());
    }

    /**
     * @return bool
     */
    public function isServerError()
    {
        $code = $this->getStatusCode();
        return (500 <= $code && 600 > $code);
    }

    /**
     * @return bool
     */
    public function isRedirect()
    {
        $code = $this->getStatusCode();
        return (300 <= $code && 400 > $code);
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        $code = $this->getStatusCode();
        return (200 <= $code && 300 > $code);
    }

    /**
     * @return string
     */
    public function renderStatusLine()
    {
        $status = sprintf(
            'HTTP/%s %d %s',
            $this->getVersion(),
            $this->getStatusCode(),
            $this->getReasonPhrase()
        );

        return trim($status);
    }

    /**
     * @return string
     */
    public function toString()
    {
        $str = $this->renderStatusLine() . "\r\n";
        $str .= $this->getHeaders()->toString();
        $str .= "\r\n";
        $str .= $this->getContent();
        return $str;
    }

    /**
     * @param $body
     * @return string
     * @throws \RuntimeException
     */
    protected function decodeChunkedBody($body)
    {
        $decBody = '';

        while (trim($body)) {

            if (!preg_match("/^([\da-fA-F]+)[^\r\n]*\r\n/sm", $body, $m)) {
                throw new \RuntimeException("Error parsing body - doesn't seem to be a chunked message");
            }

            $length = hexdec(trim($m[1]));

            $cut = strlen($m[0]);

            $decBody .= substr($body, $cut, $length);

            $body = substr($body, $cut + $length + 2);
        }

        return $decBody;
    }

    /**
     * @param $body
     * @return string
     * @throws \RuntimeException
     */
    protected function decodeGzip($body)
    {
        if (!function_exists('gzinflate')) {
            throw new \RuntimeException('zlib extension is required in order to decode "gzip" encoding');
        }

        $return = gzinflate(substr($body, 10));

        if ($return) {
            throw new \RuntimeException('Error occurred during gzip inflation');
        }

        return $return;
    }

    /**
     * @param $body
     * @return string
     * @throws \RuntimeException
     */
    protected function decodeDeflate($body)
    {
        if (!function_exists('gzuncompress')) {
            throw new \RuntimeException('zlib extension is required in order to decode "deflate" encoding');
        }

        $zlibHeader = unpack('n', substr($body, 0, 2));

        if ($zlibHeader[1] % 31 === 0) {
            return gzuncompress($body);
        }

        return gzinflate($body);
    }
}
