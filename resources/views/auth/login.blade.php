<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In | {{ \App\Models\LabSetting::current()->lab_name ?: '' }}</title>

    <!-- Professional Typography: Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Framework & Icons -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/icons.css') }}">

    <style>
        :root {
            --lis-primary: #1e3a8a;
            --lis-primary-dark: #172554;
            --lis-primary-light: #3b82f6;
            --lis-accent: #0284c7;
            --lis-bg: #f8fafc;
            --lis-card-bg: #ffffff;
            --lis-text: #0f172a;
            --lis-muted: #64748b;
            --lis-border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--lis-text);
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: var(--lis-card-bg);
            border-radius: 16px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.4), 0 0 1px 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-header {
            padding: 32px 32px 24px;
            text-align: center;
            background: linear-gradient(180deg, #f1f5f9 0%, #ffffff 100%);
            border-bottom: 1px solid var(--lis-border);
        }

        .login-brand-icon {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #1e3a8a 0%, #0284c7 100%);
            color: #ffffff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 14px;
            box-shadow: 0 8px 16px -4px rgba(2, 132, 199, 0.4);
        }

        .login-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--lis-text);
            margin: 0 0 4px 0;
            letter-spacing: -0.3px;
        }

        .login-subtitle {
            font-size: 13px;
            color: var(--lis-muted);
            margin: 0;
        }

        .login-body {
            padding: 28px 32px 32px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group-text {
            background-color: #f8fafc;
            border-color: var(--lis-border);
            color: #64748b;
            font-size: 17px;
            padding: 0 14px;
        }

        .form-control {
            border-color: var(--lis-border);
            font-size: 14px;
            padding: 11px 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--lis-accent);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .password-toggle-btn {
            background-color: #f8fafc;
            border: 1px solid var(--lis-border);
            border-left: none;
            color: #64748b;
            padding: 0 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }

        .password-toggle-btn:hover {
            color: #0f172a;
            background-color: #f1f5f9;
        }

        .form-check-label {
            font-size: 13px;
            color: #475569;
            user-select: none;
        }

        .btn-submit {
            background: linear-gradient(135deg, #1e3a8a 0%, #0284c7 100%);
            border: none;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #172554 0%, #0369a1 100%);
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35);
            transform: translateY(-1px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .login-footer {
            padding: 16px 32px;
            background-color: #f8fafc;
            border-top: 1px solid var(--lis-border);
            text-align: center;
            font-size: 12px;
            color: var(--lis-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .badge-secure {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #059669;
            font-weight: 600;
        }

        .alert {
            font-size: 13px;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    @php
        $labSetting = \App\Models\LabSetting::current();
        $clinicName = $labSetting->lab_name ?: '';
    @endphp

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Header with Hospital / Lab Identity -->
            <div class="login-header">
                <div class="login-brand-icon">
                    <i class="uil uil-hospital"></i>
                </div>
                <h1 class="login-title">{{ $clinicName }}</h1>
                <p class="login-subtitle">Laboratory Information Management System (LIS)</p>
            </div>

            <!-- Login Form Body -->
            <div class="login-body">
                @if (session('status'))
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="uil uil-check-circle me-2 fs-5"></i>
                        <div>{{ session('status') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <div class="d-flex align-items-center mb-1">
                            <i class="uil uil-exclamation-triangle me-2 fs-5"></i>
                            <strong>Authentication Failed</strong>
                        </div>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" id="loginForm" autocomplete="on">
                    @csrf

                    <!-- Username / Email Field -->
                    <div class="mb-3">
                        <label for="loginInput" class="form-label">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="uil uil-user"></i></span>
                            <input 
                                type="text" 
                                class="form-control @error('login') is-invalid @enderror" 
                                id="loginInput" 
                                name="login" 
                                value="{{ old('login') }}" 
                                placeholder="Enter username or email" 
                                required 
                                autofocus
                            >
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="mb-3">
                        <label for="passwordInput" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="uil uil-lock-alt"></i></span>
                            <input 
                                type="password" 
                                class="form-control @error('password') is-invalid @enderror" 
                                id="passwordInput" 
                                name="password" 
                                placeholder="Enter password" 
                                required
                            >
                            <button class="password-toggle-btn" type="button" id="togglePasswordBtn" title="Show / Hide Password">
                                <i class="uil uil-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" value="1" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="rememberMe">
                                Keep me signed in
                            </label>
                        </div>
                        <span class="text-muted" style="font-size: 11px;">
                            <i class="uil uil-shield-check text-primary"></i> 2FA / SSL
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-submit" id="submitBtn">
                        <i class="uil uil-sign-in-alt"></i>
                        <span>Secure Sign In</span>
                    </button>
                </form>
            </div>

            <!-- Footer Badge -->
            <div class="login-footer">
                <span class="badge-secure"><i class="uil uil-lock"></i> 256-Bit SSL Encrypted</span>
                <span>• Authorized Access Only</span>
            </div>
        </div>
    </div>

    <!-- Toggle Password Visibility Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (toggleBtn && passwordInput && toggleIcon) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        toggleIcon.classList.remove('uil-eye');
                        toggleIcon.classList.add('uil-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        toggleIcon.classList.remove('uil-eye-slash');
                        toggleIcon.classList.add('uil-eye');
                    }
                });
            }
        });
    </script>
</body>
</html>
