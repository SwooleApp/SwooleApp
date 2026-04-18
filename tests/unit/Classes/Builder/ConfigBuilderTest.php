<?php

namespace tests\Classes\Builder;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Builder\ConfigBuilder;
use Sidalex\SwooleApp\Classes\Constants\ApplicationConstants;
use Sidalex\SwooleApp\Classes\Dispatcher\DispatcherInterface;
use Sidalex\SwooleApp\Classes\Validators\ConfigValidatorInterface;
use Sidalex\SwooleApp\Application;

/**
 * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder
 *
 * Test suite for ConfigBuilder class which handles:
 * - Loading configuration from .env files
 * - Parsing and type conversion of configuration values
 * - Building hierarchical configuration structure
 */
class ConfigBuilderTest extends TestCase
{
    /**
     * Tests that constructor without parameters creates empty configuration object
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::__construct
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getConfig
     */
    public function testEmptyConstructorCreatesEmptyConfig()
    {
        $builder = new ConfigBuilder();
        $config = $builder->getConfig();

        $this->assertInstanceOf(\stdClass::class, $config);
        $this->assertEquals(new \stdClass(), $config);
    }

    /**
     * Tests loading configuration from environment variables:
     * - Parsing different data types (strings, numbers, booleans, null)
     * - Proper value conversion
     * - Nested configuration structure creation
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::loadEnvConfig
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::processConfigKey
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::setFinalValue
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::parseValue
     */
    public function testLoadConfigFromEnvVariables()
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'DB__HOST' => 'localhost',
            ApplicationConstants::APP_ENV_PREFIX . 'DB__PORT' => '3306',
            ApplicationConstants::APP_ENV_PREFIX . 'DEBUG' => 'true',
            ApplicationConstants::APP_ENV_PREFIX . 'NULL_VALUE' => 'null',
            ApplicationConstants::APP_ENV_PREFIX . 'FLOAT_VALUE' => '3.14'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $config = $builder->getConfig();

