import api from './client';

/** Thin endpoint wrappers mirroring the SlimPHP routes (Phase 1 + 2). */
export const AuthApi = {
  login: (username, password) => api.post('/auth/login', { username, password }).then((r) => r.data),
  devLogin: (email) => api.post('/auth/dev-login', { email }).then((r) => r.data), // local only

  refresh: () => api.post('/auth/refresh').then((r) => r.data),
  logout: () => api.post('/auth/logout').then((r) => r.data),
  me: () => api.get('/auth/me').then((r) => r.data),
};

export const RequestsApi = {
  list: (params = {}) => api.get('/requests', { params }).then((r) => r.data.data),
  get: (id) => api.get(`/requests/${id}`).then((r) => r.data.data),
  create: (payload) => api.post('/requests', payload).then((r) => r.data.data),
  comments: (id) => api.get(`/requests/${id}/comments`).then((r) => r.data.data),
  addComment: (id, payload) => api.post(`/requests/${id}/comments`, payload).then((r) => r.data.data),
  approvals: (id) => api.get(`/requests/${id}/approvals`).then((r) => r.data.data),
  approve: (id, payload) => api.post(`/requests/${id}/approvals`, payload).then((r) => r.data.data),
  activity: (id) => api.get(`/requests/${id}/activity`).then((r) => r.data.data),
};

export const NotificationsApi = {
  list: () => api.get('/notifications').then((r) => r.data.data),
  markRead: (id) => api.patch(`/notifications/${id}/read`).then((r) => r.data.data),
  markAllRead: () => api.post('/notifications/read-all').then((r) => r.data),
};

export const BrandApi = {
  listKits: () => api.get('/brand-kits').then((r) => r.data.data),
  getKit: (id) => api.get(`/brand-kits/${id}`).then((r) => r.data.data),
  createKit: (p) => api.post('/brand-kits', p).then((r) => r.data.data),
  updateKit: (id, p) => api.patch(`/brand-kits/${id}`, p).then((r) => r.data.data),
  deleteKit: (id) => api.delete(`/brand-kits/${id}`).then((r) => r.data),
  allowance: (clientEmail) => api.get('/brand-kits/allowance', { params: clientEmail ? { client_email: clientEmail } : {} }).then((r) => r.data.data),
  assets: (id) => api.get(`/brand-kits/${id}/assets`).then((r) => r.data.data),
  addAsset: (id, p) => api.post(`/brand-kits/${id}/assets`, p).then((r) => r.data.data),
  deleteAsset: (assetId) => api.delete(`/brand-assets/${assetId}`).then((r) => r.data),
  reviewAsset: (assetId) => api.post(`/brand-assets/${assetId}/review`).then((r) => r.data.data),
};

export const AdminApi = {
  teamMembers: () => api.get('/team-members').then((r) => r.data.data),
  subscriptions: () => api.get('/subscriptions').then((r) => r.data.data),
  invoices: () => api.get('/invoices').then((r) => r.data.data),
};

export const SubscriptionsApi = {
  list: () => api.get('/subscriptions').then((r) => r.data.data),
  update: (id, p) => api.patch(`/subscriptions/${id}`, p).then((r) => r.data.data),
};

export const ClientMembersApi = {
  list: (clientEmail) => api.get('/client-members', { params: { client_email: clientEmail } }).then((r) => r.data.data),
  create: (p) => api.post('/client-members', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/client-members/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/client-members/${id}`).then((r) => r.data),
};

// ── Settings management (global admin) ────────────────────────
export const TeamApi = {
  list: () => api.get('/team-members').then((r) => r.data.data),
  create: (p) => api.post('/team-members', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/team-members/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/team-members/${id}`).then((r) => r.data),
};

export const DesignationsApi = {
  list: () => api.get('/designations').then((r) => r.data.data),
  create: (p) => api.post('/designations', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/designations/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/designations/${id}`).then((r) => r.data),
};

export const StaffRolesApi = {
  list: () => api.get('/staff-roles').then((r) => r.data.data),
  create: (p) => api.post('/staff-roles', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/staff-roles/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/staff-roles/${id}`).then((r) => r.data),
};

