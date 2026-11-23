<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software License Activation | LM-Path</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/icons.css') }}">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
            --bg-page: #f8fafc;
            --card-border: #e2e8f0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            color: #334155;
        }

        .license-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 680px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .license-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            padding: 28px 32px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .license-header h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.3px;
        }

        .license-header p {
            margin: 4px 0 0;
            font-size: 13px;
            opacity: 0.85;
        }

        .license-body {
            padding: 32px;
        }

        .machine-id-box {
            background: #f1f5f9;
            border: 1.5px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .machine-code {
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #0f172a;
        }

        .btn-copy {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-copy:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #15803d;
        }

        .status-badge.expired {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-badge.warning {
            background: #fef3c7;
            color: #b45309;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid transparent;
            cursor: pointer;
        }

        .tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }

        .license-textarea {
            font-family: monospace;
            font-size: 12px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            height: 120px;
            resize: vertical;
        }

        .btn-activate {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            width: 100%;
            cursor: pointer;
            transition: background 0.15s;
        }

        .btn-activate:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

    <div class="license-card">
        <div class="license-header">
            <div>
                <h1><i class="uil uil-shield-check"></i> System License Management</h1>
                <p>LM-Path Clinical Laboratory System</p>
            </div>
            <div>
                @if($status['valid'])
                    <span class="status-badge active">
                        <i class="uil uil-check-circle"></i> Active ({{ $status['days_left'] }} days left)
                    </span>
                @else
                    <span class="status-badge expired">
                        <i class="uil uil-exclamation-triangle"></i> Locked / {{ $status['code'] }}
                    </span>
                @endif
            </div>
        </div>

        <div class="license-body">
            @if(session('error') || session('license_error'))
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="uil uil-exclamation-octagon me-2" style="font-size: 1.25rem;"></i>
                    <div>
                        <strong>License Lockout:</strong> {{ session('error') ?: session('license_error') }}
                    </div>
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                    <i class="uil uil-check-circle me-2" style="font-size: 1.25rem;"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            <!-- Machine ID Hardware Lock Box -->
            <label class="form-label text-muted small fw-bold text-uppercase mb-1">
                <i class="uil uil-desktop"></i> This Computer's Machine ID (Hardware Lock)
            </label>
            <div class="machine-id-box">
                <div>
                    <div class="machine-code" id="machineIdText">{{ $machineId }}</div>
                    <small class="text-muted">Unique fingerprint tied to this motherboard, disk, and operating system.</small>
                </div>
                <button type="button" class="btn-copy" onclick="copyMachineId()">
                    <i class="uil uil-copy"></i> <span id="copyBtnLabel">Copy ID</span>
                </button>
            </div>

            @if($status['valid'])
                <div class="p-3 mb-4 rounded-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <h6 class="fw-bold text-success mb-2"><i class="uil uil-check-circle"></i> Currently Installed License</h6>
                    <div class="row g-2 small text-muted">
                        <div class="col-sm-6">
                            <strong>Client / Lab:</strong> {{ $status['client_name'] }}
                        </div>
                        <div class="col-sm-6">
                            <strong>Expires On:</strong> {{ \Carbon\Carbon::parse($status['expires_at'])->format('d M Y, h:i A') }}
                        </div>
                        <div class="col-sm-6">
                            <strong>Days Remaining:</strong> <span class="fw-bold text-dark">{{ $status['days_left'] }} day(s)</span>
                        </div>
                        <div class="col-sm-6">
                            <strong>Target Machine:</strong> <code>{{ $status['license_machine_id'] }}</code>
                        </div>
                    </div>
                </div>
                <div class="text-center mb-4">
                    <a href="{{ route('lab.billing.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="uil uil-arrow-left"></i> Return to Billing Dashboard
                    </a>
                </div>
            @endif

            <hr class="my-4">

            <h5 class="fw-bold mb-3">Install / Renew License</h5>
            <p class="text-muted small mb-3">
                Send your Machine ID above to your software provider. Once you receive your <code>license.lic</code> file or activation key, apply it below.
            </p>

            <form action="{{ route('license.submit') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <ul class="nav nav-tabs mb-3 border-bottom" id="licenseTabs" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="tab-btn active" id="file-tab" onclick="switchTab('file')">
                            <i class="uil uil-file-upload"></i> Upload License File (.lic)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="tab-btn" id="key-tab" onclick="switchTab('key')">
                            <i class="uil uil-key-skeleton"></i> Paste License Key
                        </button>
                    </li>
                </ul>

                <div id="tab-file-content" class="mb-3">
                    <label class="form-label small fw-semibold">Select License File (.lic)</label>
                    <input type="file" name="license_file" class="form-control" accept=".lic,.txt">
                    <div class="form-text">Upload the <code>license.lic</code> file provided by support.</div>
                </div>

                <div id="tab-key-content" class="mb-3 d-none">
                    <label class="form-label small fw-semibold">License Key Text</label>
                    <textarea name="license_key" class="license-textarea" placeholder="-----BEGIN LM-LICENSE-----&#10;...&#10;-----END LM-LICENSE-----"></textarea>
                    <div class="form-text">Paste the full text block including BEGIN and END tags.</div>
                </div>

                <button type="submit" class="btn-activate mt-2">
                    <i class="uil uil-lock-open-alt"></i> Activate Software License
                </button>
            </form>
        </div>
    </div>

    <script>
        function copyMachineId() {
            const text = document.getElementById('machineIdText').innerText;
            navigator.clipboard.writeText(text).then(() => {
                const label = document.getElementById('copyBtnLabel');
                label.innerText = 'Copied!';
                setTimeout(() => { label.innerText = 'Copy ID'; }, 2000);
            });
        }

        function switchTab(type) {
            const fileTab = document.getElementById('file-tab');
            const keyTab = document.getElementById('key-tab');
            const fileContent = document.getElementById('tab-file-content');
            const keyContent = document.getElementById('tab-key-content');

            if (type === 'file') {
                fileTab.classList.add('active');
                keyTab.classList.remove('active');
                fileContent.classList.remove('d-none');
                keyContent.classList.add('d-none');
            } else {
                keyTab.classList.add('active');
                fileTab.classList.remove('active');
                keyContent.classList.remove('d-none');
                fileContent.classList.add('d-none');
            }
        }
    </script>
</body>
</html>
