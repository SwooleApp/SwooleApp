<?php

declare(strict_types=1);

namespace Sidalex\SwooleApp\Classes\Events;

use Swoole\Http\Server;

interface EventHandlerExecutorInterface
{
    public function executeBeforeHandlers(Server $server, EventContext $context): void;

    public function executeAfterHandlers(Server $server, EventContext $context): void;
}