export const ClientRolesApi = {
  list: () => api.get('/client-roles').then((r) => r.data.data),
  create: (p) => api.post('/client-roles', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/client-roles/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/client-roles/${id}`).then((r) => r.data),
};

export const UploadApi = {
  image: (file) => {
    const fd = new FormData();
    fd.append('file', file);
    return api.post('/uploads', fd).then((r) => r.data.url);
  },
};

export const FormConfigApi = {
  get: (key) => api.get(`/form-configs/${key}`).then((r) => r.data.data),
  save: (key, items) => api.put(`/form-configs/${key}`, { items }).then((r) => r.data.data),
  reset: (key) => api.post(`/form-configs/${key}/reset`).then((r) => r.data.data),
};

export const PlansApi = {
  list: () => api.get('/service-plans').then((r) => r.data.data),
  create: (p) => api.post('/service-plans', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/service-plans/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/service-plans/${id}`).then((r) => r.data),
};

export const SlaTiersApi = {
  list: () => api.get('/sla-tiers').then((r) => r.data.data),
  create: (t) => api.post('/sla-tiers', t).then((r) => r.data.data),
  update: (id, t) => api.patch(`/sla-tiers/${id}`, t).then((r) => r.data.data),
  remove: (id) => api.delete(`/sla-tiers/${id}`).then((r) => r.data),
};

// ── Phase 3 (mock providers) ──────────────────────────────────
export const AssistantApi = {
  draftRequest: (context) => api.post('/assistant/draft-request', context).then((r) => r.data.data),
  recommendations: () => api.get('/assistant/recommendations').then((r) => r.data.data),
};

export const OutboxApi = {
  list: () => api.get('/outbox').then((r) => r.data.data),
};

export const BillingApi = {
  portalSession: () => api.post('/billing/portal-session').then((r) => r.data),
};

// ── System / Jobs (global-admin only) ─────────────────────────
export const SystemApi = {
  jobs: () => api.get('/system/jobs').then((r) => r.data.data),
  failedJobs: () => api.get('/system/failed-jobs').then((r) => r.data.data),
  replay: (id) => api.post(`/system/failed-jobs/${id}/replay`).then((r) => r.data.data),
};

// ── One-off projects + quotes (commercial gate) ───────────────
export const ProjectsApi = {
  list: () => api.get('/projects').then((r) => r.data.data),
  get: (id) => api.get(`/projects/${id}`).then((r) => r.data.data),
  create: (p) => api.post('/projects', p).then((r) => r.data.data),
  complete: (id) => api.post(`/projects/${id}/complete`).then((r) => r.data.data),
};

export const QuotesApi = {
  listForProject: (projectId) => api.get(`/projects/${projectId}/quotes`).then((r) => r.data.data),
  create: (projectId, p) => api.post(`/projects/${projectId}/quotes`, p).then((r) => r.data.data),
  send: (id) => api.post(`/quotes/${id}/send`).then((r) => r.data.data),
  accept: (id, body) => api.post(`/quotes/${id}/accept`, body).then((r) => r.data.data),
  decline: (id) => api.post(`/quotes/${id}/decline`).then((r) => r.data.data),
  depositCheckout: (id) => api.post(`/quotes/${id}/deposit-checkout`).then((r) => r.data.data),
};

// ── Client-facing reports (§15) ───────────────────────────────
export const ReportsApi = {
  list: () => api.get('/reports').then((r) => r.data.data),
  request: (p) => api.post('/reports', p).then((r) => r.data.data),
};

// ── Ad campaigns (§14) ────────────────────────────────────────
export const CampaignsApi = {
  list: () => api.get('/campaigns').then((r) => r.data.data),
  create: (p) => api.post('/campaigns', p).then((r) => r.data.data),
  dashboard: (id) => api.get(`/campaigns/${id}/dashboard`).then((r) => r.data.data),
};

// ── Analytics: content performance (§12) ──────────────────────
export const AnalyticsApi = {
  contentPerformance: () => api.get('/analytics/content-performance').then((r) => r.data.data),
};

// ── Meta integration (§16) ────────────────────────────────────
export const MetaApi = {
  health: () => api.get('/meta/health').then((r) => r.data.data),
  connect: () => api.post('/meta/connect').then((r) => r.data.data),
  callback: (code, state) => api.post('/meta/callback', { code, state }).then((r) => r.data.data),
};

