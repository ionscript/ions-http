<?php

namespace Ions\Http\Header;

/**
 * Class ContentSecurityPolicy
 * @package Ions\Http\Header
 */
class ContentSecurityPolicy implements HeaderInterface
{
    /**
     * @var array
     */
    protected $validDirectiveNames =
        [
            'default-src',
            'script-src',
            'object-src',
            'style-src',
            'img-src',
            'media-src',
            'frame-src',
            'font-src',
            'connect-src',
            'sandbox',
            'report-uri'
        ];

    /**
     * @var array
     */
    protected $directives = [];

    /**
     * @return array
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param $name
     * @param array $sources
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDirective($name, array $sources)
    {
        if (!in_array($name, $this->validDirectiveNames, true)) {
            throw new \InvalidArgumentException(sprintf(
                '%s expects a valid directive name; received "%s"',
                __METHOD__,
                (string)$name
            ));
        }

        if (empty($sources)) {
            if ('report-uri' === $name) {
                if (isset($this->directives[$name])) {
                    unset($this->directives[$name]);
                }
                return $this;
            }
            $this->directives[$name] = "'none'";
            return $this;
        }

        array_walk($sources, [__NAMESPACE__ . '\HeaderValue', 'assertValid']);

        $this->directives[$name] = implode(' ', $sources);
        return $this;
    }

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        $header = new static();

        $headerName = $header->getName();

        list($name, $value) = Header::splitHeaderLine($headerLine);

        if (strcasecmp($name, $headerName) !== 0) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid header line for %s string: "%s"',
                $headerName,
                $name
            ));
        }

        $tokens = explode(';', $value);

        foreach ($tokens as $token) {

            $token = trim($token);

            if ($token) {
                list($directiveName, $directiveValue) = explode(' ', $token, 2);
                if (!isset($header->directives[$directiveName])) {
                    $header->setDirective($directiveName, [$directiveValue]);
                }
            }
        }

        return $header;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Content-Security-Policy';
    }

    /**
     * @return string
     */
    public function getValue()
    {
        $directives = [];
        foreach ($this->directives as $name => $value) {
            $directives[] = sprintf('%s %s;', $name, $value);
        }
        return implode(' ', $directives);
    }

    /**
     * @return string
     */
    public function toString()
    {
        return
            sprintf('%s: %s',
                $this->getName(),
                $this->getValue()
            );
    }
}
