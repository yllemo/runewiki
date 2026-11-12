<dialog id="svg-editor" class="gbg-svg-editor" aria-labelledby="svg-editor-title" data-namespace="<?= Helpers::e($svgNamespace ?? '') ?>" data-csrf="<?= Helpers::e(Helpers::csrfToken()) ?>">
    <div class="gbg-svg-toolbar">
        <h2 id="svg-editor-title">SVG-redigerare</h2>
        <button type="button" id="svg-close" class="gbg-btn gbg-btn-outline" aria-label="Stäng SVG-redigeraren">✕</button>
    </div>
    <div class="gbg-svg-toolbar">
        <label>Befintlig SVG <select id="svg-existing"><option value="">Välj fil…</option></select></label>
        <label>Exempel <select id="svg-example"><option value="">Välj exempel…</option><option value="card">Informationsruta</option><option value="flow">Flödesdiagram</option><option value="icon">Ikon</option></select></label>
        <button type="button" id="svg-local" class="gbg-btn gbg-btn-outline">Öppna lokal SVG</button>
        <input type="file" id="svg-file" accept=".svg,image/svg+xml" hidden>
        <button type="button" id="svg-refresh" class="gbg-btn gbg-btn-outline">Uppdatera förhandsvisning</button>
    </div>
    <div class="gbg-svg-columns">
        <label>SVG-kod <textarea id="svg-source" spellcheck="false" aria-describedby="svg-help"></textarea></label>
        <div class="gbg-svg-preview"><img id="svg-preview" alt="Förhandsvisning av SVG"></div>
    </div>
    <p id="svg-help" class="gbg-hint">Ctrl+Space öppnar exempel. Förhandsvisningen uppdateras automatiskt. Statisk SVG med former, text och gradienter stöds; skript, externa resurser och inbäddad HTML stöds inte.</p>
    <label>Medie-ID (namespace:fil.svg) <input id="svg-id" type="text" placeholder="projekt:diagram.svg"></label>
    <div class="gbg-svg-toolbar">
        <button type="button" id="svg-save" class="gbg-btn gbg-btn-primary">Spara SVG i media</button>
        <button type="button" id="svg-insert-code" class="gbg-btn gbg-btn-outline" hidden>Infoga SVG-kod</button>
        <button type="button" id="svg-insert-file" class="gbg-btn gbg-btn-outline" hidden>Spara och infoga bild</button>
    </div>
    <p id="svg-status" role="status" aria-live="polite"></p>
</dialog>
<script src="<?= Helpers::e($assetUrl('js/svg-editor.js')) ?>"></script>
