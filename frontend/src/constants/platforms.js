// Visual metadata for social platforms (used across the publishing UI).
export const PLATFORMS = {
  instagram: { label: 'Instagram', color: '#E1306C', letter: 'IG' },
  facebook: { label: 'Facebook', color: '#1877F2', letter: 'FB' },
  linkedin: { label: 'LinkedIn', color: '#0A66C2', letter: 'IN' },
  twitter: { label: 'X / Twitter', color: '#111111', letter: 'X' },
  tiktok: { label: 'TikTok', color: '#000000', letter: 'TT' },
  youtube: { label: 'YouTube', color: '#FF0000', letter: 'YT' },
  pinterest: { label: 'Pinterest', color: '#E60023', letter: 'PT' },
};

export const PLATFORM_KEYS = Object.keys(PLATFORMS);

export const platformMeta = (key) =>
  PLATFORMS[key] || { label: key, color: '#888', letter: (key || '?').slice(0, 2).toUpperCase() };

export const POST_STATUS = {
  draft: { label: 'Draft', color: '#6b7480' },
  pending_approval: { label: 'Pending approval', color: '#d9a441' },
  approved: { label: 'Approved', color: '#2e5496' },
  scheduled: { label: 'Scheduled', color: '#2e7d32' },
  publishing: { label: 'Publishing…', color: '#2e5496' },
  published: { label: 'Published', color: '#1b5e20' },
  failed: { label: 'Failed', color: '#c0392b' },
  cancelled: { label: 'Cancelled', color: '#6b7480' },
};
export const statusMeta = (s) => POST_STATUS[s] || { label: s, color: '#6b7480' };
