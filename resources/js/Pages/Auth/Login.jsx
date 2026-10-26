import { Head } from '@inertiajs/react';
import { LockOutlined, MailOutlined, SafetyOutlined } from '@ant-design/icons';
import { router, usePage } from '@inertiajs/react';
import { Alert, Button, Card, Checkbox, Form, Input, Typography } from 'antd';
import { useState } from 'react';

export default function Login() {
    const { appName } = usePage().props;
    const [form] = Form.useForm();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const submit = (values) => {
        setLoading(true);
        setError(null);
        router.post('/login', values, {
            onError: (errors) => {
                // Los errores de campo se muestran junto al campo; el de email también arriba.
                setError(errors.email || errors.password || 'No se pudo iniciar sesión.');
                form.setFieldValue('password', '');
            },
            onFinish: () => setLoading(false),
        });
    };

    return (
        <div className="login-wrap">
            <Head title="Iniciar sesión" />
            <Card className="login-card" variant="borderless">
                <div style={{ textAlign: 'center', marginBottom: 24 }}>
                    <SafetyOutlined style={{ fontSize: 40, color: '#1d4f91' }} />
                    <Typography.Title level={3} style={{ margin: '8px 0 0' }}>{appName}</Typography.Title>
                    <Typography.Text type="secondary">Resguardo seguro de credenciales por proyecto</Typography.Text>
                </div>

                {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}

                <Form form={form} layout="vertical" onFinish={submit} requiredMark={false} initialValues={{ remember: false }}>
                    <Form.Item name="email" label="Correo electrónico" rules={[
                        { required: true, message: 'Ingresa tu correo electrónico.' },
                        { type: 'email', message: 'El correo electrónico no es válido.' },
                    ]}>
                        <Input prefix={<MailOutlined />} autoComplete="username" size="large" placeholder="usuario@empresa.com" autoFocus />
                    </Form.Item>
                    <Form.Item name="password" label="Contraseña" rules={[{ required: true, message: 'Ingresa tu contraseña.' }]}>
                        <Input.Password prefix={<LockOutlined />} autoComplete="current-password" size="large" placeholder="••••••••" />
                    </Form.Item>
                    <Form.Item name="remember" valuePropName="checked">
                        <Checkbox>Mantener la sesión iniciada</Checkbox>
                    </Form.Item>
                    <Button type="primary" htmlType="submit" size="large" block loading={loading}>Entrar</Button>
                </Form>
            </Card>
        </div>
    );
}

Login.layout = null;
