<?php

namespace Ions\Http\Header;

/**
 * Interface HeaderInterface
 * @package Ions\Http\Header
 */
interface HeaderInterface
{
    /**
     * @param $headerLine
     * @return mixed
     */
    public static function create($headerLine);

    /**
     * @return mixed
     */
    public function getName();

    /**
     * @return mixed
     */
    public function getValue();

    /**
     * @return mixed
     */
    public function toString();
}
