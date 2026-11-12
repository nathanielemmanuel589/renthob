<?php
/**
 * Admin - Contact Form Messages
 * Messages from public contact form on website
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// For now, this is a placeholder
// The contact_messages table needs to be created when you add a public contact form
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Contact Messages — Rent Hob Admin</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    
    <style>:root{--adminuiux-content-font:"Open Sans",sans-serif;--adminuiux-content-font-weight:400;--adminuiux-title-font:"Inter",sans-serif;--adminuiux-title-font-weight:600;}</style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-green bg-gradient-3 scrollup"
      data-theme="theme-green"
      data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent"
      data-bggradient="bg-gradient-3">

    <!-- Page Loader -->
    <div class="pageloader">
        <div class="container h-100">
            <div class="row justify-content-center align-items-center text-center h-100">
                <div class="col-auto">
                    <img src="<?= BASE_URL ?>/dashboard/assets/img/logo-512.png" alt="" class="height-100 mb-3">
                    <p class="h3 mb-0">Rent Hob</p>
                    <div class="loader10 mb-3 mx-auto"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- HEADER -->
    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()">
                    <i class="sidebar-svg" data-feather="menu"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark"  src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20">
                        <span class="h4">Rent Hob</span>
                        <p class="company-tagline">Admin Panel</p>
                    </div>
                </a>
                <div class="ms-auto d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2"
                                type="button" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>"
                                 alt="Admin" class="rounded-circle" width="34" height="34" style="object-fit:cover;">
                            <span class="d-none d-md-inline small fw-semibold"><?= e($_SESSION['user_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i data-feather="settings" class="me-2" style="width:15px;height:15px;"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">

        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">Contact Form Messages</h4>
                        <p class="text-muted mb-0">Messages from public contact form</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i data-feather="mail" style="width:64px;height:64px;opacity:.3;" class="mb-3"></i>
                        <h5>Contact Form Feature Coming Soon</h5>
                        <p class="text-muted mb-4">
                            This page will display messages submitted through the public contact form on your website.<br>
                            The contact form and database table need to be set up first.
                        </p>
                        
                        <div class="alert alert-info d-inline-block text-start">
                            <h6><i data-feather="info" style="width:16px;height:16px;"></i> What you need:</h6>
                            <ol class="mb-0 ps-3">
                                <li>Create <code>contact_messages</code> table in database</li>
                                <li>Add contact form to public website (e.g., contact-us.php)</li>
                                <li>Form should save to <code>contact_messages</code> table</li>
                                <li>Messages will appear here automatically</li>
                            </ol>
                        </div>
                        
                        <div class="mt-4">
                            <a href="messages.php" class="btn btn-success">
                                <i data-feather="message-square" style="width:16px;height:16px;"></i>
                                Go to Internal Messages
                            </a>
                            <a href="conversations-monitor.php" class="btn btn-primary">
                                <i data-feather="monitor" style="width:16px;height:16px;"></i>
                                Conversation Monitor
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6><i data-feather="database" style="width:16px;height:16px;"></i> Database Schema</h6>
                                <p class="small text-muted">Suggested table structure:</p>
                                <pre class="bg-light p-3 rounded"><code>CREATE TABLE contact_messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(50),
  subject VARCHAR(255),
  message TEXT NOT NULL,
  status ENUM('new','read','replied','closed') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);</code></pre>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6><i data-feather="code" style="width:16px;height:16px;"></i> Contact Form Example</h6>
                                <p class="small text-muted">Public form on website:</p>
                                <pre class="bg-light p-3 rounded"><code>&lt;form action="submit-contact.php" method="POST"&gt;
  &lt;input name="name" required&gt;
  &lt;input name="email" type="email" required&gt;
  &lt;input name="phone"&gt;
  &lt;input name="subject"&gt;
  &lt;textarea name="message" required&gt;&lt;/textarea&gt;
  &lt;button type="submit"&gt;Send&lt;/button&gt;
&lt;/form&gt;</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col text-center text-muted small py-2">
                        &copy; <?= date('Y') ?> Rent Hob. All rights reserved.
                    </div>
                </div>

            </div>
        </main>
    </div>

    <footer class="adminuiux-mobile-footer hide-on-scrolldown style-1">
        <div class="row gx-0 justify-content-around">
            <div class="col-auto"><a href="dashboard.php" class="nav-link text-center py-2 px-3"><i data-feather="grid" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Home</p></a></div>
            <div class="col-auto"><a href="users.php" class="nav-link text-center py-2 px-3"><i data-feather="users" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Users</p></a></div>
            <div class="col-auto"><a href="properties.php" class="nav-link text-center py-2 px-3"><i data-feather="home" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Properties</p></a></div>
            <div class="col-auto"><a href="settings.php" class="nav-link text-center py-2 px-3"><i data-feather="settings" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Settings</p></a></div>
            <div class="col-auto"><a href="<?= BASE_URL ?>/logout.php" class="nav-link text-center py-2 px-3"><i data-feather="log-out" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Logout</p></a></div>
        </div>
    </footer>
</body>
</html>
