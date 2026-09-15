<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ClientMemberController;
use App\Http\Controllers\ClientRoleController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\FormConfigController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OutboxController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\PublishingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\RssController;
use App\Http\Controllers\SlaTierController;
use App\Http\Controllers\StaffRoleController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TagRuleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimeController;
use App\Http\Controllers\UploadController;
use App\Http\Middleware\JwtAuthMiddleware;

/**
 * Route table. Mirrors §7 of the architecture doc.
 * Phase 0/1 implements auth + requests; other groups are stubbed for later phases.
 */
return function (App $app): void {

    $app->get('/health', fn (Request $r, Response $res) => self_json($res, ['ok' => true]));

    $app->group('/api/v1', function (RouteCollectorProxy $g) {

        // ── Auth (public except /me) ──────────────────────────────────
        $g->post('/auth/login',        [AuthController::class, 'login']);
        $g->post('/auth/client-login', [AuthController::class, 'clientLogin']); // local auth (clients)
        $g->post('/auth/dev-login',    [AuthController::class, 'devLogin']); // APP_ENV=local only
        $g->post('/auth/refresh',      [AuthController::class, 'refresh']);

        // Invite acceptance (PUBLIC — authorized by the signed set-password token)
        $g->post('/onboarding/accept', [OnboardingController::class, 'accept']);

        // Stripe webhook (public; verified by signature in the real handler)
        $g->post('/webhooks/stripe', [BillingController::class, 'webhook']);

        // Media blob stream (public; authorized by the short-lived signed token, not JWT)
        $g->get('/media/blob', [MediaController::class, 'blob']);
        $g->post('/auth/logout',  [AuthController::class, 'logout']);
        $g->get('/auth/me',       [AuthController::class, 'me'])
            ->add(JwtAuthMiddleware::class);

        // ── Authenticated + scoped ────────────────────────────────────
        $g->group('', function (RouteCollectorProxy $a) {

            // Requests
            $a->get('/requests',      [RequestController::class, 'index']);
            $a->get('/requests/{id}', [RequestController::class, 'show']);
            $a->post('/requests',     [RequestController::class, 'store']);

            // Request sub-resources (Phase 2)
            $a->get('/requests/{id}/comments',  [CommentController::class, 'index']);
            $a->post('/requests/{id}/comments', [CommentController::class, 'store']);

            $a->get('/requests/{id}/approvals',  [ApprovalController::class, 'index']);
            $a->post('/requests/{id}/approvals', [ApprovalController::class, 'store']);

            $a->get('/requests/{id}/revisions',  [RevisionController::class, 'index']);
            $a->post('/requests/{id}/revisions', [RevisionController::class, 'store']);
            $a->patch('/revisions/{revisionId}', [RevisionController::class, 'updateStatus']);

            $a->get('/requests/{id}/reviews',  [ReviewController::class, 'index']);
            $a->post('/requests/{id}/reviews', [ReviewController::class, 'store']);

            $a->get('/requests/{id}/activity', [ActivityController::class, 'index']);

            $a->get('/requests/{id}/time',  [TimeController::class, 'index']);
            $a->post('/requests/{id}/time', [TimeController::class, 'store']);

            // Notifications (recipient-scoped)
            $a->get('/notifications',             [NotificationController::class, 'index']);
            $a->post('/notifications/read-all',   [NotificationController::class, 'markAllRead']);
            $a->patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);

            // Admin / account screens (read; scoped — agency=all, client=own)
            $a->get('/subscriptions',        [SubscriptionController::class, 'index']);
            $a->patch('/subscriptions/{id}', [SubscriptionController::class, 'update']); // edit client
            $a->get('/invoices',             [InvoiceController::class, 'index']);

            // Client team members (people who submit on a client's behalf)
            $a->get('/client-members',         [ClientMemberController::class, 'index']); // ?client_email=
            $a->post('/client-members',        [ClientMemberController::class, 'store']);
            $a->patch('/client-members/{id}',  [ClientMemberController::class, 'update']);
            $a->delete('/client-members/{id}', [ClientMemberController::class, 'destroy']);

            // ── Settings: team members, designations, roles (global-admin writes) ──
            $a->get('/team-members',         [TeamController::class, 'index']);
            $a->post('/team-members',        [TeamController::class, 'store']);
            $a->patch('/team-members/{id}',  [TeamController::class, 'update']);
            $a->delete('/team-members/{id}', [TeamController::class, 'destroy']);

            $a->get('/designations',         [DesignationController::class, 'index']);
            $a->post('/designations',        [DesignationController::class, 'store']);
            $a->patch('/designations/{id}',  [DesignationController::class, 'update']);
            $a->delete('/designations/{id}', [DesignationController::class, 'destroy']);

            $a->get('/staff-roles',          [StaffRoleController::class, 'index']);
            $a->post('/staff-roles',         [StaffRoleController::class, 'store']);
            $a->patch('/staff-roles/{id}',   [StaffRoleController::class, 'update']);
            $a->delete('/staff-roles/{id}',  [StaffRoleController::class, 'destroy']);

            $a->get('/client-roles',         [ClientRoleController::class, 'index']);
            $a->post('/client-roles',        [ClientRoleController::class, 'store']);
            $a->patch('/client-roles/{id}',  [ClientRoleController::class, 'update']);
            $a->delete('/client-roles/{id}', [ClientRoleController::class, 'destroy']);

            $a->post('/uploads',             [UploadController::class, 'store']);

            // Media (upload → quarantine → scan; tenant-scoped signed URLs)
            $a->post('/media',        [MediaController::class, 'store']);
            $a->get('/media/{id}',    [MediaController::class, 'show']);

            // Service plans (bundles/packages)
            $a->get('/service-plans',        [PlanController::class, 'index']);
            $a->post('/service-plans',       [PlanController::class, 'store']);
            $a->patch('/service-plans/{id}', [PlanController::class, 'update']);
            $a->delete('/service-plans/{id}',[PlanController::class, 'destroy']);

            // Client-facing reports (§15)
            $a->get('/reports',      [ReportController::class, 'index']);
            $a->post('/reports',     [ReportController::class, 'store']);
            $a->get('/reports/{id}', [ReportController::class, 'show']);

            // Content-performance analytics (§12, from post_metrics)
            $a->get('/analytics/content-performance', [AnalyticsController::class, 'contentPerformance']);

            // Meta integration: health + OAuth connect scaffolding (§16)
            $a->get('/meta/health',    [MetaController::class, 'health']);
            $a->post('/meta/connect',  [MetaController::class, 'connect']);
            $a->post('/meta/callback', [MetaController::class, 'callback']);

            // Onboarding (invite-only) + preferences (§17.3)
            $a->post('/onboarding/invite',       [OnboardingController::class, 'invite']);
            $a->get('/onboarding/preferences',   [OnboardingController::class, 'preferences']);
            $a->put('/onboarding/preferences',   [OnboardingController::class, 'savePreferences']);
            $a->post('/onboarding/complete',     [OnboardingController::class, 'complete']);

            // Governance: data export + account deletion (§17.4)
            $a->get('/data-exports',                    [GovernanceController::class, 'listExports']);
            $a->post('/data-exports',                   [GovernanceController::class, 'requestExport']);
            $a->get('/account-deletions',               [GovernanceController::class, 'listDeletions']);
            $a->post('/account-deletions',              [GovernanceController::class, 'requestDeletion']);
            $a->post('/account-deletions/{id}/cancel',  [GovernanceController::class, 'cancelDeletion']);

            // Tasks + dual-approval flow (§11 Layer B)
            $a->get('/tasks',                       [TaskController::class, 'index']);
            $a->post('/tasks',                      [TaskController::class, 'store']);
            $a->get('/tasks/{id}',                  [TaskController::class, 'show']);
            $a->get('/tasks/{id}/approvals',        [TaskController::class, 'approvals']);
            $a->post('/tasks/{id}/submit',          [TaskController::class, 'submit']);
            $a->post('/tasks/{id}/internal-decision', [TaskController::class, 'internalDecision']);
            $a->post('/tasks/{id}/client-decision',   [TaskController::class, 'clientDecision']);
            $a->post('/tasks/{id}/start',           [TaskController::class, 'start']);
            $a->post('/tasks/{id}/complete',        [TaskController::class, 'complete']);
            $a->post('/tasks/{id}/cancel',          [TaskController::class, 'cancel']);

            // Ad Campaign module (§14) — managers configure; clients view dashboard
            $a->get('/campaigns',                [CampaignController::class, 'index']);
            $a->post('/campaigns',               [CampaignController::class, 'store']);
            $a->get('/campaigns/{id}/dashboard', [CampaignController::class, 'dashboard']);

            // One-off projects + quotes (commercial gate)
            $a->get('/projects',                  [ProjectController::class, 'index']);
            $a->post('/projects',                 [ProjectController::class, 'store']);
            $a->get('/projects/{id}',             [ProjectController::class, 'show']);
            $a->post('/projects/{id}/complete',   [ProjectController::class, 'complete']);
            $a->get('/projects/{id}/quotes',      [QuoteController::class, 'index']);
            $a->post('/projects/{id}/quotes',     [QuoteController::class, 'store']);
            $a->post('/quotes/{id}/send',            [QuoteController::class, 'send']);
            $a->post('/quotes/{id}/accept',          [QuoteController::class, 'accept']);
            $a->post('/quotes/{id}/decline',         [QuoteController::class, 'decline']);
            $a->post('/quotes/{id}/deposit-checkout', [QuoteController::class, 'depositCheckout']);

            // SLA tiers (delivery windows referenced by plans)
            $a->get('/sla-tiers',         [SlaTierController::class, 'index']);
            $a->post('/sla-tiers',        [SlaTierController::class, 'store']);
            $a->patch('/sla-tiers/{id}',  [SlaTierController::class, 'update']);
            $a->delete('/sla-tiers/{id}', [SlaTierController::class, 'destroy']);

            // Form config (service types, tones of voice)
            $a->get('/form-configs/{key}',        [FormConfigController::class, 'show']);
            $a->put('/form-configs/{key}',        [FormConfigController::class, 'update']);
            $a->post('/form-configs/{key}/reset', [FormConfigController::class, 'reset']);

            // ── Phase 3: AI assistant, billing, outbox ────────────────
            $a->post('/assistant/draft-request', [AssistantController::class, 'draftRequest']);
            $a->get('/assistant/recommendations', [AssistantController::class, 'recommendations']);
            $a->post('/billing/portal-session',  [BillingController::class, 'portalSession']);
            $a->get('/outbox',                   [OutboxController::class, 'index']);

            // System / Jobs (global-admin only; enforced in SystemService)
            $a->get('/system/jobs',                       [SystemController::class, 'jobs']);
            $a->get('/system/failed-jobs',                [SystemController::class, 'failedJobs']);
            $a->post('/system/failed-jobs/{id}/replay',   [SystemController::class, 'replay']);

            // ── Social planning & publishing ─────────────────────────
            // Channels (connected social accounts)
            $a->get('/channels',        [ChannelController::class, 'index']);
            $a->post('/channels',       [ChannelController::class, 'store']);
            $a->patch('/channels/{id}', [ChannelController::class, 'update']);
            $a->delete('/channels/{id}',[ChannelController::class, 'destroy']);

            // Products (Instagram tagging catalog)
            $a->get('/products',        [ProductController::class, 'index']);
            $a->post('/products',       [ProductController::class, 'store']);
            $a->patch('/products/{id}', [ProductController::class, 'update']);

            // Content-tag automation rules
            $a->get('/tag-rules',        [TagRuleController::class, 'index']);
            $a->post('/tag-rules',       [TagRuleController::class, 'store']);
            $a->patch('/tag-rules/{id}', [TagRuleController::class, 'update']);
            $a->delete('/tag-rules/{id}',[TagRuleController::class, 'destroy']);

            // RSS sources (automated publishing)
            $a->get('/rss-sources',            [RssController::class, 'index']);
            $a->post('/rss-sources',           [RssController::class, 'store']);
            $a->patch('/rss-sources/{id}',     [RssController::class, 'update']);
            $a->delete('/rss-sources/{id}',    [RssController::class, 'destroy']);
            $a->post('/rss-sources/{id}/ingest', [RssController::class, 'ingest']);

            // Scheduled posts (static route before {id})
            $a->get('/posts',              [PublishingController::class, 'index']);
            $a->post('/posts',             [PublishingController::class, 'store']);
            $a->get('/posts/best-times',   [PublishingController::class, 'bestTimes']);
            $a->get('/posts/{id}',         [PublishingController::class, 'show']);
            $a->patch('/posts/{id}',       [PublishingController::class, 'update']);
            $a->post('/posts/{id}/submit', [PublishingController::class, 'submit']);
            $a->post('/posts/{id}/decision',[PublishingController::class, 'decide']);
            $a->post('/posts/{id}/schedule',[PublishingController::class, 'schedule']);
            $a->post('/posts/{id}/publish', [PublishingController::class, 'publish']);
            $a->post('/posts/{id}/cancel',  [PublishingController::class, 'cancel']);

            // Brand kits + assets
            $a->get('/brand-kits/allowance',  [BrandController::class, 'allowance']);
            $a->get('/brand-kits',            [BrandController::class, 'indexKits']);
            $a->post('/brand-kits',           [BrandController::class, 'storeKit']);
            $a->get('/brand-kits/{id}',       [BrandController::class, 'showKit']);
            $a->patch('/brand-kits/{id}',     [BrandController::class, 'updateKit']);
            $a->delete('/brand-kits/{id}',    [BrandController::class, 'destroyKit']);
            $a->get('/brand-kits/{id}/assets',  [BrandController::class, 'indexAssets']);
            $a->post('/brand-kits/{id}/assets', [BrandController::class, 'storeAsset']);
            $a->delete('/brand-assets/{assetId}',        [BrandController::class, 'destroyAsset']);
            $a->post('/brand-assets/{assetId}/review',   [BrandController::class, 'reviewAsset']); // AI review

        })->add(JwtAuthMiddleware::class);

        // Phase 3 groups (subscriptions, invoices, billing, teams, assistant,
        // webhooks) are added as those phases land.
    });
};

/** Minimal JSON helper used only by the health route. */
function self_json(Response $response, array $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}
