<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\Container;
use EcPhp\CasLib\Cas;
use EcPhp\CasLib\Configuration\Properties;
use EcPhp\CasLib\Contract\CasInterface;
use EcPhp\CasLib\Contract\Configuration\PropertiesInterface;
use EcPhp\CasLib\Contract\Response\CasResponseBuilderInterface;
use EcPhp\CasLib\Response\CasResponseBuilder;
use EcPhp\CasLib\Utils\Uri;
use EcPhp\CasLibDemo\Middleware\Authenticate;
use EcPhp\CasLibDemo\Middleware\ProxyCallback;
use EcPhp\CasLibDemo\Middleware\ResponseLogger;
use EcPhp\Ecas\Contract\Response\Factory\LoginRequestFactory as LoginRequestFactoryInterface;
use EcPhp\Ecas\Contract\Response\Factory\LoginRequestFailureFactory as LoginRequestFailureFactoryInterface;
use EcPhp\Ecas\Ecas;
use EcPhp\Ecas\EcasProperties;
use EcPhp\Ecas\Response\EcasResponseBuilder;
use EcPhp\Ecas\Response\Factory\LoginRequestFactory;
use EcPhp\Ecas\Response\Factory\LoginRequestFailureFactory;
use EcPhp\Ecas\Service\Fingerprint\DefaultFingerprint;
use EcPhp\Ecas\Service\Fingerprint\Fingerprint;
use Lcobucci\JWT\Configuration as JwtConfig;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use loophp\psr17\Psr17;
use loophp\psr17\Psr17Interface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use PSR7Sessions\Storageless\Http\Configuration;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Twig\Extension\DebugExtension;
use Webclient\Extension\Log\Client;
use Zeuxisoo\Whoops\Slim\WhoopsMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$container = Container::create([
    LoggerInterface::class => static fn (): LoggerInterface => new Logger('stdout', [new StreamHandler('php://stdout')]),
    CacheItemPoolInterface::class => static fn (): CacheItemPoolInterface => new FilesystemAdapter('', 0, sys_get_temp_dir()),
    RequestFactoryInterface::class => static fn (): RequestFactoryInterface => new Psr17Factory(),
    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => new Psr17Factory(),
    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => new Psr17Factory(),
    UploadedFileFactoryInterface::class => static fn (): UploadedFileFactoryInterface => new Psr17Factory(),
    UriFactoryInterface::class => static fn (): UriFactoryInterface => new Psr17Factory(),
    ServerRequestFactoryInterface::class => static fn (): ServerRequestFactoryInterface => new Psr17Factory(),
    Psr17Interface::class => static fn (
        RequestFactoryInterface $requestFactory,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $uploadedFileFactory,
        UriFactoryInterface $uriFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ): Psr17Interface => new Psr17(
        $requestFactory,
        $responseFactory,
        $streamFactory,
        $uploadedFileFactory,
        $uriFactory,
        $serverRequestFactory
    ),
    ClientInterface::class => static fn (
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        LoggerInterface $logger
    ): ClientInterface => new Client(
        new Psr18Client(
            HttpClient::create(
                [
                    'verify_host' => false, // We disable SSL host verification.
                    'verify_peer' => false, // We disable SSL peer verification.
                ]
            ),
            $responseFactory,
            $streamFactory
        ),
        $logger
    ),

    SessionMiddleware::class => static fn (): SessionMiddleware => new SessionMiddleware(
        new Configuration(
            JwtConfig::forSymmetricSigner(
                new Sha256(),
                InMemory::base64Encoded('OpcMuKmoxkhzW0Y1iESpjWwL/D3UBdDauJOe742BJ5Q='), // replace this with a key of your own (see below)
            )
        ),
    ),
    ProxyCallback::class => static fn (
        CasInterface $cas,
        LoggerInterface $logger
    ): ProxyCallback => new ProxyCallback(
        $cas,
        $logger
    ),
    ResponseLogger::class => static fn (LoggerInterface $logger): ResponseLogger => new ResponseLogger($logger),

    // Cas/eCas stuff
    LoginRequestFactoryInterface::class => static fn (): LoginRequestFactoryInterface => new LoginRequestFactory(),
    LoginRequestFailureFactoryInterface::class => static fn (): LoginRequestFailureFactoryInterface => new LoginRequestFailureFactory(),
    CasResponseBuilder::class => static fn (): CasResponseBuilderInterface => new CasResponseBuilder(),
    EcasResponseBuilder::class => static fn (
        CasResponseBuilder $casResponseBuilder,
        LoginRequestFactoryInterface $loginRequestFactory,
        LoginRequestFailureFactoryInterface $loginRequestFailureFactory
    ): CasResponseBuilderInterface => new EcasResponseBuilder(
        $casResponseBuilder,
        $loginRequestFactory,
        $loginRequestFailureFactory
    ),
    Properties::class => static fn (): PropertiesInterface => new Properties(
        json_decode(
            file_get_contents(__DIR__ . '/../config/caslib-config.json'),
            true
        )
    ),
    EcasProperties::class => static fn (Properties $properties): PropertiesInterface => new EcasProperties($properties),

    Cas::class => static fn (
        Psr17Interface $psr17,
        PropertiesInterface $properties,
        ClientInterface $client,
        CacheItemPoolInterface $cache,
        CasResponseBuilderInterface $casResponseBuilder
    ): CasInterface => new Cas(
        $properties,
        $client,
        $psr17,
        $cache,
        $casResponseBuilder
    ),
    Fingerprint::class => static fn (): Fingerprint => new DefaultFingerprint(),
    Ecas::class => static fn (
        Cas $cas,
        PropertiesInterface $properties,
        Psr17Interface $psr17,
        ClientInterface $client,
        CasResponseBuilderInterface $casResponseBuilder,
        Fingerprint $fingerprint,
    ): CasInterface => new Ecas(
        $cas,
        $properties,
        $psr17,
        $casResponseBuilder,
        $client,
        $fingerprint
    ),

    // Switch here between regular CAS or eCas
    CasResponseBuilderInterface::class => static fn (CasResponseBuilder $casResponseBuilder, EcasResponseBuilder $ecasResponseBuilder): CasResponseBuilderInterface => $ecasResponseBuilder,
    PropertiesInterface::class => static fn (Properties $properties, EcasProperties $ecasProperties): PropertiesInterface => $ecasProperties,
    CasInterface::class => static fn (Cas $cas, Ecas $ecas): CasInterface => $ecas,
]);

