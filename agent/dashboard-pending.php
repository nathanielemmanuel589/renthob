<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Application Under Review — Rent Hob Agent</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        :root {
            --adminuiux-content-font: "Open Sans", sans-serif;
            --adminuiux-content-font-weight: 400;
            --adminuiux-title-font: "Inter", sans-serif;
            --adminuiux-title-font-weight: 600;
        }
    </style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
    <style>
        .pending-hero {
            background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
            padding: 80px 0;
            color: white;
            text-align: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pending-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            margin: 0 auto;
            color: #333;
        }
        .status-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 20px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: -20px;
            width: 2px;
            background: #ddd;
        }
        .timeline-item:last-child:before {
            display: none;
        }
        .timeline-dot {
            position: absolute;
            left: 0;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ffd89b;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #ffd89b;
        }
        .timeline-dot.active {
            background: #19547b;
            box-shadow: 0 0 0 3px #19547b;
        }
        .contact-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
    </style>
</head>
<body>

<div class="pending-hero">
    <div class="container">
        <div class="pending-card">
            <div class="status-icon">
                <i data-feather="clock" style="width:60px;height:60px;color:white;"></i>
            </div>

            <h1 class="mb-3">Application Under Review</h1>
            <p class="lead text-muted mb-4">
                Thank you for applying to become a Rent Hob Agent!
            </p>

            <div class="alert alert-warning mb-4">
                <i data-feather="info" class="me-2" style="width:20px;height:20px;"></i>
                <strong>Status:</strong> Your application is currently being reviewed by our admin team.
            </div>

            <div class="text-start mt-5 mb-4">
                <h4 class="mb-4">What's Happening Now</h4>
                
                <div class="timeline-item">
                    <div class="timeline-dot active"></div>
                    <div>
                        <strong>Application Submitted</strong>
                        <p class="small text-muted mb-0">Your agent application has been successfully received</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot active"></div>
                    <div>
                        <strong>Admin Review</strong>
                        <p class="small text-muted mb-0">Our team is currently reviewing your profile and credentials</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div>
                        <strong>Approval Decision</strong>
                        <p class="small text-muted mb-0">You'll receive an email notification once a decision is made</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div>
                        <strong>Get Started</strong>
                        <p class="small text-muted mb-0">Upon approval, you'll gain full access to your agent dashboard</p>
                    </div>
                </div>
            </div>

            <div class="contact-box">
                <h5 class="mb-3"><i data-feather="help-circle" class="me-2" style="width:20px;height:20px;"></i> Need Help?</h5>
                <p class="small mb-2">If you have any questions about your application status, please contact us:</p>
                <p class="mb-1"><i data-feather="mail" class="me-2" style="width:16px;height:16px;"></i> <strong>Email:</strong> support@renthob.com</p>
                <p class="mb-0"><i data-feather="phone" class="me-2" style="width:16px;height:16px;"></i> <strong>Phone:</strong> +234 800 RENT HOB</p>
            </div>

            <div class="mt-4">
                <p class="small text-muted mb-3">While you wait, you can:</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="profile.php" class="btn btn-outline-primary btn-sm">
                        <i data-feather="user" style="width:14px;height:14px;"></i> Update Profile
                    </a>
                    <a href="settings.php" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="settings" style="width:14px;height:14px;"></i> Account Settings
                    </a>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">
                        <i data-feather="log-out" style="width:14px;height:14px;"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
