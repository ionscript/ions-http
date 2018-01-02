<?php

namespace Ions\Http\Client\Adapter;

/**
 * Interface StreamInterface
 * @package Ions\Http\Client\Adapter
 */
interface StreamInterface
{
    /**
     * @param $stream
     * @return mixed
     */
    public function setOutputStream($stream);
}
