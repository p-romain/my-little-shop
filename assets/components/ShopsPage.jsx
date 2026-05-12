import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useInfiniteShops } from '../hooks/useInfiniteShops';
import { authorizationHeaders } from '../lib/api';
import { EmptyState, ErrorBox, FormField, Panel } from './ui';
import { IconAdd, IconEdit, IconFilter, IconLocate } from './icons';

function readFiltersFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const name = params.get('name');
    const lat = params.get('lat');
    const lon = params.get('lon');
    const radius = params.get('radius');

    const hasLocation = lat !== null || lon !== null || radius !== null;
    const hasName = name !== null && name.trim() !== '';

    if (!hasName && !hasLocation) {
        return null;
    }

    return {
        name: hasName ? name : null,
        latitude: lat !== null ? Number(lat) : null,
        longitude: lon !== null ? Number(lon) : null,
        radius: radius !== null ? Number(radius) : null,
    };
}

function formatDistance(meters) {
    if (meters == null) {
        return null;
    }
    if (meters < 1000) {
        return `${Math.round(meters)} m`;
    }
    if (meters < 10000) {
        return `${(meters / 1000).toFixed(1)} km`;
    }
    return `${Math.round(meters / 1000)} km`;
}

function generateLeafletHtml(shopLat, shopLon, shopName, shopAddress, filterLat, filterLon) {
    const viewScript = filterLat !== null
        ? `map.fitBounds([[${shopLat},${shopLon}],[${filterLat},${filterLon}]],{padding:[60,60],maxZoom:16});`
        : `map.setView([${shopLat},${shopLon}],15);`;

    // JSON.stringify produces a safe JS string literal (handles quotes, newlines, special chars)
    const jsName    = JSON.stringify(shopName || '');
    const jsAddress = JSON.stringify(shopAddress || '');

    const filterMarkers = filterLat !== null ? `
L.polyline([[${shopLat},${shopLon}],[${filterLat},${filterLon}]],
    {color:'#3b82f6',dashArray:'7 5',weight:3,opacity:1}).addTo(map);
L.marker([${filterLat},${filterLon}],{icon:makeIcon('#3b82f6','#1e40af')})
    .bindTooltip('You are here',{permanent:true,direction:'top',className:'ml',offset:[0,-46]})
    .addTo(map);` : '';

    // Split </script> to avoid template literal parsing issues in some bundlers
    const closeScript = '</' + 'script>';

    return `<!DOCTYPE html>
<html><head>
<meta charset="utf-8"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">${closeScript}
<style>
html,body,#map{height:100%;margin:0;padding:0;}
.ml{background:#fff;border:1px solid #d1d5db;border-radius:5px;padding:4px 8px;font-size:12px;line-height:1.4;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.18);opacity:1!important;}
.ml::before{border-top-color:#d1d5db;}
</style>
</head>
<body>
<div id="map"></div>
<script>
function makeIcon(fill,stroke){
    var svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 36" width="24" height="36">'
        +'<path d="M12,0C5.4,0,0,5.4,0,12C0,21,12,36,12,36S24,21,24,12C24,5.4,18.6,0,12,0Z" fill="'+fill+'" stroke="'+stroke+'" stroke-width="1.5"/>'
        +'<circle cx="12" cy="12" r="4" fill="white"/>'
        +'</svg>';
    return L.divIcon({html:svg,iconSize:[24,36],iconAnchor:[12,36],popupAnchor:[0,-36],className:''});
}
var map=L.map('map',{zoomControl:true});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors'}).addTo(map);
var name=${jsName},addr=${jsAddress};
var shopLabel='<strong>'+name+'</strong>'+(addr?'<br><span style="color:#6b7280;font-size:11px">'+addr+'</span>':'');
L.marker([${shopLat},${shopLon}],{icon:makeIcon('#22c55e','#15803d')})
    .bindTooltip(shopLabel,{permanent:true,direction:'top',className:'ml',offset:[0,-46]})
    .addTo(map);
${filterMarkers}
${viewScript}
${closeScript}
</body>
</html>`;
}

