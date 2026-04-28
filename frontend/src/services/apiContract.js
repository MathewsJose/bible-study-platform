export function normalizeApiPayload(response) {
  if (
    response &&
    typeof response === 'object' &&
    'success' in response &&
    'data' in response
  ) {
    return response.data;
  }

  return response;
}
