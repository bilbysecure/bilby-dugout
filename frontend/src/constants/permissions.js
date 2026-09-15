// Permission catalog for building reusable roles, grouped by BilbyDugout feature.
// Staff (BilbyPixel) and client roles draw from tailored subsets.

export const STAFF_PERMISSIONS = [
  { group: 'Requests', items: [
    { key: 'requests.view', label: 'View requests' },
    { key: 'requests.create', label: 'Create requests' },
    { key: 'requests.assign', label: 'Assign team members' },
    { key: 'requests.update_status', label: 'Update status / workflow' },
    { key: 'requests.comment_internal', label: 'Post internal notes' },
  ] },
  { group: 'Publishing', items: [
    { key: 'posts.view', label: 'View calendar' },
    { key: 'posts.create', label: 'Create / edit posts' },
    { key: 'posts.approve', label: 'Approve posts' },
    { key: 'posts.publish', label: 'Publish / schedule' },
    { key: 'publishing.settings', label: 'Manage channels, RSS, tag rules' },
  ] },
  { group: 'Clients', items: [
    { key: 'clients.view', label: 'View all clients list' },
    { key: 'clients.manage', label: 'Manage clients' },
    { key: 'clients.members.view', label: 'View client members' },
    { key: 'clients.members.manage', label: 'Manage client members' },
  ] },
  { group: 'Brand Hub', items: [
    { key: 'brand.view', label: 'View brand hub' },
    { key: 'brand.manage', label: 'Manage brand hub' },
  ] },
  { group: 'Team Ops', items: [
    { key: 'teamops.view', label: 'View team ops' },
    { key: 'teamops.manage', label: 'Manage team ops' },
    { key: 'team.manage', label: 'Manage team members (BilbyPixel staff)' },
  ] },
  { group: 'Business', items: [
    { key: 'analytics.view', label: 'View analytics' },
    { key: 'billing.manage', label: 'Manage subscriptions & invoices' },
    { key: 'settings.manage', label: 'Manage roles & settings' },
  ] },
];

export const CLIENT_PERMISSIONS = [
  { group: 'Requests', items: [
    { key: 'requests.view', label: 'View requests' },
    { key: 'requests.create', label: 'Create requests' },
    { key: 'requests.approve', label: 'Approve deliverables' },
    { key: 'requests.review', label: 'Leave reviews' },
  ] },
  { group: 'Publishing', items: [
    { key: 'posts.view', label: 'View calendar' },
    { key: 'posts.create', label: 'Draft posts' },
    { key: 'posts.approve', label: 'Approve posts' },
  ] },
  { group: 'Account', items: [
    { key: 'brand.view', label: 'View brand hub' },
    { key: 'billing.view', label: 'View billing & invoices' },
    { key: 'members.manage', label: 'Manage company members' },
  ] },
];
