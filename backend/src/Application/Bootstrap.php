<?php

declare(strict_types=1);

namespace App\Application;

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

use App\Auth\AzureAdClient;
use App\Auth\AuthService;
use App\Auth\JwtService;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\UploadController;
use App\Http\Middleware\CorsMiddleware;
use App\Services\Ai\LlmService;
use App\Services\Ai\MockLlmService;
use App\Services\Jobs\QueueService;
use App\Services\Jobs\WebhookIngest;
use App\Services\Media\LocalMediaStorage;
use App\Services\Media\MediaService;
use App\Services\Media\MediaStorage;
use App\Services\Media\S3MediaStorage;
use App\Services\Media\StubVirusScanner;
use App\Services\Media\VirusScanner;
use App\Services\Payments\MockStripeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Governance\AccountDeletionService;
use App\Services\Governance\ConsentService;
use App\Services\Jobs\Handlers\AccountPurgeHandler;
use App\Services\Jobs\Handlers\RetentionPurgeHandler;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\TenantProvisioningService;
use App\Services\ActivityLogger;
use App\Services\Meta\MetaClient;
use App\Services\Meta\MetaOAuthService;
use App\Services\Meta\MetaTokenStore;
use App\Support\Encryptor;
use App\Repositories\MediaRepository;
use App\Repositories\MetaConnectionRepository;
use GuzzleHttp\Client as GuzzleClient;
use App\Services\Mail\Mailer;
use App\Services\Mail\OutboxMailer;
use App\Services\Publishing\PublishProvider;
use App\Services\Publishing\StubPublishProvider;
use App\Services\Publishing\MetaPublishProvider;
use App\Services\Publishing\PlatformPublishProvider;
use App\Services\Teams\OutboxTeamsNotifier;
use App\Services\Teams\TeamsNotifier;

/**
 * Wires the DI container, Eloquent, middleware and routes, and returns a ready Slim App.
 */
final class Bootstrap
{
    public static function createApp(): App
    {
        $root = dirname(__DIR__, 2);

        // Environment
        if (is_file($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }

        $settings = require $root . '/config/settings.php';

        // Container
        $builder = new ContainerBuilder();
        $builder->addDefinitions(self::definitions($settings, $root));
        $container = $builder->build();

        // Eloquent (boot once, globally available to models)
        self::bootEloquent($settings['db']);

        // Slim
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new CorsMiddleware($settings['app']['cors_origins']));

        $errorMiddleware = $app->addErrorMiddleware(
            (bool) $settings['app']['debug'],
            true,
            true,
            $container->get(LoggerInterface::class)
        );
        // JSON error responses
        $errorMiddleware->setDefaultErrorHandler(
            new \App\Http\Handlers\JsonErrorHandler($app->getCallableResolver(), $app->getResponseFactory())
        );

        (require $root . '/config/routes.php')($app);

        return $app;
    }

