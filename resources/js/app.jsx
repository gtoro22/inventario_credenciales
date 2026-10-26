import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { App as AntApp, ConfigProvider } from 'antd';
import esES from 'antd/locale/es_ES';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import { createRoot } from 'react-dom/client';
import AppLayout from './Layouts/AppLayout';

dayjs.locale('es');

createInertiaApp({
    title: (title) => (title ? `${title} · Credential Vault` : 'Credential Vault'),
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        const page = await pages[`./Pages/${name}.jsx`]();
        // Todas las páginas usan el layout salvo que declaren `layout = null`.
        if (page.default.layout === undefined) {
            page.default.layout = (node) => <AppLayout>{node}</AppLayout>;
        }
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <ConfigProvider locale={esES} theme={{ token: { colorPrimary: '#1d4f91', borderRadius: 6 } }}>
                <AntApp>
                    <App {...props} />
                </AntApp>
            </ConfigProvider>,
        );
    },
    progress: { color: '#1d4f91' },
});
