import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { App, Button, Card, Form, Input, Modal, Popconfirm, Select, Space, Switch, Table, Tag, Tooltip } from 'antd';
import { useCallback, useEffect, useState } from 'react';
import PageTitle from '../../components/PageTitle';
import { api, fmtDate, parseError } from '../../lib/api';
import { useAuth } from '../../lib/permissions';

function UserFormModal({ open, user, roles, profiles, projects, onClose, onSaved }) {
    const { message } = App.useApp();
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        form.resetFields();
        form.setFieldsValue(user
            ? { name: user.name, email: user.email, is_active: user.is_active, role_ids: user.roles.map((r) => r.id), profile_ids: user.profiles.map((p) => p.id), project_ids: user.projects.map((p) => p.id) }
            : { is_active: true, role_ids: [], profile_ids: [], project_ids: [] });
    }, [open, user]);

    const submit = async (values) => {
        setSaving(true);
        try {
            if (user) await api.put(`/users/${user.id}`, { ...values, password: values.password || null });
            else await api.post('/users', values);
            message.success(user ? 'Usuario actualizado' : 'Usuario creado');
            onSaved();
        } catch (e) {
            const err = parseError(e);
            if (err.fields.length) form.setFields(err.fields);
            else message.error(err.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal open={open} onCancel={onClose} title={user ? 'Editar usuario' : 'Nuevo usuario'} okText="Guardar" cancelText="Cancelar" width={640}
            confirmLoading={saving} onOk={() => form.submit()} destroyOnHidden maskClosable={false}>
            <Form form={form} layout="vertical" onFinish={submit}>
                <Form.Item name="name" label="Nombre" rules={[{ required: true, message: 'Indica un nombre.' }]}><Input maxLength={150} /></Form.Item>
                <Form.Item name="email" label="Correo electrónico" rules={[{ required: true, message: 'Indica un correo.' }, { type: 'email', message: 'Correo no válido.' }]}>
                    <Input autoComplete="off" />
                </Form.Item>
                <Form.Item name="password" label="Contraseña" extra="Mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos."
                    rules={[{ required: !user, message: 'Indica una contraseña.' }, { min: 12, message: 'Mínimo 12 caracteres.' }]}>
                    <Input.Password autoComplete="new-password" placeholder={user ? 'Dejar vacío para no cambiarla' : ''} />
                </Form.Item>
                <Form.Item name="role_ids" label="Roles"><Select mode="multiple" placeholder="Roles" optionFilterProp="label" options={roles.map((r) => ({ value: r.id, label: r.name }))} /></Form.Item>
                <Form.Item name="profile_ids" label="Perfiles (permisos adicionales)"><Select mode="multiple" placeholder="Perfiles" optionFilterProp="label" options={profiles.map((p) => ({ value: p.id, label: p.name }))} /></Form.Item>
                <Form.Item name="project_ids" label="Proyectos con acceso" extra="Sin proyectos asignados, el usuario no verá ninguno.">
                    <Select mode="multiple" placeholder="Proyectos" optionFilterProp="label" options={projects.map((p) => ({ value: p.id, label: `${p.name} (${p.code})` }))} />
                </Form.Item>
                <Form.Item name="is_active" label="Activo" valuePropName="checked"><Switch /></Form.Item>
            </Form>
        </Modal>
    );
}

export default function UsersIndex() {
    const { message } = App.useApp();
    const { user: me, can } = useAuth();
    const [rows, setRows] = useState([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(15);
    const [formState, setFormState] = useState({ open: false, user: null });
    const [catalog, setCatalog] = useState({ roles: [], profiles: [], projects: [] });

    useEffect(() => {
        Promise.all([api.get('/roles'), api.get('/profiles'), can('projects.view') ? api.get('/projects', { params: { per_page: 100 } }) : Promise.resolve({ data: { data: [] } })])
            .then(([r, p, pr]) => setCatalog({ roles: r.data.data, profiles: p.data.data, projects: pr.data.data }));
    }, []);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/users', { params: { ...filters, page, per_page: pageSize } });
            setRows(data.data);
            setTotal(data.meta.total);
        } catch (e) {
            message.error(parseError(e).message);
        } finally {
            setLoading(false);
        }
    }, [filters, page, pageSize]);

    useEffect(() => { load(); }, [load]);

    const remove = async (u) => {
        try { await api.delete(`/users/${u.id}`); message.success('Usuario eliminado'); load(); } catch (e) { message.error(parseError(e).message); }
    };

    const columns = [
        { title: 'Nombre', dataIndex: 'name' },
        { title: 'Correo', dataIndex: 'email', responsive: ['md'] },
        { title: 'Roles', dataIndex: 'roles', render: (rs) => rs.map((r) => <Tag key={r.id} color={r.slug === 'super-admin' ? 'red' : 'blue'}>{r.name}</Tag>) },
        { title: 'Perfiles', dataIndex: 'profiles', responsive: ['xl'], render: (ps) => ps.map((p) => <Tag key={p.id} color="purple">{p.name}</Tag>) },
        { title: 'Proyectos', dataIndex: 'projects', responsive: ['lg'], render: (ps, u) => (u.is_super_admin ? <Tag>Todos</Tag> : ps.length) },
        { title: 'Último acceso', dataIndex: 'last_login_at', responsive: ['xl'], render: fmtDate },
        { title: 'Estado', dataIndex: 'is_active', render: (a) => <Tag color={a ? 'success' : 'default'}>{a ? 'Activo' : 'Inactivo'}</Tag> },
        {
            title: 'Acciones', key: 'actions', fixed: 'right', render: (_, u) => {
                const locked = u.is_super_admin && !me.is_super_admin;
                return (
                    <Space>
                        <Tooltip title={locked ? 'Solo un superadministrador puede editarlo' : 'Editar'}><Button size="small" icon={<EditOutlined />} disabled={locked} onClick={() => setFormState({ open: true, user: u })} /></Tooltip>
                        <Popconfirm title="¿Eliminar usuario?" okText="Eliminar" okButtonProps={{ danger: true }} cancelText="Cancelar" onConfirm={() => remove(u)} disabled={locked || u.id === me.id}>
                            <Button size="small" danger icon={<DeleteOutlined />} disabled={locked || u.id === me.id} />
                        </Popconfirm>
                    </Space>
                );
            },
        },
    ];

    return (
        <>
            <PageTitle title="Usuarios" subtitle="Cuentas, roles, perfiles y acceso por proyecto"
                extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => setFormState({ open: true, user: null })}>Nuevo usuario</Button>} />
            <Card>
                <div className="filters">
                    <Input.Search allowClear placeholder="Buscar por nombre o correo" style={{ width: 280 }} onSearch={(v) => { setPage(1); setFilters((f) => ({ ...f, search: v || undefined })); }} />
                    <Select allowClear placeholder="Rol" style={{ width: 200 }} onChange={(v) => { setPage(1); setFilters((f) => ({ ...f, role: v })); }} options={catalog.roles.map((r) => ({ value: r.slug, label: r.name }))} />
                    <Select allowClear placeholder="Estado" style={{ width: 130 }} onChange={(v) => { setPage(1); setFilters((f) => ({ ...f, active: v })); }} options={[{ value: 1, label: 'Activos' }, { value: 0, label: 'Inactivos' }]} />
                </div>
                <Table rowKey="id" loading={loading} dataSource={rows} columns={columns} scroll={{ x: 700 }}
                    pagination={{ current: page, pageSize, total, showSizeChanger: true, showTotal: (t) => `${t} usuarios` }}
                    onChange={(p) => { setPage(p.current); setPageSize(p.pageSize); }} />
            </Card>
            <UserFormModal open={formState.open} user={formState.user} {...catalog} onClose={() => setFormState({ open: false, user: null })}
                onSaved={() => { setFormState({ open: false, user: null }); load(); }} />
        </>
    );
}
