/* Stable public detail URL; works at the domain root and under an installation folder. */
(function (root) {
    const api = {
        url(base, id, year) {
            if (!Number.isSafeInteger(Number(id)) || Number(id) < 1) throw new Error('Invalid asset ID');
            const url = new URL('asset.php', base);
            url.searchParams.set('id', String(id));
            if (year !== null && year !== undefined) url.searchParams.set('year', String(year));
            return url.href;
        }
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.SenaAssetQR = api;
})(globalThis);
