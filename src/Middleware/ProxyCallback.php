<?php

declare(strict_types=1);

namespace EcPhp\CasLibDemo\Middleware;

use EcPhp\CasLib\Contract\CasInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ProxyCallback implements MiddlewareInterface
{
    public function __construct(
        private readonly CasInterface $cas,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->logger->info('Watching current request for proxy callback parameters...');

        try {
            $this->cas->handleProxyCallback($request);
        } catch (Throwable $exception) {
            $this->logger->info(sprintf('Ignoring request... (reason: %s)', $exception->getMessage()));

            return $handler->handle($request);
        }

        return $handler->handle($request);
    }
}
