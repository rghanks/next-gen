/**
 * LM Path & Empath - Professional Billing Module
 * - Mutually exclusive patient mode (Search vs Register New)
 * - Live patient autocomplete & database-wide live service search
 * - Referral doctor dropdown with modal registration
 * - Today's Visits offcanvas side panel with real-time stats, service counts, and receipts
 */
(function($) {
    'use strict';

    const LabBilling = {
        cartItems: [],
        defaultServices: [],
        selectedPatient: null,
        clientToken: null,
        currentServiceIndex: -1,
        currentPatientIndex: -1,
        discountMode: '%', // '%' or 'amount'
        serviceSearchTimer: null,
        patientSearchTimer: null,
        activePatientMode: 'search', // 'search' or 'register'
        todayVisitsData: [],

        init: function() {
            this.endpoints = $('.lis-billing-workspace').data();
            this.generateClientToken();
            this.initDefaultServices();
            this.initInitialCart();
            this.bindEvents();
            this.bindKeyboardShortcuts();
            this.recalculateTotals();
            this.initPatientModeState();
            this.initTodayVisitsCollapseState();
            this.loadTodayVisits();
        },

        initPatientModeState: function() {
            if ($('#switch9').is(':checked')) {
                this.activePatientMode = 'register';
                $('#pane_search_patient').hide();
                $('#pane_register_patient').show();
            } else {
                this.activePatientMode = 'search';
                $('#pane_search_patient').show();
                $('#pane_register_patient').hide();
            }
        },

        generateClientToken: function() {
            this.clientToken = 'tok_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
            $('#client_token').val(this.clientToken);
        },

        bindKeyboardShortcuts: function() {
            const self = this;
            if (window.Shortcuts) {
                window.Shortcuts.register('billing', 'alt+p', () => {
                    if (self.activePatientMode === 'search') {
                        $('#uhid').focus().select();
                    } else {
                        $('#reg_name').focus().select();
                    }
                }, 'Focus Patient');
                window.Shortcuts.register('billing', 'alt+t', () => $('#search_service').focus().select(), 'Focus Test Search');
                window.Shortcuts.register('billing', 'alt+n', () => self.resetForm(), 'New Bill');
                window.Shortcuts.register('billing', 'ctrl+enter', () => $('#btn-save-bill').trigger('click'), 'Save Bill and Print');
            }

            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    $('#btn-save-bill').trigger('click');
                }
            });
        },

        initDefaultServices: function() {
            const self = this;
            // Always use real investigation prices from the database
            if (window.initialInvestigations && window.initialInvestigations.length > 0) {
                self.defaultServices = window.initialInvestigations;
            } else {
                self.defaultServices = [];
            }

            self.renderResultsList(self.defaultServices);
        },

        initInitialCart: function() {
            const self = this;
            self.cartItems = [];
            $('#cart-tbody tr[data-service-id]').each(function() {
                const tr = $(this);
                const sId = parseInt(tr.data('service-id')) || 0;
                const name = tr.find('.service-name-text').text().trim();
                const charge = parseFloat(tr.find('.item-charge').val()) || 0;
                const qty = parseInt(tr.find('.item-qty').val()) || 1;
                const discount = parseFloat(tr.find('.item-discount').val()) || 0;
                const amount = (charge * qty) - discount;

                self.cartItems.push({
                    service_id: sId,
                    name: name,
                    charge: charge,
                    qty: qty,
                    discount: discount,
                    amount: amount
                });
            });
            self.renderCartTable();
        },

        bindEvents: function() {
            const self = this;

            // ════════════════════════════════════════════════════════════════
            // 1. PATIENT MODE TOGGLE SWITCH (#switch9: Checked = New, Unchecked = Old)
            // ════════════════════════════════════════════════════════════════
            $('#switch9').on('change', function() {
                if ($(this).is(':checked')) {
                    self.activePatientMode = 'register';
                    $('#search_box_container').slideUp(150);
                    $('#patient_info_bar').slideUp(150);
                    $('#pane_register_patient').slideDown(150);
                    $('#patient_id').val('');
                    $('#reg_name').focus();
                } else {
                    self.activePatientMode = 'search';
                    $('#pane_register_patient').slideUp(150);
                    $('#search_box_container').slideDown(150);
                    $('#patient_info_bar').slideDown(150);
                    $('#uhid').focus();
                }
            });

            // Enforce digits only and max 10 digits on registration mobile field
            $('#reg_phone').on('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
            });

            // Refresh Sample Number in Series
            $('#btn-refresh-sample-no').on('click', function() {
                self.refreshNextSampleNo();
            });

            // ════════════════════════════════════════════════════════════════
            // 2. LIVE PATIENT AUTOCOMPLETE SEARCH
            // ════════════════════════════════════════════════════════════════
            $('#uhid').on('input', function() {
                const q = $(this).val().trim();
                clearTimeout(self.patientSearchTimer);
                self.currentPatientIndex = -1;

                if (q.length < 1) {
                    $('#patient-autocomplete-dropdown').hide().empty();
                    return;
                }

                self.patientSearchTimer = setTimeout(() => {
                    $.get(self.endpoints.patientUrl, { q: q }, function(res) {
                        self.renderPatientDropdown(res, q);
                    }).fail(function() {
                        $('#patient-autocomplete-dropdown').hide();
                    });
                }, 160);
            });

            // Keyboard navigation on patient search box
            $('#uhid').on('keydown', function(e) {
                const items = $('#patient-autocomplete-dropdown .patient-autocomplete-item');
                const isDropdownVisible = $('#patient-autocomplete-dropdown').is(':visible') && items.length > 0;

                if (e.key === 'ArrowDown' && isDropdownVisible) {
                    e.preventDefault();
                    self.currentPatientIndex = (self.currentPatientIndex + 1) % items.length;
                    self.highlightPatientItem(items);
                } else if (e.key === 'ArrowUp' && isDropdownVisible) {
                    e.preventDefault();
                    self.currentPatientIndex = (self.currentPatientIndex - 1 + items.length) % items.length;
                    self.highlightPatientItem(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (isDropdownVisible && self.currentPatientIndex >= 0) {
                        items.eq(self.currentPatientIndex).trigger('click');
                    } else if (isDropdownVisible && items.length > 0) {
                        items.first().trigger('click');
                    } else {
                        self.searchPatientExact();
                    }
                } else if (e.key === 'Escape') {
                    $('#patient-autocomplete-dropdown').hide();
                }
            });

            // Click on patient in dropdown
            $(document).on('click', '.patient-autocomplete-item', function() {
                const patient = $(this).data('patient');
                if (patient) {
                    self.selectPatient(patient);
                }
                $('#patient-autocomplete-dropdown').hide();
            });

            // Close patient dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#pane_search_patient, #patient-autocomplete-dropdown').length) {
                    $('#patient-autocomplete-dropdown').hide();
                }
            });

            // Search button click
            $('#search-btn').on('click', function() {
                self.searchPatientExact();
            });

            // Reset button click
            $('#reset-btn').on('click', function() {
                self.resetForm();
            });

            // ════════════════════════════════════════════════════════════════
            // 3. LIVE INVESTIGATION SEARCH & KEYBOARD NAVIGATION
            // ════════════════════════════════════════════════════════════════
            $('#search_service').on('input', function() {
                const term = $(this).val().trim();
                clearTimeout(self.serviceSearchTimer);
                self.currentServiceIndex = -1;

                if (!term) {
                    self.renderResultsList(self.defaultServices);
                    return;
                }

                // Query database API across all services
                self.serviceSearchTimer = setTimeout(() => {
                    $.get(self.endpoints.servicesUrl, { q: term, payer_category: 'general' }, function(res) {
                        if ($('#search_service').val().trim() === term) {
                            self.renderResultsList(res);
                        }
                    }).fail(function() {
                        const filtered = self.defaultServices.filter(function(item) {
                            const name = (item.name || '').toLowerCase();
                            const code = (item.short_code || '').toLowerCase();
                            return name.includes(term.toLowerCase()) || code.includes(term.toLowerCase());
                        });
                        self.renderResultsList(filtered);
                    });
                }, 160);
            });

            $('#search_service').on('keydown', function(e) {
                const items = $('#results li:not(:first-child:contains("No matching"))');
                if (!items.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    self.currentServiceIndex = (self.currentServiceIndex + 1) % items.length;
                    self.highlightResultItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    self.currentServiceIndex = (self.currentServiceIndex - 1 + items.length) % items.length;
                    self.highlightResultItem(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (self.currentServiceIndex >= 0 && self.currentServiceIndex < items.length) {
                        items.eq(self.currentServiceIndex).trigger('click');
                    } else if (items.length > 0) {
                        items.first().trigger('click');
                    }
                }
            });

            // Add test to cart when clicked from results list
            $(document).on('click', '#results li', function() {
                const s = $(this).data('service');
                if (s) {
                    self.addItem(s);
                    $('#search_service').val('').focus();
                    self.renderResultsList(self.defaultServices);
                }
            });

            // ════════════════════════════════════════════════════════════════
            // 4. CART TABLE ROW EVENTS
            // ════════════════════════════════════════════════════════════════
            $(document).on('click', '.btn-remove-item', function() {
                const tr = $(this).closest('tr');
                const sId = parseInt(tr.data('service-id'));
                self.removeItem(sId);
            });

            $(document).on('input change', '.item-charge', function() {
                const tr = $(this).closest('tr');
                const sId = parseInt(tr.data('service-id'));
                const charge = parseFloat($(this).val()) || 0;
                self.updateItemCharge(sId, charge);
            });

            $(document).on('change', '.item-qty', function() {
                const tr = $(this).closest('tr');
                const sId = parseInt(tr.data('service-id'));
                const qty = parseInt($(this).val()) || 1;
                self.updateItemQty(sId, qty);
            });

            $(document).on('input change', '.item-discount', function() {
                const tr = $(this).closest('tr');
                const sId = parseInt(tr.data('service-id'));
                const disc = parseFloat($(this).val()) || 0;
                self.updateItemDiscount(sId, disc);
            });

            // ════════════════════════════════════════════════════════════════
            // 5. DISCOUNT & PAYMENT SETTLEMENT
            // ════════════════════════════════════════════════════════════════
            $('#discount_percent').on('input', function() {
                self.recalculateTotals();
            });

            $('.payment-field').on('input', function() {
                self.recalculateTenders();
            });

            // ════════════════════════════════════════════════════════════════
            // 6. REFERRAL DOCTOR REGISTRATION MODAL
            // ════════════════════════════════════════════════════════════════
            $('#btn-add-referral-modal').on('click', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('addReferralModal');
                if (modalEl) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    } else if ($.fn.modal) {
                        $(modalEl).modal('show');
                    }
                }
                setTimeout(() => $('#new_ref_name').focus(), 300);
            });

            $('#form-add-referral').on('submit', function(e) {
                e.preventDefault();
                const name = $('#new_ref_name').val().trim();
                const phone = $('#new_ref_phone').val().trim();
                const degree = $('#new_ref_degree').val().trim();

                if (!name) {
                    window.lisToast('Please enter doctor or clinic name.', 'warning');
                    return;
                }

                const btn = $('#btn-save-referral').prop('disabled', true).text('Saving...');

                $.ajax({
                    url: self.endpoints.referralStoreUrl,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        name: name,
                        mobile_no: phone,
                        degree: degree
                    }),
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="uil uil-check"></i> Save Referral');
                        const ref = res.referral;

                        // Add new option and select it
                        const labelText = ref.name + (ref.mobile_no ? ' (' + ref.mobile_no + ')' : '');
                        const newOption = $('<option></option>')
                            .attr('value', ref.name)
                            .attr('data-phone', ref.mobile_no || '')
                            .text(labelText)
                            .prop('selected', true);

                        $('#referral_select').append(newOption);

                        // Close modal reliably
                        const modalEl = document.getElementById('addReferralModal');
                        if (modalEl) {
                            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                                modal.hide();
                            } else if ($.fn.modal) {
                                $(modalEl).modal('hide');
                            }
                        }

                        // Reset form
                        $('#form-add-referral')[0].reset();
                        window.lisToast('Referring doctor added: ' + ref.name, 'success');
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="uil uil-check"></i> Save Referral');
                        let err = 'Failed to save referral.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            err = xhr.responseJSON.message;
                        }
                        window.lisToast(err, 'danger');
                    }
                });
            });

            // ════════════════════════════════════════════════════════════════
            // 7. TODAY'S VISITS ACTIONS & COLLAPSE / EXPAND TOGGLE
            // ════════════════════════════════════════════════════════════════
            $('#btn-refresh-today-visits').on('click', function() {
                self.loadTodayVisits();
            });

            $('#tv-search-filter').on('input', function() {
                const term = $(this).val().toLowerCase().trim();
                self.filterTodayVisitsList(term);
            });

            // Collapse to right side (Desktop & Mobile)
            $('#btn-collapse-today-visits').on('click', function() {
                self.collapseTodayVisits();
            });

            // Expand from collapsed tab on right side
            $('#btn-expand-today-visits, #tab-expand-today-visits').on('click', function() {
                self.expandTodayVisits();
            });

            // Header toggle button (Desktop & Mobile)
            $('#btn-header-toggle-today-visits').on('click', function() {
                self.toggleTodayVisits();
            });

            // Mobile backdrop click to close drawer
            $('#today-visits-mobile-backdrop').on('click', function() {
                self.collapseTodayVisits();
            });

            // Auto-adjust collapse state on window resize
            $(window).on('resize', function() {
                clearTimeout(self.resizeTimer);
                self.resizeTimer = setTimeout(() => {
                    self.initTodayVisitsCollapseState();
                }, 200);
            });

            // ════════════════════════════════════════════════════════════════
            // 8. BILL GENERATION & PRINT
            // ════════════════════════════════════════════════════════════════
            $('#btn-save-bill').on('click', function() {
                self.submitBill();
            });

            // ════════════════════════════════════════════════════════════════
            // 9. DIRECT BROWSER PRINT PREVIEW (NO MODAL, SINGLE TRIGGER)
            // ════════════════════════════════════════════════════════════════
            $(document).off('click', '.btn-preview-receipt').on('click', '.btn-preview-receipt', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const url = $(this).data('url');
                self.printReceiptDirectly(url);
            });
        },

        // Render live autocomplete dropdown for patient search
        renderPatientDropdown: function(patients, query) {
            const dropdown = $('#patient-autocomplete-dropdown').empty();

            if (!patients || !patients.length) {
                dropdown.append(`
                    <div class="patient-autocomplete-empty">
                        <i class="uil uil-search-alt text-muted mb-1 font-size-16"></i>
                        <div>No matching patient found for "<strong>${escapeHtml(query)}</strong>"</div>
                        <div class="text-muted" style="font-size: 11px;">Switch to "Register New Patient" to create a new record.</div>
                    </div>
                `);
                dropdown.show();
                return;
            }

            patients.forEach(p => {
                const item = $(`
                    <div class="patient-autocomplete-item">
                        <div class="patient-name">
                            <span>${escapeHtml(p.name)}</span>
                            <span class="patient-uhid">#${p.uhid || p.id}</span>
                        </div>
                        <div class="patient-meta">
                            <span><i class="uil uil-phone font-size-11"></i> ${p.mobile_no || 'No Mobile'}</span>
                            <span><i class="uil uil-user font-size-11"></i> ${p.age || 'Age N/A'}</span>
                            <span class="badge bg-light text-secondary border">${p.category || 'Cash'}</span>
                        </div>
                    </div>
                `);
                item.data('patient', p);
                dropdown.append(item);
            });

            dropdown.show();
        },

        highlightPatientItem: function(items) {
            items.removeClass('active');
            if (this.currentPatientIndex >= 0 && this.currentPatientIndex < items.length) {
                const activeEl = items.eq(this.currentPatientIndex).addClass('active');
                const container = $('#patient-autocomplete-dropdown');
                const scrollPos = activeEl.position().top + container.scrollTop();
                if (scrollPos < container.scrollTop() || scrollPos > container.scrollTop() + container.height()) {
                    container.scrollTop(scrollPos - 30);
                }
            }
        },

        selectPatient: function(p) {
            this.selectedPatient = p;
            $('#b_uhid').text(p.uhid || p.id);
            $('#b_name').text(p.name);
            $('#b_age').text(p.age);
            $('#b_mobile_no').text(p.mobile_no || 'N/A');
            $('#b_i_cover').text(p.category || 'Cash');
            $('#patient_id').val(p.id);
            $('#uhid').val(p.uhid || p.id);

            if (p.ref_by && $('#referral_select option[value="' + p.ref_by + '"]').length) {
                $('#referral_select').val(p.ref_by);
            }

            $('#patient-autocomplete-dropdown').hide();
            window.lisToast('Patient selected: ' + p.name, 'success');
            $('#search_service').focus();
        },

        searchPatientExact: function() {
            const self = this;
            const q = $('#uhid').val().trim();
            if (!q) {
                window.lisToast('Please enter UHID, Mobile number, or Name.', 'warning');
                return;
            }

            $.get(self.endpoints.patientUrl, { q: q }, function(res) {
                if (res && res.length > 0) {
                    self.selectPatient(res[0]);
                } else {
                    window.lisToast('No patient found for: ' + q, 'warning');
                }
            }).fail(function() {
                window.lisToast('Failed to fetch patient details.', 'danger');
            });
        },

        highlightResultItem: function(items) {
            items.removeClass('active');
            if (this.currentServiceIndex >= 0 && this.currentServiceIndex < items.length) {
                const activeEl = items.eq(this.currentServiceIndex).addClass('active');
                const container = $('#results');
                const scrollPos = activeEl.position().top + container.scrollTop();
                if (scrollPos < container.scrollTop() || scrollPos > container.scrollTop() + container.height()) {
                    container.scrollTop(scrollPos - 40);
                }
            }
        },

        renderResultsList: function(list) {
            const ul = $('#results').empty();
            if (!list || !list.length) {
                ul.append('<li style="color: #64748b; font-style: italic; padding: 10px;">No matching investigation found in catalog.</li>');
                return;
            }

            list.forEach(function(item) {
                const li = $('<li></li>');
                li.data('service', item);

                let displayTitle = item.name;
                if (item.short_code) {
                    displayTitle += ` <span style="color: #64748b; font-size: 11px;">(${item.short_code})</span>`;
                }

                let content = `<span>${displayTitle}</span>`;
                content += `<div class="d-flex align-items-center gap-2">`;
                if (item.is_outsource) {
                    content += `<span class="badge-outsource">Outsource</span>`;
                }
                content += `<span class="badge-rate">₹${parseFloat(item.price || 100).toFixed(0)}</span>`;
                content += `</div>`;

                li.html(content);
                ul.append(li);
            });
        },

        addItem: function(service) {
            const self = this;
            const existing = self.cartItems.find(i => i.service_id === service.id);
            if (existing) {
                existing.qty += 1;
                existing.amount = Math.max(0, (existing.charge * existing.qty) - existing.discount);
                window.lisToast('Increased quantity for ' + service.name, 'info');
            } else {
                const charge = parseFloat(service.price) >= 0 ? parseFloat(service.price) : 0;
                self.cartItems.push({
                    service_id: service.id,
                    name: service.name,
                    charge: charge,
                    qty: 1,
                    discount: 0,
                    amount: charge
                });
                window.lisToast('Added: ' + service.name, 'success');
            }

            self.renderCartTable();
            self.recalculateTotals();
        },

        removeItem: function(serviceId) {
            const self = this;
            self.cartItems = self.cartItems.filter(i => i.service_id !== serviceId);
            self.renderCartTable();
            self.recalculateTotals();
        },

        updateItemCharge: function(serviceId, charge) {
            const item = this.cartItems.find(i => i.service_id === serviceId);
            if (item) {
                item.charge = Math.max(0, charge);
                item.amount = Math.max(0, (item.charge * item.qty) - item.discount);
                const tr = $(`#cart-tbody tr[data-service-id="${serviceId}"]`);
                tr.find('.item-amount').val(item.amount.toFixed(2));
                this.recalculateTotals();
            }
        },

        updateItemQty: function(serviceId, qty) {
            const item = this.cartItems.find(i => i.service_id === serviceId);
            if (item) {
                item.qty = Math.max(1, qty);
                item.amount = Math.max(0, (item.charge * item.qty) - item.discount);
                const tr = $(`#cart-tbody tr[data-service-id="${serviceId}"]`);
                tr.find('.item-amount').val(item.amount.toFixed(2));
                this.recalculateTotals();
            }
        },

        updateItemDiscount: function(serviceId, discount) {
            const item = this.cartItems.find(i => i.service_id === serviceId);
            if (item) {
                item.discount = Math.max(0, discount);
                item.amount = Math.max(0, (item.charge * item.qty) - item.discount);
                const tr = $(`#cart-tbody tr[data-service-id="${serviceId}"]`);
                tr.find('.item-amount').val(item.amount.toFixed(2));
                this.recalculateTotals();
            }
        },

        renderCartTable: function() {
            const self = this;
            const tbody = $('#cart-tbody').empty();

            if (!self.cartItems.length) {
                tbody.append(`
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted" style="font-style: italic;">
                            No investigations selected. Type below to search and add tests.
                        </td>
                    </tr>
                `);
                return;
            }

            self.cartItems.forEach(function(item, idx) {
                const tr = $(`
                    <tr data-service-id="${item.service_id}" data-index="${idx}">
                        <td class="text-center serial-no">${idx + 1}</td>
                        <td class="text-center" style="color: var(--brand-text-muted);">${item.service_id}</td>
                        <td class="service-name-text fw-medium">${escapeHtml(item.name)}</td>
                        <td><input type="number" step="0.01" min="0" class="cell-input text-end item-charge" value="${item.charge.toFixed(2)}" title="Edit rate"></td>
                        <td>
                            <select class="cell-select item-qty">
                                <option value="1" ${item.qty === 1 ? 'selected' : ''}>1</option>
                                <option value="2" ${item.qty === 2 ? 'selected' : ''}>2</option>
                                <option value="3" ${item.qty === 3 ? 'selected' : ''}>3</option>
                                <option value="4" ${item.qty === 4 ? 'selected' : ''}>4</option>
                                <option value="5" ${item.qty === 5 ? 'selected' : ''}>5</option>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" min="0" class="cell-input text-end item-discount" value="${item.discount}"></td>
                        <td><input type="number" step="0.01" class="cell-input readonly-bg text-end item-amount" value="${item.amount.toFixed(2)}" readonly></td>
                        <td class="text-center">
                            <button type="button" class="btn-trash-outline btn-remove-item" title="Remove item">
                                <i class="uil uil-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `);
                tbody.append(tr);
            });
        },

        recalculateTotals: function() {
            const self = this;
            let subtotal = 0;
            self.cartItems.forEach(function(item) {
                subtotal += item.amount;
            });

            $('#lbl-subtotal').val('₹' + subtotal.toFixed(2));

            const discPercent = Math.min(100, Math.max(0, parseFloat($('#discount_percent').val()) || 0));
            const discountAmt = Math.min((subtotal * discPercent) / 100, subtotal);
            const netPayable = Math.max(0, subtotal - discountAmt);

            $('#lbl-total').val('₹' + netPayable.toFixed(2));

            self.recalculateTenders();
        },

        recalculateTenders: function() {
            const netPayable = parseFloat($('#lbl-total').val().replace('₹', '')) || 0;
            let totalPaid = 0;

            $('.payment-field').each(function() {
                totalPaid += (parseFloat($(this).val()) || 0);
            });

            const due = Math.max(0, netPayable - totalPaid);

            $('#lbl-total-paid').text('₹' + totalPaid.toFixed(2));
            $('#lbl-due-amount').text('₹' + due.toFixed(2));

            if (due > 0.01) {
                $('#lbl-due-amount').removeClass('text-paid').addClass('text-due');
            } else {
                $('#lbl-due-amount').removeClass('text-due').addClass('text-paid');
            }
        },

        // ════════════════════════════════════════════════════════════════
        // TODAY'S VISITS DATA LOADING & RENDERING
        // ════════════════════════════════════════════════════════════════
        loadTodayVisits: function() {
            const self = this;
            const container = $('#today-visits-container');

            $.get(self.endpoints.todayVisitsUrl, function(res) {
                self.todayVisitsData = res.visits || [];

                // Update Header and Collapsed stats
                $('#badge-today-count').text(res.count || 0);
                $('#badge-today-count-header').text(res.count || 0);
                $('#badge-today-count-collapsed').text(res.count || 0);
                $('#tv-stat-count').text(res.count || 0);
                $('#tv-stat-billed').text('₹' + (res.total_billed || 0).toLocaleString());
                $('#tv-stat-paid').text('₹' + (res.total_paid || 0).toLocaleString());

                self.renderTodayVisits(self.todayVisitsData);
            }).fail(function() {
                container.html(`
                    <div class="text-center py-4 text-muted">
                        <i class="uil uil-exclamation-triangle font-size-24 text-warning mb-2"></i>
                        <div>Could not load today's visits.</div>
                    </div>
                `);
            });
        },

        initTodayVisitsCollapseState: function() {
            if (window.innerWidth < 992) {
                // Small screen: collapsed by default
                $('.opd-desk-layout').addClass('is-collapsed');
                $('#opd-desk-right').removeClass('mobile-open');
                $('#today-visits-mobile-backdrop').hide();
            } else {
                // Desktop: by default open, unless explicitly collapsed
                const isCollapsed = localStorage.getItem('todayVisitsCollapsed') === 'true';
                if (isCollapsed) {
                    $('.opd-desk-layout').addClass('is-collapsed');
                } else {
                    $('.opd-desk-layout').removeClass('is-collapsed');
                }
            }
        },

        collapseTodayVisits: function() {
            if (window.innerWidth < 992) {
                $('#opd-desk-right').removeClass('mobile-open');
                $('#today-visits-mobile-backdrop').fadeOut(150);
            } else {
                $('.opd-desk-layout').addClass('is-collapsed');
                localStorage.setItem('todayVisitsCollapsed', 'true');
            }
        },

        expandTodayVisits: function() {
            if (window.innerWidth < 992) {
                $('#opd-desk-right').addClass('mobile-open');
                $('#today-visits-mobile-backdrop').fadeIn(150);
            } else {
                $('.opd-desk-layout').removeClass('is-collapsed');
                localStorage.setItem('todayVisitsCollapsed', 'false');
            }
        },

        toggleTodayVisits: function() {
            if (window.innerWidth < 992) {
                if ($('#opd-desk-right').hasClass('mobile-open')) {
                    this.collapseTodayVisits();
                } else {
                    this.expandTodayVisits();
                }
            } else {
                if ($('.opd-desk-layout').hasClass('is-collapsed')) {
                    this.expandTodayVisits();
                } else {
                    this.collapseTodayVisits();
                }
            }
        },

        renderTodayVisits: function(list) {
            const container = $('#today-visits-container').empty();

            if (!list || !list.length) {
                container.html(`
                    <div class="text-center py-5 text-muted">
                        <i class="uil uil-users-alt font-size-24 text-muted mb-2"></i>
                        <div class="fw-semibold">No visits recorded today yet.</div>
                        <div style="font-size: 11.5px;">Generate a bill to view today's list here.</div>
                    </div>
                `);
                return;
            }

            list.forEach(v => {
                let statusBadgeClass = 'badge-status-paid';
                if (v.status === 'Partial') statusBadgeClass = 'badge-status-partial';
                else if (v.status === 'Unpaid') statusBadgeClass = 'badge-status-unpaid';

                const receiptUrl = v.receipt_url || `/lab/billing/receipt/${v.order_id || v.bill_id}`;

                const card = $(`
                    <div class="today-visit-item" data-search="${escapeHtml((v.patient_name + ' ' + v.uhid + ' ' + v.ref_by).toLowerCase())}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                <span class="fw-bold text-dark" style="font-size: 13px;">${escapeHtml(v.patient_name)}</span>
                                <span class="badge bg-light text-secondary border ms-1" style="font-size: 10px;">#${v.uhid}</span>
                            </div>
                            <span class="${statusBadgeClass}">${v.status}</span>
                        </div>

                        <div class="d-flex justify-content-between text-muted mb-2" style="font-size: 11px;">
                            <span><i class="uil uil-clock me-1"></i>${v.time}</span>
                            <span><i class="uil uil-flask me-1 text-teal"></i>${v.services_count} Service${v.services_count > 1 ? 's' : ''}</span>
                            <span><i class="uil uil-user-md me-1"></i>${escapeHtml(v.ref_by)}</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 11.5px;">
                            <div>
                                <span class="text-muted">Billed:</span>
                                <strong class="text-dark">₹${parseFloat(v.billed_amount).toFixed(0)}</strong>
                                <span class="text-muted ms-2">Paid:</span>
                                <strong class="text-success">₹${parseFloat(v.paid_amount).toFixed(0)}</strong>
                                ${v.due_amount > 0 ? `<span class="text-muted ms-2">Due:</span><strong class="text-danger">₹${parseFloat(v.due_amount).toFixed(0)}</strong>` : ''}
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-teal py-0 px-2 btn-preview-receipt" data-url="${escapeHtml(receiptUrl)}" data-title="Receipt #${escapeHtml(v.bill_number || '')}" data-accession="${escapeHtml(v.accession_no || '')}" style="font-size: 11px;" title="Preview & Print Receipt">
                                <i class="uil uil-print"></i>
                            </button>
                        </div>
                    </div>
                `);

                container.append(card);
            });
        },

        filterTodayVisitsList: function(term) {
            if (!term) {
                $('.today-visit-item').show();
                return;
            }

            $('.today-visit-item').each(function() {
                const searchData = $(this).data('search') || '';
                if (searchData.includes(term)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        resetForm: function() {
            this.cartItems = [];
            this.selectedPatient = null;
            $('#cart-tbody').empty();
            $('#uhid').val('');
            $('#b_uhid').text('---');
            $('#b_name').text('---');
            $('#b_age').text('---');
            $('#b_mobile_no').text('---');
            $('#b_i_cover').text('Cash');
            $('#patient_id').val('');
            $('#remarks').val('');
            $('#discount_percent').val('');
            $('#custom_sample_no').val('');
            this.refreshNextSampleNo();

            // Reset register form
            $('#reg_name, #reg_age, #reg_phone, #reg_city').val('');
            $('#patient-autocomplete-dropdown').hide();

            $('#switch9').prop('checked', false);
            this.activePatientMode = 'search';
            $('#search_box_container').show();
            $('#patient_info_bar').show();
            $('#pane_register_patient').hide();

            $('.payment-field').val('');
            $('#tx_upi, #tx_card').val('');
            $('#lbl-subtotal').val('₹0.00');
            $('#lbl-total').val('₹0.00');
            $('#lbl-total-paid').text('₹0.00');
            $('#lbl-due-amount').text('₹0.00');

            this.generateClientToken();
            window.lisToast('Billing desk form cleared.', 'info');

            if (this.activePatientMode === 'search') {
                $('#uhid').focus();
            } else {
                $('#reg_name').focus();
            }
        },

        submitBill: function() {
            const self = this;
            if (!self.cartItems.length) {
                window.lisToast('Please add at least one investigation to the bill.', 'warning');
                $('#search_service').focus();
                return;
            }

            let patientId = null;
            let walkinData = null;

            if (self.activePatientMode === 'search') {
                patientId = $('#patient_id').val();
                if (!patientId) {
                    window.lisToast('Please search and select an existing patient.', 'warning');
                    $('#uhid').focus();
                    return;
                }
            } else {
                // Register New Patient mode
                const regName = $('#reg_name').val().trim();
                const regAge = parseInt($('#reg_age').val()) || 0;
                const regSex = $('#reg_sex').val().toLowerCase();
                const regPhone = $('#reg_phone').val().trim().replace(/\D/g, '');

                if (!regName) {
                    window.lisToast('Please enter patient full name.', 'warning');
                    $('#reg_name').focus();
                    return;
                }

                if (!regAge || regAge <= 0) {
                    window.lisToast('Please enter a valid patient age.', 'warning');
                    $('#reg_age').focus();
                    return;
                }

                if (!regPhone) {
                    window.lisToast('Please enter mobile number.', 'warning');
                    $('#reg_phone').focus();
                    return;
                }

                if (regPhone.length !== 10) {
                    window.lisToast('Mobile number must be exactly 10 digits.', 'warning');
                    $('#reg_phone').focus();
                    return;
                }

                walkinData = {
                    name: regName,
                    age: regAge,
                    sex: regSex,
                    phone: regPhone
                };
            }

            const referralDoctor = $('#referral_select').val() || 'Self';
            const remarks = $('#remarks').val();
            const customSampleNo = $('#custom_sample_no').val() ? $('#custom_sample_no').val().trim() : null;

            const payments = [];
            $('.payment-field').each(function() {
                const amt = parseFloat($(this).val()) || 0;
                if (amt > 0) {
                    payments.push({
                        payment_mode: $(this).data('mode'),
                        amount: amt,
                        transaction_id: $('#tx_' + $(this).data('mode')).val() || null
                    });
                }
            });

            // User enters payments manually; only send entered payments
            const subtotalVal = parseFloat($('#lbl-subtotal').val().replace('₹', '')) || 0;
            const netVal = parseFloat($('#lbl-total').val().replace('₹', '')) || 0;
            const discountAmt = Math.max(0, subtotalVal - netVal);

            const payload = {
                client_token: self.clientToken,
                patient_id: patientId ? parseInt(patientId) : null,
                walkin: walkinData,
                priority: 'routine',
                referral_doctor: referralDoctor,
                clinical_notes: remarks || '',
                payer_category: 'general',
                custom_sample_no: customSampleNo || null,
                items: self.cartItems.map(function(item) {
                    return {
                        service_id: item.service_id,
                        charge: parseFloat(item.charge) || 0,
                        price: parseFloat(item.charge) || 0,
                        qty: parseFloat(item.qty) || 1,
                        discount: parseFloat(item.discount) || 0,
                        amount: parseFloat(item.amount) || 0
                    };
                }),
                bill_discount: discountAmt,
                bill_discount_per: parseFloat($('#discount_percent').val()) || 0,
                discount_reason: (parseFloat($('#discount_percent').val()) || 0) > 0 ? ((parseFloat($('#discount_percent').val()) || 0) + '% Discount') : null,
                payments: payments
            };

            const btn = $('#btn-save-bill').prop('disabled', true).html('<i class="uil uil-spinner-alt"></i> Generating...');

            // Open receipt popup synchronously on user click to prevent popup blockers
            const printWin = window.open('', 'ReceiptPrint', 'width=460,height=650,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes');
            if (printWin) {
                try {
                    printWin.document.write('<!DOCTYPE html><html><head><title>Preparing Receipt...</title><style>body{font-family:sans-serif;text-align:center;padding:50px 20px;color:#555;background:#f8fafc;}</style></head><body><div style="font-size:14px;font-weight:600;">Generating Bill Receipt...</div><div style="font-size:12px;color:#888;margin-top:6px;">Opening print preview...</div></body></html>');
                } catch(e) {}
            }

            $.ajax({
                url: self.endpoints.storeUrl,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function(res) {
                    btn.prop('disabled', false).html('<i class="uil uil-print"></i> Generate & Print');
                    window.lisToast('Bill generated successfully! Accession: ' + (res.order?.accession_no || ''), 'success');

                    if (res.receipt_url) {
                        if (printWin && !printWin.closed) {
                            printWin.location.href = res.receipt_url;
                        } else {
                            self.printReceiptDirectly(res.receipt_url);
                        }
                    } else if (printWin && !printWin.closed) {
                        printWin.close();
                    }

                    if (res.next_sample_no) {
                        $('#custom_sample_no').attr('placeholder', 'Auto: ' + res.next_sample_no).val('');
                    }

                    // Reload today's visits in real-time
                    self.loadTodayVisits();

                    setTimeout(function() {
                        self.resetForm();
                    }, 500);
                },
                error: function(xhr) {
                    if (printWin && !printWin.closed) {
                        printWin.close();
                    }
                    btn.prop('disabled', false).html('<i class="uil uil-print"></i> Generate & Print');
                    let msg = 'Failed to generate bill.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    window.lisToast(msg, 'danger');
                }
            });
        },

        refreshNextSampleNo: function() {
            const self = this;
            const url = self.endpoints.nextSampleUrl || '/lab/billing/next-sample-no';
            $.get(url, function(res) {
                if (res && res.next_sample_no) {
                    $('#custom_sample_no').attr('placeholder', 'Auto: ' + res.next_sample_no).val('');
                }
            });
        },

        printReceiptDirectly: function(receiptUrl) {
            if (!receiptUrl) return;

            const printWin = window.open(receiptUrl, 'ReceiptPrint', 'width=460,height=650,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes');
            if (printWin) {
                printWin.focus();
            } else {
                window.location.href = receiptUrl;
            }
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.LabBilling = LabBilling;
    $(function() {
        LabBilling.init();
    });

})(jQuery);
