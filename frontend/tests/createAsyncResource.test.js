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

test('createAsyncResource can retry the latest request arguments', async () => {
  const calls = [];
  const resource = createAsyncResource(
    async (...args) => {
      calls.push(args);
      return { items: args };
    },
    (result) => result.items
  );

  await resource.load('John', 1);
  await resource.retry();

  assert.deepEqual(calls, [
    ['John', 1],
    ['John', 1],
  ]);
});

test('createAsyncResource can hydrate data from an external response', () => {
  const resource = createAsyncResource(
    async () => ({ items: [] }),
    (result) => result.items
  );

  resource.hydrate({ items: ['combined'] }, 'John', 3, 16);

  assert.deepEqual(resource.data.value, ['combined']);
  assert.deepEqual(resource.lastArgs.value, ['John', 3, 16]);
  assert.equal(resource.hasLoaded.value, true);
  assert.equal(resource.error.value, null);
  assert.equal(resource.loading.value, false);
  assert.equal(resource.refreshing.value, false);
});
