<?php

namespace Ions\Http\Header;

/**
 * Class AbstractAccept
 * @package Ions\Http\Header
 *
 * Abstract Accept Header
 *
 * Naming conventions:
 *
 *    Accept: audio/mp3; q=0.2; version=0.5, audio/basic+mp3
 *   |------------------------------------------------------|  header line
 *   |------|                                                  field name
 *          |-----------------------------------------------|  field value
 *          |-------------------------------|                  field value part
 *          |------|                                           type
 *                  |--|                                       subtype
 *                  |--|                                       format
 *                                                |----|       subtype
 *                                                      |---|  format
 *                      |-------------------|                  parameter set
 *                              |-----------|                  parameter
 *                              |-----|                        parameter key
 *                                      |--|                   parameter value
 *                        |---|                                priority
 *
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 *
 */
abstract class AbstractAccept implements HeaderInterface
{
    /**
     * @var array
     */
    protected $fieldValueParts = [];

    /**
     * @var
     */
    protected $regexAddType;

    /**
     * @var bool
     */
    protected $sorted = false;

    /**
     * @param $headerLine
     */
    public function parseHeaderLine($headerLine)
    {
        if (strpos($headerLine, ':') !== false) {

            list($name, $value) = Header::splitHeaderLine($headerLine);

            if (strtolower($name) !== strtolower($this->getName())) {
                $value = $headerLine;
            }

        } else {
            $value = $headerLine;
        }

        Header::assertValid($value);

        foreach ($this->getValuePartsFromHeaderLine($value) as $value) {
            $this->addValuePartToQueue($value);
        }
    }

    /**
     * @param $headerLine
     * @return static
     */
    public static function create($headerLine)
    {
        $obj = new static();

        $obj->parseHeaderLine($headerLine);

        return $obj;
    }

    /**
     * @param $headerLine
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getValuePartsFromHeaderLine($headerLine)
    {
        if (!preg_match_all('/(?:[^,"]|"(?:[^\\\"]|\\\.)*")+/', $headerLine, $values) || !isset($values[0])) {
            throw new \InvalidArgumentException('Invalid header line for ' . $this->getName() . ' header string');
        }

        $out = [];

        foreach ($values[0] as $value) {
            $value = trim($value);
            $out[] = $this->parseValuePart($value);
        }

        return $out;
    }

    /**
     * @param $fieldValuePart
     * @return object
     */
    protected function parseValuePart($fieldValuePart)
    {
        $raw = $fieldValuePart;

        if ($pos = strpos($fieldValuePart, '/')) {
            $type = trim(substr($fieldValuePart, 0, $pos));
        } else {
            $type = trim($fieldValuePart);
        }

        $params = $this->getParametersFromFieldValuePart($fieldValuePart);

        if ($pos = strpos($fieldValuePart, ';')) {
            $fieldValuePart = trim(substr($fieldValuePart, 0, $pos));
        }

        if (strpos($fieldValuePart, '/')) {
            $subtypeWhole = $format = $subtype = trim(substr($fieldValuePart, strpos($fieldValuePart, '/') + 1));
        } else {
            $subtypeWhole = '';
            $format = '*';
            $subtype = '*';
        }

        $pos = strpos($subtype, '+');

        if (false !== $pos) {
            $format = trim(substr($subtype, $pos + 1));
            $subtype = trim(substr($subtype, 0, $pos));
        }

        $aggregated = [
            'typeString' => trim($fieldValuePart),
            'type' => $type,
            'subtype' => $subtype,
            'subtypeRaw' => $subtypeWhole,
            'format' => $format,
            'priority' => isset($params['q']) ? $params['q'] : 1,
            'params' => $params,
            'raw' => trim($raw)
        ];

        return (object)$aggregated;
    }

    /**
     * @param $fieldValuePart
     * @return array
     */
    protected function getParametersFromFieldValuePart($fieldValuePart)
    {
        $params = [];

        if ($pos = strpos($fieldValuePart, ';') !== false) {

            preg_match_all('/(?:[^;"]|"(?:[^\\\"]|\\\.)*")+/', $fieldValuePart, $paramsStrings);

            if (isset($paramsStrings[0])) {
                array_shift($paramsStrings[0]);
                $paramsStrings = $paramsStrings[0];
            }

            foreach ($paramsStrings as $param) {

                $explode = explode('=', $param, 2);

                if (count($explode) === 2) {
                    $value = trim($explode[1]);
                } else {
                    $value = null;
                }

                if (isset($value[0]) && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr(substr($value, 1), 0, -1);
                }

                $params[trim($explode[0])] = stripslashes($value);
            }
        }

        return $params;
    }

    /**
     * @param null $values
     * @return string
     */
    public function getValue($values = null)
    {
        if ($values === null) {
            return $this->getValue($this->fieldValueParts);
        }

        $strings = [];

        /** @var array $values */
        foreach ($values as $value) {
            $params = $value->params;
            array_walk($params, [$this, 'assembleAcceptParam']);
            $strings[] = implode(';', [$value->typeString] + $params);
        }

        return implode(', ', $strings);
    }

