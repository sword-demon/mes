export class ApiRequestError extends Error {
    constructor(
        message: string,
        /** HTTP status, or 0 when the server could not be reached. */
        public readonly status: number,
        /** Business code from the response envelope; defaults to the HTTP status. */
        public readonly code: number = status,
        public readonly errors?: Record<string, string[]>,
    ) {
        super(message);
        this.name = 'ApiRequestError';
    }
}