// Create the app from the container
$app = Bridge::create($container);

$app
    ->get(
        '/',
        static function (
            Request $request,
            Response $response,
            PropertiesInterface $properties
        ): Response {
            /** SessionInterface $session */
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

            if (null !== $session) {
                $args['session'] = (array) $session->jsonSerialize();
            }

            $args['service'] = $request->getUri();
            $args['properties'] = $properties;

            return Twig::fromRequest($request)->render($response, 'index.html.twig', $args);
        }
    )
    ->setName('index');

$app
    ->get(
        '/simple',
        static function (Request $request, Response $response): Response {
            return Twig::fromRequest($request)->render($response, 'simple.html.twig');
        }
    )
    ->setName('simple');

$app
    ->get(
        '/restricted',
        static function (
            Request $request,
            Response $response,
            CasInterface $cas
        ): Response {
            /** @var SessionInterface $session */
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

            if ($session->isEmpty()) {
                return $cas->login(
                    $request,
                    [] + Uri::getParams($request->getUri())
                );
            }

            $args['service'] = $request->getUri();
            $args['session'] = $session->jsonSerialize();

            return Twig::fromRequest($request)->render($response, 'restricted.html.twig', $args);
        }
    )
    ->setName('restricted');

$app
    ->get(
        '/login',
        static function (CasInterface $cas, Request $request): Response {
            return $cas->login(
                $request,
                [] + Uri::getParams($request->getUri())
            );
        }
    )
    ->setName('cas-login');

$app
    ->get(
        '/logout',
        static function (CasInterface $cas, Request $request): Response {
            /** @var SessionInterface $session */
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);

            $session->clear();

            return $cas->logout(
                $request,
                [] + Uri::getParams($request->getUri())
            );
        }
    )
    ->setName('cas-logout');

$twig = Twig::create(
    __DIR__ . '/../templates',
    [
        'cache' => false,
        'debug' => true,
    ]
);
$twig->addExtension(new DebugExtension());

// Add Twig
$app->add(TwigMiddleware::create($app, $twig));

// Watch requests
$app->add(Authenticate::class);
$app->add(ProxyCallback::class);
$app->add(SessionMiddleware::class);
$app->add(ResponseLogger::class);
$app->add(WhoopsMiddleware::class);

$app->run();
