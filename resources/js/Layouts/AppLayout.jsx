import {
    AuditOutlined, DashboardOutlined, FolderOpenOutlined, KeyOutlined, LockOutlined, LogoutOutlined,
    SafetyCertificateOutlined, SettingOutlined, TeamOutlined, UserOutlined, UsergroupAddOutlined,
} from '@ant-design/icons';
import { Link, router, usePage } from '@inertiajs/react';
import { Avatar, Dropdown, Grid, Layout, Menu, Space, Tag, Typography } from 'antd';
import { useState } from 'react';
import { useAuth } from '../lib/permissions';

const { Sider, Header, Content } = Layout;

/** El menú se genera dinámicamente a partir de los permisos efectivos del usuario. */
const MENU = [
    { key: '/', href: '/', label: 'Dashboard', icon: <DashboardOutlined />, permission: 'dashboard.view', exact: true },
    { key: '/projects', href: '/projects', label: 'Proyectos', icon: <FolderOpenOutlined />, permission: 'projects.view' },
    { key: '/credentials', href: '/credentials', label: 'Credenciales', icon: <KeyOutlined />, permission: 'credentials.view' },
    { key: '/users', href: '/users', label: 'Usuarios', icon: <TeamOutlined />, permission: 'users.manage' },
    { key: '/roles', href: '/roles', label: 'Roles y permisos', icon: <SafetyCertificateOutlined />, permission: 'roles.manage' },
    { key: '/profiles', href: '/profiles', label: 'Perfiles', icon: <UsergroupAddOutlined />, permission: 'profiles.manage' },
    { key: '/audit', href: '/audit', label: 'Auditoría', icon: <AuditOutlined />, permission: 'audit.view' },
    { key: '/settings', href: '/settings', label: 'Configuración', icon: <SettingOutlined />, permission: null },
];

export default function AppLayout({ children }) {
    const { url, props } = usePage();
    const { user, can } = useAuth();
    const screens = Grid.useBreakpoint();
    const [collapsed, setCollapsed] = useState(false);
    const isMobile = screens.lg === false;

    const items = MENU.filter((item) => !item.permission || can(item.permission)).map((item) => ({
        key: item.key,
        icon: item.icon,
        label: <Link href={item.href}>{item.label}</Link>,
    }));

    const path = url.split('?')[0];
    const selected = MENU.filter((m) => (m.exact ? path === m.href : path.startsWith(m.href)))
        .sort((a, b) => b.href.length - a.href.length)[0]?.key;

    const userMenu = {
        items: [
            { key: 'settings', icon: <SettingOutlined />, label: <Link href="/settings">Configuración</Link> },
            { type: 'divider' },
            { key: 'logout', icon: <LogoutOutlined />, label: 'Cerrar sesión', danger: true },
        ],
        onClick: ({ key }) => key === 'logout' && router.post('/logout'),
    };

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider
                collapsible
                breakpoint="lg"
                collapsedWidth={isMobile ? 0 : 64}
                collapsed={collapsed}
                onCollapse={setCollapsed}
                style={isMobile ? { position: 'fixed', zIndex: 20, height: '100vh' } : undefined}
            >
                <div className="logo">
                    <LockOutlined />
                    {!collapsed && <span>{props.appName}</span>}
                </div>
                <Menu theme="dark" mode="inline" selectedKeys={selected ? [selected] : []} items={items}
                    onClick={() => isMobile && setCollapsed(true)} />
            </Sider>
            <Layout>
                <Header style={{ background: '#fff', padding: '0 16px', display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 12, boxShadow: '0 1px 4px rgba(0,0,0,.08)' }}>
                    <Space size="small" wrap={false}>
                        {!isMobile && user.roles.map((role) => <Tag key={role} color="blue">{role}</Tag>)}
                        <Dropdown menu={userMenu} trigger={['click']}>
                            <Space style={{ cursor: 'pointer' }}>
                                <Avatar icon={<UserOutlined />} style={{ background: '#1d4f91' }} />
                                {!isMobile && <Typography.Text strong>{user.name}</Typography.Text>}
                            </Space>
                        </Dropdown>
                    </Space>
                </Header>
                <Content style={{ margin: 0, padding: 24 }}>{children}</Content>
            </Layout>
        </Layout>
    );
}
