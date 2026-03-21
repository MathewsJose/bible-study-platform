import test from 'node:test';
import assert from 'node:assert/strict';
import { loadBookmarks, parseBookmarks, saveBookmarks } from '../src/utils/bookmarkStorage.js';

test('parseBookmarks tolerates bad or unexpected storage values', () => {
  assert.deepEqual(parseBookmarks('not-json'), []);
  assert.deepEqual(parseBookmarks('{"book":"John"}'), []);
  assert.deepEqual(parseBookmarks('[{"book":"John","chapter":"3","verse":"16"}]'), [
    { book: 'John', chapter: 3, verse: 16 },
  ]);
});

test('loadBookmarks and saveBookmarks use the provided storage safely', () => {
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
