import { CopyOutlined, EyeInvisibleOutlined } from '@ant-design/icons';
import { App, Button, Modal, Space, Typography } from 'antd';
import { useEffect, useState } from 'react';
import { api, parseError } from '../lib/api';

const AUTO_HIDE_SECONDS = 30;

/**
 * Muestra el secreto en claro. El valor vive solo en el estado del componente,
 * se oculta solo tras unos segundos y se descarta al cerrar.
 */
export default function SecretModal({ credential, secret, onClose }) {
    const { message } = App.useApp();
    const [left, setLeft] = useState(AUTO_HIDE_SECONDS);

    useEffect(() => {
        if (!secret) return undefined;
        setLeft(AUTO_HIDE_SECONDS);
        const timer = setInterval(() => setLeft((s) => s - 1), 1000);
        return () => clearInterval(timer);
    }, [secret]);

    useEffect(() => {
        if (secret && left <= 0) onClose();
    }, [left, secret, onClose]);

    const copy = async () => {
        try {
            // Copiar es una acción distinta de ver: se audita por separado.
            const { data } = await api.post(`/credentials/${credential.id}/reveal`, { confirm: true, purpose: 'copy' });
            await navigator.clipboard.writeText(data.data.secret ?? '');
            message.success('Secreto copiado al portapapeles');
        } catch (e) {
            message.error(parseError(e).message);
        }
    };

    return (
        <Modal open={Boolean(secret)} onCancel={onClose} title={`Secreto: ${credential?.name ?? ''}`} destroyOnHidden
            footer={<Button onClick={onClose} icon={<EyeInvisibleOutlined />}>Ocultar</Button>}>
            <Typography.Paragraph type="secondary">Esta consulta quedó registrada en auditoría. Se ocultará en {Math.max(left, 0)} s.</Typography.Paragraph>
            <div className="secret-box">{secret}</div>
            {credential?.abilities?.copy && (
                <Space style={{ marginTop: 12 }}><Button icon={<CopyOutlined />} onClick={copy}>Copiar</Button></Space>
            )}
        </Modal>
    );
}