    /** Minimal container for CLI commands (env + Eloquent + services, no HTTP). */
    public static function bootConsole(): ContainerInterface
    {
        $root = dirname(__DIR__, 2);
        if (is_file($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }
        $settings = require $root . '/config/settings.php';

        $builder = new ContainerBuilder();
        $builder->addDefinitions(self::definitions($settings, $root));
        $container = $builder->build();

        self::bootEloquent($settings['db']);
        return $container;
    }

    private static function definitions(array $settings, string $root): array
    {
        return [
            'settings' => $settings,

            LoggerInterface::class => function () use ($root): LoggerInterface {
                $logger = new Logger('app');
                $logger->pushHandler(new StreamHandler($root . '/var/app.log', Logger::DEBUG));
                return $logger;
            },

            JwtService::class => fn () => new JwtService($settings['jwt']),

            AzureAdClient::class => fn () => new AzureAdClient($settings['azure']),

            AuthService::class => fn (ContainerInterface $c) => new AuthService(
                $c->get(AzureAdClient::class),
                $c->get(JwtService::class),
                $settings['jwt']
            ),

            AuthController::class => fn (ContainerInterface $c) => new AuthController(
                $c->get(AuthService::class),
                $settings
            ),

            // Publishing: dispatch per platform — Instagram/Facebook deliver live via
            // the Meta client; other networks fall back to the stub until built.
            PublishProvider::class => fn (ContainerInterface $c) => new PlatformPublishProvider(
                $c->get(MetaPublishProvider::class),
                new StubPublishProvider(),
            ),

            // Phase 3 integrations — credential-free mock providers (swap for real later).
            Mailer::class => fn () => new OutboxMailer(),
            TeamsNotifier::class => fn () => new OutboxTeamsNotifier(),
            LlmService::class => fn () => new MockLlmService(),

            // Media storage — driver chosen by config (local now, s3 later: config swap only).
            MediaStorage::class => fn () => ($settings['media']['disk'] ?? 'local') === 's3'
                ? new S3MediaStorage($settings['media'])
                : new LocalMediaStorage($settings['media']),
            VirusScanner::class => fn () => new StubVirusScanner(), // swap for ClamAvScanner to enable scanning

            // Payments — mock Stripe scaffold (no real charges); real driver swaps in with keys.
            PaymentGateway::class => fn () => new MockStripeGateway($settings['stripe']),

            // Governance + onboarding (config-injected).
            ConsentService::class => fn () => new ConsentService($settings['governance']),
            AccountDeletionService::class => fn (ContainerInterface $c) => new AccountDeletionService(
                $c->get(ActivityLogger::class),
                $settings['governance'],
            ),
            OnboardingService::class => fn (ContainerInterface $c) => new OnboardingService(
                $c->get(TenantProvisioningService::class),
                $c->get(Mailer::class),
                $c->get(AuthService::class),
                $c->get(ConsentService::class),
                $c->get(ActivityLogger::class),
                (string) ($settings['jwt']['secret'] ?? ''),
                $settings['onboarding'],
            ),
            OnboardingController::class => fn (ContainerInterface $c) => new OnboardingController(
                $c->get(OnboardingService::class),
                $settings,
            ),
            AccountPurgeHandler::class => fn (ContainerInterface $c) => new AccountPurgeHandler(
                $c->get(MediaStorage::class),
                $settings['governance'],
                $c->get(LoggerInterface::class),
            ),
            RetentionPurgeHandler::class => fn (ContainerInterface $c) => new RetentionPurgeHandler(
                $settings['governance']['retention'],
                $c->get(LoggerInterface::class),
            ),

            // Meta integration (§16). Token encryption + a Graph/Marketing client.
            Encryptor::class => fn () => new Encryptor(
                Encryptor::resolveKey((string) ($settings['security']['encryption_key'] ?? ''), (string) ($settings['jwt']['secret'] ?? '')),
            ),
            // Reports (§15): branded renderer (HTML now; PDF is a drop-in swap).
            \App\Services\Reports\ReportRenderer::class => fn () => new \App\Services\Reports\HtmlReportRenderer(),
            \App\Services\Jobs\Handlers\ReportGenerateHandler::class => fn (ContainerInterface $c) => new \App\Services\Jobs\Handlers\ReportGenerateHandler(
                $c->get(\App\Services\Reports\ReportDataService::class),
                $c->get(\App\Services\Reports\ReportBrandingService::class),
                $c->get(\App\Services\Reports\ReportRenderer::class),
                $c->get(MediaStorage::class),
                $c->get(Mailer::class),
                $settings['reports'],
            ),

            // Campaign alerts need the campaigns config (client-digest flag).
            \App\Services\Campaigns\CampaignAlertService::class => fn (ContainerInterface $c) => new \App\Services\Campaigns\CampaignAlertService(
                $c->get(\App\Services\NotificationService::class),
                $c->get(Mailer::class),
                $settings['campaigns'],
            ),

            MetaClient::class => fn (ContainerInterface $c) => new MetaClient(
                new GuzzleClient(['base_uri' => ($settings['meta']['graph_base_url'] ?? 'https://graph.facebook.com') . '/']),
                $c->get(MetaTokenStore::class),
                $settings['meta'],
            ),
            MetaOAuthService::class => fn (ContainerInterface $c) => new MetaOAuthService(
                new GuzzleClient(['base_uri' => ($settings['meta']['graph_base_url'] ?? 'https://graph.facebook.com') . '/']),
                $c->get(MetaTokenStore::class),
                $c->get(MetaClient::class),
                $c->get(MetaConnectionRepository::class),
                $settings['meta'],
                (string) ($settings['jwt']['secret'] ?? ''),
            ),
            MediaService::class => fn (ContainerInterface $c) => new MediaService(
                $c->get(MediaStorage::class),
                $c->get(MediaRepository::class),
                $c->get(QueueService::class),
                $settings['media'],
            ),

            UploadController::class => fn () => new UploadController($settings),
            BillingController::class => fn (ContainerInterface $c) => new BillingController($settings, $c->get(WebhookIngest::class)),
        ];
    }

    private static function bootEloquent(array $db): void
    {
        $capsule = new Capsule();

        $capsule->addConnection([
            'driver'    => $db['driver'],
            'host'      => $db['host'],
            'port'      => $db['port'],
            'database'  => $db['database'],
            'username'  => $db['username'],
            'password'  => $db['password'],
            'charset'   => $db['charset'],
            'collation' => $db['collation'],
            'prefix'    => $db['prefix'],
            'strict'    => true,
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
