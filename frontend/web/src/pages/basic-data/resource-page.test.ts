import { describe, expect, it } from 'vitest';
import { withClearedSelects } from './resource-page';

const fields = [
    { name: 'name', label: '名称' },
    { name: 'team_id', label: '所属班组', type: 'select' as const },
];

describe('withClearedSelects', () => {
    it('sends null for a cleared select so the API can detach the parent', () => {
        expect(withClearedSelects({ name: '张三', team_id: undefined }, fields))
            .toEqual({ name: '张三', team_id: null });
    });

    it('leaves a filled select and plain text fields untouched', () => {
        expect(withClearedSelects({ name: '张三', team_id: 3 }, fields))
            .toEqual({ name: '张三', team_id: 3 });
    });
});
