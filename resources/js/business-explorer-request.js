export async function explorerRequest(fetcher, url, payload, csrf) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    let response;
    try {
    try { response = await fetcher(url, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        signal: controller.signal,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(payload),
    }); } catch (exception) {
        if (exception.name === 'AbortError') throw new Error('A consulta demorou mais que o esperado. Tente novamente.');
        throw new Error('Não foi possível conectar ao servidor. Verifique a conexão e tente novamente.');
    }
    if (response.redirected) throw new Error('Sua sessão expirou. Recarregue a página e entre novamente.');
    let body;
    try { body = await response.json(); } catch (exception) {if(exception.name==='AbortError')throw new Error('A consulta demorou mais que o esperado. Sua pesquisa foi preservada.');throw new Error('Não foi possível interpretar a resposta. Tente novamente.');}
    if (!response.ok) {
        const fieldError = Object.values(body.errors ?? {}).flat().find((message) => typeof message === 'string');
        const exception = new Error(fieldError ?? body.message ?? 'Não foi possível concluir a operação. Tente novamente.');
        exception.fields = Object.keys(body.errors ?? {});if(typeof body.correlation_id==='string'&&/^[a-f0-9-]{36}$/.test(body.correlation_id))exception.correlationId=body.correlation_id;throw exception;
    }
    return body;
    } finally { clearTimeout(timeout); }
}
