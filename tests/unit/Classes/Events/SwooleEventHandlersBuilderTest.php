<?php

namespace tests\Classes\Events;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Events\SwooleEventHandlersBuilder;
use Sidalex\SwooleApp\Classes\Events\SwooleEventHandlersRegistry;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

/**
 * @covers \Sidalex\SwooleApp\Classes\Events\SwooleEventHandlersBuilder
 *
 * Test suite for SwooleEventHandlersBuilder class which validates
 * and processes event handlers configuration
 */
class SwooleEventHandlersBuilderTest extends TestCase
{
    public function testBuildWithEmptyConfig()
    {
        $config = new ConfigWrapper(new \stdClass());
        $builder = new SwooleEventHandlersBuilder($config);
        $registry = $builder->build();

        $this->assertInstanceOf(SwooleEventHandlersRegistry::class, $registry);
        $this->assertFalse($registry->hasHandlers('workerStart', 'before'));
    }

    public function testBuildWithValidHandlers()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'before' => [
                    100 => [BuilderTestHandler::class, 'handle'],
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);
        $registry = $builder->build();

        $this->assertTrue($registry->hasHandlers('workerStart', 'before'));
        $handlers = $registry->getHandlers('workerStart', 'before');
        $this->assertCount(1, $handlers);
    }

    public function testBuildWithMultipleHandlersAndPhases()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'before' => [
                    -100 => [BuilderTestHandler::class, 'handle'],
                    100 => [BuilderTestDuplicate1::class, 'handle'],
                ],
                'after' => [
                    0 => [BuilderTestHandler3::class, 'handle'],
                ],
            ],
            'request' => [
                'before' => [
                    0 => [BuilderTestRequestHandler::class, 'handle'],
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);
        $registry = $builder->build();

        $this->assertTrue($registry->hasHandlers('workerStart', 'before'));
        $this->assertTrue($registry->hasHandlers('workerStart', 'after'));
        $this->assertTrue($registry->hasHandlers('request', 'before'));
    }

    public function testBuildWithClosureHandler()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'before' => [
                    0 => function ($app, $server, $workerId) {
                        echo "Handler executed";
                    },
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);
        $registry = $builder->build();

        $this->assertTrue($registry->hasHandlers('workerStart', 'before'));
        $handlers = $registry->getHandlers('workerStart', 'before');
        $this->assertCount(1, $handlers);
        $this->assertArrayHasKey('callable', $handlers[0]);
    }

    public function testInvalidEventThrowsException()
    {
        $configData = new \stdClass();
        $configData->events = [
            'invalidEvent' => [
                'before' => [],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid event 'invalidEvent'");
        $builder->build();
    }

    public function testDuplicatePriorityNotAllowed()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStop' => [
                'after' => [
                    50 => [BuilderTestDuplicate1::class, 'handle'],
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);
        $registry = $builder->build();

        $this->assertTrue($registry->hasHandlers('workerStop', 'after'));
    }

    public function testNonExistentClassThrowsException()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'before' => [
                    100 => ['NonExistentClass', 'handle'],
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Handler class 'NonExistentClass' not found");
        $builder->build();
    }

    public function testNonExistentMethodThrowsException()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'before' => [
                    100 => [BuilderTestHandler::class, 'nonExistentMethod'],
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Handler method");
        $builder->build();
    }

    public function testInvalidHandlerFormatThrowsException()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'before' => [
                    100 => 'invalid_string',
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid handler');
        $builder->build();
    }

    public function testHandlersAreSortedByPriority()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'before' => [
                    100 => [BuilderTestPosterior::class, 'handle'],
                    -100 => [BuilderTestPrior::class, 'handle'],
                    0 => [BuilderTestMiddle::class, 'handle'],
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);
        $registry = $builder->build();

        $handlers = $registry->getHandlers('workerStart', 'before');
        $keys = array_keys($handlers);

        $this->assertSame(-100, $keys[0]);
        $this->assertSame(0, $keys[1]);
        $this->assertSame(100, $keys[2]);
    }

    public function testAllValidEventsSupported()
    {
        $validEvents = ['workerStart', 'workerStop', 'request', 'task', 'connect', 'close'];

        foreach ($validEvents as $event) {
            $configData = new \stdClass();
            $configData->events = [
                $event => [
                    'before' => [
                        0 => [BuilderTestHandler::class, 'handle'],
                    ],
                ],
            ];

            $config = new ConfigWrapper($configData);
            $builder = new SwooleEventHandlersBuilder($config);

            $registry = $builder->build();
            $this->assertTrue($registry->hasHandlers($event, 'before'), "Event {$event} should be supported");
        }
    }

    public function testInvalidPhaseIsIgnored()
    {
        $configData = new \stdClass();
        $configData->events = [
            'workerStart' => [
                'invalid' => [
                    0 => [BuilderTestHandler::class, 'handle'],
                ],
            ],
        ];

        $config = new ConfigWrapper($configData);
        $builder = new SwooleEventHandlersBuilder($config);
        $registry = $builder->build();

        $this->assertFalse($registry->hasHandlers('workerStart', 'invalid'));
    }
}

class BuilderTestHandler
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class BuilderTestDuplicate1
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class BuilderTestDuplicate2
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class BuilderTestHandler3
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class BuilderTestRequestHandler
{
    public function handle($app, $request, $response): void
    {
    }
}

class BuilderTestPosterior
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class BuilderTestPrior
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class BuilderTestMiddle
{
    public function handle($app, $server, $workerId): void
    {
    }
}