import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { configureEcho } from '@laravel/echo-react';
import { Toaster } from 'sonner';
import axios from 'axios';
import { getCsrfToken, handleCsrfMismatch, debugCsrfToken } from './utils/csrf';

configureEcho({
    broadcaster: 'reverb',
});

// Configure global CSRF token handling
const csrfToken = getCsrfToken();

// Configure axios defaults
axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken || '';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

// Add request interceptor to ensure CSRF token is always included
axios.interceptors.request.use(function (config) {
    // Get the latest CSRF token from meta tag (in case it was refreshed)
    const token = getCsrfToken();
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
}, function (error) {
    return Promise.reject(error);
});

// Add response interceptor to handle CSRF token mismatch
axios.interceptors.response.use(function (response) {
    return response;
}, function (error) {
    if (error.response?.status === 419) {
        // CSRF token mismatch - handle it properly
        handleCsrfMismatch();
    }
    return Promise.reject(error);
});

// Configure global fetch to include CSRF token
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    const token = getCsrfToken();
    
    if (token) {
        options.headers = {
            ...options.headers,
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }
    
    return originalFetch(url, options);
};

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <Toaster position="top-right" />
            </>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Debug CSRF token in development
if (process.env.NODE_ENV === 'development') {
    debugCsrfToken();
}
