import { Card } from 'antd';
import CredentialsTable from '../../components/CredentialsTable';
import PageTitle from '../../components/PageTitle';

export default function CredentialsIndex() {
    return (
        <>
            <PageTitle title="Credenciales" subtitle="Los secretos están enmascarados; revelarlos requiere permiso y queda auditado" />
            <Card><CredentialsTable /></Card>
        </>
    );
}
