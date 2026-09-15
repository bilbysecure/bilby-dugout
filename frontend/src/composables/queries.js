import { useQuery } from '@tanstack/vue-query';
import {
  RequestsApi, NotificationsApi, BrandApi, AdminApi,
  PublishingApi, ChannelsApi, ProductsApi, TagRulesApi, RssApi,
} from '@/api/endpoints';

export const useRequestsQuery = () =>
  useQuery({ queryKey: ['requests'], queryFn: () => RequestsApi.list() });

export const useRequestQuery = (id) =>
  useQuery({ queryKey: ['request', id], queryFn: () => RequestsApi.get(id) });

export const useNotificationsQuery = () =>
  useQuery({ queryKey: ['notifications'], queryFn: () => NotificationsApi.list() });

export const useBrandKitsQuery = () =>
  useQuery({ queryKey: ['brand-kits'], queryFn: () => BrandApi.listKits() });

export const useTeamQuery = () =>
  useQuery({ queryKey: ['team-members'], queryFn: () => AdminApi.teamMembers() });

export const useSubscriptionsQuery = () =>
  useQuery({ queryKey: ['subscriptions'], queryFn: () => AdminApi.subscriptions() });

export const useInvoicesQuery = () =>
  useQuery({ queryKey: ['invoices'], queryFn: () => AdminApi.invoices() });

// ── Publishing ────────────────────────────────────────────────
export const usePostsQuery = () =>
  useQuery({ queryKey: ['posts'], queryFn: () => PublishingApi.posts() });

export const useChannelsQuery = () =>
  useQuery({ queryKey: ['channels'], queryFn: () => ChannelsApi.list() });

export const useProductsQuery = () =>
  useQuery({ queryKey: ['products'], queryFn: () => ProductsApi.list() });

export const useTagRulesQuery = () =>
  useQuery({ queryKey: ['tag-rules'], queryFn: () => TagRulesApi.list() });

export const useRssQuery = () =>
  useQuery({ queryKey: ['rss-sources'], queryFn: () => RssApi.list() });
