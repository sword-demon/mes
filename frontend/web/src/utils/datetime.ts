import dayjs, { type ConfigType, type Dayjs } from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

dayjs.extend(utc);
dayjs.extend(timezone);

/** 与后端 APP_BUSINESS_TIMEZONE 保持一致的业务时区。 */
export const BUSINESS_TIMEZONE = import.meta.env.VITE_BUSINESS_TIMEZONE ?? 'Asia/Shanghai';

const CALENDAR_DATE = /^\d{4}-\d{2}-\d{2}$/;

/**
 * 按业务时区渲染接口时间戳。
 *
 * 纯日期（YYYY-MM-DD）保持原值，避免跨时区把业务日偏移一天。
 */
export function businessTime(value?: ConfigType): Dayjs {
    const parsed = dayjs(value);

    return typeof value === 'string' && CALENDAR_DATE.test(value) ? parsed : parsed.tz(BUSINESS_TIMEZONE);
}
