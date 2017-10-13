<?php

namespace Enqueue\AmqpTools;

/**
 * The config could be an array, string DSN or null. In case of null it will attempt to connect to localhost with default credentials.
 *
 * 1. The config could be an array with next options:
 *   host - The host to connect too. Note: Max 1024 characters
 *   port - Port on the host
 *   vhost - The virtual host on the host. Note: Max 128 characters
 *   user - The user name to use. Note: Max 128 characters
 *   pass - Password. Note: Max 128 characters
 *   read_timeout - Timeout in for income activity. Note: 0 or greater seconds. May be fractional
 *   write_timeout - Timeout in for outcome activity. Note: 0 or greater seconds. May be fractional
 *   connect_timeout - Connection timeout. Note: 0 or greater seconds. May be fractional
 *   heartbeat - how often to send heartbeat. 0 means off
 *   persisted - bool, Whether it use single persisted connection or open a new one for every context
 *   lazy - the connection will be performed as later as possible, if the option set to true
 *   qos_prefetch_size - The server will send a message in advance if it is equal to or smaller in size than the available prefetch size. May be set to zero, meaning "no specific limit"
 *   qos_prefetch_count - Specifies a prefetch window in terms of whole messages
 *   qos_global - If "false" the QoS settings apply to the current channel only. If this field is "true", they are applied to the entire connection.
 *
 * 2. null - in this case it tries to connect to locahost with default settings
 * 3. amqp: same as 2.
 * 4. amqp://user:pass@host:10000/vhost?lazy=true&persisted=false&read_timeout=2
 * 5. amqp+foo: - the scheme driver could be used. (make sure you added it to the list of supported schemes)
 *
 * @see https://www.rabbitmq.com/uri-spec.html
 */
