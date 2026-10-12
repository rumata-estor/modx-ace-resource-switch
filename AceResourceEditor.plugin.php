/**
 * AceResourceEditor — code editor for resources with richtext disabled.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * CKEditor remains the RTE for resources with richtext enabled.
 * Requires the installed Ace extra (which_element_editor = Ace).
 */
if ($modx->event->name !== 'OnDocFormPrerender') {
    return;
}
$controller = $modx->controller;
$resource = $controller->resourceArray ?? null;
if (!is_array($resource) || !array_key_exists('richtext', $resource)) {
    return;
}
if (in_array($resource['class_key'] ?? '', array(
    'modStaticResource', 'modSymLink', 'modWebLink', 'modXMLRPCResource'
), true)) {
    return;
}
if ($modx->getOption('which_element_editor', null, '') !== 'Ace') {
    return;
}
$context = $controller->context ?? null;
$useEditor = $context
    ? $context->getOption('use_editor', $modx->getOption('use_editor'))
    : $modx->getOption('use_editor');
$whichEditor = $context
    ? $context->getOption('which_editor', $modx->getOption('which_editor'))
    : $modx->getOption('which_editor');
if (!$useEditor || !is_string($whichEditor) ||
    trim($whichEditor) === '' || strcasecmp(trim($whichEditor), 'Ace') === 0) {
    return;
}
// Ace's own plugin initializes its JS assets. This plugin only supplies
// the missing resource-field binding when another RTE is configured.
$mime = 'text/html';
$contentTypeId = (int) ($resource['content_type'] ?? 0);
if ($contentTypeId > 0) {
    $contentType = $modx->getObject('modContentType', $contentTypeId);
    if ($contentType) {
        $mime = (string) $contentType->get('mime_type');
    }
}
if ($mime === 'text/html') {
    $mime = (string) $modx->getOption('ace.html_elements_mime', null, 'text/html');
    if ($mime === '') {
        $mime = 'text/html';
    }
}
$modxTags = in_array($mime, array('text/html', 'text/x-smarty', 'text/x-twig'), true) ? 1 : 0;
$settings = json_encode(array('mime' => $mime, 'modxTags' => $modxTags),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$js = <<<'JAVASCRIPT'
(function () {
    'use strict';
    var settings = __ACE_RESOURCE_CONFIG__;
    var tries = 0;

    function init() {
        var panel = Ext.getCmp('modx-panel-resource');
        var ta = Ext.getCmp('ta');
        var checkbox = Ext.getCmp('modx-resource-richtext');
        if (!panel || !ta || !ta.el || !checkbox ||
            !window.MODx || !MODx.ux || !MODx.ux.Ace ||
            typeof MODx.ux.Ace.replaceComponent !== 'function') {
            if (++tries < 60) { setTimeout(init, 100); }
            return;
        }
        if (panel._aceResourceSwitchInstalled) { return; }
        panel._aceResourceSwitchInstalled = true;

        var fieldName = ta.name || ta.el.dom.name || 'content';
        var upstreamSave = null;
        // Keep exact HTML independent of CKEditor's DOM serialization.
        var rteSnapshot = null;
        var rteChanged = false;
        var rteReady = false;
        var trackedRte = null;
        var formattingWarningShown = false;

        function rte() {
            return MODx.loadedRTEs && MODx.loadedRTEs.ta;
        }
        function trackRte(source) {
            var wrapper = rte();
            var editor = wrapper && wrapper.editor;
            if (!editor || typeof editor.on !== 'function') { return; }
            if (trackedRte === editor) { return; }
            trackedRte = editor;
            rteSnapshot = source;
            rteChanged = false;
            rteReady = false;
            function ready() {
                if (trackedRte !== editor || rteReady) { return; }
                // Initial HTML parsing is not a user modification.
                if (typeof editor.resetDirty === 'function') {
                    editor.resetDirty();
                }
                rteReady = true;
            }
            editor.on('instanceReady', ready);
            editor.on('change', function() {
                if (trackedRte === editor && rteReady) { rteChanged = true; }
            });
            if (editor.status === 'ready') { ready(); }
        }
        function rteHasChanges() {
            if (!rte() || !trackedRte || !rteReady) { return false; }
            if (rteChanged) { return true; }
            // Fallback for CKEditor source mode.
            return typeof trackedRte.checkDirty === 'function' &&
                trackedRte.checkDirty();
        }
        function currentRteValue() {
            var wrapper = rte();
            if (!wrapper) { return ta.el.dom.value; }
            if (!rteHasChanges() && rteSnapshot !== null) {
                return rteSnapshot;
            }
            if (wrapper.editor &&
                typeof wrapper.editor.getData === 'function') {
                return wrapper.editor.getData();
            }
            return ta.el.dom.value;
        }
        function warnAboutFormatting() {
            if (formattingWarningShown || !rteHasChanges()) { return; }
            formattingWarningShown = true;
            if (Ext.Msg && typeof Ext.Msg.alert === 'function') {
                Ext.Msg.alert('Ace / CKEditor',
                    'Изменения из CKEditor перенесены в Ace. ' +
                    'CKEditor мог изменить форматирование исходного HTML ' +
                    '(отступы, переносы строк и комментарии). ' +
                    'Проверьте код перед сохранением.');
            }
        }
        function isRichtext() {
            return checkbox.getValue() === true;
        }
        // MODX resource panel reads the original textarea into hiddenContent
        // *before* invoking MODx.onSaveEditor. Keep the actual form field
        // synchronized with whichever editor is active on every save.
        function syncedSave(field) {
            if (field === ta) {
                var aceEditor = ta.aceEditor;
                var value;
                if (isRichtext() && rte()) {
                    value = currentRteValue();
                    if (aceEditor) {
                        aceEditor.setValue(value);
                        aceEditor.valueHolder.value = value;
                    }
                } else if (aceEditor) {
                    value = aceEditor.getValue();
                    aceEditor.valueHolder.value = value;
                } else {
                    value = ta.el.dom.value;
                }
                ta.el.dom.value = value;
                var hidden = Ext.getCmp('hiddenContent');
                if (hidden && hidden.setValue) { hidden.setValue(value); }
            }
            if (typeof upstreamSave === 'function') {
                return upstreamSave.apply(this, arguments);
            }
        }
        function installSaveHook() {
            if (MODx.onSaveEditor !== syncedSave) {
                upstreamSave = MODx.onSaveEditor;
                MODx.onSaveEditor = syncedSave;
            }
        }
        function showAce() {
            var hasRte = !!rte();
            // Determine whether real edits were made before destroying CKEditor.
            var hadChanges = hasRte && rteHasChanges();
            var value = currentRteValue();
            if (hadChanges) { warnAboutFormatting(); }
            if (hasRte && typeof MODx.unloadRTE === 'function') {
                MODx.unloadRTE('ta');
            }
            trackedRte = null;
            rteSnapshot = null;
            rteReady = false;
            rteChanged = false;
            panel.rteLoaded = false;
            ta.el.dom.value = value;
            if (!ta.aceEditor) {
                MODx.ux.Ace.replaceComponent('ta', settings.mime, settings.modxTags);
            }
            var aceEditor = ta.aceEditor;
            if (!aceEditor) { return; }
            aceEditor.setValue(value);
            aceEditor.valueHolder.value = value;
            aceEditor.valueHolder.name = fieldName;
            aceEditor.el.setDisplayed(true);
            if (aceEditor.editor) { aceEditor.editor.resize(true); }
            ta.el.dom.removeAttribute('name');
            ta.el.dom.style.display = 'none';
            installSaveHook();
        }
        function showCKEditor() {
            var aceEditor = ta.aceEditor;
            var startingSource = aceEditor ? aceEditor.getValue() : ta.el.dom.value;
            if (aceEditor) {
                ta.el.dom.value = startingSource;
                aceEditor.valueHolder.name = '';
                aceEditor.el.setDisplayed(false);
            }
            ta.el.dom.name = fieldName;
            if (!rte() && typeof MODx.loadRTE === 'function') {
                MODx.loadRTE('ta');
            }
            panel.rteLoaded = !!rte();
            if (panel.rteLoaded) {
                trackRte(startingSource);
                // CKEditor owns the visual surface, and textarea is the
                // named form field. Ace's input must have no name.
                ta.el.dom.style.display = 'none';
            } else if (aceEditor) {
                // Fail safe: keep the code editor available if RTE failed.
                showAce();
            }
            installSaveHook();
        }
        function reconcile() {
            if (isRichtext()) { showCKEditor(); }
            else { showAce(); }
        }
        checkbox.on('check', reconcile);
        reconcile();
    }
    Ext.onReady(init);
}());
JAVASCRIPT;
$controller->addHtml('<script>' .
    str_replace('__ACE_RESOURCE_CONFIG__', $settings, $js) .
    '</script>');
