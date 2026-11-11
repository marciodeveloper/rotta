export const actionNeedsReason = (status) => ['rejected', 'archived'].includes(status);

export function installAdminActions(root = document) {
    const dialog = root.querySelector('[data-action-dialog]');
    if (!dialog) return;
    const form = dialog.querySelector('[data-action-form]');
    const reason = form.elements.reason;
    const errors = dialog.querySelector('[data-action-errors]');
    const confirm = dialog.querySelector('[data-action-confirm]');
    const cancel = dialog.querySelector('[data-action-cancel]');
    const progress = dialog.querySelector('[data-action-progress]');
    let origin;
    let busy = false;

    const menuButton = (menu) => root.querySelector(`[aria-controls="${menu.id}"]`);
    const closeMenu = (menu, focus = false) => {
        menu.hidePopover();
        menuButton(menu)?.setAttribute('aria-expanded', 'false');
        if (focus) menuButton(menu)?.focus();
    };
    root.addEventListener('toggle', (event) => {
        if (event.target.matches?.('[data-action-menu]')) menuButton(event.target)?.setAttribute('aria-expanded', String(event.newState === 'open'));
    }, true);

    root.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-menu-toggle]');
        if (toggle) {
            const menu = root.getElementById(toggle.getAttribute('aria-controls'));
            if (menu.matches(':popover-open')) { closeMenu(menu, true); return; }
            menu.showPopover();
            const bounds = toggle.getBoundingClientRect();
            menu.style.left = `${Math.max(8, Math.min(bounds.right - menu.offsetWidth, innerWidth - menu.offsetWidth - 8))}px`;
            menu.style.top = `${Math.max(8, Math.min(bounds.bottom + 6, innerHeight - menu.offsetHeight - 8))}px`;
            toggle.setAttribute('aria-expanded', 'true');
            menu.querySelector('[role=menuitem]')?.focus();
            return;
        }
        const button = event.target.closest('[data-crm-action]');
        if (!button || busy) return;
        origin = button.closest('[data-action-menu]') ? menuButton(button.closest('[data-action-menu]')) : button;
        root.querySelectorAll('[data-action-menu]:popover-open').forEach((menu) => closeMenu(menu));
        form.reset(); errors.hidden = true; errors.textContent = ''; progress.textContent = '';
        dialog.querySelector('[data-action-reason-error]').textContent = '';
        reason.removeAttribute('aria-invalid');
        const resend = button.dataset.crmAction === 'resend';
        form.action = button.dataset.actionUrl;
        form.elements._method.disabled = resend;
        form.elements.status.disabled = resend;
        form.elements.status.value = button.dataset.targetStatus;
        form.elements.status_version.value = button.dataset.statusVersion;
        const needsReason = !resend && actionNeedsReason(button.dataset.targetStatus);
        reason.disabled = !needsReason; reason.required = needsReason;
        dialog.querySelector('[data-action-reason]').hidden = !needsReason;
        dialog.querySelector('[data-action-title]').textContent = button.dataset.actionLabel;
        dialog.querySelector('[data-action-record]').textContent = button.dataset.recordName;
        dialog.querySelector('[data-action-current]').textContent = button.dataset.currentStatus;
        dialog.querySelector('[data-action-target]').textContent = button.dataset.targetLabel;
        dialog.querySelector('[data-action-resend-note]').hidden = !resend;
        const refresh = dialog.querySelector('[data-action-refresh]');
        refresh.hidden = true; refresh.href = window.location.href;
        confirm.textContent = button.dataset.actionLabel;
        dialog.showModal();
        (needsReason ? reason : cancel).focus();
    });

    root.addEventListener('keydown', (event) => {
        const menu = event.target.closest('[data-action-menu]');
        if (event.target.closest('[data-menu-toggle]') && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault(); event.target.closest('[data-menu-toggle]').click(); return;
        }
        if (!menu) return;
        if (event.key === 'Escape') { event.preventDefault(); closeMenu(menu, true); return; }
        if (event.key === 'Tab') { closeMenu(menu); return; }
        const items = [...menu.querySelectorAll('[role=menuitem]:not(:disabled)')];
        const index = items.indexOf(root.activeElement);
        const next = { ArrowDown: (index + 1) % items.length, ArrowUp: (index - 1 + items.length) % items.length, Home: 0, End: items.length - 1 }[event.key];
        if (next !== undefined) { event.preventDefault(); items[next]?.focus(); }
    });
    cancel.addEventListener('click', () => { if (!busy) dialog.close(); });
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const items = [...dialog.querySelectorAll('button:not(:disabled), textarea:not(:disabled), a[href], input:not([type=hidden]):not(:disabled)')]
            .filter((item) => item.getClientRects().length > 0 && !item.closest('[hidden]'));
        if (items.length === 0) { event.preventDefault(); return; }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && (root.activeElement === first || !items.includes(root.activeElement))) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && (root.activeElement === last || !items.includes(root.activeElement))) {
            event.preventDefault(); first.focus();
        }
    });
    dialog.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => origin?.focus());
    reason.addEventListener('input', () => {
        if (reason.value.trim().length >= 10 && reason.value.trim().length <= 500) {
            reason.setAttribute('aria-invalid', 'false');
            dialog.querySelector('[data-action-reason-error]').textContent = '';
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        const localReasonError = !reason.disabled && (reason.value.trim().length < 10 || reason.value.trim().length > 500);
        dialog.querySelector('[data-action-reason-error]').textContent = localReasonError ? 'Informe uma justificativa de 10 a 500 caracteres.' : '';
        reason.setAttribute('aria-invalid', String(localReasonError));
        if (localReasonError) { reason.focus(); return; }
        busy = true; confirm.disabled = true; cancel.disabled = true;
        errors.hidden = true; progress.textContent = 'Processando solicitação…';
        form.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            let body = {};
            try { body = await response.json(); } catch { /* A proxy may return an HTML error page. */ }
            if (!response.ok) {
                const messages = Object.values(body.errors ?? {}).flat().filter((message) => typeof message === 'string');
                const fallback = response.status === 429 ? 'Limite de solicitações atingido. Aguarde antes de tentar novamente.'
                    : [401, 419].includes(response.status) ? 'Sua sessão expirou. Recarregue a página e entre novamente.'
                    : response.status === 403 ? 'Você não tem permissão para executar esta ação.'
                    : 'Não foi possível concluir a ação. Recarregue a página para verificar o resultado antes de tentar novamente.';
                errors.textContent = messages.length ? messages.join(' ') : fallback;
                errors.hidden = false;
                if (body.errors?.reason) {
                    dialog.querySelector('[data-action-reason-error]').textContent = body.errors.reason.join(' ');
                    reason.setAttribute('aria-invalid', 'true'); reason.focus();
                } else errors.focus();
                dialog.querySelector('[data-action-refresh]').hidden = false;
                progress.textContent = '';
                return;
            }
            progress.textContent = body.message ?? 'Operação concluída.';
            window.location.reload();
        } catch {
            errors.textContent = 'Falha de comunicação. Recarregue a página para verificar o resultado antes de tentar novamente.';
            errors.hidden = false; errors.focus(); progress.textContent = '';
            dialog.querySelector('[data-action-refresh]').hidden = false;
        } finally {
            busy = false; confirm.disabled = false; cancel.disabled = false; form.removeAttribute('aria-busy');
        }
    });
}
