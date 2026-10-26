import { App, DatePicker, Form, Input, InputNumber, Modal, Select } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { api, parseError } from '../lib/api';
import { useAuth } from '../lib/permissions';

export default function CredentialFormModal({ open, credential, meta, projects, fixedProjectId, onClose, onSaved }) {
    const { message } = App.useApp();
    const { can } = useAuth();
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);
    const editing = Boolean(credential);
    const typeId = Form.useWatch('credential_type_id', form);
    const isCertificate = meta?.credential_types.find((t) => t.id === typeId)?.slug === 'certificate';

    useEffect(() => {
        if (!open) return;
        form.resetFields();
        if (credential) {
            form.setFieldsValue({
                ...credential,
                environment: credential.environment.value,
                status: credential.status.value,
                expires_at: credential.expires_at ? dayjs(credential.expires_at) : null,
                secret: '',
            });
        } else {
            form.setFieldsValue({ project_id: fixedProjectId, environment: 'development', status: 'active' });
        }
    }, [open, credential]);

    const submit = async (values) => {
        setSaving(true);
        const payload = { ...values, expires_at: values.expires_at ? values.expires_at.format('YYYY-MM-DD') : null };
        try {
            if (editing) await api.put(`/credentials/${credential.id}`, payload);
            else await api.post('/credentials', payload);
            message.success(editing ? 'Credencial actualizada' : 'Credencial creada');
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
        <Modal open={open} onCancel={onClose} title={editing ? 'Editar credencial' : 'Nueva credencial'} width={720}
            okText="Guardar" cancelText="Cancelar" confirmLoading={saving} onOk={() => form.submit()} destroyOnHidden maskClosable={false}>
            <Form form={form} layout="vertical" onFinish={submit} requiredMark="optional">
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', columnGap: 16 }}>
                    <Form.Item name="name" label="Nombre / alias" rules={[{ required: true, message: 'Indica un nombre.' }]}>
                        <Input maxLength={150} />
                    </Form.Item>
                    <Form.Item name="project_id" label="Proyecto" rules={[{ required: true, message: 'Selecciona un proyecto.' }]}>
                        <Select disabled={Boolean(fixedProjectId)} showSearch optionFilterProp="label" placeholder="Proyecto"
                            options={(projects ?? []).map((p) => ({ value: p.id, label: `${p.name} (${p.code})` }))} />
                    </Form.Item>
                    <Form.Item name="credential_type_id" label="Tipo" rules={[{ required: true, message: 'Selecciona un tipo.' }]}>
                        <Select placeholder="Tipo" options={meta?.credential_types.map((t) => ({ value: t.id, label: t.name }))} />
                    </Form.Item>
                    <Form.Item name="criticality_level_id" label="Criticidad" rules={[{ required: true, message: 'Selecciona la criticidad.' }]}
                        extra={!can('credentials.critical') && 'El nivel Crítica requiere un permiso adicional.'}>
                        <Select placeholder="Criticidad" options={meta?.criticality_levels.map((l) => ({
                            value: l.id, label: l.name, disabled: l.slug === 'critical' && !can('credentials.critical'),
                        }))} />
                    </Form.Item>
                    <Form.Item name="environment" label="Entorno" rules={[{ required: true }]}>
                        <Select options={meta?.environments.map((e) => ({ value: e.value, label: e.label }))} />
                    </Form.Item>
                    <Form.Item name="status" label="Estado" rules={[{ required: true }]}>
                        <Select options={meta?.statuses.map((s) => ({ value: s.value, label: s.label }))} />
                    </Form.Item>
                    <Form.Item name="username" label="Usuario"><Input autoComplete="off" maxLength={255} /></Form.Item>
                    <Form.Item name="host" label="URL o host"><Input maxLength={500} /></Form.Item>
                    <Form.Item name="port" label="Puerto" rules={[{ type: 'number', min: 1, max: 65535, message: 'Puerto entre 1 y 65535.' }]}>
                        <InputNumber style={{ width: '100%' }} min={1} max={65535} />
                    </Form.Item>
                    <Form.Item name="expires_at" label="Fecha de expiración"><DatePicker style={{ width: '100%' }} /></Form.Item>
                </div>
                <Form.Item name="secret" label={isCertificate ? 'Certificado / contenido sensible' : 'Contraseña o secreto'}
                    extra={editing ? 'Déjalo vacío para conservar el valor actual.' : 'Se almacena cifrado (AES-256-GCM).'}>
                    {isCertificate
                        ? <Input.TextArea rows={5} autoComplete="off" spellCheck={false} />
                        : <Input.Password autoComplete="new-password" visibilityToggle={false} />}
                </Form.Item>
                <Form.Item name="notes" label="Notas internas"><Input.TextArea rows={3} maxLength={5000} showCount /></Form.Item>
            </Form>
        </Modal>
    );
}
