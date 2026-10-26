import { useEffect, useState } from 'react';
import { api } from '../lib/api';

let cache = null;

/** Catálogos (tipos, criticidad, entornos, estados), cacheados por sesión de página. */
export function useMeta() {
    const [meta, setMeta] = useState(cache);

    useEffect(() => {
        if (cache) return;
        api.get('/meta').then(({ data }) => {
            cache = data.data;
            setMeta(cache);
        });
    }, []);

    return meta;
}
