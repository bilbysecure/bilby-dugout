<?php

declare(strict_types=1);

/**
 * Central configuration, populated from environment variables.
 * Loaded by the DI container (see src/Application/Bootstrap.php).
 */
return [
    'app' => [
        'env'   => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        'url'   => $_ENV['APP_URL'] ?? 'http://localhost:8080',
        'cors_origins' => array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? ''))),
    ],

    'db' => [
        // MySQL / MariaDB is the only supported runtime driver.
        'driver'    => 'mysql',
        'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port'      => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database'  => $_ENV['DB_DATABASE'] ?? 'bilbydugout',
        'username'  => $_ENV['DB_USERNAME'] ?? 'root',
        'password'  => $_ENV['DB_PASSWORD'] ?? '',
        'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ],

    'jwt' => [
        'secret'      => $_ENV['JWT_SECRET'] ?? '',
        'issuer'      => $_ENV['JWT_ISSUER'] ?? 'bilbydugout',
        'access_ttl'  => (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900),
        'refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TTL'] ?? 2592000),
        'algo'        => 'HS256',
    ],

    'azure' => [
        'tenant_id'     => $_ENV['AZURE_TENANT_ID'] ?? '',
        'client_id'     => $_ENV['AZURE_CLIENT_ID'] ?? '',
        'client_secret' => $_ENV['AZURE_CLIENT_SECRET'] ?? '',
        'auth_flow'     => $_ENV['AUTH_FLOW'] ?? 'ropc',
    ],

    'graph' => [
        'service_mailbox' => $_ENV['GRAPH_SERVICE_MAILBOX'] ?? '',
    ],

    'stripe' => [
        'secret_key'     => $_ENV['STRIPE_SECRET_KEY'] ?? '',
        'webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
        // Checkout redirect targets (deposit flow).
        'success_url'    => $_ENV['STRIPE_SUCCESS_URL'] ?? (rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/') . '/projects?deposit=success'),
        'cancel_url'     => $_ENV['STRIPE_CANCEL_URL'] ?? (rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/') . '/projects?deposit=cancel'),
        // Stripe Tax (GST). When enabled, Checkout collects automatic tax.
        'tax' => [
            'enabled'       => filter_var($_ENV['STRIPE_TAX_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
            'behavior'      => $_ENV['STRIPE_TAX_BEHAVIOR'] ?? 'exclusive', // prices are GST-exclusive
            'gst_rate_pct'  => (float) ($_ENV['STRIPE_GST_RATE_PCT'] ?? 10.0), // AU GST 10%
            'currency'      => $_ENV['STRIPE_CURRENCY'] ?? 'aud',
        ],
    ],

    'llm' => [
        'provider' => 'anthropic',
        'api_key'  => $_ENV['ANTHROPIC_API_KEY'] ?? '',
        'model'    => $_ENV['LLM_MODEL'] ?? 'claude-sonnet-4-6',
    ],

    'media' => [
        // Driver selection — swap to 's3' later without touching services/controllers.
        'disk'       => $_ENV['MEDIA_DISK'] ?? 'local',
        // Local driver: files live OUTSIDE the web root (never statically served).
        'root'       => $_ENV['MEDIA_ROOT'] ?? (dirname(__DIR__) . '/storage/media'),
        // HMAC secret for local signed URLs (falls back to the app JWT secret).
        'url_secret' => $_ENV['MEDIA_URL_SECRET'] ?? ($_ENV['JWT_SECRET'] ?? ''),
        'base_url'   => rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/'),
        'signed_ttl' => (int) ($_ENV['MEDIA_SIGNED_TTL'] ?? 300),
        // S3 driver config (used when disk = s3; see S3MediaStorage).
        's3' => [
            'bucket'         => $_ENV['MEDIA_S3_BUCKET'] ?? '',
            'region'         => $_ENV['MEDIA_S3_REGION'] ?? '',
            'cloudfront_url' => $_ENV['MEDIA_CLOUDFRONT_URL'] ?? '',
        ],
        // Upload allowlist: ext => accepted MIME types.
        'allowed' => [
            'png'  => ['image/png'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
            'mp4'  => ['video/mp4'],
            'mov'  => ['video/quicktime'],
        ],
        // Per-group byte ceilings.
        'max_bytes' => [
            'image'    => 10 * 1024 * 1024,
            'document' => 25 * 1024 * 1024,
            'video'    => 200 * 1024 * 1024,
            'default'  => 10 * 1024 * 1024,
        ],
        'image_exts' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
    ],

    'security' => [
        // 32-byte key for encrypting stored secrets (Meta tokens). base64 of 32 raw bytes.
        // If unset, Bootstrap derives one from JWT_SECRET (set a dedicated key in prod).
        'encryption_key' => $_ENV['APP_ENCRYPTION_KEY'] ?? '',
    ],

    'meta' => [
        // Provided by the operator; leave empty and the connect flow surfaces "not configured".
        'app_id'         => $_ENV['META_APP_ID'] ?? '',
        'app_secret'     => $_ENV['META_APP_SECRET'] ?? '',
        'api_version'    => $_ENV['META_API_VERSION'] ?? 'v21.0',
        'graph_base_url' => rtrim($_ENV['META_GRAPH_BASE_URL'] ?? 'https://graph.facebook.com', '/'),
        'redirect_uri'   => $_ENV['META_REDIRECT_URI'] ?? (rtrim($_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5173', '/') . '/meta/callback'),
        // Report-only for now. ads_management + audience creation are FUTURE upgrades.
        'scopes'         => ['ads_read'],
    ],

    'reports' => [
        // Optionally email the client when a report is ready.
        'email_on_ready' => filter_var($_ENV['REPORT_EMAIL_ON_READY'] ?? false, FILTER_VALIDATE_BOOL),
    ],

    'campaigns' => [
        // Optional client digest email when an alert fires (staff are always notified).
        'client_digest' => filter_var($_ENV['CAMPAIGN_CLIENT_DIGEST'] ?? false, FILTER_VALIDATE_BOOL),
    ],

    'onboarding' => [
        // Where the SPA hosts the set-password page (the invite link points here).
        'frontend_url'      => rtrim($_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5173', '/'),
        'invite_ttl'        => (int) ($_ENV['INVITE_TTL'] ?? 259200), // 3 days
    ],

    'governance' => [
        // Accepted-policy versions captured as consent at signup + quote acceptance.
        'privacy_version'   => $_ENV['PRIVACY_POLICY_VERSION'] ?? '2026-01',
        'terms_version'     => $_ENV['TERMS_VERSION'] ?? '2026-01',
        // Account deletion: soft-delete grace before hard purge.
        'deletion_grace_days' => (int) ($_ENV['DELETION_GRACE_DAYS'] ?? 30),
        // HARD PURGE is destructive — disabled until explicitly enabled.
        'purge_enabled'     => filter_var($_ENV['PURGE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
        // Retention windows in days; 0/null disables the purge (nothing deleted by default).
        'retention' => [
            'activity_logs_days'  => (int) ($_ENV['RETAIN_ACTIVITY_LOGS_DAYS'] ?? 0),
            'notifications_days'   => (int) ($_ENV['RETAIN_NOTIFICATIONS_DAYS'] ?? 0),
            'refresh_tokens_days'  => (int) ($_ENV['RETAIN_REFRESH_TOKENS_DAYS'] ?? 0),
        ],
    ],
];
