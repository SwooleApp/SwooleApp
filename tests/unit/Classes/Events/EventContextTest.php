<?php

namespace tests\Classes\Events;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Events\EventContext;

/**
 * @covers \Sidalex\SwooleApp\Classes\Events\EventContext
 *
 * Test suite for EventContext class which stores event data
 */
class EventContextTest extends TestCase
{
    public function testForRequestCreatesContext(): void
    {
        $request = new \Swoole\Http\Request();
        $response = new \Swoole\Http\Response();

        $context = EventContext::forRequest($request, $response);

        $this->assertInstanceOf(EventContext::class, $context);
        $this->assertSame($request, $context->getRequest());
        $this->assertSame($response, $context->getResponse());
    }

    public function testGettersReturnNullByDefault(): void
    {
        $context = new EventContext();

        $this->assertNull($context->getWorkerId());
        $this->assertNull($context->getTaskId());
        $this->assertNull($context->getReactorId());
        $this->assertNull($context->getFd());
        $this->assertNull($context->getRequest());
        $this->assertNull($context->getResponse());
        $this->assertNull($context->getTaskData());
        $this->assertNull($context->getTaskResult());
        $this->assertNull($context->getEventName());
    }

    public function testSetAndGetEventName(): void
    {
        $context = new EventContext();

        $context->setEventName('workerStart');
        $this->assertSame('workerStart', $context->getEventName());
    }

    public function testSetTaskResult(): void
    {
        $context = new EventContext();

        $result = new \stdClass();
        $result->done = true;
        $context->setTaskResult($result);

        $this->assertSame($result, $context->getTaskResult());
    }

    public function testSetEventNameReturnsSelf(): void
    {
        $context = new EventContext();
        $result = $context->setEventName('test');

        $this->assertSame($context, $result);
    }

    public function testSetTaskResultReturnsSelf(): void
    {
        $context = new EventContext();
        $result = $context->setTaskResult('result');

        $this->assertSame($context, $result);
    }
}