import { ArrowLeftOutlined } from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import { Alert, Badge, Button, Card, Col, Descriptions, Row, Skeleton, Statistic, Tag, Typography } from 'antd';
import { useCallback, useEffect, useState } from 'react';
import CredentialsTable from '../../components/CredentialsTable';
import PageTitle from '../../components/PageTitle';
import { CriticalityTag } from '../../components/Tags';
import { api, fmtDate, parseError } from '../../lib/api';
import { useAuth } from '../../lib/permissions';

const STATUS_LABEL = { active: 'Activas', inactive: 'Inactivas', revoked: 'Revocadas', expired: 'Expiradas' };

export default function ProjectShow({ projectId }) {
    const { can } = useAuth();
    const [state, setState] = useState({ project: null, metrics: null, error: null });

    const load = useCallback(() => {
        api.get(`/projects/${projectId}`)
            .then(({ data }) => setState({ project: data.data, metrics: data.metrics, error: null }))
            .catch((e) => setState((s) => ({ ...s, error: parseError(e).message })));
    }, [projectId]);

    useEffect(() => { load(); }, [load]);

    const { project, metrics, error } = state;
    if (error) return <Alert type="error" showIcon message={error} />;
    if (!project) return <Skeleton active paragraph={{ rows: 8 }} />;

    return (
        <>
            <PageTitle title={project.name} subtitle={project.code}
                extra={<Link href="/projects"><Button icon={<ArrowLeftOutlined />}>Volver</Button></Link>} />

            <Row gutter={[16, 16]}>
                <Col xs={24} lg={10}>
                    <Card title="Información">
                        <Descriptions column={1} size="small">
                            <Descriptions.Item label="Estado"><Badge status={project.is_active ? 'success' : 'default'} text={project.is_active ? 'Activo' : 'Inactivo'} /></Descriptions.Item>
                            <Descriptions.Item label="Responsable">{project.owner?.name ?? '—'}</Descriptions.Item>
                            <Descriptions.Item label="Etiquetas">{project.tags.length ? project.tags.map((t) => <Tag key={t} color="blue">{t}</Tag>) : '—'}</Descriptions.Item>
                            <Descriptions.Item label="Creado">{fmtDate(project.created_at)}</Descriptions.Item>
                            <Descriptions.Item label="Descripción">{project.description || '—'}</Descriptions.Item>
                        </Descriptions>
                    </Card>
                </Col>
                <Col xs={24} lg={14}>
                    <Card title="Métricas del proyecto">
                        <Row gutter={[16, 16]}>
                            <Col xs={8}><Statistic title="Credenciales" value={project.credentials_count} /></Col>
                            <Col xs={8}><Statistic title="Críticas" value={project.critical_count} valueStyle={{ color: project.critical_count ? '#cf1322' : undefined }} /></Col>
                            <Col xs={8}><Statistic title="Por expirar" value={metrics.expiring} valueStyle={{ color: metrics.expiring ? '#d46b08' : undefined }} /></Col>
                        </Row>
                        <div style={{ marginTop: 16 }}>
                            <Typography.Text type="secondary">Criticidad: </Typography.Text>
                            {metrics.by_criticality.length ? metrics.by_criticality.map((m) => <span key={m.slug} style={{ marginRight: 8 }}><CriticalityTag level={m} /> {m.count}</span>) : '—'}
                        </div>
                        <div style={{ marginTop: 8 }}>
                            <Typography.Text type="secondary">Estado: </Typography.Text>
                            {metrics.by_status.length ? metrics.by_status.map((m) => <Tag key={m.status}>{STATUS_LABEL[m.status] ?? m.status}: {m.count}</Tag>) : '—'}
                        </div>
                        <div style={{ marginTop: 8 }}>
                            <Typography.Text type="secondary">Tipos: </Typography.Text>
                            {metrics.by_type.length ? metrics.by_type.map((m) => <Tag key={m.slug} color="geekblue">{m.name}: {m.count}</Tag>) : '—'}
                        </div>
                    </Card>
                </Col>
            </Row>

            {can('credentials.view') && (
                <Card title="Credenciales del proyecto" style={{ marginTop: 16 }}>
                    <CredentialsTable fixedProjectId={project.id} onChanged={load} />
                </Card>
            )}
        </>
    );
}
