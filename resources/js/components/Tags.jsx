import { Tag } from 'antd';

/** Etiqueta de color por criticidad (el color viene del catálogo del backend). */
export function CriticalityTag({ level }) {
    if (!level) return null;
    return <Tag color={level.color} style={{ fontWeight: 500 }}>{level.name}</Tag>;
}

const STATUS_COLORS = { active: 'success', inactive: 'default', revoked: 'error', expired: 'warning' };

export function StatusTag({ status }) {
    if (!status) return null;
    return <Tag color={STATUS_COLORS[status.value] ?? 'default'}>{status.label}</Tag>;
}

const ENV_COLORS = { development: 'cyan', qa: 'purple', staging: 'geekblue', production: 'volcano' };

export function EnvironmentTag({ environment }) {
    if (!environment) return null;
    return <Tag color={ENV_COLORS[environment.value]}>{environment.label}</Tag>;
}
