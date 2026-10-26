import { DeleteOutlined, LockOutlined, PlusOutlined, SaveOutlined } from '@ant-design/icons';
import { App, Alert, Button, Card, Checkbox, Form, Input, Modal, Popconfirm, Space, Table, Tag, Tooltip, Typography } from 'antd';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { api, parseError } from '../lib/api';

/**
 * Matriz visual de permisos (filas) por rol o perfil (columnas).
 * Los cambios se acumulan localmente y se guardan juntos.
 */
export default function AccessMatrix({ endpoint, singular, plural }) {
    const { message } = App.useApp();
    const [permissions, setPermissions] = useState([]);
    const [items, setItems] = useState([]);
    const [matrix, setMatrix] = useState({}); // id -> Set(permission_id)
    const [original, setOriginal] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [creating, setCreating] = useState(false);
    const [form] = Form.useForm();

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const [perm, list] = await Promise.all([api.get('/permissions'), api.get(`/${endpoint}`)]);
            setPermissions(perm.data.data);
            setItems(list.data.data);
            const m = Object.fromEntries(list.data.data.map((i) => [i.id, new Set(i.permission_ids)]));
            setMatrix(m);
            setOriginal(Object.fromEntries(Object.entries(m).map(([k, v]) => [k, new Set(v)])));
        } catch (e) {
            message.error(parseError(e).message);
        } finally {
            setLoading(false);
        }
    }, [endpoint]);

    useEffect(() => { load(); }, [load]);

    const dirtyIds = useMemo(() => items.filter((i) => {
        const a = matrix[i.id]; const b = original[i.id];
        return a && b && (a.size !== b.size || [...a].some((x) => !b.has(x)));
    }).map((i) => i.id), [items, matrix, original]);

    const toggle = (itemId, permId, checked) => setMatrix((m) => {
        const next = new Set(m[itemId]);
        checked ? next.add(permId) : next.delete(permId);
        return { ...m, [itemId]: next };
    });

    const save = async () => {
        setSaving(true);
        try {
            for (const id of dirtyIds) {
                const item = items.find((i) => i.id === id);
                await api.put(`/${endpoint}/${id}`, { name: item.name, description: item.description, permission_ids: [...matrix[id]] });
            }
            message.success('Cambios guardados');
            await load();
        } catch (e) {
            message.error(parseError(e).message);
        } finally {
            setSaving(false);
        }
    };

    const create = async (values) => {
        try {
            await api.post(`/${endpoint}`, { ...values, permission_ids: [] });
            message.success(`${singular} creado`);
            setCreating(false);
            form.resetFields();
            load();
        } catch (e) {
            const err = parseError(e);
            if (err.fields.length) form.setFields(err.fields); else message.error(err.message);
        }
    };

    const remove = async (item) => {
        try { await api.delete(`/${endpoint}/${item.id}`); message.success(`${singular} eliminado`); load(); } catch (e) { message.error(parseError(e).message); }
    };

    const groups = useMemo(() => {
        // Lista plana: una fila de cabecera por grupo seguida de sus permisos.
        const out = [];
        let last = null;
        permissions.forEach((p) => {
            if (p.group !== last) { out.push({ key: `g-${p.group}`, group: p.group, isGroup: true }); last = p.group; }
            out.push({ ...p, key: p.id });
        });
        return out;
    }, [permissions]);

    const columns = [
        { title: 'Permiso', dataIndex: 'name', fixed: 'left', width: 280, render: (n, r) => (r.isGroup ? <Typography.Text strong>{r.group}</Typography.Text> : <Tooltip title={r.slug}>{n}</Tooltip>) },
        ...items.map((item) => ({
            title: (
                <Space direction="vertical" size={0} align="center">
                    <span>{item.name} {item.is_super_admin && <LockOutlined />}</span>
                    <Typography.Text type="secondary" style={{ fontSize: 11, fontWeight: 400 }}>{item.users_count} usuario(s)</Typography.Text>
                    {!item.is_system && (
                        <Popconfirm title={`¿Eliminar ${singular}?`} okText="Eliminar" okButtonProps={{ danger: true }} cancelText="Cancelar" onConfirm={() => remove(item)}>
                            <Button size="small" type="text" danger icon={<DeleteOutlined />} />
                        </Popconfirm>
                    )}
                </Space>
            ),
            key: item.id, align: 'center', width: 130,
            render: (_, r) => (r.isGroup ? null : (
                <Checkbox checked={item.is_super_admin || matrix[item.id]?.has(r.id)} disabled={item.is_super_admin}
                    onChange={(e) => toggle(item.id, r.id, e.target.checked)} />
            )),
        })),
    ];

    return (
        <Card extra={(
            <Space>
                <Button icon={<PlusOutlined />} onClick={() => setCreating(true)}>Nuevo {singular}</Button>
                <Button type="primary" icon={<SaveOutlined />} disabled={!dirtyIds.length} loading={saving} onClick={save}>
                    Guardar cambios{dirtyIds.length ? ` (${dirtyIds.length})` : ''}
                </Button>
            </Space>
        )}>
            <Alert type="info" showIcon style={{ marginBottom: 16 }}
                message="Los permisos efectivos de un usuario son la unión de los de sus roles y perfiles. El Super Administrador posee todos de forma implícita y no es editable." />
            <Table size="small" loading={loading} dataSource={groups} columns={columns} pagination={false} bordered
                rowClassName={(r) => (r.isGroup ? 'matrix-group' : '')} scroll={{ x: 300 + items.length * 130 }} />

            <Modal open={creating} title={`Nuevo ${singular}`} okText="Crear" cancelText="Cancelar" onCancel={() => setCreating(false)} onOk={() => form.submit()} destroyOnHidden>
                <Form form={form} layout="vertical" onFinish={create}>
                    <Form.Item name="name" label="Nombre" rules={[{ required: true, message: 'Indica un nombre.' }]}><Input maxLength={100} /></Form.Item>
                    <Form.Item name="description" label="Descripción"><Input.TextArea rows={2} maxLength={500} /></Form.Item>
                    <Typography.Text type="secondary">Después de crearlo, marca sus permisos en la matriz.</Typography.Text>
                </Form>
            </Modal>
        </Card>
    );
}