class ConnectionConfig
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var array|null|string
     */
    private $inputConfig;

    /**
     * @var array
     */
    private $defaultConfig;

    /**
     * @var string[]
     */
    private $supportedSchemes;

    /**
     * @param array|string|null $config
     */
    public function __construct($config = null)
    {
        $this->inputConfig = $config;

        $this->supportedSchemes = [];
        $this->defaultConfig = [
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'pass' => 'guest',
            'vhost' => '/',
            'read_timeout' => 3.,
            'write_timeout' => 3.,
            'connection_timeout' => 3.,
            'heartbeat' => 0,
            'persisted' => true,
            'lazy' => true,
            'qos_global' => false,
            'qos_prefetch_size' => 0,
            'qos_prefetch_count' => 1,
        ];

        $this->addSupportedScheme('amqp');
    }

    /**
     * @param string $schema
     *
     * @return self
     */
    public function addSupportedScheme($schema)
    {
        $this->supportedSchemes[] = $schema;
        $this->supportedSchemes = array_unique($this->supportedSchemes);

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return self
     */
    public function addDefaultOption($name, $value)
    {
        $this->defaultConfig[$name] = $value;

        return $this;
    }

    /**
     * @return self
     */
    public function parse()
    {
        if (empty($this->inputConfig) || in_array($this->inputConfig, $this->supportedSchemes, true)) {
            $config = [];
        } elseif (is_string($this->inputConfig)) {
            $config = $this->parseDsn($this->inputConfig);
        } elseif (is_array($this->inputConfig)) {
            $config = $this->inputConfig;
            if (array_key_exists('dsn', $config)) {
                $dsn = $config['dsn'];
                unset($config['dsn']);

                $config = array_replace($config, $this->parseDsn($dsn));
            }
        } else {
            throw new \LogicException('The config must be either an array of options, a DSN string or null');
        }

        $config = array_replace($this->defaultConfig, $config);
        $config['host'] = (string) $config['host'];
        $config['port'] = (int) ($config['port']);
        $config['user'] = (string) $config['user'];
        $config['pass'] = (string) $config['pass'];
        $config['read_timeout'] = max((float) ($config['read_timeout']), 0);
        $config['write_timeout'] = max((float) ($config['write_timeout']), 0);
        $config['connection_timeout'] = max((float) ($config['connection_timeout']), 0);
        $config['heartbeat'] = max((float) ($config['heartbeat']), 0);
        $config['persisted'] = !empty($config['persisted']);
        $config['lazy'] = !empty($config['lazy']);
        $config['qos_global'] = !empty($config['qos_global']);
        $config['qos_prefetch_count'] = max((int) ($config['qos_prefetch_count']), 0);
        $config['qos_prefetch_size'] = max((int) ($config['qos_prefetch_size']), 0);

        $this->config = $config;

        return $this;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->getOption('host');
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->getOption('port');
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->getOption('user');
    }

    /**
     * @return string
     */
    public function getPass()
    {
        return $this->getOption('pass');
    }

    /**
     * @return string
     */
    public function getVHost()
    {
        return $this->getOption('vhost');
    }

    /**
     * @return int
     */
    public function getReadTimeout()
    {
        return $this->getOption('read_timeout');
    }

    /**
     * @return int
     */
    public function getWriteTimeout()
    {
        return $this->getOption('write_timeout');
    }

    /**
     * @return int
     */
    public function getConnectionTimeout()
    {
        return $this->getOption('connection_timeout');
    }

    /**
     * @return int
     */
    public function getHeartbeat()
    {
        return $this->getOption('heartbeat');
    }

    /**
     * @return bool
     */
    public function isPersisted()
    {
        return $this->getOption('persisted');
    }

    /**
     * @return bool
     */
    public function isLazy()
    {
        return $this->getOption('lazy');
    }

    /**
     * @return bool
     */
    public function isQosGlobal()
    {
        return $this->getOption('qos_global');
    }

    /**
     * @return int
     */
    public function getQosPrefetchSize()
    {
        return $this->getOption('qos_prefetch_size');
    }

    /**
     * @return int
     */
    public function getQosPrefetchCount()
    {
        return $this->getOption('qos_prefetch_count');
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return bool
     */
    public function getOption($name, $default = null)
    {
        $config = $this->getConfig();

        return array_key_exists($name, $config) ? $config[$name] : $default;
    }

    /**
     * @throws \LogicException if the input config has not been parsed
     *
     * @return array
     */
    public function getConfig()
    {
        if (null === $this->config) {
            throw new \LogicException('The config has not been parsed.');
        }

        return $this->config;
    }

    /**
     * @param string $dsn
     *
     * @return array
     */
    private function parseDsn($dsn)
    {
        if (false === parse_url($dsn)) {
            throw new \LogicException(sprintf('Failed to parse DSN "%s"', $dsn));
        }

        $config = [];

        $scheme = parse_url($dsn, PHP_URL_SCHEME);
        if (false == in_array($scheme, $this->supportedSchemes, true)) {
            throw new \LogicException(sprintf('The given DSN scheme "%s" is not supported. Could be one of "%s" only.', $scheme, implode('", "', $this->supportedSchemes)));
        }

        if ($host = parse_url($dsn, PHP_URL_HOST)) {
            $config['host'] = $host;
        }
        if ($port = parse_url($dsn, PHP_URL_PORT)) {
            $config['port'] = $port;
        }
        if ($user = parse_url($dsn, PHP_URL_USER)) {
            $config['user'] = $user;
        }
        if ($pass = parse_url($dsn, PHP_URL_PASS)) {
            $config['pass'] = $pass;
        }

        if ($query = parse_url($dsn, PHP_URL_QUERY)) {
            $queryConfig = [];
            parse_str($query, $queryConfig);

            $config = array_replace($queryConfig, $config);
        }

        if ($path = parse_url($dsn, PHP_URL_PATH)) {
            $config['vhost'] = ltrim($path, '/');
        }

        return array_map('urldecode', $config);
    }
}
