<?php
require_once __DIR__ . '/includes/bootstrap.php';
mysqli_report(MYSQLI_REPORT_OFF);
$action = $_GET['action'] ?? '';
if ($action === 'forgot_password' || $action === 'reset_password') {
    header('Content-Type: application/json');
    ob_start();
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
        if (!$admin) { die(json_encode(['success'=>true,'message'=>'If the username exists, a reset token has been generated.'])); }
        $token = bin2hex(random_bytes(16));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = mysqli_prepare($conn, "UPDATE admins SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
        if (!$stmt) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiry, $admin['id']);
        if (!mysqli_stmt_execute($stmt)) { http_response_code(500); die(json_encode(['success'=>false,'error'=>mysqli_error($conn)])); }
        die(json_encode(['success'=>true,'message'=>'If the username exists, a reset token has been generated.']));
    }
    if ($action === 'reset_password') {
        $token = trim($_POST['token'] ?? ''); $password = trim($_POST['password'] ?? '');
        if (!$token||!$password) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Token and password required'])); }
        if (strlen($password)<8) { http_response_code(400); die(json_encode(['success'=>false,'error'=>'Password must be at least 8 characters'])); }
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
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>CubeSpace - Workspace Solutions</title>
    <meta name="description" content="Flexible office solutions in Chennai - managed offices, coworking spaces, and commercial leasing for startups, SMEs and enterprises.">
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
        .hero h1 {
            font-size: 45px;
        }
        .hero p {
            font-size: 20px;
        }
        @media (max-width: 991px) {
            .hero h1 { font-size: 42px; }
            .hero p { font-size: 18px; }
        }
        @media (max-width: 767px) {
            .hero h1 { font-size: 34px; }
            .hero p { font-size: 16px; }
        }
        @media (max-width: 575px) {
            .hero h1 { font-size: 28px; }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- =========================
     HERO
    ========================= -->

    <section class="hero">

        <div class="hero-content">

            <div class="leasing-title" style="padding-bottom:0;margin-bottom:0; font-size: 45px; font-weight: 1200;">www.cubespaces.in</div>
            <p class="leasing-title" style="padding-top:0;font-weight:150;">Online office space search platform</p>
            <h1>Find your Perfect Workspace</h1>

            <p>Flexible office solutions for startups, SMEs and Enterprises</p>

            <div class="leasing-box">

                <div class="leasing-title">Long-term Leasing</div>

                <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
                    <div class="leasing-tabs">
                        <button type="button" class="tab-btn active" onclick="cubeNavigate('managed_offices.php')">
                            Managed Office Spaces<br><span style="text-transform: none;">( All Inclusive Rentals )</span>
                            <br><span class="hero-tab-cta" style="text-decoration:none !important;">Click Here</span>
                        </button>
                    </div>
                    <div class="leasing-tabs">
                        <button type="button" class="tab-btn" onclick="cubeNavigate('furnished_offices.php')">
                            Furnished / Unfurnished Office Spaces<br><span style="text-transform: none;">( Regular Rental on S<span style="text-transform: lowercase;">q</span> F<span style="text-transform: lowercase;">t</span> basis )</span>
                            <br><span class="hero-tab-cta" style="text-decoration:none !important;">Click Here</span>
                        </button>
                    </div>
                </div>

            </div>

        </div>

    </section>

    <!-- =========================
     ABOUT US
    ========================= -->

    <section class="about-section" id="about">

        <div class="about-container">

            <div class="about-image">
                <img src="https://images.unsplash.com/photo-1497366754035-f200968a6e72?w=1200" alt="Modern Office Workspace" loading="lazy" width="600" height="450">
                <div class="experience-box">
                    <span>Serving Clients since</span>
                    <h3>2013</h3>
                </div>
            </div>

            <div class="about-content">

                <h3 class="about-title">ABOUT US</h3>

                <h2 style="text-align: justify;">Welcome to cubespaces.in, online office space search platform of Falcon Leasing And Real Estate Solutions, Chennai</h2>

                <p style="font-family: 'Segoe UI', sans-serif; text-align: justify;">
    cubespaces.in simplifies workspace discovery with flexible leasing options, managed offices and customized solutions. We primarily serve IT, ITES, Corporate sectors for their office space expansions and consolidations in Chennai.
</p>

<p style="font-family: 'Segoe UI', sans-serif; text-align: justify;">
    Whether you're looking for a fully managed office, private workspace, corporate headquarters, our team helps you find the right space tailored to your business needs.
</p>

<p style="font-family: 'Segoe UI', sans-serif; text-align: justify;">
    With our expertise, we do in-depth due diligence of office spaces we propose to our clients and ensure a smooth occupation throughout the lease tenure. We have served over 200+ well established IT, ITES and Corporate clients for their office space requirement in Chennai.
</p>
                <div class="about-features">
                    <div class="feature-item"><i class="fa-solid fa-circle-check" style="color: #0d4ab4; margin-right: 8px;"></i> Flexible Leasing Solutions</div>
                    <div class="feature-item"><i class="fa-solid fa-circle-check" style="color: #0d4ab4; margin-right: 8px;"></i> Premium Office Locations</div>
                    <div class="feature-item"><i class="fa-solid fa-circle-check" style="color: #0d4ab4; margin-right: 8px;"></i> Dedicated Workspace Experts</div>
                    <div class="feature-item"><i class="fa-solid fa-circle-check" style="color: #0d4ab4; margin-right: 8px;"></i> Enterprise Ready Offices</div>
                </div>

            </div>

        </div>

    </section>

    <!-- =========================
     EXPERT BOX
    ========================= -->

    <div class="expert-box">

        <h2 class="expert-title">Why search office alone? Smart teams always choose expert and efficient assistance…</h2>

        <div class="expert-grid">
            <div class="expert-item">
                <i class="fa-solid fa-user-tie"></i>
                <p>Your Dedicated Consultant</p>
            </div>
            <div class="expert-item">
                <i class="fa-solid fa-handshake"></i>
                <p>Win-win commercial and lease negotiations, smooth transition</p>
            </div>
            <div class="expert-item">
                <i class="fa-solid fa-building"></i>
                <p>Verified, state-of-the-art Grade A office spaces with all modern amenities</p>
            </div>
        </div>

    </div>

    <!-- =========================
     CLIENTS
    ========================= -->

    <section class="clients-section">

        <div class="container">

            <h2>Some of clients we served</h2>

            <div class="logo-slider">
                <div class="logo-track" id="logoTrack">
                    <div class="logo-item"><img src="assets/logos/1.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/2.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/3.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/4.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/5.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/6.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/7.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/8.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/9.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/10.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/11.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/12.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/13.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/14.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/15.png" alt="Client logo" width="130" height="60"></div>
                    <div class="logo-item"><img src="assets/logos/16.png" alt="Client logo" width="130" height="60"></div>
                </div>
            </div>

        </div>

    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- =========================
     ADMIN LOGIN MODAL
    ========================= -->


    <script src="assets/js/site-nav.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/api-client.js"></script>
    <script src="assets/js/toast.js"></script>
    <script src="assets/js/realtime.js"></script>
    <script src="assets/js/forms.js"></script>
    <script src="assets/js/main.js"></script>
    <script>

        function openTab(evt, tabId) {
            var buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(function(b) { b.classList.remove('active'); });
            evt.currentTarget.classList.add('active');
        }

        // Duplicate logos for infinite scroll
        var track = document.getElementById('logoTrack');
        if (track) {
            var clones = track.innerHTML;
            track.innerHTML = clones + clones;
        }

    </script>

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
