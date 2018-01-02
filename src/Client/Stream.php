<?php

namespace Ions\Http\Client;

use Ions\Http\Response;

/**
 * Class Stream
 * @package Ions\Http\Client
 */
class Stream extends Response
{
    /**
     * @var int
     */
    protected $contentLength;

    /**
     * @var int
     */
    protected $contentStreamed = 0;

    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var string
     */
    protected $streamName;

    /**
     * @var bool
     */
    protected $cleanup;

    /**
     * @param null $contentLength
     */
    public function setContentLength($contentLength = null)
    {
        $this->contentLength = $contentLength;
    }

    /**
     * @return int
     */
    public function getContentLength()
    {
        return $this->contentLength;
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * @param $stream
     * @return $this
     */
    public function setStream($stream)
    {
        $this->stream = $stream;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCleanup()
    {
        return $this->cleanup;
    }

    /**
     * @param bool $cleanup
     */
    public function setCleanup($cleanup = true)
    {
        $this->cleanup = $cleanup;
    }

    /**
     * @return string
     */
    public function getStreamName()
    {
        return $this->streamName;
    }

    /**
     * @param $streamName
     * @return $this
     */
    public function setStreamName($streamName)
    {
        $this->streamName = $streamName;
        return $this;
    }

    /**
     * @param $responseString
     * @param $stream
     * @return static
     * @throws \InvalidArgumentException|\OutOfRangeException
     */
    public static function fromStream($responseString, $stream)
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \InvalidArgumentException('A valid stream is required');
        }

        $headerComplete = false;
        $headersString  = '';
        $responseArray  = [];

        if ($responseString) {
            $responseArray = explode("\n", $responseString);
        }

        while (count($responseArray)) {
            $nextLine        = array_shift($responseArray);
            $headersString  .= $nextLine."\n";
            $nextLineTrimmed = trim($nextLine);
            if ($nextLineTrimmed === '') {
                $headerComplete = true;
                break;
            }
        }

        if (! $headerComplete) {
            while (false !== ($nextLine = fgets($stream))) {
                $headersString .= trim($nextLine)."\r\n";
                if ($nextLine === "\r\n" || $nextLine === "\n") {
                    $headerComplete = true;
                    break;
                }
            }
        }

        if (! $headerComplete) {
            throw new \OutOfRangeException('End of header not found');
        }

        $response = static::create($headersString);

        if (is_resource($stream)) {
            $response->setStream($stream);
        }

        if (count($responseArray)) {
            $response->content = implode("\n", $responseArray);
        }

        $headers = $response->getHeaders();
        foreach ($headers as $header) {

            if ($header->getFieldName() === 'Content-Length') {
                $response->setContentLength((int) $header->getFieldValue());
                $contentLength = $response->getContentLength();
                if (strlen($response->content) > $contentLength) {
                    throw new \OutOfRangeException(sprintf(
                        'Too much content was extracted from the stream (%d instead of %d bytes)',
                        strlen($response->content),
                        $contentLength
                    ));
                }
                break;
            }
        }

        return $response;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        if ($this->stream !== null) {
            $this->readStream();
        }
        return parent::getBody();
    }

    /**
     * @return string
     */
    public function getRawBody()
    {
        if ($this->stream) {
            $this->readStream();
        }
        return $this->content;
    }

    /**
     * @return void
     */
    protected function readStream()
    {
        $contentLength = $this->getContentLength();
        if (null !== $contentLength) {
            $bytes = $contentLength - $this->contentStreamed;
        } else {
            $bytes = -1;
        }

        if (! is_resource($this->stream) || $bytes === 0) {
            return;
        }

        $this->content         .= stream_get_contents($this->stream, $bytes);
        $this->contentStreamed += strlen($this->content);

        if ($this->getContentLength() === $this->contentStreamed) {
            $this->stream = null;
        }
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->stream)) {
            $this->stream = null;
        }
        if ($this->cleanup) {
            unlink($this->streamName);
        }
    }
}
