import test from 'node:test';
import assert from 'node:assert/strict';
import {
  BIBLE_BOOKS,
  getChapterCount,
  getChapterOptions,
  isValidBook,
  isValidChapter,
} from '../src/utils/constants.js';

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
