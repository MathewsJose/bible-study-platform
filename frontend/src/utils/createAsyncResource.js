import { ref } from 'vue';

function createRequestManager() {
  let currentRequestId = 0;

  return {
    begin() {
      currentRequestId += 1;
      return currentRequestId;
    },
    isCurrent(requestId) {
      return requestId === currentRequestId;
    },
  };
}

export function createAsyncResource(fetcher, mapResult) {
  const data = ref([]);
  const loading = ref(false);
  const refreshing = ref(false);
  const error = ref(null);
  const hasLoaded = ref(false);
  const lastArgs = ref(null);
  const requestManager = createRequestManager();

  async function load(...args) {
    const requestId = requestManager.begin();
    const shouldRefreshInBackground = hasLoaded.value;
    lastArgs.value = args;

    if (shouldRefreshInBackground) {
      refreshing.value = true;
    } else {
      loading.value = true;
    }

    error.value = null;

    try {
      const response = await fetcher(...args);

      if (!requestManager.isCurrent(requestId)) {
        return { stale: true };
      }

      data.value = mapResult(response);
      hasLoaded.value = true;

      return { stale: false, data: data.value };
    } catch (err) {
      if (!requestManager.isCurrent(requestId)) {
        return { stale: true };
      }

      error.value = err.message || 'Unexpected error';

      if (!hasLoaded.value) {
        data.value = [];
      }

      return { stale: false, error: err };
    } finally {
      if (requestManager.isCurrent(requestId)) {
        loading.value = false;
        refreshing.value = false;
      }
    }
  }

  async function retry() {
    if (!lastArgs.value) {
      return { stale: false };
    }

    return load(...lastArgs.value);
  }

  return {
    data,
    loading,
    refreshing,
    error,
    hasLoaded,
    lastArgs,
    load,
    retry,
  };
}
