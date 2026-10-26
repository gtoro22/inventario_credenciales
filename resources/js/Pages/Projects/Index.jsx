import { DeleteOutlined, EditOutlined, EyeOutlined, PlusOutlined } from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import { App, Button, Card, Form, Input, Modal, Popconfirm, Select, Space, Switch, Table, Tag, Tooltip } from 'antd';
import { useCallback, useEffect, useState } from 'react';
import PageTitle from '../../components/PageTitle';
import { api, parseError } from '../../lib/api';
import { useAuth } from '../../lib/permissions';

function ProjectFormModal({ open, project, onClose, onSaved }) {
    const { message } = App.useApp();
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        form.resetFields();
        form.setFieldsValue(project ? { ...project } : { is_active: true, tags: [] });
    }, [open, project]);

    const submit = async (values) => {
        setSaving(true);
        try {
            if (project) await api.put(`/projects/${project.id}`, values);
            else await api.post('/projects', values);
            message.success(project ? 'Proyecto actualizado' : 'Proyecto creado');
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
        <Modal open={open} onCancel={onClose} title={project ? 'Editar proyecto' : 'Nuevo proyecto'} okText="Guardar" cancelText="Cancelar"
            confirmLoading={saving} onOk={() => form.submit()} destroyOnHidden maskClosable={false}>
            <Form form={form} layout="vertical" onFinish={submit}>
                <Form.Item name="name" label="Nombre" rules={[{ required: true, message: 'Indica un nombre.' }]}><Input maxLength={150} /></Form.Item>
                <Form.Item name="code" label="Código interno" extra="Letras, números, punto, guion y guion bajo."
                    rules={[{ required: true, message: 'Indica un código.' }, { pattern: /^[A-Za-z0-9._-]+$/, message: 'Formato de código no válido.' }]}>
                    <Input maxLength={50} />
                </Form.Item>
                <Form.Item name="description" label="Descripción"><Input.TextArea rows={3} maxLength={2000} showCount /></Form.Item>
                <Form.Item name="tags" label="Etiquetas"><Select mode="tags" tokenSeparators={[',']} placeholder="Escribe y presiona Enter" maxTagCount="responsive" /></Form.Item>
                <Form.Item name="is_active" label="Activo" valuePropName="checked"><Switch /></Form.Item>
            </Form>
        </Modal>
    );
}

export default function ProjectsIndex() {
    const { message } = App.useApp();
    const { can } = useAuth();
    const [rows, setRows] = useState([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(15);
    const [sort, setSort] = useState({});
    const [formState, setFormState] = useState({ open: false, project: null });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/projects', { params: { ...filters, ...sort, page, per_page: pageSize } });
            setRows(data.data);
            setTotal(data.meta.total);
        } catch (e) {
            message.error(parseError(e).message);
        } finally {
            setLoading(false);
        }
    }, [filters, sort, page, pageSize]);

    useEffect(() => { load(); }, [load]);

    const act = async (fn, okMsg) => {
        try { await fn(); message.success(okMsg); load(); } catch (e) { message.error(parseError(e).message); }
    };

    const columns = [
        { title: 'Código', dataIndex: 'code', sorter: true, render: (c) => <Tag>{c}</Tag> },
        { title: 'Nombre', dataIndex: 'name', sorter: true, render: (n, p) => <Link href={`/projects/${p.id}`}>{n}</Link> },
        { title: 'Responsable', dataIndex: ['owner', 'name'], responsive: ['lg'], render: (n) => n ?? '—' },
        { title: 'Etiquetas', dataIndex: 'tags', responsive: ['xl'], render: (t) => t.map((x) => <Tag key={x} color="blue">{x}</Tag>) },
        { title: 'Credenciales', dataIndex: 'credentials_count', sorter: true, align: 'right' },
        { title: 'Críticas', dataIndex: 'critical_count', align: 'right', render: (n) => (n ? <Tag color="red">{n}</Tag> : 0) },
        {
            title: 'Estado', dataIndex: 'is_active', render: (active, p) => (can('projects.update') && p.abilities.update ? (
                <Popconfirm title={active ? '¿Desactivar proyecto?' : '¿Activar proyecto?'} okText="Sí" cancelText="No"
                    onConfirm={() => act(() => api.patch(`/projects/${p.id}/active`, { is_active: !active }), active ? 'Proyecto desactivado' : 'Proyecto activado')}>
                    <Switch checked={active} checkedChildren="Activo" unCheckedChildren="Inactivo" />
                </Popconfirm>
            ) : <Tag color={active ? 'success' : 'default'}>{active ? 'Activo' : 'Inactivo'}</Tag>),
        },
        {
            title: 'Acciones', key: 'actions', fixed: 'right', render: (_, p) => (
                <Space>
                    <Tooltip title="Ver detalle"><Link href={`/projects/${p.id}`}><Button size="small" icon={<EyeOutlined />} /></Link></Tooltip>
                    {p.abilities.update && <Tooltip title="Editar"><Button size="small" icon={<EditOutlined />} onClick={() => setFormState({ open: true, project: p })} /></Tooltip>}
                    {p.abilities.delete && (
                        <Popconfirm title="¿Eliminar proyecto?" description={p.is_active ? 'Debes desactivarlo primero.' : 'Se eliminarán también sus credenciales.'}
                            okText="Eliminar" okButtonProps={{ danger: true, disabled: p.is_active }} cancelText="Cancelar"
                            onConfirm={() => act(() => api.delete(`/projects/${p.id}`), 'Proyecto eliminado')}>
                            <Button size="small" danger icon={<DeleteOutlined />} />
                        </Popconfirm>
                    )}
                </Space>
            ),
        },
    ];

    return (
        <>
            <PageTitle title="Proyectos" subtitle="Cada proyecto agrupa sus credenciales y quién puede verlas"
                extra={can('projects.create') && <Button type="primary" icon={<PlusOutlined />} onClick={() => setFormState({ open: true, project: null })}>Nuevo proyecto</Button>} />
            <Card>
                <div className="filters">
                    <Input.Search allowClear placeholder="Buscar por nombre o código" style={{ width: 280 }} onSearch={(v) => { setPage(1); setFilters((f) => ({ ...f, search: v || undefined })); }} />
                    <Select allowClear placeholder="Estado" style={{ width: 150 }} onChange={(v) => { setPage(1); setFilters((f) => ({ ...f, status: v })); }}
                        options={[{ value: 'active', label: 'Activos' }, { value: 'inactive', label: 'Inactivos' }]} />
                </div>
                <Table rowKey="id" loading={loading} dataSource={rows} columns={columns} scroll={{ x: 800 }}
                    pagination={{ current: page, pageSize, total, showSizeChanger: true, showTotal: (t) => `${t} proyectos` }}
                    onChange={(p, _f, s) => {
                        setPage(p.current); setPageSize(p.pageSize);
                        setSort(s.field && s.order ? { sort: s.field, direction: s.order === 'descend' ? 'desc' : 'asc' } : {});
                    }} />
            </Card>
            <ProjectFormModal open={formState.open} project={formState.project} onClose={() => setFormState({ open: false, project: null })}
                onSaved={() => { setFormState({ open: false, project: null }); load(); }} />
        </>
    );
}
