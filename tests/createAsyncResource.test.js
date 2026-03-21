import test from 'node:test';
import assert from 'node:assert/strict';
import { createAsyncResource } from '../src/utils/createAsyncResource.js';

test('createAsyncResource keeps the latest async result when requests overlap', async () => {
  let resolveFirst;
  let resolveSecond;

  const resource = createAsyncResource(
    (label) =>
      new Promise((resolve) => {
        if (label === 'first') {
          resolveFirst = () => resolve({ items: ['first'] });
          return;
        }

        resolveSecond = () => resolve({ items: ['second'] });
      }),
    (result) => result.items
  );

  const firstRequest = resource.load('first');
  const secondRequest = resource.load('second');

  resolveSecond();
  await secondRequest;

  resolveFirst();
  await firstRequest;

  assert.deepEqual(resource.data.value, ['second']);
  assert.equal(resource.loading.value, false);
  assert.equal(resource.refreshing.value, false);
});