export function ShopsPage({ token }) {
    const [activeFilters, setActiveFilters] = useState(() => readFiltersFromUrl());
    const { shops, loading, loadingMore, error, violations, hasNextPage, reload, loadMore } = useInfiniteShops(token, activeFilters);
    const [modalMode, setModalMode] = useState(null);
    const [editingShopId, setEditingShopId] = useState(null);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [selectedShopId, setSelectedShopId] = useState(null);
    const filterFieldErrors = useMemo(() => validationErrorsByPath(violations), [violations]);
    const selectedShop = shops.find((shop) => shop.id === selectedShopId) ?? shops.find(hasCoordinates) ?? null;

    const hasActiveFilters = activeFilters !== null && (activeFilters.name !== null || activeFilters.latitude !== null);
    const filterPoint = (activeFilters !== null && activeFilters.latitude !== null)
        ? { latitude: activeFilters.latitude, longitude: activeFilters.longitude }
        : null;

    useEffect(() => {
        const params = new URLSearchParams();
        if (activeFilters?.name) {
            params.set('name', activeFilters.name);
        }
        if (activeFilters?.latitude !== null && activeFilters?.latitude !== undefined) {
            params.set('lat', String(activeFilters.latitude));
        }
        if (activeFilters?.longitude !== null && activeFilters?.longitude !== undefined) {
            params.set('lon', String(activeFilters.longitude));
        }
        if (activeFilters?.radius !== null && activeFilters?.radius !== undefined) {
            params.set('radius', String(activeFilters.radius));
        }
        const search = params.toString();
        window.history.replaceState(null, '', search ? `?${search}` : window.location.pathname);
    }, [activeFilters]);

    useEffect(() => {
        if (!shops.length) {
            setSelectedShopId(null);
            return;
        }

        if (!selectedShopId || !shops.some((shop) => shop.id === selectedShopId)) {
            setSelectedShopId((shops.find(hasCoordinates) ?? shops[0]).id);
        }
    }, [selectedShopId, shops]);

    useEffect(() => {
        if (Object.keys(filterFieldErrors).length > 0) {
            setFiltersOpen(true);
        }
    }, [filterFieldErrors]);

    function closeModal() {
        setModalMode(null);
        setEditingShopId(null);
    }

    function handleShopScroll(event) {
        const element = event.currentTarget;
        const threshold = 120;

        if (element.scrollTop + element.clientHeight >= element.scrollHeight - threshold) {
            loadMore();
        }
    }

    return (
        <main className="h-full min-h-0 flex-1 overflow-hidden p-4 sm:p-6">
            <div className="mx-auto flex h-full max-w-6xl flex-col gap-4">
                <div className="shrink-0">
                    <h1 className="text-2xl font-semibold tracking-normal">Shops</h1>
                    <p className="text-sm text-gray-500">Store locations and assigned managers.</p>
                </div>

                {error && <ErrorBox message={error} />}

                <section className="grid min-h-0 flex-1 grid-rows-[minmax(0,1fr)_minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(240px,16rem)_1fr] lg:grid-rows-none">
                    <Panel
                        title="Shops"
                        className="flex min-h-0 flex-col"
                        action={(
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setModalMode('create')}
                                    className="cursor-pointer rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                >
                                    <IconAdd className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setFiltersOpen(true)}
                                    className={hasActiveFilters
                                        ? 'cursor-pointer rounded border border-blue-400 bg-blue-50 px-3 py-1 text-sm text-blue-700 hover:bg-blue-100'
                                        : 'cursor-pointer rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-100'}
                                    aria-label="Filter shops"
                                >
                                    <IconFilter className="h-4 w-4" />
                                </button>
                            </div>
                        )}
                    >
                        <div onScroll={handleShopScroll} className="min-h-0 flex-1 overflow-y-auto divide-y divide-gray-100">
                            {loading && <EmptyState label="Loading shops..." />}
                            {!loading && shops.map((shop) => (
                                <ShopListItem
                                    key={shop.id}
                                    shop={shop}
                                    selected={shop.id === selectedShop?.id}
                                    onSelect={() => setSelectedShopId(shop.id)}
                                    onEdit={() => {
                                        setEditingShopId(shop.id);
                                        setModalMode('edit');
                                    }}
                                />
                            ))}
                            {!loading && shops.length === 0 && <EmptyState label="No shops found." />}
                            {loadingMore && <EmptyState label="Loading more shops..." />}
                            {!hasNextPage && shops.length > 0 && (
                                <p className="px-4 py-3 text-center text-xs text-gray-400">End of list</p>
                            )}
                        </div>
                    </Panel>

                    <Panel title={selectedShop?.name ?? 'Shop'} className="flex min-h-0 flex-col">
                        <div className="min-h-0 flex-1 bg-gray-100">
                            <ShopMap shop={selectedShop} filterPoint={filterPoint} />
                        </div>
                    </Panel>
                </section>
            </div>

            {filtersOpen && (
                <FiltersModal
                    initialFilters={activeFilters}
                    initialFieldErrors={filterFieldErrors}
                    onClose={() => setFiltersOpen(false)}
                    onApply={(filters) => {
                        setActiveFilters(filters);
                        setFiltersOpen(false);
                    }}
                    token={token}
                />
            )}

            {modalMode && (
                <ShopModal
                    mode={modalMode}
                    shopId={editingShopId}
                    token={token}
                    onClose={closeModal}
                    onSaved={() => {
                        closeModal();
                        reload();
                    }}
                />
            )}
        </main>
    );
}

