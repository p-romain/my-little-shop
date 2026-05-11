import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { authorizationHeaders } from '../lib/api';

const pageSize = 30;

export function useInfiniteShops(token, filters = null) {
    const [shops, setShops] = useState([]);
    const [page, setPage] = useState(1);
    const [hasNextPage, setHasNextPage] = useState(true);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState('');
    const loadingRef = useRef(false);

    const filterName = filters?.name ?? null;
    const filterLat = filters?.latitude ?? null;
    const filterLon = filters?.longitude ?? null;
    const filterRadius = filters?.radius ?? null;

    const loadPage = useCallback(async (nextPage, replace = false) => {
        if (loadingRef.current) {
            return;
        }

        loadingRef.current = true;

        if (replace) {
            setLoading(true);
        } else {
            setLoadingMore(true);
        }

        setError('');

        try {
            const url = new URL('/api/shops', window.location.origin);
            url.searchParams.set('page', String(nextPage));

            if (filterName) {
                url.searchParams.set('name', filterName);
            }

            if (filterLat !== null) {
                url.searchParams.set('latitude', String(filterLat));
                url.searchParams.set('longitude', String(filterLon));
                url.searchParams.set('radius', String(filterRadius));
            }

            const response = await fetch(url, {
                headers: authorizationHeaders(token),
            });

            if (!response.ok) {
                throw new Error('Unable to load shops.');
            }

            const json = await response.json();
            const nextShops = json.items ?? [];

            setShops((current) => (replace ? nextShops : [...current, ...nextShops]));
            setPage(nextPage);
            setHasNextPage(nextPage < (json.pages ?? 1));
        } catch (apiError) {
            setError(apiError instanceof Error ? apiError.message : 'Unable to reach the API.');
        } finally {
            loadingRef.current = false;
            setLoading(false);
            setLoadingMore(false);
        }
    }, [token, filterName, filterLat, filterLon, filterRadius]);

    const reload = useCallback(() => loadPage(1, true), [loadPage]);

    const loadMore = useCallback(() => {
        if (!loading && !loadingMore && hasNextPage) {
            loadPage(page + 1);
        }
    }, [hasNextPage, loadPage, loading, loadingMore, page]);

    useEffect(() => {
        reload();
    }, [reload]);

    return useMemo(
        () => ({ shops, loading, loadingMore, error, hasNextPage, reload, loadMore }),
        [error, hasNextPage, loadMore, loading, loadingMore, reload, shops]
    );
}
