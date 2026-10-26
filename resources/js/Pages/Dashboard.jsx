import { ExclamationCircleOutlined, FolderOpenOutlined, KeyOutlined, WarningOutlined } from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import { Alert, Badge, Card, Col, Empty, List, Progress, Row, Skeleton, Statistic, Tag, Typography } from 'antd';
import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import PageTitle from '../components/PageTitle';
import { CriticalityTag } from '../components/Tags';
import { api, parseError } from '../lib/api';

// Mapeo de los colores preset de Ant Design (catálogo del backend) a hex para las gráficas.
const HEX = { green: '#52c41a', gold: '#faad14', orange: '#fa8c16', red: '#f5222d' };
const TYPE_COLORS = ['#1d4f91', '#13a8a8', '#722ed1', '#eb2f96', '#2f54eb', '#a0d911', '#fa541c', '#8c8c8c'];

function ChartCard({ title, empty, children }) {
    return (
        <Card title={title} style={{ height: '100%' }}>
            {empty ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Sin datos" /> : <div style={{ height: 280 }}>{children}</div>}
        </Card>
    );
}

export default function Dashboard() {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        api.get('/dashboard/metrics').then((res) => setData(res.data.data)).catch((e) => setError(parseError(e).message));
    }, []);

    if (error) return <Alert type="error" showIcon message={error} />;
    if (!data) return <><PageTitle title="Dashboard" /><Skeleton active paragraph={{ rows: 10 }} /></>;

    const criticality = data.by_criticality.map((r) => ({ ...r, fill: HEX[r.color] ?? '#999' }));
    const hasCreds = data.totals.credentials > 0;

    return (
        <>
            <PageTitle title="Dashboard" subtitle="Resumen de los proyectos a los que tienes acceso" />

            {!data.can_view_credentials && (
                <Alert type="info" showIcon style={{ marginBottom: 16 }} message="Tu rol no incluye acceso a credenciales: solo se muestran los proyectos." />
            )}

            <Row gutter={[16, 16]}>
                <Col xs={12} md={6}><Card><Statistic title="Proyectos" value={data.totals.projects} prefix={<FolderOpenOutlined />} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Credenciales" value={data.totals.credentials} prefix={<KeyOutlined />} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Críticas" value={data.totals.critical} valueStyle={{ color: data.totals.critical ? '#cf1322' : undefined }} prefix={<ExclamationCircleOutlined />} /></Card></Col>
                <Col xs={12} md={6}><Card><Statistic title="Próximas a expirar" value={data.totals.expiring} valueStyle={{ color: data.totals.expiring ? '#d46b08' : undefined }} prefix={<WarningOutlined />} /></Card></Col>
            </Row>

            <Typography.Title level={4} style={{ margin: '24px 0 12px' }}>Mis proyectos</Typography.Title>
            {data.projects.length === 0 ? (
                <Card><Empty description="No tienes proyectos asignados" /></Card>
            ) : (
                <Row gutter={[16, 16]} className="project-grid">
                    {data.projects.map((p) => (
                        <Col key={p.id} xs={24} sm={12} xl={8} xxl={6}>
                            <Link href={`/projects/${p.id}`}>
                                <Card hoverable title={p.name} extra={<Badge status={p.is_active ? 'success' : 'default'} text={p.is_active ? 'Activo' : 'Inactivo'} />}>
                                    <Typography.Text type="secondary">{p.code}</Typography.Text>
                                    <Row gutter={16} style={{ marginTop: 12 }}>
                                        <Col span={12}><Statistic title="Credenciales" value={p.credentials_count} /></Col>
                                        <Col span={12}><Statistic title="Críticas" value={p.critical_count} valueStyle={{ color: p.critical_count ? '#cf1322' : undefined }} /></Col>
                                    </Row>
                                    <Progress percent={p.credentials_count ? Math.round((p.critical_count / p.credentials_count) * 100) : 0}
                                        size="small" strokeColor="#f5222d" format={(v) => `${v}% críticas`} style={{ marginTop: 8 }} />
                                </Card>
                            </Link>
                        </Col>
                    ))}
                </Row>
            )}

            <Typography.Title level={4} style={{ margin: '24px 0 12px' }}>Métricas</Typography.Title>
            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <ChartCard title="Credenciales por proyecto" empty={!hasCreds}>
                        <ResponsiveContainer>
                            <BarChart data={data.by_project} margin={{ left: -16 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="name" tick={{ fontSize: 12 }} interval={0} />
                                <YAxis allowDecimals={false} />
                                <Tooltip />
                                <Bar dataKey="count" name="Credenciales" fill="#1d4f91" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </Col>
                <Col xs={24} lg={12}>
                    <ChartCard title="Distribución por criticidad" empty={!hasCreds}>
                        <ResponsiveContainer>
                            <PieChart>
                                <Pie data={criticality} dataKey="count" nameKey="name" innerRadius={55} outerRadius={95} paddingAngle={2}>
                                    {criticality.map((r) => <Cell key={r.slug} fill={r.fill} />)}
                                </Pie>
                                <Tooltip />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </Col>
                <Col xs={24} lg={12}>
                    <ChartCard title="Distribución por tipo de credencial" empty={!data.by_type.length}>
                        <ResponsiveContainer>
                            <PieChart>
                                <Pie data={data.by_type} dataKey="count" nameKey="name" outerRadius={95}>
                                    {data.by_type.map((r, i) => <Cell key={r.slug} fill={TYPE_COLORS[i % TYPE_COLORS.length]} />)}
                                </Pie>
                                <Tooltip />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Credenciales próximas a expirar (30 días)" style={{ height: '100%' }}>
                        {data.expiring.length === 0 ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Nada por expirar" /> : (
                            <List size="small" dataSource={data.expiring} renderItem={(c) => (
                                <List.Item extra={<Tag color={c.days_left <= 7 ? 'red' : 'orange'}>{c.days_left < 0 ? 'Vencida' : `${c.days_left} d`}</Tag>}>
                                    <List.Item.Meta title={c.name} description={<>{c.project} · {c.expires_at} · <CriticalityTag level={c.criticality} /></>} />
                                </List.Item>
                            )} />
                        )}
                    </Card>
                </Col>
            </Row>
        </>
    );
}
