/**
 * CSRF Token Helper for Laravel
 * Automatically handles CSRF tokens for AJAX requests
 */

class CSRFHelper {
    constructor() {
        this.token = null;
        this.tokenUrl = '/csrf-token';
        this.init();
    }

    /**
     * Initialize the CSRF helper
     */
    async init() {
        try {
            await this.refreshToken();
            this.setupAxiosInterceptor();
            this.setupFetchInterceptor();
        } catch (error) {
            console.error('Failed to initialize CSRF helper:', error);
        }
    }

    /**
     * Get the current CSRF token
     */
    getToken() {
        return this.token;
    }

    /**
     * Refresh the CSRF token from the server
     */
    async refreshToken() {
        try {
            const response = await fetch(this.tokenUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.token = data.token;
            
            // Update meta tag if it exists
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', this.token);
            }

            console.log('CSRF token refreshed successfully');
            return this.token;
        } catch (error) {
            console.error('Failed to refresh CSRF token:', error);
            throw error;
        }
    }

    /**
     * Setup Axios interceptor for automatic CSRF token inclusion
     */
    setupAxiosInterceptor() {
        if (typeof axios !== 'undefined') {
            // Request interceptor
            axios.interceptors.request.use(
                (config) => {
                    if (this.token && (config.method === 'post' || config.method === 'put' || config.method === 'delete' || config.method === 'patch')) {
                        config.headers['X-CSRF-TOKEN'] = this.token;
                    }
                    return config;
                },
                (error) => {
                    return Promise.reject(error);
                }
            );

            // Response interceptor for handling CSRF token errors
            axios.interceptors.response.use(
                (response) => {
                    return response;
                },
                async (error) => {
                    if (error.response && error.response.status === 419) {
                        console.log('CSRF token expired, refreshing...');
                        try {
                            await this.refreshToken();
                            // Retry the original request
                            const originalRequest = error.config;
                            originalRequest.headers['X-CSRF-TOKEN'] = this.token;
                            return axios(originalRequest);
                        } catch (refreshError) {
                            console.error('Failed to refresh CSRF token:', refreshError);
                        }
                    }
                    return Promise.reject(error);
                }
            );

            console.log('Axios CSRF interceptor setup complete');
        }
    }

    /**
     * Setup Fetch interceptor for automatic CSRF token inclusion
     */
    setupFetchInterceptor() {
        const originalFetch = window.fetch;
        
        window.fetch = async (url, options = {}) => {
            // Add CSRF token to POST, PUT, DELETE, PATCH requests
            if (this.token && options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
                options.headers = {
                    ...options.headers,
                    'X-CSRF-TOKEN': this.token
                };
            }

            try {
                const response = await originalFetch(url, options);
                
                // Handle CSRF token errors
                if (response.status === 419) {
                    console.log('CSRF token expired, refreshing...');
                    try {
                        await this.refreshToken();
                        // Retry the original request
                        options.headers = {
                            ...options.headers,
                            'X-CSRF-TOKEN': this.token
                        };
                        return await originalFetch(url, options);
                    } catch (refreshError) {
                        console.error('Failed to refresh CSRF token:', refreshError);
                    }
                }
                
                return response;
            } catch (error) {
                return Promise.reject(error);
            }
        };

        console.log('Fetch CSRF interceptor setup complete');
    }

    /**
     * Make a request with automatic CSRF token handling
     */
    async request(url, options = {}) {
        try {
            if (!this.token) {
                await this.refreshToken();
            }

            const response = await fetch(url, {
                ...options,
                headers: {
                    ...options.headers,
                    'X-CSRF-TOKEN': this.token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (response.status === 419) {
                await this.refreshToken();
                return await fetch(url, {
                    ...options,
                    headers: {
                        ...options.headers,
                        'X-CSRF-TOKEN': this.token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
            }

            return response;
        } catch (error) {
            console.error('Request failed:', error);
            throw error;
        }
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.csrfHelper = new CSRFHelper();
    });
} else {
    window.csrfHelper = new CSRFHelper();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CSRFHelper;
}
