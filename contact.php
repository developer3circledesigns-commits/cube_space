<?php
require_once __DIR__ . '/includes/bootstrap.php';
mysqli_report(MYSQLI_REPORT_OFF);
$action = $_GET['action'] ?? '';
if ($action === 'forgot_password' || $action === 'reset_password') {
    header('Content-Type: application/json');
    if ($action === 'forgot_password') {
        $username = trim($_POST['username'] ?? '');
        if (!$username) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Username required'])); }
        cubespace_load_db_config();
        if (!$conn) { http_response_code(500); die(json_encode(['success'=>false,'error'=>'DB unavailable'])); }
        $stmt = mysqli_prepare($conn, "SELECT id FROM admins WHERE username = ?");
        if (!$stmt) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        if (!$admin) { http_response_code(404); die(json_encode(['success'=>false,'error'=>'Admin not found'])); }
        $token = bin2hex(random_bytes(16));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = mysqli_prepare($conn, "UPDATE admins SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
        if (!$stmt) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiry, $admin['id']);
        if (!mysqli_stmt_execute($stmt)) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        die(json_encode(['success'=>true,'message'=>'Use this token. Expires in 1 hour.','reset_token'=>$token]));
    }
    if ($action === 'reset_password') {
        $token = trim($_POST['token'] ?? ''); $password = trim($_POST['password'] ?? '');
        if (!$token||!$password) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Token and password required'])); }
        if (strlen($password)<6) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Password min 6 chars'])); }
        cubespace_load_db_config();
        if (!$conn) { http_response_code(500); die(json_encode(['success'=>false,'error'=>'DB unavailable'])); }
        $stmt = mysqli_prepare($conn, "SELECT id FROM admins WHERE reset_token = ? AND reset_token_expiry > NOW()");
        if (!$stmt) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        if (!$admin) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Invalid or expired token'])); }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($conn, "UPDATE admins SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        if (!$stmt) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        mysqli_stmt_bind_param($stmt, 'si', $hash, $admin['id']);
        if (!mysqli_stmt_execute($stmt)) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        die(json_encode(['success'=>true,'message'=>'Password reset successfully.']));
    }
}
?>
<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Contact Us - CubeSpace</title>
    <meta name="description" content="Get in touch with CubeSpace for managed office, coworking and commercial leasing solutions in Chennai. Call +91 99622 00015 or email us.">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="assets/images/favicon-32x32.png">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" href="assets/images/hero.jpg" as="image">
    <link rel="preload" href="assets/css/style.css?v=5" as="style">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" as="style" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></noscript>
    <style>
        * { border-radius: 0 !important; }
        /* Contact page hero banner */
        .contact-hero {
            background: linear-gradient(rgba(0,0,0,.5),rgba(0,0,0,.5)),url(assets/images/hero.jpg) center/cover;
            min-height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 100px 20px 60px;
        }
        .contact-hero h1 {
            color: #fff;
            font-size: 35px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .contact-hero p {
            color: #fff;
            font-size: 17px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Contact section */
        .contact-section {
            max-width: 1100px;
            margin: 0 auto;
            padding: 60px 24px;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 32px;
            align-items: start;
        }
        .contact-form-card,
        .contact-info-card {
            background: #fff;
            border: 1px solid #e8ecf0;
            padding: 32px;
        }
        .contact-form-card h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .contact-form-card .subtitle {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 24px;
        }
        .contact-form-card input,
        .contact-form-card select,
        .contact-form-card textarea {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid #d2d3ee;
            font-size: 14px;
            font-family: inherit;
            color: #212121;
            background: #fff;
            outline: none;
        }
        .contact-form-card textarea {
            height: auto;
            padding: 12px 14px;
            resize: vertical;
        }
        .contact-form-card .phone-group {
            display: flex;
        }
        .contact-form-card .phone-prefix {
            height: 48px;
            padding: 0 14px;
            background: #f4f4fb;
            border: 1px solid #d2d3ee;
            border-right: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #64748b;
            white-space: nowrap;
        }
        .contact-form-card .phone-input {
            flex: 1;
            height: 48px;
            padding: 0 14px;
            border: 1px solid #d2d3ee;
            font-size: 14px;
            font-family: inherit;
            color: #212121;
            background: #fff;
            outline: none;
        }
        .contact-form-card .btn-submit-form {
            width: 100%;
            height: 48px;
            background: #0d4ab4;
            color: #fff;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .contact-form-card .btn-submit-form:hover {
            background: #083891;
        }
        .contact-info-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .contact-info-card h3 i {
            margin-right: 8px;
            color: #0d4ab4;
        }
        .contact-info-card .info-desc {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .contact-info-card .info-value {
            /*font-size: 15px;*/
            /*font-weight: 700;*/
            color: #111827;
        }
        .contact-info-group {
            margin-bottom: 28px;
        }

        /* Contact page responsive */
        @media (max-width: 768px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
            .contact-hero {
                min-height: 200px;
                padding: 90px 16px 40px;
            }
            .contact-hero h1 {
                font-size: 28px;
            }
            .contact-hero p {
                font-size: 15px;
            }
            .contact-section {
                padding: 40px 16px;
            }
            .contact-form-card,
            .contact-info-card {
                padding: 24px;
            }
        }
        @media (max-width: 576px) {
            .contact-hero {
                min-height: 180px;
                padding: 80px 12px 30px;
            }
            .contact-hero h1 {
                font-size: 24px;
            }
            .contact-section {
                padding: 30px 12px;
            }
            .contact-form-card,
            .contact-info-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- =========================
     HERO BANNER
    ========================= -->
    <section class="contact-hero">
        <div>
            <h1>Share Your Requirement</h1>
            <p>Tell us your workspace needs and our experts will find the perfect space for you.</p>
        </div>
    </section>

    <!-- =========================
     CONTACT SECTION
    ========================= -->
    <section class="contact-section">
        <div class="contact-grid">

            <!-- FORM -->
            <div class="contact-form-card">
                <h2>Share your requirement</h2>
                <p class="subtitle">We'd like to hear from you! Tell us your requirements and our workspace experts will reach out to you at the earliest.</p>

                <form id="contactForm">

                    <div id="contactMsg" style="display:none; padding:12px; margin-bottom:15px;"></div>

                    <div class="mb-3">
                        <input type="text" name="name" placeholder="Name*" required data-rules="required|max:120" data-label="Name">
                    </div>

                    <input type="hidden" name="csrf_token" id="csrfToken" value="">

                    <div class="mb-3">
                        <div class="phone-group">
                            <span class="phone-prefix">🇮🇳 +91</span>
                            <input type="tel" name="phone" class="phone-input" placeholder="Mobile number*" required data-rules="required|phone" maxlength="10" title="Enter 10-digit Indian mobile number">
                        </div>
                    </div>

                    <div class="mb-3">
                        <input type="email" name="email" placeholder="Email" data-rules="email">
                    </div>

                    <div class="mb-3">
                        <select name="interest">
                            <option value="" disabled selected hidden>I am interested in</option>
                            <option value="managed">Managed Furnished Office</option>
                            <option value="furnished">Furnished / Unfurnished Office</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <input type="text" name="company" placeholder="Company Name" data-rules="max:160">
                    </div>

                    <div class="mb-3">
                        <select name="seats">
                            <option value="">Total Seats Required</option>
                            <option value="10-50">10-50</option>
                            <option value="51-100">51-100</option>
                            <option value="101-200">101-200</option>
                            <option value="200+">200+</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <textarea name="message" placeholder="Tell us more about your requirements" rows="3" data-rules="max:1000"></textarea>
                    </div>

                    <!-- Honeypot -->
                    <input type="text" name="website" style="position:absolute;left:-9999px;top:-9999px" tabindex="-1" autocomplete="off">

                    <input type="hidden" name="office_id" id="hiddenOfficeId" value="">
                    <input type="hidden" name="source" id="hiddenSource" value="">

                    <button type="submit" class="btn-submit-form">Submit Request</button>

                </form>
            </div>

            <!-- CONTACT INFO -->
            <div class="contact-info-card">
                <div class="contact-info-group">
                    <h3><i class="fa-solid fa-phone"></i>Call us</h3>
                    <p class="info-desc">Reach out to our sales team directly for any details.</p>
                    <p class="info-value">+(91) 99622 00015</p>
                </div>

                <div class="contact-info-group">
                    <h3><i class="fa-solid fa-envelope"></i>Email us</h3>
                    <p class="info-desc">For sales queries, partnerships, feedback, etc. Drop us an email</p>
                    <p class="info-value">hafiz@falconlease.com / sales@falconlease.com</p>
                </div>

                <div>
                    <h3><i class="fa-solid fa-location-dot"></i>Address</h3>
                    <p class="info-value">Chennai</p>
                </div>
            </div>

        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        function apiUrl(path) {
            if (window.CubeBase && typeof CubeBase.url === 'function') {
                return CubeBase.url(path);
            }
            return path;
        }
    </script>
    <script>
        // Populate hidden fields from URL params and page context
        (function() {
            const params = new URLSearchParams(window.location.search);
            const officeId = params.get('office_id');
            const office = params.get('office');
            if (officeId) document.getElementById('hiddenOfficeId').value = officeId;

            const ref = document.referrer || '';
            const src = ref
                ? (ref.includes('google.') ? 'google' :
                   ref.includes('facebook.') ? 'facebook' :
                   ref.includes('instagram.') ? 'instagram' :
                   ref.includes('linkedin.') ? 'linkedin' :
                   ref.includes('localhost') || ref.includes(window.location.host) ? 'direct' :
                   'referral')
                : 'direct';
            const sourceParts = [src];
            if (office) sourceParts.push('office:' + office);
            if (ref && !ref.includes(window.location.host)) sourceParts.push('ref:' + ref);
            document.getElementById('hiddenSource').value = sourceParts.join(' | ');

            let token = sessionStorage.getItem('csrf_token');
            if (!token) {
                token = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                sessionStorage.setItem('csrf_token', token);
            }
            document.getElementById('csrfToken').value = token;
        })();

        document.querySelector('input[name="phone"]').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });

        document.getElementById('contactForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (window.CSForms && !CSForms.validate(this)) return;
            const msg = document.getElementById('contactMsg');
            const btn = this.querySelector('button');
            const fd = new FormData(this);
            msg.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Submitting...';
            try {
                const d = window.CubeAPI
                    ? await CubeAPI.postForm('/api/contact.php', fd)
                    : await fetch(apiUrl('/api/contact.php'), { method: 'POST', body: fd }).then(r => r.json());
                msg.style.display = 'block';
                if (d.success) {
                    msg.style.background = '#ecfdf5'; msg.style.color = '#065f46'; msg.style.border = '1px solid #a7f3d0';
                    msg.innerHTML = '<i class="fa-solid fa-circle-check" style="margin-right:8px"></i>' + d.message;
                    this.reset();
                    const hp = this.querySelector('input[name="website"]');
                    if (hp) { hp.value = ''; hp.style.position = 'absolute'; hp.style.left = '-9999px'; }
                } else {
                    msg.style.background = '#fef2f2'; msg.style.color = '#991b1b'; msg.style.border = '1px solid #fecaca';
                    msg.innerHTML = '<i class="fa-solid fa-circle-exclamation" style="margin-right:8px"></i>' + (d.message || d.error || 'Something went wrong');
                }
            } catch (err) {
                msg.style.display = 'block';
                msg.style.background = '#fef2f2'; msg.style.color = '#991b1b'; msg.style.border = '1px solid #fecaca';
                msg.innerHTML = '<i class="fa-solid fa-circle-exclamation" style="margin-right:10px"></i>' + (err.message || 'Network error. Please try again.');
                console.error('Contact form error:', err);
            }
            btn.disabled = false;
            btn.textContent = 'Submit Request';
        });


    </script>
    <script src="assets/js/site-nav.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/api-client.js"></script>
    <script src="assets/js/toast.js"></script>
    <script src="assets/js/realtime.js"></script>
    <script src="assets/js/forms.js"></script>
    <script src="assets/js/main.js"></script>

<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fa-solid fa-circle-check text-success mb-3 d-none" id="alertIconSuccess" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-exclamation text-danger mb-3 d-none" id="alertIconError" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-info text-primary mb-3 d-none" id="alertIconInfo" style="font-size: 2rem;"></i>
                <p class="mb-0 fw-medium" id="alertMessage">Message</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
function showAlertModal(message, type) {
    type = type || 'info';
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('alertIconSuccess').classList.add('d-none');
    document.getElementById('alertIconError').classList.add('d-none');
    document.getElementById('alertIconInfo').classList.add('d-none');
    document.getElementById('alertIcon' + type.charAt(0).toUpperCase() + type.slice(1)).classList.remove('d-none');
    var modalEl = document.getElementById('alertModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}
</script>
</body>
</html>
