// Crystal theme · light, BilbyPixel purple-led, soft supporting accents.
// Purple leads; accents are muted and used only for categorical distinction.
export const PALETTE = ['#6C4AB6', '#5FB6C4', '#7C93E8', '#E3B96B', '#79C6A2', '#E091B4', '#8A6FD1', '#9AB0EA', '#C9B8EE', '#B9A7E6'];

export const STATUS_COLORS = {
  submitted: '#6C4AB6', in_progress: '#7C93E8', review: '#5FB6C4', revision: '#E3B96B',
  approved: '#79C6A2', scheduled: '#8A6FD1', published: '#4B2A85', completed: '#4E9E78',
  cancelled: '#B4ADC6', pending_owner_approval: '#C9B8EE',
};

export const PRIORITY_COLORS = { low: '#79C6A2', normal: '#7C93E8', high: '#8A6FD1', urgent: '#E091B4' };

export const humanize = (s) => String(s || '').replaceAll('_', ' ');
