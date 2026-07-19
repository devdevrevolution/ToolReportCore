import { createRouter, createWebHistory } from 'vue-router'
import DesignerShell from './components/shell/DesignerShell.vue'

export const router = createRouter({
    history: createWebHistory('/pdf-designer'),
    routes: [
        {
            path: '/',
            name: 'designer-new',
            component: DesignerShell,
            props: (route) => ({
                // Read templateId from path param OR query string (?template_id=)
                // for backward compatibility with old links.
                templateId: (route.params.templateId as string) ?? (route.query.template_id as string) ?? null,
            }),
        },
        {
            path: '/:templateId(\\d+)',
            name: 'designer-edit',
            component: DesignerShell,
            props: true,
        },
    ],
})