import { PRODUCT_SN_STATUSES } from '@/api/production';

export const snStatusColor = Object.fromEntries(
    PRODUCT_SN_STATUSES.map(({ value, color }) => [value, color]),
);
