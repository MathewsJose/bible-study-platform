import { tryUseNuxtApp } from '#imports';
import { $fetch } from 'ofetch';
import { normalizeApiPayload } from './apiContract';

export function getApiBaseUrl() {
  const nuxtApp = tryUseNuxtApp();

  if (nuxtApp?.$config?.public?.apiBaseUrl) {
    return nuxtApp.$config.public.apiBaseUrl;
  }

  if (import.meta.client) {
    return window.__NUXT__?.config?.public?.apiBaseUrl || '';
  }

  return process.env.NUXT_PUBLIC_API_BASE_URL || process.env.VITE_API_BASE_URL || '';
}

export async function apiGet(path, params) {
  try {
    const response = await $fetch(path, {
      baseURL: getApiBaseUrl() || undefined,
      params,
      timeout: 10000,
    });

    return normalizeApiPayload(response);
  } catch (error) {
    const message =
      error?.data?.message ||
      error?.statusMessage ||
      error?.message ||
      'Unexpected API error';

    throw new Error(message);
  }
}