    /**
     * @param $value
     * @param $key
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function assembleAcceptParam(&$value, $key)
    {
        $separators = ['(', ')', '<', '>', '@', ',', ';', ':', '/', '[', ']', '?', '=', '{', '}', ' ', "\t"];

        $escaped = preg_replace_callback('/[[:cntrl:]"\\\\]/', function ($v) {
            return '\\' . $v[0];
        }, $value);

        if ($escaped === $value && !array_intersect(str_split($value), $separators)) {
            $value = $key . ($value ? '=' . $value : '');
        } else {
            $value = $key . ($value ? '="' . $escaped . '"' : '');
        }

        return $value;
    }

    /**
     * @param $type
     * @param int $priority
     * @param array $params
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function addType($type, $priority = 1, array $params = [])
    {
        if (!preg_match($this->regexAddType, $type)) {
            throw new \InvalidArgumentException(sprintf('%s expects a valid type; received "%s"', __METHOD__, (string)$type));
        }

        if ((!is_int($priority) && !is_float($priority) && !is_numeric($priority)) || $priority > 1 || $priority < 0) {
            throw new \InvalidArgumentException(sprintf('%s expects a numeric priority; received %s', __METHOD__, (string)$priority));
        }

        if ($priority !== 1) {
            $params = ['q' => sprintf('%01.1f', $priority)] + $params;
        }

        $assembledString = $this->getValue([(object)['typeString' => $type, 'params' => $params]]);
        $value = $this->parseValuePart($assembledString);

        $this->addValuePartToQueue($value);

        return $this;
    }

    /**
     * @param $matchAgainst
     * @return bool
     */
    protected function hasType($matchAgainst)
    {
        return (bool)$this->match($matchAgainst);
    }

    /**
     * @param $matchAgainst
     * @return bool|mixed
     */
    public function match($matchAgainst)
    {
        if (is_string($matchAgainst)) {
            $matchAgainst = $this->getValuePartsFromHeaderLine($matchAgainst);
        }

        foreach ($this->getPrioritized() as $left) {
            foreach ($matchAgainst as $right) {
                if ($right->type === '*' || $left->type === '*') {
                    if ($this->matchAcceptParams($left, $right)) {
                        $left->setMatchedAgainst($right);
                        return $left;
                    }
                }

                if ($left->type == $right->type) {
                    if (($left->subtype == $right->subtype || ($right->subtype === '*' || $left->subtype === '*')) && ($left->format === $right->format || $right->format === '*' || $left->format === '*')) {
                        if ($this->matchAcceptParams($left, $right)) {
                            $left->setMatchedAgainst($right);
                            return $left;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param $match1
     * @param $match2
     * @return bool
     */
    protected function matchAcceptParams($match1, $match2)
    {
        foreach ($match2->params as $key => $value) {
            if (isset($match1->params[$key])) {
                if (strpos($value, '-')) {
                    preg_match('/^(?|([^"-]*)|"([^"]*)")-(?|([^"-]*)|"([^"]*)")\z/', $value, $pieces);

                    if (count($pieces) == 3 && (version_compare($pieces[1], $match1->params[$key], '<=') xor version_compare($pieces[2], $match1->params[$key], '>='))) {
                        return false;
                    }

                } elseif (strpos($value, '|')) {
                    $options = explode('|', $value);

                    $good = false;

                    foreach ($options as $option) {
                        if ($option == $match1->params[$key]) {
                            $good = true;
                            break;
                        }
                    }

                    if (!$good) {
                        return false;
                    }
                } elseif ($match1->params[$key] != $value) {
                    return false;
                }
            }
        }

        return $match1;
    }

    /**
     * @param $value
     */
    protected function addValuePartToQueue($value)
    {
        $this->fieldValueParts[] = $value;
        $this->sorted = false;
    }

    /**
     * @return void
     */
    protected function sortValueParts()
    {
        $sort = function ($a, $b) {
            if ($a->priority > $b->priority) {
                return -1;
            } elseif ($a->priority < $b->priority) {
                return 1;
            }

            $values = ['type', 'subtype', 'format'];

            foreach ($values as $value) {
                if ($a->$value === '*' && $b->$value !== '*') {
                    return 1;
                } elseif ($b->$value === '*' && $a->$value !== '*') {
                    return -1;
                }
            }

            if ($a->type === 'application' && $b->type !== 'application') {
                return -1;
            } elseif ($b->type === 'application' && $a->type !== 'application') {
                return 1;
            }

            if (strlen($a->raw) == strlen($b->raw)) {
                return 0;
            }

            return (strlen($a->raw) > strlen($b->raw)) ? -1 : 1;
        };

        usort($this->fieldValueParts, $sort);

        $this->sorted = true;
    }

    /**
     * @return array
     */
    public function getPrioritized()
    {
        if (!$this->sorted) {
            $this->sortValueParts();
        }

        return $this->fieldValueParts;
    }
}
