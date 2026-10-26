import AccessMatrix from '../../components/AccessMatrix';
import PageTitle from '../../components/PageTitle';

export default function RolesIndex() {
    return (
        <>
            <PageTitle title="Roles y permisos" subtitle="Matriz de permisos por rol" />
            <AccessMatrix endpoint="roles" singular="rol" plural="roles" />
        </>
    );
}