        $this->assertEquals('localhost', $config->DB->HOST);
        $this->assertSame(3306, $config->DB->PORT);
        $this->assertTrue($config->DEBUG);
        $this->assertNull($config->NULL_VALUE);
        $this->assertSame(3.14, $config->FLOAT_VALUE);
    }

    /**
     * Tests merging base configuration with new values:
     * - Preserving existing values
     * - Overwriting specified values
     * - Adding new values
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::__construct
     */
    public function testMergeWithBaseConfig()
    {
        $baseConfig = new \stdClass();
        $baseConfig->DB = new \stdClass();
        $baseConfig->DB->HOST = 'remotehost';
        $baseConfig->EXISTING = 'value';

        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'DB__PORT' => '3306',
            ApplicationConstants::APP_ENV_PREFIX . 'EXISTING' => 'new_value'
        ];

        $builder = new ConfigBuilder($baseConfig, $envVariables);
        $config = $builder->getConfig();

        $this->assertEquals('remotehost', $config->DB->HOST);
        $this->assertSame(3306, $config->DB->PORT);
        $this->assertEquals('new_value', $config->EXISTING);
    }

    /**
     * Tests parsing different value types:
     * - Integers
     * - Floating point numbers
     * - Boolean values (true/false)
     * - Null values
     * - Strings
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::parseValue
     */
    public function testParseDifferentValueTypes()
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'INT' => '42',
            ApplicationConstants::APP_ENV_PREFIX . 'FLOAT' => '3.14',
            ApplicationConstants::APP_ENV_PREFIX . 'BOOL_TRUE' => 'true',
            ApplicationConstants::APP_ENV_PREFIX . 'BOOL_FALSE' => 'false',
            ApplicationConstants::APP_ENV_PREFIX . 'NULL' => 'null',
            ApplicationConstants::APP_ENV_PREFIX . 'STRING' => 'test'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $config = $builder->getConfig();

        $this->assertSame(42, $config->INT);
        $this->assertSame(3.14, $config->FLOAT);
        $this->assertTrue($config->BOOL_TRUE);
        $this->assertFalse($config->BOOL_FALSE);
        $this->assertNull($config->NULL);
        $this->assertSame('test', $config->STRING);
    }

    /**
     * Tests creation of deeply nested configuration structure:
     * - Automatic creation of intermediate objects
     * - Proper value assignment at any nesting level
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::processConfigKey
     */
    public function testDeepNestedConfigStructure()
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'LEVEL1__LEVEL2__LEVEL3__VALUE' => 'test'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $config = $builder->getConfig();

        $this->assertTrue(property_exists($config, 'LEVEL1'));
        $this->assertTrue(property_exists($config->LEVEL1, 'LEVEL2'));
        $this->assertTrue(property_exists($config->LEVEL1->LEVEL2, 'LEVEL3'));
        $this->assertEquals('test', $config->LEVEL1->LEVEL2->LEVEL3->VALUE);
    }

    /**
     * Tests array-like notation in configuration:
     * - Creating arrays instead of objects for numeric keys
     * - Proper value assignment in arrays
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::setFinalValue
     */
    public function testArrayNotationInConfig()
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'ITEMS__0' => 'first',
            ApplicationConstants::APP_ENV_PREFIX . 'ITEMS__1' => 'second'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $config = $builder->getConfig();

        $this->assertIsArray($config->ITEMS);
        $this->assertEquals('first', $config->ITEMS[0]);
        $this->assertEquals('second', $config->ITEMS[1]);
    }

    /**
     * Tests configuration validation:
     * - Error handling during validation
     * - Error message collection
     * - Validation status return
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::validate
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getErrors
     */
    public function testConfigValidation()
    {
        $validator = new class implements ConfigValidatorInterface {
            public function validate(\stdClass $config): void
            {
                throw new \RuntimeException('Test error');
            }
        };

        $builder = new ConfigBuilder();
        $result = $builder->validate([get_class($validator)]);
        $errors = $builder->getErrors();

        $this->assertFalse($result);
        $this->assertContains('Test error', $errors);
    }

    /**
     * Tests handling of missing .env file:
     * - No errors should occur when file is missing
     * - Configuration should remain empty
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::loadDotEnv
     */
    public function testMissingEnvFileHandling()
    {
        $builder = new TestableConfigBuilder(envFilePath: '/path/to/nonexistent/.env');
        $builder->loadDotEnv();

        $this->assertEmpty((array)$builder->getConfig());
    }

    /**
     * Tests loading configuration from .env file:
     * - Parsing file with comments and empty lines
     * - Ignoring variables without SWOOLE_APP_ prefix
     * - Proper type conversion
     * - Verifying DEBUG remains boolean true
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::loadDotEnv
     */
    public function testLoadFromEnvFile()
    {
        $envContent = "
# Comment
SWOOLE_APP_DB__HOST=localhost
SWOOLE_APP_DB__PORT=3306
SWOOLE_APP_DEBUG=true
OTHER_VAR=ignore
    ";
        $tempFile = tempnam(sys_get_temp_dir(), 'envtest');
        file_put_contents($tempFile, $envContent);

        try {
            $builder = new TestableConfigBuilder(envFilePath: $tempFile);
            $builder->loadDotEnv();
            $config = $builder->getConfig();

            $this->assertEquals('localhost', $config->DB->HOST);
            $this->assertSame(3306, $config->DB->PORT);
            $this->assertTrue($config->DEBUG);
            $this->assertIsBool($config->DEBUG);
            $this->assertSame(true, $config->DEBUG);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::buildServerConfig
     */
    public function testBuildServerConfigWithEmptyConfig(): void
    {
        $builder = new ConfigBuilder();
        $serverConfig = $builder->buildServerConfig();

        $this->assertIsArray($serverConfig);
        $this->assertArrayHasKey('worker_num', $serverConfig);
        $this->assertArrayHasKey('task_worker_num', $serverConfig);
        $this->assertIsInt($serverConfig['worker_num']);
        $this->assertIsInt($serverConfig['task_worker_num']);
        $this->assertArrayNotHasKey('dispatch_func', $serverConfig);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::buildServerConfig
     */
    public function testBuildServerConfigWithSwooleConfig(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->SWOOLE = new \stdClass();
        $baseConfig->SWOOLE->worker_num = 8;
        $baseConfig->SWOOLE->task_worker_num = 16;
        $baseConfig->SWOOLE->task_enable_coroutine = true;

        $builder = new ConfigBuilder($baseConfig);
        $serverConfig = $builder->buildServerConfig();

        $this->assertSame(8, $serverConfig['worker_num']);
        $this->assertSame(16, $serverConfig['task_worker_num']);
        $this->assertTrue($serverConfig['task_enable_coroutine']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::buildServerConfig
     */
    public function testBuildServerConfigPreservesCustomConfig(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->SWOOLE = new \stdClass();
        $baseConfig->SWOOLE->worker_num = 4;
        $baseConfig->SWOOLE->log_file = '/var/log/swoole.log';
        $baseConfig->SWOOLE->daemonize = true;

        $builder = new ConfigBuilder($baseConfig);
        $serverConfig = $builder->buildServerConfig();

        $this->assertSame(4, $serverConfig['worker_num']);
        $this->assertSame('/var/log/swoole.log', $serverConfig['log_file']);
        $this->assertTrue($serverConfig['daemonize']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::buildServerConfig
     */
    public function testBuildServerConfigWithDispatcher(): void
    {
        $dispatcher = $this->createMock(DispatcherInterface::class);

        $builder = new ConfigBuilder();
        $serverConfig = $builder->buildServerConfig($dispatcher);

        $this->assertArrayNotHasKey('dispatch_func', $serverConfig);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getCyclicJobsStrategy
     */
    public function testGetCyclicJobsStrategyDefault(): void
    {
        $builder = new ConfigBuilder();
        $strategy = $builder->getCyclicJobsStrategy();

        $this->assertSame('ALL_WORKERS', $strategy);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getCyclicJobsStrategy
     */
    public function testGetCyclicJobsStrategyFromConfig(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->cyclic_jobs = new \stdClass();
        $baseConfig->cyclic_jobs->strategy = 'DEDICATED_WORKER';

        $builder = new ConfigBuilder($baseConfig);
        $strategy = $builder->getCyclicJobsStrategy();

        $this->assertSame('DEDICATED_WORKER', $strategy);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getCyclicJobsStrategy
     */
    public function testGetCyclicJobsStrategyCaseInsensitive(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->cyclic_jobs = new \stdClass();
        $baseConfig->cyclic_jobs->strategy = 'round_robin';

        $builder = new ConfigBuilder($baseConfig);
        $strategy = $builder->getCyclicJobsStrategy();

        $this->assertSame('ROUND_ROBIN', $strategy);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::buildServerConfig
     */
    public function testBuildServerConfigDefaultsAreAppliedWhenNotSet(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->SWOOLE = new \stdClass();
        $baseConfig->SWOOLE->log_file = '/tmp/swoole.log';

        $builder = new ConfigBuilder($baseConfig);
        $serverConfig = $builder->buildServerConfig();

        $this->assertSame('/tmp/swoole.log', $serverConfig['log_file']);
        $this->assertArrayHasKey('worker_num', $serverConfig);
        $this->assertArrayHasKey('task_worker_num', $serverConfig);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerHost
     */
    public function testGetServerHostDefault(): void
    {
        $builder = new ConfigBuilder();
        $host = $builder->getServerHost();

        $this->assertSame('0.0.0.0', $host);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerHost
     */
    public function testGetServerHostFromEnv(): void
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'SERVER_HOST' => '127.0.0.1'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $host = $builder->getServerHost();

        $this->assertSame('127.0.0.1', $host);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerHost
     */
    public function testGetServerHostFromConfig(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->server = new \stdClass();
        $baseConfig->server->host = 'localhost';

        $builder = new ConfigBuilder($baseConfig);
        $host = $builder->getServerHost();

        $this->assertSame('localhost', $host);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerPort
     */
    public function testGetServerPortDefault(): void
    {
        $builder = new ConfigBuilder();
        $port = $builder->getServerPort();

        $this->assertSame(9501, $port);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerPort
     */
    public function testGetServerPortFromEnv(): void
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'SERVER_PORT' => '8080'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $port = $builder->getServerPort();

        $this->assertSame(8080, $port);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerPort
     */
    public function testGetServerPortFromConfig(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->server = new \stdClass();
        $baseConfig->server->port = 3000;

        $builder = new ConfigBuilder($baseConfig);
        $port = $builder->getServerPort();

        $this->assertSame(3000, $port);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerMode
     */
    public function testGetServerModeDefault(): void
    {
        $builder = new ConfigBuilder();
        $mode = $builder->getServerMode();

        $this->assertSame(SWOOLE_PROCESS, $mode);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerMode
     */
    public function testGetServerModeBaseFromEnv(): void
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'SERVER_MODE' => 'BASE'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $mode = $builder->getServerMode();

        $this->assertSame(SWOOLE_BASE, $mode);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerMode
     */
    public function testGetServerModeProcessFromEnv(): void
    {
        $envVariables = [
            ApplicationConstants::APP_ENV_PREFIX . 'SERVER_MODE' => 'PROCESS'
        ];

        $builder = new ConfigBuilder(null, $envVariables);
        $mode = $builder->getServerMode();

        $this->assertSame(SWOOLE_PROCESS, $mode);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerMode
     */
    public function testGetServerModeBaseFromConfig(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->server = new \stdClass();
        $baseConfig->server->mode = 'BASE';

        $builder = new ConfigBuilder($baseConfig);
        $mode = $builder->getServerMode();

        $this->assertSame(SWOOLE_BASE, $mode);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\ConfigBuilder::getServerMode
     */
    public function testGetServerModeProcessFromConfig(): void
    {
        $baseConfig = new \stdClass();
        $baseConfig->server = new \stdClass();
        $baseConfig->server->mode = 'PROCESS';

        $builder = new ConfigBuilder($baseConfig);
        $mode = $builder->getServerMode();

        $this->assertSame(SWOOLE_PROCESS, $mode);
    }
}

/**
 * Test helper class extending ConfigBuilder for testing protected methods
 */
class TestableConfigBuilder extends ConfigBuilder
{
    /**
     * Exposes protected loadDotEnv method for testing
     */
    public function loadDotEnv(): void
    {
        parent::loadDotEnv();
    }
}