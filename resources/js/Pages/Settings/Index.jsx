import { App, Alert, Button, Card, Col, Descriptions, Form, Input, Row, Tag } from 'antd';
import { useState } from 'react';
import PageTitle from '../../components/PageTitle';
import { api, parseError } from '../../lib/api';
import { useAuth } from '../../lib/permissions';

export default function SettingsIndex() {
    const { message } = App.useApp();
    const { user } = useAuth();
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);

    const submit = async (values) => {
        setSaving(true);
        try {
            await api.put('/me/password', values);
            message.success('Contraseña actualizada');
            form.resetFields();
        } catch (e) {
            const err = parseError(e);
            if (err.fields.length) form.setFields(err.fields); else message.error(err.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <>
            <PageTitle title="Configuración" subtitle="Tu cuenta y seguridad" />
            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <Card title="Mi cuenta">
                        <Descriptions column={1} size="small">
                            <Descriptions.Item label="Nombre">{user.name}</Descriptions.Item>
                            <Descriptions.Item label="Correo">{user.email}</Descriptions.Item>
                            <Descriptions.Item label="Roles">{user.roles.map((r) => <Tag key={r} color="blue">{r}</Tag>)}</Descriptions.Item>
                            <Descriptions.Item label="Perfiles">{user.profiles.length ? user.profiles.map((p) => <Tag key={p} color="purple">{p}</Tag>) : '—'}</Descriptions.Item>
                            <Descriptions.Item label="Permisos efectivos">
                                {user.is_super_admin ? <Tag color="red">Todos (Super Administrador)</Tag> : (user.permissions.length ? user.permissions.map((p) => <Tag key={p}>{p}</Tag>) : 'Ninguno')}
                            </Descriptions.Item>
                        </Descriptions>
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Cambiar contraseña">
                        <Alert type="info" showIcon style={{ marginBottom: 16 }} message="Mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos." />
                        <Form form={form} layout="vertical" onFinish={submit}>
                            <Form.Item name="current_password" label="Contraseña actual" rules={[{ required: true, message: 'Ingresa tu contraseña actual.' }]}><Input.Password autoComplete="current-password" /></Form.Item>
                            <Form.Item name="password" label="Nueva contraseña" rules={[{ required: true, message: 'Ingresa la nueva contraseña.' }, { min: 12, message: 'Mínimo 12 caracteres.' }]}><Input.Password autoComplete="new-password" /></Form.Item>
                            <Form.Item name="password_confirmation" label="Confirmar nueva contraseña" dependencies={['password']} rules={[
                                { required: true, message: 'Confirma la contraseña.' },
                                ({ getFieldValue }) => ({ validator: (_, v) => (!v || getFieldValue('password') === v ? Promise.resolve() : Promise.reject(new Error('Las contraseñas no coinciden.'))) }),
                            ]}><Input.Password autoComplete="new-password" /></Form.Item>
                            <Button type="primary" htmlType="submit" loading={saving}>Actualizar contraseña</Button>
                        </Form>
                    </Card>
                </Col>
            </Row>
        </>
    );
}
