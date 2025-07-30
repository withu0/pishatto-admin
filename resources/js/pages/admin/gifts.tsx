import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export default function AdminGifts() {
    useEffect(() => {
        // Redirect to the new gifts index page
        router.visit('/admin/gifts');
    }, []);

    return null;
}
