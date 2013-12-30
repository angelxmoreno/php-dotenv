<?php

namespace josegonzalez\Dotenv;

use InvalidArgumentException;
use LogicException;
use JsonSerializable;
use RuntimeException;

// FIXME Don't declare interface, if it's already exists.
if (version_compare(PHP_VERSION, '5.4.0') < 0) && !interface_exists('JsonSerializable', false)) {
    /**
     * For compatibility with PHP 5.3.
     */
    interface JsonSerializable
    {
        /**
         * @return array
         */
        function jsonSerialize();
    }
} else {
    use JsonSerializable;
}


class Load implements JsonSerializable
{

    protected $filepath = null;

    protected $environment = null;

    public function __construct($filepath = null)
    {
        $this->setFilepath($filepath);
        return $this;
    }

    public function filepath()
    {
        return $this->filepath;
    }

    public function setFilepath($filepath = null)
    {
        if ($filepath == null) {
            $filepath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
        }
        $this->filepath = $filepath;
        return $this;
    }

    public static function load($options = null)
    {
        $filepath = null;
        if (is_string($options)) {
            $filepath = $options;
            $options = array();
        }

        $dotenv = new \josegonzalez\Dotenv\Load($filepath);
        $dotenv->parse();
        if (array_key_exists('expect', $options)) {
            $dotenv->expect($options['expect']);
        }

        if (array_key_exists('define', $options)) {
            $dotenv->define();
        }

        if (array_key_exists('toEnv', $options)) {
            $dotenv->toEnv($options['toEnv']);
        }

        if (array_key_exists('toServer', $options)) {
            $dotenv->toServer($options['toServer']);
        }

        return $dotenv;
    }

    public function parse()
    {
        if (!file_exists($this->filepath)) {
            throw new InvalidArgumentException(sprintf("Environment file '%s' is not found.", $this->filepath));
        }

        if (!is_readable($this->filepath)) {
            throw new InvalidArgumentException(sprintf("Environment file '%s' is not readable.", $this->filepath));
        }

        $fc = file_get_contents($this->filepath);
        if ($fc === false) {
            throw new InvalidArgumentException(sprintf("Environment file '%s' is not readable.", $this->filepath));
        }

        $lines = explode(PHP_EOL, $fc);

        $this->environment = array();
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            if (!preg_match('/(?:export )?([a-zA-Z_][a-zA-Z0-9_]*)=(.*)/', $line, $matches)) {
                continue;
            }

            $key = $matches[1];
            $value = $matches[2];
            if (preg_match('/^\'(.*)\'$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            }

            $this->environment[$key] = $value;
        }

        return $this;
    }

    public function expect()
    {
        $this->requireParse('expect');

        $args = func_get_args();
        if (count($args) == 0) {
            throw new InvalidArgumentException("No arguments were passed to expect()");
        }

        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }

        $keys = (array) $args;
        $missingEnvs = array();

        foreach ($keys as $key) {
            if (!isset($this->environment[$key])) {
                $missingEnvs[] = $key;
            }
        }

        if (!empty($missingEnvs)) {
            throw new RuntimeException(sprintf("Required ENV vars missing: ['%s']", implode("', '", $missingEnvs)));
        }

        return $this;
    }

    public function define()
    {
        $this->requireParse('define');
        foreach ($this->environment as $key => $value) {
            if (defined($key)) {
                throw new LogicException(sprintf('Key "%s" has already been defined', $key));
            }

            define($key, $value);
        }

        return $this;
    }

    public function toEnv($overwrite = false)
    {
        $this->requireParse('toEnv');
        foreach ($this->environment as $key => $value) {
            if (isset($_ENV[$key]) && !$overwrite) {
                throw new LogicException(sprintf('Key "%s" has already been defined in $_ENV', $key));
            }

            $_ENV[$key] = $value;
        }

        return $this;
    }

    public function toServer($overwrite = false)
    {
        $this->requireParse('toServer');
        foreach ($this->environment as $key => $value) {
            if (isset($_SERVER[$key]) && !$overwrite) {
                throw new LogicException(sprintf('Key "%s" has already been defined in $_SERVER', $key));
            }

            $_SERVER[$key] = $value;
        }

        return $this;
    }

    public function toArray()
    {
        $this->requireParse('toArray');
        return $this->environment;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function __toString()
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }

    protected function requireParse($method)
    {
        if (!is_array($this->environment)) {
            throw new LogicException(sprintf('Environment must be parsed before calling %s()', $method));
        }
    }
}
