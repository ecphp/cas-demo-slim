<?php

declare(strict_types=1);

namespace EcPhp\CasLibDemo\Middleware;

use EcPhp\CasLib\Contract\CasInterface;
use EcPhp\CasLib\Contract\Configuration\PropertiesInterface;
use EcPhp\CasLib\Utils\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use Throwable;

final class Authenticate implements MiddlewareInterface
{
    public function __construct(
        private readonly PropertiesInterface $properties,
        private readonly CasInterface $cas,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->logger->info('Watching current request for ticket parameter...');

        try {
            $credentials = $this->cas->authenticate($request);
        } catch (Throwable $exception) {
            $this->logger->info(sprintf('Ignoring request... (reason: %s)', $exception->getMessage()));

            return $handler->handle($request);
        }

        $this->logger->info('CAS authentication successful, redirecting to url without ticket parameter...');

        // Redirect the user to the same page without ticket parameter.
        $redirect = (string) Uri::removeParams(
            $request->getUri(),
            'ticket'
        );

        /** @var \PSR7Sessions\Storageless\Session\SessionInterface $session */
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        $session->set('user', $credentials);

        return $handler
            ->handle($request)
            ->withStatus(302)
            ->withHeader('Location', $redirect);
    }
}