function ShopListItem({ shop, selected, onSelect, onEdit }) {
    const distance = formatDistance(shop.distance);

    return (
        <div
            className={selected
                ? 'flex items-start justify-between gap-3 bg-blue-50 px-4 py-3'
                : 'flex items-start justify-between gap-3 px-4 py-3 hover:bg-gray-50'}
        >
            <button
                type="button"
                onClick={onSelect}
                className="min-w-0 flex-1 cursor-pointer text-left"
            >
                <span className="block truncate text-sm font-medium">{shop.name}</span>
                <span className="block truncate text-xs text-gray-500">{shop.address || 'No address'}</span>
                {distance !== null && (
                    <span className="block text-xs text-blue-600">{distance}</span>
                )}
            </button>
            <button
                type="button"
                aria-label={`Edit ${shop.name}`}
                title="Edit"
                onClick={onEdit}
                className="shrink-0 cursor-pointer rounded border border-gray-300 bg-white px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
            >
                <IconEdit className="h-4 w-4" />
            </button>
        </div>
    );
}

function ShopMap({ shop, filterPoint }) {
    const [blobUrl, setBlobUrl] = useState(null);

    const shopId      = shop?.id ?? null;
    const shopLat     = shop?.latitude ?? null;
    const shopLon     = shop?.longitude ?? null;
    const shopName    = shop?.name ?? '';
    const shopAddress = shop?.address ?? '';
    const filterLat   = filterPoint?.latitude ?? null;
    const filterLon   = filterPoint?.longitude ?? null;

    useEffect(() => {
        if (shopLat == null || shopLon == null) {
            setBlobUrl(null);
            return;
        }

        const html = generateLeafletHtml(
            Number(shopLat), Number(shopLon),
            shopName, shopAddress,
            filterLat !== null ? Number(filterLat) : null,
            filterLon !== null ? Number(filterLon) : null,
        );
        const blob = new Blob([html], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        setBlobUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [shopId, shopLat, shopLon, shopName, shopAddress, filterLat, filterLon]);

    if (!shop || !hasCoordinates(shop)) {
        return (
            <div className="flex h-full items-center justify-center px-4 text-center text-sm text-gray-500">
                Select a shop with GPS coordinates to display it on the map.
            </div>
        );
    }

    if (blobUrl !== null) {
        return (
            <iframe
                key={blobUrl}
                title={`Map for ${shop.name}`}
                src={blobUrl}
                className="h-full w-full border-0"
            />
        );
    }

    return null;
}

function AddressSuggestionsDropdown({ rect, suggestions, onSelect }) {
    if (!rect || suggestions.length === 0) {
        return null;
    }

    return (
        <div
            className="fixed z-[60] max-h-56 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"
            style={{ top: rect.top, left: rect.left, width: rect.width }}
        >
            {suggestions.map((suggestion) => (
                <button
                    key={suggestion.place_id}
                    type="button"
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onSelect(suggestion);
                    }}
                    className="block w-full cursor-pointer border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-700 last:border-b-0 hover:bg-gray-50"
                >
                    {suggestion.display_name}
                </button>
            ))}
        </div>
    );
}

