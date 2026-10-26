import { Head } from '@inertiajs/react';
import { Typography } from 'antd';

export default function PageTitle({ title, subtitle, extra }) {
    return (
        <>
            <Head title={title} />
            <div className="page-title">
                <div>
                    <Typography.Title level={2} style={{ margin: 0 }}>{title}</Typography.Title>
                    {subtitle && <Typography.Text type="secondary">{subtitle}</Typography.Text>}
                </div>
                <div>{extra}</div>
            </div>
        </>
    );
}
