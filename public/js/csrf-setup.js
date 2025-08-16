/**
 * CSRF Token Setup for Laravel
 * This file automatically handles CSRF tokens for all AJAX requests
 */

(function() {
    'use strict';

    // Get CSRF token from meta tag
    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : null;
    }

    // Refresh CSRF token from server
    async function refreshCsrfToken() {
        try {
            const response = await fetch('/csrf-token', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    metaTag.setAttribute('content', data.token);
                }
                return data.token;
            }
        } catch (error) {
            console.error('Failed to refresh CSRF token:', error);
        }
        return null;
    }

    // Setup Axios interceptors if Axios is available
    if (typeof axios !== 'undefined') {
        // Request interceptor
        axios.interceptors.request.use(
            function (config) {
                const token = getCsrfToken();
                if (token && ['post', 'put', 'delete', 'patch'].includes(config.method.toLowerCase())) {
                    config.headers['X-CSRF-TOKEN'] = token;
                }
                return config;
            },
            function (error) {
                return Promise.reject(error);
            }
        );

        // Response interceptor
        axios.interceptors.response.use(
            function (response) {
                return response;
            },
            async function (error) {
                if (error.response && error.response.status === 419) {
                    console.log('CSRF token expired, refreshing...');
                    const newToken = await refreshCsrfToken();
                    if (newToken) {
                        // Retry the original request
                        const originalRequest = error.config;
                        originalRequest.headers['X-CSRF-TOKEN'] = newToken;
                        return axios(originalRequest);
                    }
                }
                return Promise.reject(error);
            }
        );

        console.log('Axios CSRF interceptors configured');
    }

    // Setup Fetch interceptor
    const originalFetch = window.fetch;
    window.fetch = async function(url, options = {}) {
        const token = getCsrfToken();
        
        // Add CSRF token to state-changing requests
        if (token && options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
            options.headers = {
                ...options.headers,
                'X-CSRF-TOKEN': token
            };
        }

        try {
            const response = await originalFetch(url, options);
            
            // Handle CSRF token errors
            if (response.status === 419) {
                console.log('CSRF token expired, refreshing...');
                const newToken = await refreshCsrfToken();
                if (newToken) {
                    // Retry the original request
                    options.headers = {
                        ...options.headers,
                        'X-CSRF-TOKEN': newToken
                    };
                    return await originalFetch(url, options);
                }
            }
            
            return response;
        } catch (error) {
            return Promise.reject(error);
        }
    };

    console.log('Fetch CSRF interceptor configured');

    // Auto-refresh CSRF token every 30 minutes
    setInterval(refreshCsrfToken, 30 * 60 * 1000);

    // Make functions available globally
    window.csrfUtils = {
        getToken: getCsrfToken,
        refreshToken: refreshCsrfToken
    };

    console.log('CSRF setup completed');
})();

