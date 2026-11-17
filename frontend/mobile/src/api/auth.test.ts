import { describe, expect, it } from 'vitest';
import { ApiRequestError } from './request';
import { loginErrorMessage } from './auth';

describe('loginErrorMessage', () => {
    it('maps an email field error to the generic credentials message', () => {
        const error = new ApiRequestError('These credentials do not match.', 422, 422, {
            email: ['These credentials do not match.'],
        });

        expect(loginErrorMessage(error)).toBe('邮箱或密码错误');
    });

    it('falls back to the first field error', () => {
        const error = new ApiRequestError('Validation failed.', 422, 422, {
            password: ['密码不能为空'],
        });

        expect(loginErrorMessage(error)).toBe('密码不能为空');
    });

    it('handles non-API errors', () => {
        expect(loginErrorMessage(new Error('boom'))).toBe('boom');
        expect(loginErrorMessage('oops')).toBe('登录失败');
    });
});
