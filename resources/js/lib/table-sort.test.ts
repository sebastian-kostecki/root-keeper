import { describe, expect, it } from 'vite-plus/test';
import { columnOf, nextSort } from './table-sort';

type Column = 'a' | 'b';

describe('columnOf', () => {
    it('strips the descending prefix', () => {
        expect(columnOf<Column>('a')).toBe('a');
        expect(columnOf<Column>('-b')).toBe('b');
    });
});

describe('nextSort', () => {
    it('flips the active column', () => {
        expect(nextSort<Column>('a', 'a')).toBe('-a');
        expect(nextSort<Column>('-a', 'a')).toBe('a');
    });

    it('starts a new column ascending', () => {
        expect(nextSort<Column>('-a', 'b')).toBe('b');
        expect(nextSort<Column>('a', 'b')).toBe('b');
    });
});
