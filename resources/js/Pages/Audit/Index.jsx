import { ReloadOutlined } from '@ant-design/icons';
import { App, Button, Card, DatePicker, Input, Select, Table, Tag, Typography } from 'antd';
import { useCallback, useEffect, useState } from 'react';
import PageTitle from '../../components/PageTitle';
import { api, fmtDate, parseError } from '../../lib/api';

const ACTIONS = {
    'auth.login': ['Inicio de sesión', 'green'], 'auth.logout': ['Cierre de sesión', 'default'], 'auth.login_failed': ['Login fallido', 'red'],
    'project.created': ['Proyecto creado', 'blue'], 'project.updated': ['Proyecto editado', 'geekblue'], 'project.deleted': ['Proyecto eliminado', 'red'],
    'project.activated': ['Proyecto activado', 'green'], 'project.deactivated': ['Proyecto desactivado', 'orange'],
    'credential.created': ['Credencial creada', 'blue'], 'credential.updated': ['Credencial editada', 'geekblue'], 'credential.deleted': ['Credencial eliminada', 'red'],
    'credential.secret_revealed': ['Secreto revelado', 'volcano'], 'credential.secret_copied': ['Secreto copiado', 'volcano'], 'credential.expired': ['Credencial expirada', 'orange'],
    'credentials.exported': ['Exportación CSV', 'purple'],
    'user.created': ['Usuario creado', 'blue'], 'user.updated': ['Usuario editado', 'geekblue'], 'user.deleted': ['Usuario eliminado', 'red'], 'user.password_changed': ['Cambio de contraseña', 'purple'],
    'role.created': ['Rol creado', 'blue'], 'role.updated': ['Rol editado', 'geekblue'], 'role.deleted': ['Rol eliminado', 'red'],
    'profile.created': ['Perfil creado', 'blue'], 'profile.updated': ['Perfil editado', 'geekblue'], 'profile.deleted': ['Perfil eliminado', 'red'],
};

function Values({ old, next }) {
    const keys = [...new Set([...Object.keys(old ?? {}), ...Object.keys(next ?? {})])];
    if (!keys.length) return <Typography.Text type="secondary">Sin detalle de valores.</Typography.Text>;
    return (
        <Table size="small" pagination={false} rowKey="k" dataSource={keys.map((k) => ({ k }))} columns={[
            { title: 'Campo', dataIndex: 'k', width: 160 },
            { title: 'Anterior', render: (_, r) => <code>{JSON.stringify(old?.[r.k] ?? null)}</code> },
            { title: 'Nuevo', render: (_, r) => <code>{JSON.stringify(next?.[r.k] ?? null)}</code> },
        ]} />
    );
}

export default function AuditIndex() {
    const { message } = App.useApp();
    const [rows, setRows] = useState([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(25);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/audit-logs', { params: { ...filters, page, per_page: pageSize } });
            setRows(data.data);
            setTotal(data.meta.total);
        } catch (e) {
            message.error(parseError(e).message);
        } finally {
            setLoading(false);
        }
    }, [filters, page, pageSize]);

    useEffect(() => { load(); }, [load]);
    const setFilter = (patch) => { setPage(1); setFilters((f) => ({ ...f, ...patch })); };

    const columns = [
        { title: 'Fecha', dataIndex: 'created_at', width: 170, render: fmtDate },
        { title: 'Usuario', render: (_, r) => r.user?.name ?? r.user_email ?? '—' },
        { title: 'Acción', dataIndex: 'action', render: (a) => <Tag color={ACTIONS[a]?.[1]}>{ACTIONS[a]?.[0] ?? a}</Tag> },
        { title: 'Entidad', responsive: ['md'], render: (_, r) => (r.entity_type ? `${r.entity_type}${r.entity_label ? `: ${r.entity_label}` : ''}` : '—') },
        { title: 'IP', dataIndex: 'ip_address', responsive: ['lg'] },
    ];

    return (
        <>
            <PageTitle title="Auditoría" subtitle="Registro inmutable de acciones sensibles. Los secretos nunca aparecen en texto plano." />
            <Card>
                <div className="filters">
                    <Input.Search allowClear placeholder="Buscar usuario, entidad, IP…" style={{ width: 260 }} onSearch={(v) => setFilter({ search: v || undefined })} />
                    <Select allowClear showSearch optionFilterProp="label" placeholder="Acción" style={{ width: 220 }} onChange={(v) => setFilter({ action: v })}
                        options={Object.entries(ACTIONS).map(([value, [label]]) => ({ value, label }))} />
                    <DatePicker.RangePicker onChange={(r) => setFilter({ from: r?.[0]?.format('YYYY-MM-DD'), to: r?.[1]?.format('YYYY-MM-DD') })} />
                    <Button icon={<ReloadOutlined />} onClick={load} />
                </div>
                <Table rowKey="id" size="middle" loading={loading} dataSource={rows} columns={columns} scroll={{ x: 700 }}
                    expandable={{
                        expandedRowRender: (r) => (
                            <div>
                                <Typography.Paragraph type="secondary" style={{ marginBottom: 8 }}>
                                    {r.user_email} · IP {r.ip_address ?? '—'} · {r.user_agent ?? 'sin user agent'}{r.project_id ? ` · proyecto #${r.project_id}` : ''}
                                </Typography.Paragraph>
                                <Values old={r.old_values} next={r.new_values} />
                            </div>
                        ),
                    }}
                    pagination={{ current: page, pageSize, total, showSizeChanger: true, showTotal: (t) => `${t} eventos` }}
                    onChange={(p) => { setPage(p.current); setPageSize(p.pageSize); }} />
            </Card>
        </>
    );
}
