import { $fetch } from 'ofetch';

export function getApiBaseUrl() {
  if (typeof window !== 'undefined') {
    return window.__NUXT__?.config?.public?.apiBaseUrl || '';
  }

  return process.env.NUXT_PUBLIC_API_BASE_URL || process.env.VITE_API_BASE_URL || '';
}

export async function apiGet(path, params) {
  try {
    return await $fetch(path, {
      baseURL: getApiBaseUrl() || undefined,
      params,
      timeout: 10000,
    });
  } catch (error) {
    const message =
      error?.data?.message ||
      error?.statusMessage ||
      error?.message ||
      'Unexpected API error';

    throw new Error(message);
  }
}
