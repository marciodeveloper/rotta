<dialog class="crm-action-dialog" data-action-dialog aria-labelledby="action-dialog-title" aria-describedby="action-dialog-description">
    <form method="POST" data-action-form novalidate>
        @csrf
        <input type="hidden" name="_method" value="PATCH"><input type="hidden" name="status"><input type="hidden" name="status_version">
        <div class="eyebrow"><span></span> Ação administrativa</div>
        <h2 id="action-dialog-title" data-action-title>Confirmar ação</h2>
        <p id="action-dialog-description">Confira os dados antes de confirmar a operação.</p>
        <dl class="crm-action-summary"><div><dt>Interessado</dt><dd data-action-record></dd></div><div><dt>Status atual</dt><dd data-action-current></dd></div><div><dt>Destino</dt><dd data-action-target></dd></div></dl>
        <div class="alert alert-error" data-action-errors role="alert" tabindex="-1" hidden></div>
        <label class="field" data-action-reason><span>Justificativa</span><textarea name="reason" rows="4" minlength="10" maxlength="500" aria-describedby="action-reason-help action-reason-error"></textarea><small id="action-reason-help">Informe de 10 a 500 caracteres.</small><b id="action-reason-error" data-action-reason-error></b></label>
        <p data-action-resend-note hidden>Um novo link será enviado ao endereço já registrado. O link anterior será invalidado. Aguarde o processamento da fila.</p>
        <a href="" data-action-refresh hidden>Recarregar o cadastro para obter a versão atual</a>
        <div class="crm-dialog-footer"><button type="button" class="button button-secondary" data-action-cancel>Cancelar</button><button type="submit" class="button button-primary" data-action-confirm>Confirmar</button></div>
        <p role="status" aria-live="polite" data-action-progress></p>
    </form>
</dialog>
