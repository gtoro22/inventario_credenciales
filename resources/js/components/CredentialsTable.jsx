import { CopyOutlined, DeleteOutlined, DownloadOutlined, EditOutlined, EyeOutlined, PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import { App, Button, Input, Popconfirm, Select, Space, Table, Tag, Tooltip, Typography } from 'antd';
import { useCallback, useEffect, useState } from 'react';
import { api, parseError } from '../lib/api';
import { useAuth } from '../lib/permissions';
import CredentialFormModal from './CredentialFormModal';
import SecretModal from './SecretModal';
import { CriticalityTag, EnvironmentTag, StatusTag } from './Tags';
import { useMeta } from './useMeta';

/** Tabla de credenciales con filtros, formulario y revelado seguro. `fixedProjectId` la acota a un proyecto. */
export default function CredentialsTable({ fixedProjectId, onChanged }) {
    const { message, modal } = App.useApp();
    const { can } = useAuth();
    const meta = useMeta();
    const [projects, setProjects] = useState([]);
    const [rows, setRows] = useState([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(15);
    const [sort, setSort] = useState({});
    const [formState, setFormState] = useState({ open: false, credential: null });
    const [revealed, setRevealed] = useState({ credential: null, secret: null });

    const params = useCallback(() => ({
        ...filters, ...(fixedProjectId ? { project_id: fixedProjectId } : {}), page, per_page: pageSize, ...sort,
    }), [filters, fixedProjectId, page, pageSize, sort]);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/credentials', { params: params() });
            setRows(data.data);
            setTotal(data.meta.total);
        } catch (e) {
            message.error(parseError(e).message);
        } finally {
            setLoading(false);
        }
    }, [params]);

    useEffect(() => { load(); }, [load]);
    useEffect(() => {
        if (!fixedProjectId && can('projects.view')) api.get('/projects', { params: { per_page: 100 } }).then((r) => setProjects(r.data.data));
    }, []);
    useEffect(() => {
        if (fixedProjectId && can('projects.view')) api.get(`/projects/${fixedProjectId}`).then((r) => setProjects([r.data.data]));
    }, [fixedProjectId]);

    const setFilter = (key, value) => { setPage(1); setFilters((f) => ({ ...f, [key]: value || undefined })); };

    const reveal = (credential, purpose) => {
        modal.confirm({
            title: purpose === 'copy' ? 'Copiar secreto' : 'Revelar secreto',
            content: `Vas a ${purpose === 'copy' ? 'copiar' : 'ver'} el secreto de "${credential.name}". La acción queda registrada en auditoría con tu usuario, IP y hora.`,
            okText: purpose === 'copy' ? 'Sí, copiar' : 'Sí, revelar',
            cancelText: 'Cancelar',
            onOk: async () => {
                try {
                    const { data } = await api.post(`/credentials/${credential.id}/reveal`, { confirm: true, purpose });
                    if (purpose === 'copy') {
                        await navigator.clipboard.writeText(data.data.secret ?? '');
                        message.success('Secreto copiado al portapapeles');
                    } else {
                        setRevealed({ credential, secret: data.data.secret ?? '(vacío)' });
                    }
                } catch (e) {
                    message.error(parseError(e).message);
                }
            },
        });
    };

    const remove = async (credential) => {
        try {
            await api.delete(`/credentials/${credential.id}`);
            message.success('Credencial eliminada');
            load();
            onChanged?.();
        } catch (e) {
            message.error(parseError(e).message);
        }
    };

    const exportCsv = () => {
        window.location.href = `/api/credentials/export?${new URLSearchParams(Object.entries(params()).filter(([k, v]) => v !== undefined && !['page', 'per_page', 'sort', 'direction'].includes(k)))}`;
    };

    const columns = [
        {
            title: 'Nombre', dataIndex: 'name', sorter: true, width: 260, render: (name, c) => (
                <div><Typography.Text strong>{name}</Typography.Text><br /><Typography.Text type="secondary" style={{ fontSize: 12 }}>{[c.username, c.host && `${c.host}${c.port ? `:${c.port}` : ''}`].filter(Boolean).join(' @ ')}</Typography.Text></div>
            ),
        },
        ...(fixedProjectId ? [] : [{ title: 'Proyecto', dataIndex: ['project', 'name'], render: (n, c) => (can('projects.view') ? <Link href={`/projects/${c.project_id}`}>{n}</Link> : n) }]),
        { title: 'Tipo', dataIndex: ['type', 'name'], responsive: ['lg'] },
        { title: 'Entorno', dataIndex: 'environment', sorter: true, render: (e) => <EnvironmentTag environment={e} /> },
        { title: 'Criticidad', dataIndex: 'criticality', render: (l) => <CriticalityTag level={l} /> },
        { title: 'Estado', dataIndex: 'status', sorter: true, render: (s) => <StatusTag status={s} /> },
        {
            title: 'Secreto', dataIndex: 'secret', width: 150, render: (s, c) => (
                <Space size={4}>
                    <span className="secret-mask">{c.has_secret ? '********' : '—'}</span>
                    {c.has_secret && c.abilities.reveal && <Tooltip title="Revelar secreto"><Button size="small" type="text" icon={<EyeOutlined />} onClick={() => reveal(c, 'view')} /></Tooltip>}
                    {c.has_secret && c.abilities.copy && <Tooltip title="Copiar secreto"><Button size="small" type="text" icon={<CopyOutlined />} onClick={() => reveal(c, 'copy')} /></Tooltip>}
                </Space>
            ),
        },
        {
            title: 'Expira', dataIndex: 'expires_at', sorter: true, width: 130, responsive: ['xl'], render: (d, c) => (d ? <>{d} {c.expiring_soon && <Tag color="orange">Pronto</Tag>}</> : '—'),
        },
        {
            title: 'Acciones', key: 'actions', fixed: 'right', render: (_, c) => (
                <Space>
                    {c.abilities.update && <Tooltip title="Editar"><Button size="small" icon={<EditOutlined />} onClick={() => setFormState({ open: true, credential: c })} /></Tooltip>}
                    {c.abilities.delete && (
                        <Popconfirm title="¿Eliminar credencial?" description="Esta acción queda registrada en auditoría." okText="Eliminar" okButtonProps={{ danger: true }} cancelText="Cancelar" onConfirm={() => remove(c)}>
                            <Button size="small" danger icon={<DeleteOutlined />} />
                        </Popconfirm>
                    )}
                </Space>
            ),
        },
    ];

    const sel = (placeholder, key, options, width = 150) => (
        <Select allowClear placeholder={placeholder} style={{ width }} value={filters[key]} onChange={(v) => setFilter(key, v)} options={options} showSearch optionFilterProp="label" />
    );

    return (
        <>
            <div className="filters">
                <Input.Search allowClear placeholder="Buscar por nombre, usuario o host" style={{ width: 280 }} onSearch={(v) => setFilter('search', v)} />
                {!fixedProjectId && sel('Proyecto', 'project_id', projects.map((p) => ({ value: p.id, label: p.name })), 180)}
                {sel('Tipo', 'type', meta?.credential_types.map((t) => ({ value: t.slug, label: t.name })), 190)}
                {sel('Entorno', 'environment', meta?.environments.map((e) => ({ value: e.value, label: e.label })))}
                {sel('Estado', 'status', meta?.statuses.map((s) => ({ value: s.value, label: s.label })), 130)}
                {sel('Criticidad', 'criticality', meta?.criticality_levels.map((l) => ({ value: l.slug, label: l.name })), 130)}
                <Button icon={<ReloadOutlined />} onClick={load} />
                <span style={{ flex: 1 }} />
                {can('credentials.export') && <Button icon={<DownloadOutlined />} onClick={exportCsv}>Exportar CSV</Button>}
                {can('credentials.create') && <Button type="primary" icon={<PlusOutlined />} onClick={() => setFormState({ open: true, credential: null })}>Nueva credencial</Button>}
            </div>

            <Table rowKey="id" size="middle" loading={loading} dataSource={rows} columns={columns} scroll={{ x: 1150 }}
                pagination={{ current: page, pageSize, total, showSizeChanger: true, showTotal: (t) => `${t} credenciales` }}
                onChange={(p, _f, s) => {
                    setPage(p.current); setPageSize(p.pageSize);
                    setSort(s.field && s.order ? { sort: Array.isArray(s.field) ? s.field[0] : s.field, direction: s.order === 'descend' ? 'desc' : 'asc' } : {});
                }} />

            <CredentialFormModal open={formState.open} credential={formState.credential} meta={meta} projects={projects} fixedProjectId={fixedProjectId}
                onClose={() => setFormState({ open: false, credential: null })}
                onSaved={() => { setFormState({ open: false, credential: null }); load(); onChanged?.(); }} />
            <SecretModal credential={revealed.credential} secret={revealed.secret} onClose={() => setRevealed({ credential: null, secret: null })} />
        </>
    );
}