function SingleSelectDropdown({ options, value, onChange, placeholder = 'Select…', loading = false }) {
    const [open, setOpen] = useState(false);
    const [rect, setRect] = useState(null);
    const triggerRef = useRef(null);
    const dropdownRef = useRef(null);

    useEffect(() => {
        if (!open) { setRect(null); return; }
        if (triggerRef.current) {
            const r = triggerRef.current.getBoundingClientRect();
            setRect({ top: r.bottom + 4, left: r.left, width: r.width });
        }
    }, [open]);

    useEffect(() => {
        if (!open) return;
        function onMouseDown(e) {
            if (
                triggerRef.current && !triggerRef.current.contains(e.target) &&
                dropdownRef.current && !dropdownRef.current.contains(e.target)
            ) setOpen(false);
        }
        document.addEventListener('mousedown', onMouseDown);
        return () => document.removeEventListener('mousedown', onMouseDown);
    }, [open]);

    useEffect(() => {
        if (!open) return;
        function onKey(e) { if (e.key === 'Escape') setOpen(false); }
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    const selected = options.find((o) => String(o.value) === String(value));

    return (
        <>
            <button
                ref={triggerRef}
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="box-border flex w-full cursor-pointer items-center justify-between rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
            >
                <span className={selected ? 'truncate text-gray-900' : 'text-gray-400'}>
                    {loading ? 'Loading…' : (selected ? selected.label : placeholder)}
                </span>
                <svg viewBox="0 0 20 20" className={`ml-2 h-4 w-4 shrink-0 text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`} fill="currentColor">
                    <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
            </button>

            {open && rect && (
                <div
                    ref={dropdownRef}
                    className="fixed z-[60] max-h-60 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"
                    style={{ top: rect.top, left: rect.left, width: rect.width }}
                >
                    {options.length === 0 && (
                        <p className="px-3 py-2 text-sm text-gray-400">No options available.</p>
                    )}
                    {options.map((option) => {
                        const isSelected = String(option.value) === String(value);
                        return (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => { onChange(String(option.value)); setOpen(false); }}
                                className={`flex w-full cursor-pointer items-center gap-2 border-b border-gray-100 px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-gray-50 ${isSelected ? 'font-medium text-blue-700' : 'text-gray-700'}`}
                            >
                                <span className="flex-1 truncate">{option.label}</span>
                                {isSelected && (
                                    <svg viewBox="0 0 20 20" className="h-4 w-4 shrink-0 text-blue-600" fill="currentColor">
                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                    </svg>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
        </>
    );
}

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-600">{message}</p>;
}

function validationErrorsByPath(violations) {
    if (!Array.isArray(violations)) {
        return {};
    }

    return violations.reduce((errors, violation) => {
        if (violation?.propertyPath && violation?.message && !errors[violation.propertyPath]) {
            errors[violation.propertyPath] = violation.message;
        }

        return errors;
    }, {});
}

function ShopModal({ mode, shopId, token, onClose, onSaved }) {
    const isEdit = mode === 'edit';
    const [form, setForm] = useState({ name: '', address: '', latitude: '', longitude: '', managerId: '' });
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});
    const suppressSuggestions = useRef(false);
    const addressInputRef = useRef(null);
    const [suggestions, setSuggestions] = useState([]);
    const [dropdownRect, setDropdownRect] = useState(null);

    useEffect(() => {
        async function loadUsers() {
            try {
                const response = await fetch('/api/users', {
                    headers: authorizationHeaders(token),
                });
                if (response.ok) {
                    const json = await response.json();
                    setUsers(json.items ?? []);
                }
            } catch {
                // ignore
            }
        }
        loadUsers();
    }, [token]);

    useEffect(() => {
        if (!isEdit || !shopId) {
            return;
        }

        let cancelled = false;

        async function loadShop() {
            setLoading(true);
            setError('');
            setFieldErrors({});

            try {
                const response = await fetch(`/api/shops/${shopId}`, {
                    headers: authorizationHeaders(token),
                });

                if (!response.ok) {
                    throw new Error('Unable to load this shop.');
                }

                const shop = await response.json();

                if (!cancelled) {
                    suppressSuggestions.current = true;
                    setForm({
                        name: shop.name || '',
                        address: shop.address || '',
                        latitude: shop.latitude ?? '',
                        longitude: shop.longitude ?? '',
                        managerId: shop.manager?.id ?? '',
                    });
                }
            } catch (loadError) {
                if (!cancelled) {
                    setError(loadError instanceof Error ? loadError.message : 'Unable to load this shop.');
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        loadShop();

        return () => {
            cancelled = true;
        };
    }, [isEdit, shopId, token]);

    useEffect(() => {
        if (suppressSuggestions.current) {
            suppressSuggestions.current = false;
            return;
        }

        if (form.address.trim().length < 3) {
            setSuggestions([]);
            return;
        }

        const timeout = window.setTimeout(async () => {
            try {
                const url = new URL('https://nominatim.openstreetmap.org/search');
                url.searchParams.set('format', 'jsonv2');
                url.searchParams.set('limit', '5');
                url.searchParams.set('q', form.address);

                const response = await fetch(url);

                if (!response.ok) {
                    return;
                }

                setSuggestions(await response.json());
            } catch {
                setSuggestions([]);
            }
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [form.address]);

    useEffect(() => {
        if (suggestions.length > 0 && addressInputRef.current) {
            const rect = addressInputRef.current.getBoundingClientRect();
            setDropdownRect({ top: rect.bottom + 2, left: rect.left, width: rect.width });
        } else {
            setDropdownRect(null);
        }
    }, [suggestions]);

    function updateField(field, value) {
        setForm((current) => ({ ...current, [field]: value }));
        setFieldErrors((current) => {
            if (!current[field]) {
                return current;
            }

            const next = { ...current };
            delete next[field];

            return next;
        });
    }

    function selectSuggestion(suggestion) {
        suppressSuggestions.current = true;
        setForm((current) => ({
            ...current,
            address: suggestion.display_name,
            latitude: suggestion.lat,
            longitude: suggestion.lon,
        }));
        setSuggestions([]);
    }

    async function submit(event) {
        event.preventDefault();
        setSaving(true);
        setError('');
        setFieldErrors({});
        let hasValidationErrors = false;

        try {
            const response = await fetch(isEdit ? `/api/shops/${shopId}` : '/api/shops', {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    ...authorizationHeaders(token),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ...form,
                    latitude: form.latitude !== '' ? Number(form.latitude) : null,
                    longitude: form.longitude !== '' ? Number(form.longitude) : null,
                    managerId: form.managerId !== '' ? Number(form.managerId) : null,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                const nextFieldErrors = validationErrorsByPath(data.violations);
                hasValidationErrors = Object.keys(nextFieldErrors).length > 0;
                setFieldErrors(nextFieldErrors);

                throw new Error(data.error || 'Unable to save this shop.');
            }

            onSaved();
        } catch (saveError) {
            if (!hasValidationErrors) {
                setError(saveError instanceof Error ? saveError.message : 'Unable to save this shop.');
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-white/40 p-4 backdrop-blur-md">
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />
            <section className="relative z-10 w-full max-w-xl rounded-lg border border-white/50 bg-white/85 shadow-xl">
                <header className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h2 className="text-base font-semibold">{isEdit ? 'Edit shop' : 'New shop'}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="cursor-pointer rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"
                    >
                        Close
                    </button>
                </header>

                <form onSubmit={submit} noValidate className="flex flex-col gap-4 p-4">
                    {loading ? (
                        <EmptyState label="Loading shop..." />
                    ) : (
                        <>
                            <FormField label="Name" required>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(event) => updateField('name', event.target.value)}
                                    className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <FieldError message={fieldErrors.name} />
                            </FormField>

                            <FormField label="Manager" required>
                                <SingleSelectDropdown
                                    options={users.map((u) => ({ value: u.id, label: u.email }))}
                                    value={form.managerId}
                                    onChange={(val) => updateField('managerId', val)}
                                    placeholder="Select a manager..."
                                    loading={users.length === 0 && loading}
                                />
                                <FieldError message={fieldErrors.managerId} />
                            </FormField>

                            <FormField label="Address" required>
                                <input
                                    ref={addressInputRef}
                                    type="text"
                                    value={form.address}
                                    onChange={(event) => updateField('address', event.target.value)}
                                    onBlur={() => setTimeout(() => setSuggestions([]), 150)}
                                    className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <FieldError message={fieldErrors.address} />
                                <p className="mt-1 text-xs text-gray-400">Selecting a suggestion will automatically fill in the latitude and longitude.</p>
                            </FormField>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField label="Latitude" required>
                                    <input
                                        type="text"
                                        inputMode="decimal"
                                        value={form.latitude}
                                        onChange={(event) => updateField('latitude', event.target.value)}
                                        className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                                    />
                                    <FieldError message={fieldErrors.latitude} />
                                </FormField>
                                <FormField label="Longitude" required>
                                    <input
                                        type="text"
                                        inputMode="decimal"
                                        value={form.longitude}
                                        onChange={(event) => updateField('longitude', event.target.value)}
                                        className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                                    />
                                    <FieldError message={fieldErrors.longitude} />
                                </FormField>
                            </div>

                            {error && <ErrorBox message={error} />}

                            <footer className="flex justify-end gap-2 border-t border-gray-200 pt-4">
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="cursor-pointer rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="cursor-pointer rounded bg-blue-600 px-3 py-1 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {saving ? 'Saving...' : 'Save'}
                                </button>
                            </footer>
                        </>
                    )}
                </form>
            </section>

            {dropdownRect && (
                <AddressSuggestionsDropdown
                    rect={dropdownRect}
                    suggestions={suggestions}
                    onSelect={selectSuggestion}
                />
            )}
        </div>
    );
}

function FiltersModal({ initialFilters, initialFieldErrors = {}, onClose, onApply, token }) {
    const [form, setForm] = useState({
        name: initialFilters?.name ?? '',
        address: '',
        latitude: initialFilters?.latitude != null ? String(initialFilters.latitude) : '',
        longitude: initialFilters?.longitude != null ? String(initialFilters.longitude) : '',
        radius: initialFilters?.radius != null ? String(initialFilters.radius) : '',
    });
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState(initialFieldErrors);
    const [locating, setLocating] = useState(false);
    const suppressSuggestions = useRef(false);
    const addressInputRef = useRef(null);
    const [suggestions, setSuggestions] = useState([]);
    const [dropdownRect, setDropdownRect] = useState(null);

    useEffect(() => {
        setFieldErrors(initialFieldErrors);
    }, [initialFieldErrors]);

    useEffect(() => {
        if (suppressSuggestions.current) {
            suppressSuggestions.current = false;
            return;
        }

        if (form.address.trim().length < 3) {
            setSuggestions([]);
            return;
        }

        const timeout = window.setTimeout(async () => {
            try {
                const url = new URL('https://nominatim.openstreetmap.org/search');
                url.searchParams.set('format', 'jsonv2');
                url.searchParams.set('limit', '5');
                url.searchParams.set('q', form.address);

                const response = await fetch(url);

                if (!response.ok) {
                    return;
                }

                setSuggestions(await response.json());
            } catch {
                setSuggestions([]);
            }
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [form.address]);

    useEffect(() => {
        if (suggestions.length > 0 && addressInputRef.current) {
            const rect = addressInputRef.current.getBoundingClientRect();
            setDropdownRect({ top: rect.bottom + 2, left: rect.left, width: rect.width });
        } else {
            setDropdownRect(null);
        }
    }, [suggestions]);

    function updateField(field, value) {
        setForm((current) => ({ ...current, [field]: value }));
        setError('');
        setFieldErrors((current) => {
            if (!current[field]) {
                return current;
            }

            const next = { ...current };
            delete next[field];

            return next;
        });
    }

    function selectSuggestion(suggestion) {
        suppressSuggestions.current = true;
        setForm((current) => ({
            ...current,
            address: suggestion.display_name,
            latitude: suggestion.lat,
            longitude: suggestion.lon,
        }));
        setSuggestions([]);
    }

    function handleLocate() {
        if (!navigator.geolocation || locating) return;
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    const { latitude, longitude } = position.coords;
                    const url = new URL('https://nominatim.openstreetmap.org/reverse');
                    url.searchParams.set('format', 'jsonv2');
                    url.searchParams.set('lat', String(latitude));
                    url.searchParams.set('lon', String(longitude));
                    const response = await fetch(url);
                    const data = response.ok ? await response.json() : null;
                    suppressSuggestions.current = true;
                    setForm((current) => ({
                        ...current,
                        address: data?.display_name ?? '',
                        latitude: String(latitude),
                        longitude: String(longitude),
                    }));
                } catch {
                    // ignore
                } finally {
                    setLocating(false);
                }
            },
            () => setLocating(false),
            { timeout: 10000 },
        );
    }

    function handleSubmit(event) {
        event.preventDefault();

        const filters = {
            name: form.name.trim() || null,
            latitude: form.latitude !== '' ? Number(form.latitude) : null,
            longitude: form.longitude !== '' ? Number(form.longitude) : null,
            radius: form.radius !== '' ? Number(form.radius) : null,
        };

        onApply(
            filters.name === null && filters.latitude === null && filters.longitude === null && filters.radius === null
                ? null
                : filters,
        );
    }

    function handleReset() {
        onApply(null);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-white/40 p-4 backdrop-blur-md">
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />
            <section className="relative z-10 w-full max-w-xl rounded-lg border border-white/50 bg-white/85 shadow-xl">
                <header className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h2 className="text-base font-semibold">Filter shops</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="cursor-pointer rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"
                    >
                        Close
                    </button>
                </header>

                <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4 p-4">
                    <FormField label="Name">
                        <input
                            type="text"
                            value={form.name}
                            onChange={(event) => updateField('name', event.target.value)}
                            placeholder="Search by name…"
                            className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                        />
                    </FormField>

                    <FormField label="Address">
                        <div className="flex gap-2">
                            <input
                                ref={addressInputRef}
                                type="text"
                                value={form.address}
                                onChange={(event) => updateField('address', event.target.value)}
                                onBlur={() => setTimeout(() => setSuggestions([]), 150)}
                                placeholder="Type an address to prefill lat/lon…"
                                className="box-border min-w-0 flex-1 rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                            />
                            <button
                                type="button"
                                onClick={handleLocate}
                                disabled={locating}
                                title="Use my current location"
                                className="flex shrink-0 cursor-pointer items-center justify-center rounded border border-gray-300 bg-white px-2.5 text-gray-500 hover:bg-gray-50 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <IconLocate className={`h-4 w-4 ${locating ? 'animate-pulse' : ''}`} />
                            </button>
                        </div>
                        <p className="mt-1 text-xs text-gray-400">Selecting a suggestion will automatically fill in the latitude and longitude.</p>
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Latitude">
                            <input
                                type="text"
                                inputMode="decimal"
                                value={form.latitude}
                                onChange={(event) => updateField('latitude', event.target.value)}
                                className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                            />
                            <FieldError message={fieldErrors.latitude} />
                        </FormField>
                        <FormField label="Longitude">
                            <input
                                type="text"
                                inputMode="decimal"
                                value={form.longitude}
                                onChange={(event) => updateField('longitude', event.target.value)}
                                className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                            />
                            <FieldError message={fieldErrors.longitude} />
                        </FormField>
                    </div>

                    <FormField label="Radius">
                        <select
                            value={form.radius}
                            onChange={(event) => updateField('radius', event.target.value)}
                            className="box-border w-full rounded border border-gray-300 bg-white p-2 text-sm focus:border-blue-500 focus:outline-none"
                        >
                            <option value="">—</option>
                            <option value="50">50 m</option>
                            <option value="1000">1 km</option>
                            <option value="5000">5 km</option>
                            <option value="20000">20 km</option>
                            <option value="100000">100 km</option>
                            <option value="1000000">1 000 km</option>
                        </select>
                        <FieldError message={fieldErrors.radius} />
                    </FormField>

                    {error && <ErrorBox message={error} />}

                    <footer className="flex justify-end border-t border-gray-200 pt-4">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={handleReset}
                                className="cursor-pointer text-sm text-gray-500 underline-offset-2 hover:text-gray-700 hover:underline"
                            >
                                Reset
                            </button>
                            <button
                                type="submit"
                                className="cursor-pointer rounded bg-blue-600 px-3 py-1 text-sm font-medium text-white hover:bg-blue-700"
                            >
                                Apply
                            </button>
                        </div>
                    </footer>
                </form>
            </section>

            {dropdownRect && (
                <AddressSuggestionsDropdown
                    rect={dropdownRect}
                    suggestions={suggestions}
                    onSelect={selectSuggestion}
                />
            )}
        </div>
    );
}

function hasCoordinates(shop) {
    return shop.latitude !== null && shop.longitude !== null && shop.latitude !== undefined && shop.longitude !== undefined;
}

function osmEmbedUrl(latitude, longitude) {
    const lat = Number(latitude);
    const lon = Number(longitude);
    const delta = 0.012;
    const url = new URL('https://www.openstreetmap.org/export/embed.html');

    url.searchParams.set('bbox', `${lon - delta},${lat - delta},${lon + delta},${lat + delta}`);
    url.searchParams.set('layer', 'mapnik');
    url.searchParams.set('marker', `${lat},${lon}`);

    return url.toString();
}
