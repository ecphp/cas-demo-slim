<?php

declare(strict_types=1);

namespace EcPhp\CasLibDemo\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class ResponseLogger implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);

        $result = [
            sprintf('status: %s', $response->getStatusCode()),
        ];

        if ($response->hasHeader('Location')) {
            $result[] = sprintf('redirection: %s', $response->getHeaderLine('Location'));
        }

        $this->logger->info(
            sprintf('Response: %s', implode(', ', $result))
        );

        return $response;
    }
}
