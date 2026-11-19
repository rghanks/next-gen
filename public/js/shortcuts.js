/**
 * Lab Information System (LIS) Central Shortcut Registry & Dispatcher
 */
(function(window, $) {
    'use strict';

    const Shortcuts = {
        handlers: {},
        overlayElement: null,

        init: function() {
            this.bindGlobalKeys();
            this.createHelpModal();
            document.querySelectorAll('[data-nav-shortcut]').forEach(link => {
                this.register('global', link.dataset.navShortcut, () => window.location.href = link.href, link.textContent.trim());
            });
            this.register('global', '/', () => {
                document.querySelector('main input[type="search"], main input[name="q"], #barcode_scanner_input')?.focus();
            }, 'Focus workspace search');
        },

        register: function(context, combo, callback, description) {
            const key = combo.toLowerCase().replace(/\s+/g, '');
            if (!this.handlers[context]) {
                this.handlers[context] = [];
            }
            this.handlers[context].push({
                combo: key,
                callback: callback,
                description: description || ''
            });
        },

        bindGlobalKeys: function() {
            const self = this;
            $(document).on('keydown', function(e) {
                // Determine if active element is text input
                const tag = (e.target.tagName || '').toLowerCase();
                const isInput = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
                const hasModifier = e.ctrlKey || e.altKey || e.metaKey;

                // Escape always works
                if (e.key === 'Escape') {
                    if (self.overlayElement && self.overlayElement.is(':visible')) {
                        self.overlayElement.hide();
                        e.preventDefault();
                        return;
                    }
                    if ($('.lis-modal-overlay:visible').length) {
                        $('.lis-modal-overlay:visible').hide();
                        e.preventDefault();
                        return;
                    }
                    $(document).trigger('lis:escape');
                    return;
                }

                // If inside input without Ctrl/Alt, do NOT intercept regular typing except Enter or Tab or Arrows
                if (isInput && !hasModifier && !['enter', 'tab', 'arrowup', 'arrowdown'].includes(e.key.toLowerCase())) {
                    return;
                }

                // Check for '?' to open help cheat sheet (when not in input)
                if (!isInput && e.key === '?') {
                    e.preventDefault();
                    self.showHelpModal();
                    return;
                }

                // Construct current key combo
                const parts = [];
                if (e.ctrlKey) parts.push('ctrl');
                if (e.altKey) parts.push('alt');
                if (e.shiftKey) parts.push('shift');
                if (e.metaKey) parts.push('meta');

                let key = e.key.toLowerCase();
                if (key === 'control' || key === 'alt' || key === 'shift' || key === 'meta') {
                    return; // Ignore lone modifier keydowns
                }

                if (key === ' ') key = 'space';
                parts.push(key);
                const comboStr = parts.join('+');

                // Dispatch to active context handlers or global handlers
                let handled = false;
                const contexts = ['global', $('body').data('lis-context') || 'billing'];

                for (let i = 0; i < contexts.length; i++) {
                    const ctx = contexts[i];
                    const list = self.handlers[ctx] || [];
                    for (let j = 0; j < list.length; j++) {
                        if (list[j].combo === comboStr) {
                            e.preventDefault();
                            list[j].callback(e);
                            handled = true;
                            break;
                        }
                    }
                    if (handled) break;
                }
            });
        },

        createHelpModal: function() {
            const html = `
            <div id="lis-shortcut-overlay" class="lis-modal-overlay" style="display: none;">
                <div class="lis-modal">
                    <div class="lis-modal-header">
                        <span>LIS Keyboard Shortcuts Cheat Sheet</span>
                        <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" id="lis-close-help">Esc</button>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto; padding: 12px;">
                        <table class="table table-sm table-striped mb-0" style="font-size: 12px;">
                            <thead>
                                <tr>
                                    <th style="width: 130px;">Shortcut</th>
                                    <th>Action</th>
                                    <th>Context</th>
                                </tr>
                            </thead>
                            <tbody id="lis-shortcut-list">
                                <tr><td><span class="kbd-hint">/</span></td><td>Focus global search</td><td>Global</td></tr>
                                <tr><td><span class="kbd-hint">?</span></td><td>Open this shortcut help</td><td>Global</td></tr>
                                <tr><td><span class="kbd-hint">Alt+1 … 6</span></td><td>Switch: Billing / Specimen / Results / Verify / Reports / Masters</td><td>Global</td></tr>
                                <tr><td><span class="kbd-hint">Esc</span></td><td>Close panels / Cancel selection</td><td>Global</td></tr>
                                <tr><td><span class="kbd-hint">Alt+N</span></td><td>New Bill</td><td>Billing</td></tr>
                                <tr><td><span class="kbd-hint">Alt+P</span></td><td>Focus Patient search / Quick Walk-in</td><td>Billing</td></tr>
                                <tr><td><span class="kbd-hint">Alt+T</span></td><td>Focus Test / Package search</td><td>Billing</td></tr>
                                <tr><td><span class="kbd-hint">Alt+D</span></td><td>Open Discount panel</td><td>Billing</td></tr>
                                <tr><td><span class="kbd-hint">Alt+M</span></td><td>Open Payment Split panel</td><td>Billing</td></tr>
                                <tr><td><span class="kbd-hint">Ctrl+Enter</span></td><td>Save Bill & Generate Receipt</td><td>Billing</td></tr>
                                <tr><td><span class="kbd-hint">Del</span></td><td>Remove selected test item</td><td>Billing</td></tr>
                                <tr><td><span class="kbd-hint">J / K or ↑ / ↓</span></td><td>Move up/down rows</td><td>Worklists</td></tr>
                                <tr><td><span class="kbd-hint">Enter</span></td><td>Open selected worklist item</td><td>Worklists</td></tr>
                                <tr><td><span class="kbd-hint">Ctrl+S</span></td><td>Save Parameter Results</td><td>Results</td></tr>
                                <tr><td><span class="kbd-hint">Ctrl+Enter</span></td><td>Validate / Verify Results</td><td>Results / Verify</td></tr>
                                <tr><td><span class="kbd-hint">Alt+R</span></td><td>Print / Download Report PDF</td><td>Reports</td></tr>
                                <tr><td><span class="kbd-hint">Alt+W</span></td><td>Dispatch Report via WhatsApp</td><td>Reports</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>`;
            $('body').append(html);
            this.overlayElement = $('#lis-shortcut-overlay');

            $('#lis-close-help').on('click', () => {
                this.overlayElement.hide();
            });

            this.overlayElement.on('click', (e) => {
                if ($(e.target).is('#lis-shortcut-overlay')) {
                    this.overlayElement.hide();
                }
            });
        },

        showHelpModal: function() {
            if (this.overlayElement) {
                this.overlayElement.css('display', 'flex');
            }
        }
    };

    window.Shortcuts = Shortcuts;
    $(function() {
        Shortcuts.init();
    });

})(window, jQuery);
