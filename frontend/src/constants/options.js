// Mirrors the backend enums (Request entity / Domain\Enums). In Phase 3 these
// can be sourced from the FormConfig endpoint instead of hardcoding.

export const SERVICE_TYPES = [
  { value: 'social_media_post', label: 'Social media post' },
  { value: 'graphic_design', label: 'Graphic design' },
  { value: 'video_production', label: 'Video production' },
  { value: 'brand_asset', label: 'Brand asset' },
  { value: 'marketing_campaign', label: 'Marketing campaign' },
  { value: 'content_writing', label: 'Content writing' },
  { value: 'website_update', label: 'Website update' },
  { value: 'ads_campaign', label: 'Ads campaign' },
  { value: 'seo_content', label: 'SEO content' },
  { value: 'print_design', label: 'Print design' },
  { value: 'other', label: 'Other' },
];

export const PLATFORMS = [
  'instagram', 'facebook', 'linkedin', 'twitter', 'tiktok', 'youtube', 'website', 'email',
];

export const OBJECTIVES = [
  { value: 'brand_awareness', label: 'Brand awareness' },
  { value: 'lead_generation', label: 'Lead generation' },
  { value: 'sales_conversion', label: 'Sales conversion' },
  { value: 'engagement', label: 'Engagement' },
  { value: 'traffic', label: 'Traffic' },
  { value: 'retention', label: 'Retention' },
  { value: 'event_promotion', label: 'Event promotion' },
  { value: 'other', label: 'Other' },
];

export const TONES = [
  'professional', 'friendly', 'bold', 'playful', 'luxury', 'educational', 'urgent', 'other',
];

export const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
