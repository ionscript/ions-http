<?php

namespace Ions\Http\Header;

/**
 * Class Expires
 * @package Ions\Http\Header
 */
class Expires extends AbstractDate
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Expires';
    }

    /**
     * @param $date
     * @return $this
     */
    public function setDate($date)
    {
        if ($date === '0' || $date === 0) {
            $date = date(DATE_W3C, 0);
        }

        return parent::setDate($date);
    }
}
