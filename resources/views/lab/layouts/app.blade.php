<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Billing | ')</title>

    <!-- Professional Typography: Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Framework & Icons -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/icons.css') }}">
    <link rel="stylesheet" href="{{ asset('css/empath-opd-billing.css') }}?v={{ filemtime(public_path('css/empath-opd-billing.css')) }}">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('js/shortcuts.js') }}?v={{ filemtime(public_path('js/shortcuts.js')) }}"></script>
    @stack('styles')
</head>
<body data-lis-context="@yield('context', 'billing')">

    <!-- Professional Horizontal Header Bar -->
    @php
        $appLabSetting = \App\Models\LabSetting::current();
    @endphp
    <header class="empath-topbar" id="page-topbar">
        <div class="topbar-brand-section">
            <span class="topbar-clinic-badge">
                <i class="uil uil-hospital"></i> {{ $appLabSetting->lab_name ?: 'Rahul Clinic' }}
            </span>
            <div class="horizontal-menu">
                <a href="{{ route('lab.dashboard') }}" class="{{ request()->routeIs('lab.dashboard') ? 'active' : '' }}">
                    <i class="uil uil-apps"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('lab.billing.index') }}" class="{{ request()->routeIs('lab.billing.index') ? 'active' : '' }}">
                    <i class="uil uil-file-alt"></i>
                    <span>Billing</span>
                </a>
                <a href="{{ route('lab.billing.daily-summary') }}" class="{{ request()->routeIs('lab.billing.daily-summary') ? 'active' : '' }}">
                    <i class="uil uil-bill"></i>
                    <span>All Bills</span>
                </a>
                <a href="{{ route('lab.orders.index') }}" class="{{ request()->routeIs('lab.orders.*') ? 'active' : '' }}">
                    <i class="uil uil-clipboard-notes"></i>
                    <span>Lab Orders</span>
                </a>
                <a href="{{ route('lab.masters.tests.index') }}" class="{{ request()->routeIs('lab.masters.*') ? 'active' : '' }}">
                    <i class="uil uil-setting"></i>
                    <span>Masters</span>
                </a>
                <a href="{{ route('lab.settings.index') }}" class="{{ request()->routeIs('lab.settings.*') ? 'active' : '' }}">
                    <i class="uil uil-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <div class="empath-topbar-right">
            <div class="noti-badge-container" title="Notifications">
                <i class="uil uil-bell"></i>
                <span class="noti-badge">2</span>
            </div>
            <div class="dropdown">
                <div class="empath-user-profile dropdown-toggle" id="userAccountDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Current User Account">
                    <span class="empath-user-avatar"><i class="uil uil-user"></i></span>
                    <span class="d-none d-sm-inline">{{ Auth::user()->name ?? Auth::user()->username ?? 'Staff' }}</span>
                    <i class="uil uil-angle-down"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userAccountDropdown" style="min-width: 220px; border-radius: 10px; margin-top: 8px;">
                    <li class="px-3 py-2 border-bottom bg-light">
                        <div class="fw-bold text-dark" style="font-size: 13px;">{{ Auth::user()->name ?? 'Laboratory User' }}</div>
                        <div class="text-muted" style="font-size: 11px;">
                            {{ '@' . (Auth::user()->username ?? 'user') }}
                            @if(Auth::user() && Auth::user()->roles->isNotEmpty())
                                • <span class="badge bg-primary text-white" style="font-size: 10px;">{{ Auth::user()->roles->first()->name }}</span>
                            @endif
                        </div>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#changePasswordModal" style="font-size: 13px;">
                            <i class="uil uil-key-skeleton me-2 text-primary"></i> Change Password
                        </button>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" id="logoutForm" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item py-2 text-danger" style="font-size: 13px;">
                                <i class="uil uil-sign-out-alt me-2"></i> Sign Out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    @if(!empty($licenseStatus) && !empty($licenseStatus['is_expiring_soon']))
        <div style="background: #fffbeb; border-bottom: 1px solid #fde68a; padding: 10px 24px; display: flex; align-items: center; justify-content: space-between; font-size: 13px; color: #92400e;">
            <div>
                <i class="uil uil-exclamation-triangle" style="font-size: 16px; margin-right: 6px;"></i>
                <strong>License Notice:</strong> Your system license will expire in <strong>{{ $licenseStatus['days_left'] }} day(s)</strong> (on {{ \Carbon\Carbon::parse($licenseStatus['expires_at'])->format('d M Y') }}). Please renew promptly to prevent system lockout.
            </div>
            <a href="{{ route('license.activate') }}" class="btn btn-sm btn-warning" style="font-size: 11px; padding: 3px 10px; font-weight: 600;">
                Renew License
            </a>
        </div>
    @endif

    <!-- Main Content Area -->
    <main class="billing-main-container" id="main-content">
        @yield('content')
    </main>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
                <div class="modal-header bg-light py-2 px-3 border-bottom">
                    <h5 class="modal-title fs-6 fw-bold text-dark d-flex align-items-center" id="changePasswordModalLabel">
                        <i class="uil uil-key-skeleton text-primary me-2 fs-5"></i> Update Security Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('user.password.update') }}" method="POST" id="changePasswordForm">
                    @csrf
                    <div class="modal-body p-3">
                        @if ($errors->passwordUpdate->any())
                            <div class="alert alert-danger py-2 px-3 mb-3" style="font-size: 13px; border-radius: 8px;">
                                <div class="fw-semibold d-flex align-items-center mb-1">
                                    <i class="uil uil-exclamation-triangle me-1 fs-5"></i>
                                    <span>Validation Failed</span>
                                </div>
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->passwordUpdate->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Current Password -->
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold mb-1">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="uil uil-lock-alt"></i></span>
                                <input 
                                    type="password" 
                                    name="current_password" 
                                    id="modalCurrentPassword"
                                    class="form-control @if($errors->passwordUpdate->has('current_password')) is-invalid @endif" 
                                    required 
                                    autocomplete="current-password"
                                    placeholder="Enter your current password"
                                >
                                <button class="btn btn-outline-secondary toggle-modal-pwd" type="button" data-target="modalCurrentPassword" title="Show / Hide Password">
                                    <i class="uil uil-eye"></i>
                                </button>
                            </div>
                            @if($errors->passwordUpdate->has('current_password'))
                                <div class="text-danger small mt-1" style="font-size: 12px;">
                                    <i class="uil uil-times-circle me-1"></i>{{ $errors->passwordUpdate->first('current_password') }}
                                </div>
                            @endif
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold mb-1">New Password (min 6 characters) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="uil uil-key-skeleton"></i></span>
                                <input 
                                    type="password" 
                                    name="new_password" 
                                    id="modalNewPassword"
                                    class="form-control @if($errors->passwordUpdate->has('new_password')) is-invalid @endif" 
                                    required 
                                    minlength="6" 
                                    autocomplete="new-password"
                                    placeholder="Enter new password"
                                >
                                <button class="btn btn-outline-secondary toggle-modal-pwd" type="button" data-target="modalNewPassword" title="Show / Hide Password">
                                    <i class="uil uil-eye"></i>
                                </button>
                            </div>
                            @if($errors->passwordUpdate->has('new_password'))
                                <div class="text-danger small mt-1" style="font-size: 12px;">
                                    <i class="uil uil-times-circle me-1"></i>{{ $errors->passwordUpdate->first('new_password') }}
                                </div>
                            @endif
                        </div>

                        <!-- Confirm New Password -->
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold mb-1">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="uil uil-check-circle"></i></span>
                                <input 
                                    type="password" 
                                    name="new_password_confirmation" 
                                    id="modalConfirmPassword"
                                    class="form-control @if($errors->passwordUpdate->has('new_password_confirmation')) is-invalid @endif" 
                                    required 
                                    minlength="6" 
                                    autocomplete="new-password"
                                    placeholder="Re-enter new password"
                                >
                                <button class="btn btn-outline-secondary toggle-modal-pwd" type="button" data-target="modalConfirmPassword" title="Show / Hide Password">
                                    <i class="uil uil-eye"></i>
                                </button>
                            </div>
                            @if($errors->passwordUpdate->has('new_password_confirmation'))
                                <div class="text-danger small mt-1" style="font-size: 12px;">
                                    <i class="uil uil-times-circle me-1"></i>{{ $errors->passwordUpdate->first('new_password_confirmation') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2 border-top">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="uil uil-check me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Global Toast Notifications -->
    <div id="lis-toast-container" class="lis-toast-container" role="status" aria-live="polite" aria-atomic="true"></div>

    <script>
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        window.lisToast = function(message, type = 'success') {
            const toast = $('<div>').addClass('lis-toast lis-toast-' + type).text(message);
            $('#lis-toast-container').append(toast);
            setTimeout(() => toast.fadeOut(150, function() { $(this).remove(); }), 4200);
        };

        @if(session('password_status'))
            window.lisToast(@json(session('password_status')), 'success');
        @endif

        @if(session('status'))
            window.lisToast(@json(session('status')), 'success');
        @endif

        @if($errors->passwordUpdate->any())
            $(document).ready(function() {
                var modalEl = document.getElementById('changePasswordModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }
            });
        @endif

        // Password visibility toggles inside modal
        $(document).on('click', '.toggle-modal-pwd', function(e) {
            e.preventDefault();
            var targetId = $(this).data('target');
            var input = document.getElementById(targetId);
            var icon = $(this).find('i');
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.removeClass('uil-eye').addClass('uil-eye-slash');
                } else {
                    input.type = 'password';
                    icon.removeClass('uil-eye-slash').addClass('uil-eye');
                }
            }
        });
    </script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
