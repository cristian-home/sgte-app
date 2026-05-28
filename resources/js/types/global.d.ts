import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            tagline: string;
            auth: Auth;
            sidebarOpen: boolean;
            config: {
                operation_tz: string;
                viewer_tz: string;
                version: string;
                environment: string;
            };
            [key: string]: unknown;
        };
    }
}
