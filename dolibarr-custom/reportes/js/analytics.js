document.addEventListener('DOMContentLoaded', () => {
    const notice = document.getElementById('ra-updating');
    if (!notice) return;
    let attempts = 0;
    const poll = async () => {
        try {
            const url = new URL(location.href); url.searchParams.set('status', '1');
            const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('status');
            const state = await response.json();
            if (!state.running) { const next = new URL(location.href); next.searchParams.delete('refresh'); location.replace(next.toString()); return; }
        } catch (_) { /* Keep the last charts visible while the worker catches up. */ }
        if (++attempts < 80) setTimeout(poll, 3000);
        else notice.textContent = 'La actualización está tardando más de lo habitual. Podés volver a consultar el panel en unos momentos.';
    };
    setTimeout(poll, 3000);
});
