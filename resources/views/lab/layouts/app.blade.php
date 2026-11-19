<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OPD Billing | Rahul Clinic & Laboratory')</title>

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
                <a href="{{ route('lab.billing.index') }}" class="{{ request()->routeIs('lab.billing.index') ? 'active' : '' }}">
                    <i class="uil uil-file-alt"></i>
                    <span>OPD Billing</span>
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
            <div class="empath-user-profile" title="Current User Account">
                <span class="empath-user-avatar"><i class="uil uil-user"></i></span>
                <span>{{ Auth::user()->name?? 'Admin' }}</span>
                <i class="uil uil-angle-down"></i>
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

    <!-- Global Toast Notifications -->
    <div id="lis-toast-container" class="lis-toast-container" role="status" aria-live="polite" aria-atomic="true"></div>

    <script>
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        window.lisToast = function(message, type = 'success') {
            const toast = $('<div>').addClass('lis-toast lis-toast-' + type).text(message);
            $('#lis-toast-container').append(toast);
            setTimeout(() => toast.fadeOut(150, function() { $(this).remove(); }), 4200);
        };
    </script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
