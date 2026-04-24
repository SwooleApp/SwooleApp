<?php

namespace tests\Classes\Events;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Events\SwooleEventHandlersRegistry;

/**
 * @covers \Sidalex\SwooleApp\Classes\Events\SwooleEventHandlersRegistry
 *
 * Test suite for SwooleEventHandlersRegistry class which stores event handlers
 */
class SwooleEventHandlersRegistryTest extends TestCase
{
    public function testRegisterAndGetHandlers()
    {
        $registry = new SwooleEventHandlersRegistry();

        $handlers = [
            0 => ['class' => TestHandler::class, 'method' => 'handle'],
        ];

        $registry->registerHandlers('workerStart', 'before', $handlers);

        $this->assertTrue($registry->hasHandlers('workerStart', 'before'));
        $this->assertEquals($handlers, $registry->getHandlers('workerStart', 'before'));
    }

    public function testHasHandlersReturnsFalseWhenEmpty()
    {
        $registry = new SwooleEventHandlersRegistry();

        $this->assertFalse($registry->hasHandlers('workerStart', 'before'));
    }

    public function testGetHandlersReturnsEmptyArrayWhenNotRegistered()
    {
        $registry = new SwooleEventHandlersRegistry();

        $result = $registry->getHandlers('workerStart', 'before');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRegisterHandlersReplacesExisting()
    {
        $registry = new SwooleEventHandlersRegistry();

        $handlers1 = [
            0 => ['class' => RegistryTestHandler1::class, 'method' => 'handle'],
        ];
        $handlers2 = [
            0 => ['class' => RegistryTestHandler2::class, 'method' => 'handle'],
        ];

        $registry->registerHandlers('workerStart', 'before', $handlers1);
        $registry->registerHandlers('workerStart', 'before', $handlers2);

        $result = $registry->getHandlers('workerStart', 'before');
        $this->assertEquals($handlers2, $result);
    }

    public function testMultipleEventsAndPhases()
    {
        $registry = new SwooleEventHandlersRegistry();

        $handlers1 = [
            0 => ['class' => TestHandler::class, 'method' => 'handle'],
        ];
        $handlers2 = [
            0 => ['class' => TestHandler::class, 'method' => 'handle'],
        ];

        $registry->registerHandlers('workerStart', 'before', $handlers1);
        $registry->registerHandlers('workerStart', 'after', $handlers2);
        $registry->registerHandlers('workerStop', 'before', $handlers1);

        $this->assertTrue($registry->hasHandlers('workerStart', 'before'));
        $this->assertTrue($registry->hasHandlers('workerStart', 'after'));
        $this->assertTrue($registry->hasHandlers('workerStop', 'before'));
        $this->assertFalse($registry->hasHandlers('workerStop', 'after'));
    }

    public function testHandlersWithCallable()
    {
        $registry = new SwooleEventHandlersRegistry();

        $callable = function ($app, $server, $workerId) {
            echo "Called";
        };

        $handlers = [
            0 => ['class' => '', 'method' => '', 'callable' => $callable],
        ];

        $registry->registerHandlers('workerStart', 'before', $handlers);

        $retrieved = $registry->getHandlers('workerStart', 'before');
        $this->assertArrayHasKey('callable', $retrieved[0]);
        $this->assertIsCallable($retrieved[0]['callable']);
    }
}

class TestHandler
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class RegistryTestHandler1
{
    public function handle($app, $server, $workerId): void
    {
    }
}

class RegistryTestHandler2
{
    public function handle($app, $server, $workerId): void
    {
    }
}