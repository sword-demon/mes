import { describe, expect, it } from 'vitest';
import { loginErrorMessage } from './auth';
import { ApiRequestError } from '@/utils/api-error';

describe('loginErrorMessage', () => {
    it('maps a credential failure to a Chinese message', () => {
        const error = new ApiRequestError(
            'These credentials do not match.',
            422,
            422,
            { email: ['These credentials do not match our records.'] },
        );

        expect(loginErrorMessage(error)).toBe('邮箱或密码错误');
    });

    it('surfaces field validation errors instead of blaming the password', () => {
        const error = new ApiRequestError('The given data was invalid.', 422, 422, {
            password: ['The password field is required.'],
        });

        expect(loginErrorMessage(error)).toBe('The password field is required.');
    });

    it('passes other failures through', () => {
        expect(loginErrorMessage(new ApiRequestError('服务不可用', 503))).toBe(
            '服务不可用',
        );
    });

    it('falls back for unknown errors', () => {
        expect(loginErrorMessage(new Error('boom'))).toBe('boom');
        expect(loginErrorMessage('nope')).toBe('登录失败');
    });
});
