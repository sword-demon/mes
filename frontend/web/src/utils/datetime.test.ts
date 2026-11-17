import { describe, expect, it } from 'vitest';
import { businessTime } from './datetime';

describe('businessTime', () => {
    it('renders an instant in the business timezone', () => {
        expect(businessTime('2026-11-15T16:00:00.000000Z').format('YYYY-MM-DD HH:mm')).toBe('2026-11-16 00:00');
    });

    it('keeps calendar dates as written', () => {
        expect(businessTime('2026-11-16').format('YYYY-MM-DD HH:mm')).toBe('2026-11-16 00:00');
    });
});
