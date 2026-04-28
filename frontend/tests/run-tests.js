import assert from 'node:assert/strict';
import {
  BIBLE_BOOKS,
  getChapterCount,
  getChapterOptions,
  isValidBook,
  isValidChapter,
  isValidVerseNumber,
} from '../src/utils/constants.js';
import { loadBookmarks, parseBookmarks, saveBookmarks } from '../src/utils/bookmarkStorage.js';
import { createAsyncResource } from '../src/utils/createAsyncResource.js';
import {
  getSampleBibleContent,
  getSampleHistoricalData,
  getSampleTeachingsData,
} from '../src/services/sampleData.js';
import { normalizeApiPayload } from '../src/services/apiContract.js';

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

test('verse validation rejects invalid verse numbers', () => {
  assert.equal(isValidVerseNumber(1), true);
  assert.equal(isValidVerseNumber('16'), true);
  assert.equal(isValidVerseNumber(0), false);
  assert.equal(isValidVerseNumber('not-a-verse'), false);
  assert.equal(isValidVerseNumber(1.5), false);
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

test('sample content does not contain common mojibake artifacts', () => {
  const payload = {
    bible: getSampleBibleContent('John', 1),
    history: getSampleHistoricalData('John', 1, 14),
    teachings: getSampleTeachingsData('John', 1, 14),
  };
  const serialized = JSON.stringify(payload);

  assert.equal(serialized.includes('\u00c3'), false);
  assert.equal(serialized.includes('\u00e2'), false);
  assert.equal(serialized.includes('\ufffd'), false);
});

test('api payload normalization unwraps the Laravel envelope in one place', () => {
  assert.deepEqual(normalizeApiPayload({
    success: true,
    data: { verses: [{ verse: 1, text: 'In the beginning' }] },
  }), {
    verses: [{ verse: 1, text: 'In the beginning' }],
  });

  assert.deepEqual(normalizeApiPayload({
    items: ['Historical note'],
  }), {
    items: ['Historical note'],
  });
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
