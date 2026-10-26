import AccessMatrix from '../../components/AccessMatrix';
import PageTitle from '../../components/PageTitle';

export default function ProfilesIndex() {
    return (
        <>
            <PageTitle title="Perfiles" subtitle="Agrupaciones de permisos que se suman a los del rol" />
            <AccessMatrix endpoint="profiles" singular="perfil" plural="perfiles" />
        </>
    );
}
