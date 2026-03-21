import assert from 'node:assert/strict';
import {
  BIBLE_BOOKS,
  getChapterCount,
  getChapterOptions,
  isValidBook,
  isValidChapter,
} from '../src/utils/constants.js';
import { loadBookmarks, parseBookmarks, saveBookmarks } from '../src/utils/bookmarkStorage.js';
import { createAsyncResource } from '../src/utils/createAsyncResource.js';

const tests = [];

function test(name, fn) {
  tests.push({ name, fn });
}

test('book metadata exposes the full canon and chapter counts', () => {
  assert.equal(BIBLE_BOOKS[0], 'Genesis');
  assert.equal(BIBLE_BOOKS.at(-1), 'Revelation');
  assert.equal(getChapterCount('Psalms'), 150);
  assert.equal(getChapterCount('Jude'), 1);
});

test('chapter options are generated from the selected book metadata', () => {
  assert.deepEqual(getChapterOptions('Philemon'), [1]);
  assert.deepEqual(getChapterOptions('John').slice(0, 3), [1, 2, 3]);
  assert.equal(getChapterOptions('John').at(-1), 21);
});

test('book and chapter validation rejects invalid selections', () => {
  assert.equal(isValidBook('John'), true);
  assert.equal(isValidBook('Fake Book'), false);
  assert.equal(isValidChapter('John', 21), true);
  assert.equal(isValidChapter('John', 22), false);
});

test('bookmark parsing tolerates bad or unexpected storage values', () => {
  assert.deepEqual(parseBookmarks('not-json'), []);
  assert.deepEqual(parseBookmarks('{"book":"John"}'), []);
  assert.deepEqual(parseBookmarks('[{"book":"John","chapter":"3","verse":"16"}]'), [
    { book: 'John', chapter: 3, verse: 16 },
  ]);
});

test('bookmark storage uses the provided storage safely', () => {
  const storage = {
    value: null,
    getItem() {
      return this.value;
    },
    setItem(_key, nextValue) {
      this.value = nextValue;
    },
  };

  assert.deepEqual(loadBookmarks(storage), []);

  saveBookmarks([{ book: 'Luke', chapter: 2, verse: 10 }], storage);

  assert.deepEqual(loadBookmarks(storage), [{ book: 'Luke', chapter: 2, verse: 10 }]);
});

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

let failures = 0;

for (const { name, fn } of tests) {
  try {
    await fn();
    console.log(`PASS ${name}`);
  } catch (error) {
    failures += 1;
    console.error(`FAIL ${name}`);
    console.error(error);
  }
}

if (failures > 0) {
  process.exitCode = 1;
} else {
  console.log(`All ${tests.length} tests passed.`);
}