// ── Media (§17.2) — tenant-scoped signed URLs ─────────────────
export const MediaApi = {
  signedUrl: (id) => api.get(`/media/${id}`).then((r) => r.data.data),
};

// ── Onboarding + governance (§17.3 / §17.4) ───────────────────
export const OnboardingApi = {
  accept: (token, password) => api.post('/onboarding/accept', { token, password }).then((r) => r.data),
  invite: (p) => api.post('/onboarding/invite', p).then((r) => r.data.data),
  preferences: () => api.get('/onboarding/preferences').then((r) => r.data.data),
  savePreferences: (p) => api.put('/onboarding/preferences', p).then((r) => r.data.data),
  complete: () => api.post('/onboarding/complete').then((r) => r.data),
};

export const GovernanceApi = {
  listExports: () => api.get('/data-exports').then((r) => r.data.data),
  requestExport: () => api.post('/data-exports').then((r) => r.data.data),
  requestDeletion: (clientEmail) => api.post('/account-deletions', { client_email: clientEmail }).then((r) => r.data.data),
};

// ── Tasks + dual-approval flow (§11 Layer B) ──────────────────
export const TasksApi = {
  list: () => api.get('/tasks').then((r) => r.data.data),
  get: (id) => api.get(`/tasks/${id}`).then((r) => r.data.data),
  create: (p) => api.post('/tasks', p).then((r) => r.data.data),
  submit: (id) => api.post(`/tasks/${id}/submit`).then((r) => r.data.data),
  internalDecision: (id, body) => api.post(`/tasks/${id}/internal-decision`, body).then((r) => r.data.data),
  clientDecision: (id, body) => api.post(`/tasks/${id}/client-decision`, body).then((r) => r.data.data),
  start: (id) => api.post(`/tasks/${id}/start`).then((r) => r.data.data),
  complete: (id) => api.post(`/tasks/${id}/complete`).then((r) => r.data.data),
  cancel: (id) => api.post(`/tasks/${id}/cancel`).then((r) => r.data.data),
  history: (id) => api.get(`/tasks/${id}/approvals`).then((r) => r.data.data),
};

// ── Social planning & publishing ──────────────────────────────
export const PublishingApi = {
  posts: (params = {}) => api.get('/posts', { params }).then((r) => r.data.data),
  post: (id) => api.get(`/posts/${id}`).then((r) => r.data.data),
  createPost: (p) => api.post('/posts', p).then((r) => r.data.data),
  updatePost: (id, p) => api.patch(`/posts/${id}`, p).then((r) => r.data.data),
  submitPost: (id) => api.post(`/posts/${id}/submit`).then((r) => r.data.data),
  decidePost: (id, body) => api.post(`/posts/${id}/decision`, body).then((r) => r.data.data),
  schedulePost: (id, scheduled_at) => api.post(`/posts/${id}/schedule`, { scheduled_at }).then((r) => r.data.data),
  publishPost: (id) => api.post(`/posts/${id}/publish`).then((r) => r.data.data),
  cancelPost: (id) => api.post(`/posts/${id}/cancel`).then((r) => r.data.data),
  bestTimes: (platform, timezone) => api.get('/posts/best-times', { params: { platform, timezone } }).then((r) => r.data.data),
};

export const ChannelsApi = {
  list: () => api.get('/channels').then((r) => r.data.data),
  create: (p) => api.post('/channels', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/channels/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/channels/${id}`).then((r) => r.data),
};

export const ProductsApi = {
  list: () => api.get('/products').then((r) => r.data.data),
  create: (p) => api.post('/products', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/products/${id}`, p).then((r) => r.data.data),
};

export const TagRulesApi = {
  list: () => api.get('/tag-rules').then((r) => r.data.data),
  create: (p) => api.post('/tag-rules', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/tag-rules/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/tag-rules/${id}`).then((r) => r.data),
};

export const RssApi = {
  list: () => api.get('/rss-sources').then((r) => r.data.data),
  create: (p) => api.post('/rss-sources', p).then((r) => r.data.data),
  update: (id, p) => api.patch(`/rss-sources/${id}`, p).then((r) => r.data.data),
  remove: (id) => api.delete(`/rss-sources/${id}`).then((r) => r.data),
  ingest: (id) => api.post(`/rss-sources/${id}/ingest`).then((r) => r.data),
};
