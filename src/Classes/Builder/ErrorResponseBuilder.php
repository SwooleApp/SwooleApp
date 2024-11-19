<?php

namespace Sidalex\SwooleApp\Classes\Builder;

use Swoole\Http\Response;

class ErrorResponseBuilder
{
    private Response $response;

    /**
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * @param string $errorMessage
     * @param int $errorCode
     * @param array<mixed>|object|string|int|null $details
     * @return Response
     */
    public function errorResponse(string $errorMessage, int $errorCode = 400, array|object|string|int|null $details = null): Response
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setStatusCode($errorCode);
        $this->response->end(
            json_encode(
                [
                    "status" => "error",
                    "message" => $errorMessage,
                    "details" => $details,
                ]
            )
        );
        return $this->response;
    }

}