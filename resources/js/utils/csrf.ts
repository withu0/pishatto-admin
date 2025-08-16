/**
 * CSRF Token Utility Functions
 * Provides centralized CSRF token management for the application
 */

/**
 * Get the current CSRF token from the meta tag
 */
export function getCsrfToken(): string | null {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null;
    
    // Debug logging in development
    if (process.env.NODE_ENV === 'development') {
        console.log('CSRF Token retrieved:', token ? `${token.substring(0, 10)}...` : 'null');
    }
    
    return token;
}

/**
 * Refresh the CSRF token by making a request to the CSRF token endpoint
 */
export async function refreshCsrfToken(): Promise<void> {
    try {
        console.log('Refreshing CSRF token...');
        const response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
        });
        
        if (response.ok) {
            const data = await response.json();
            // Update the meta tag with the new token
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag && data.token) {
                metaTag.setAttribute('content', data.token);
                console.log('CSRF token refreshed successfully');
            }
        } else {
            console.error('Failed to refresh CSRF token:', response.status, response.statusText);
        }
    } catch (error) {
        console.error('Failed to refresh CSRF token:', error);
    }
}

/**
 * Create headers object with CSRF token
 */
export function createCsrfHeaders(additionalHeaders: Record<string, string> = {}): Record<string, string> {
    const token = getCsrfToken();
    const headers = {
        'X-CSRF-TOKEN': token || '',
        'X-Requested-With': 'XMLHttpRequest',
        ...additionalHeaders,
    };
    
    // Debug logging in development
    if (process.env.NODE_ENV === 'development') {
        console.log('CSRF Headers created:', {
            'X-CSRF-TOKEN': token ? `${token.substring(0, 10)}...` : 'empty',
            'X-Requested-With': 'XMLHttpRequest',
            ...additionalHeaders
        });
    }
    
    return headers;
}

/**
 * Configure fetch request with CSRF token
 */
export function fetchWithCsrf(url: string, options: RequestInit = {}): Promise<Response> {
    const headers = createCsrfHeaders(options.headers as Record<string, string>);
    
    return fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin',
    });
}

/**
 * Handle CSRF token mismatch by refreshing the page
 */
export function handleCsrfMismatch(): void {
    console.warn('CSRF token mismatch detected. Refreshing page...');
    window.location.reload();
}

/**
 * Validate CSRF token format
 */
export function validateCsrfToken(token: string | null): boolean {
    if (!token) {
        console.error('CSRF token is null or empty');
        return false;
    }
    
    // CSRF tokens are typically 40 characters long (Laravel default)
    if (token.length !== 40) {
        console.error('CSRF token length is invalid:', token.length);
        return false;
    }
    
    // Check if token contains only valid characters (alphanumeric)
    if (!/^[a-zA-Z0-9]+$/.test(token)) {
        console.error('CSRF token contains invalid characters');
        return false;
    }
    
    return true;
}

/**
 * Debug CSRF token state
 */
export function debugCsrfToken(): void {
    const token = getCsrfToken();
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    
    console.log('CSRF Token Debug Info:', {
        token: token ? `${token.substring(0, 10)}...` : 'null',
        tokenLength: token?.length || 0,
        isValid: validateCsrfToken(token),
        metaTagExists: !!metaTag,
        metaTagContent: metaTag?.getAttribute('content') ? `${metaTag.getAttribute('content')?.substring(0, 10)}...` : 'null'
    });
}
