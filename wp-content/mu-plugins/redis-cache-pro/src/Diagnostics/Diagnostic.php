<?php
/**
 * Copyright © Rhubarb Tech Inc. All Rights Reserved.
 *
 * All information contained herein is, and remains the property of Rhubarb Tech Incorporated.
 * The intellectual and technical concepts contained herein are proprietary to Rhubarb Tech Incorporated and
 * are protected by trade secret or copyright law. Dissemination and modification of this information or
 * reproduction of this material is strictly forbidden unless prior written permission is obtained from
 * Rhubarb Tech Incorporated.
 *
 * You should have received a copy of the `LICENSE` with this file. If not, please visit:
 * https://objectcache.pro/license.txt
 */

declare(strict_types=1);

namespace RedisCachePro\Diagnostics;

/**
 * @property-read string $name
 * @property-read mixed $value
 * @property-read string $text
 * @property-read string $cli
 * @property-read string $html
 */
class Diagnostic
{
    /**
     * Diagnostic success type.
     *
     * @var string
     */
    const SUCCESS = 'success';

    /**
     * Diagnostic warning type.
     *
     * @var string
     */
    const WARNING = 'warning';

    /**
     * Diagnostic error type.
     *
     * @var string
     */
    const ERROR = 'error';

    /**
     * The diagnostic's type.
     *
     * @var string|null
     */
    protected $type;

    /**
     * The human readable name of the diagnostic.
     *
     * @var string
     */
    protected $name;

    /**
     * The value of the diagnostic.
     *
     * @var mixed
     */
    protected $value;

    /**
     * The value labels of the diagnostic.
     *
     * @var array<mixed>
     */
    protected $labels = [];

    /**
     * A human readable comment (addendum) related to the diagnostic.
     *
     * @var string|null
     */
    protected $comment;

    /**
     * Whether to include the comment in the value.
     *
     * @var bool
     */
    protected $withComment = false;

    /**
     * Instantiates a new diagnostic instance for given `$name`.
     *
     * @param  string  $name
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public static function name($name)
    {
        $instance = new self;
        $instance->name = $name;

        return $instance;
    }

    /**
     * Add value labels to diagnostic.
     *
     * @param  array<mixed>  $labels
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function labels(array $labels)
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * Add comment to diagnostic.
     *
     * @param  string  $comment
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function comment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Turn on comment.
     *
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function withComment()
    {
        $this->withComment = true;

        return $this;
    }

    /**
     * Whether the diagnostic has a value.
     *
     * @return bool
     */
    public function hasValue()
    {
        return ! is_null($this->value);
    }

    /**
     * Set diagnostic's value.
     *
     * @param  mixed  $value
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function value($value)
    {
        $this->value = $this->obfuscate($this->name, $value);

        return $this;
    }

    /**
     * Set diagnostic's values.
     *
     * @param  array<mixed>  $values
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function values($values)
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->obfuscate($key, $value);
        }

        $this->value = $values;

        return $this;
    }

    /**
     * Set diagnostic's value as success.
     *
     * @param  mixed  $value
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function success($value)
    {
        $this->type = self::SUCCESS;
        $this->value = $value;

        return $this;
    }

    /**
     * Whether the diagnostic is the success type.
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->type === self::SUCCESS;
    }

    /**
     * Set diagnostic's value as warning.
     *
     * @param  mixed  $value
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function warning($value)
    {
        $this->type = self::WARNING;
        $this->value = $value;

        return $this;
    }

    /**
     * Whether the diagnostic is the warning type.
     *
     * @return bool
     */
    public function isWarning()
    {
        return $this->type === self::WARNING;
    }

    /**
     * Set diagnostic's value as error.
     *
     * @param  mixed  $value
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function error($value)
    {
        $this->type = self::ERROR;
        $this->value = $value;

        return $this;
    }

    /**
     * Whether the diagnostic is the error type.
     *
     * @return bool
     */
    public function isError()
    {
        return $this->type === self::ERROR;
    }

    /**
     * Whether the diagnostic is the error type.
     *
     * @return bool
     */
    public function hasIssue()
    {
        return $this->type === self::WARNING
            || $this->type === self::ERROR;
    }

    /**
     * Set diagnostic's value as JSON.
     *
     * @param  mixed  $json
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function json($json)
    {
        $this->value = json_encode($json);

        return $this;
    }

    /**
     * Set diagnostic's value as formatted JSON.
     *
     * @param  mixed  $json
     * @return \RedisCachePro\Diagnostics\Diagnostic
     */
    public function prettyJson($json)
    {
        if (is_object($json)) {
            $json = get_object_vars($json);
        }

        if (is_array($json)) {
            foreach ($json as $key => $value) {
                $json[$key] = $this->obfuscate($key, $value);
            }
        }

        $this->value = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this;
    }

    /**
     * Obfuscate given value if it's a sensitive diagnostic.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @return mixed
     */
    protected function obfuscate($name, $value)
    {
        $name = strtolower((string) $name);

        if (! in_array($name, ['token', 'password', 'url'])) {
            return $value;
        }

        if (in_array($value, ['null', null], true)) {
            return $value;
        }

        switch ($name) {
            case 'password':
                return '••••••••';
            case 'token':
                return sprintf('••••••••%s', substr($value, -4));
            case 'url':
                /** @var string|null $password */
                $password = parse_url($value, PHP_URL_PASS);

                return str_replace((string) $password, '••••••••', $value);
        }
    }

    /**
     * Return value with CLI formatting.
     *
     * @return string
     */
    public function cli()
    {
        switch ($this->type) {
            case self::SUCCESS:
                return "%g{$this->text}%n";
            case self::WARNING:
                return "%y{$this->text}%n";
            case self::ERROR:
                return "%r{$this->text}%n";
        }

        return $this->text;
    }

    /**
     * Return value with HTML formatting.
     *
     * @return string
     */
    public function html()
    {
        $value = esc_html($this->text);

        switch ($this->type) {
            case self::SUCCESS:
                return "<data style=\"color: #208e11;\">{$value}</data>";
            case self::WARNING:
                return "<data style=\"color: #e1a948;\">{$value}</data>";
            case self::ERROR:
                return "<data style=\"color: #d54e21;\">{$value}</data>";
        }

        return "<data>{$value}</data>";
    }

    /**
     * Helper method to return diagnostic properties.
     *
     * @param  string  $name
     * @return string|void
     */
    public function __get($name)
    {
        switch ($name) {
            case 'name':
                return $this->name;
            case 'value':
                return $this->value;
            case 'text':
                return $this->__toString();
            case 'cli':
                return $this->cli();
            case 'html':
                return $this->html();
        }
    }

    /**
     * Return diagnostic value as string.
     *
     * @return string
     */
    public function __toString()
    {
        $value = $this->value;

        if (is_scalar($value) && isset($this->labels[$value])) {
            $value = $this->labels[$value];
        }

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        if ($this->withComment && $this->comment) {
            $value .= " ($this->comment)";
        }

        $this->withComment = false;

        return (string) $value;
    }
}
