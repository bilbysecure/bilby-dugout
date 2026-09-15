// Shared display metadata for request cards (type icon, status/priority pills).
const TYPE_META = {
  social_media_post: { icon: '📱', tint: '#fdeef4' }, graphic_design: { icon: '🎨', tint: '#efeafe' },
  video_production: { icon: '🎬', tint: '#fdeaea' }, brand_asset: { icon: '🏷️', tint: '#e6f6f4' },
  marketing_campaign: { icon: '📣', tint: '#fff1e0' }, content_writing: { icon: '✍️', tint: '#eaf1fb' },
  website_update: { icon: '🌐', tint: '#e6f5fb' }, ads_campaign: { icon: '📊', tint: '#e9f6ea' },
  seo_content: { icon: '🔍', tint: '#eeeefc' }, print_design: { icon: '🖨️', tint: '#f2eee9' }, other: { icon: '📋', tint: '#eef0f3' },
};
export const typeMeta = (t) => TYPE_META[t] || TYPE_META.other;

const STATUS_META = {
  pending_owner_approval: ['Pending approval', '#a15c00', '#fff4e0'], submitted: ['Submitted', '#2e5496', '#eaf1fb'],
  in_progress: ['In Progress', '#a15c00', '#fff4e0'], review: ['Client Review', '#7b3fb5', '#f4eafb'],
  revision: ['Revision', '#a15c00', '#fff4e0'], approved: ['Approved', '#0b7a68', '#e6f6f2'],
  scheduled: ['Scheduled', '#1b7a2e', '#e7f6ea'], published: ['Published', '#155d24', '#e7f6ea'],
  completed: ['Completed', '#3a6a44', '#eaf4ec'], cancelled: ['Cancelled', '#6b7480', '#eef0f3'],
};
export const statusMeta = (s) => STATUS_META[s] || [s, '#6b7480', '#eef0f3'];

const PRIORITY_META = { urgent: ['Urgent', '#c0392b', '#fdece9'], high: ['High', '#a15c00', '#fff4e0'], normal: ['Normal', '#2e5496', '#eaf1fb'], low: ['Low', '#6b7480', '#eef0f3'] };
export const priorityMeta = (p) => PRIORITY_META[p] || PRIORITY_META.normal;

export const fmtDate = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '');
