import { useRuntimeConfig } from '#imports';
import { $fetch } from 'ofetch';

export function getApiBaseUrl() {
  const config = useRuntimeConfig();
  return config.public.apiBaseUrl || '';
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
