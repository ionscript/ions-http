<?php

namespace Ions\Http\Server;

use Ions\Http\Header\HeaderInterface;
use Ions\Http\Response as HttpResponse;

/**
 * Class Response
 * @package Ions\Http\Server
 */
class Response extends HttpResponse
{
    /**
     * @var
     */
    protected $version;
    /**
     * @var int
     */
    protected $compression = 0;

    /**
     * @var bool
     */
    protected $contentSent = false;

    /**
     * @return string
     */
    public function getVersion()
    {
        if (!$this->version) {
            $this->version = $this->detectVersion();
        }
        return $this->version;
    }

    /**
     * @return string
     */
    protected function detectVersion()
    {
        if (isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1') {
            return self::VERSION_11;
        }

        return self::VERSION_10;
    }

    /**
     * @return bool
     */
    public function headersSent()
    {
        return headers_sent();
    }

    /**
     * @return bool
     */
    public function contentSent()
    {
        return $this->contentSent;
    }

    /**
     * @return $this
     */
    public function sendHeaders()
    {
        if ($this->headersSent()) {
            return $this;
        }

        $status = $this->renderStatusLine();

        header($status);

        foreach ($this->getHeaders()->getHeaders() as $header) {

            if ($header instanceof HeaderInterface) {
                header($header->toString(), false);
                continue;
            }

            header($header->toString());
        }

        $this->headersSent = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function sendContent()
    {
        if ($this->contentSent()) {
            return $this;
        }

        echo $this->compression ? $this->compress($this->getContent(), $this->compression) : $this->getContent();

        $this->contentSent = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function send()
    {
        $this->sendHeaders()->sendContent();

        return $this;
    }

    /**
     * @param $url
     * @param int $status
     */
    public function redirect($url, $status = self::HTTP_FOUND)
    {
        header('Location: ' . str_replace(['&amp;', "\n", "\r"], ['&', '', ''], $url), true, $status);
        exit();
    }

    /**
     * @param $data
     * @param int $level
     * @param string $encoding
     * @return string
     */
    protected function compress($data, $level = 0, $encoding = '')
    {
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false)) {
            $encoding = 'deflate';
        }

        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)) {
            $encoding = 'gzip';
        }

        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)) {
            $encoding = 'x-gzip';
        }

        if (!$encoding || ($level < -1 || $level > 9)) {
            return $data;
        }

        if (!extension_loaded('zlib') || ini_get('zlib.output_compression')) {
            return $data;
        }

        if (headers_sent()) {
            return $data;
        }

        if (connection_status()) {
            return $data;
        }

        header('Content-Encoding: ' . $encoding);

        if ($encoding === 'deflate') {
            return gzdeflate($data, (int)$level);
        }

        return gzencode($data, (int)$level);
    }

    /**
     * @param $level
     */
    public function setCompression($level)
    {
        $this->compression = $level;
    }

    /**
     * @return int
     */
    public function getCompression()
    {
        return $this->compression;
    }
}
