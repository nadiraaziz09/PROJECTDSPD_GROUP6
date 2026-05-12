<?php
include_once 'auth.php';

function page_header($title = 'PawFect Home', $active = '') {
    $loggedIn = !empty($_SESSION['email']);
    $name = $_SESSION['name'] ?? 'Account';
    $home = role_home();

    // Role-based Pets link
    $pets_link = 'pets.php'; // default for regular users
    if ($loggedIn && in_array((int)($_SESSION['role'] ?? 0), [2,3])) {
        $pets_link = 'manage_pets.php'; // staff/admin
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="PawFect Home, Pet Adoption System" name="keywords">
    <meta content="PawFect Home Pet Adoption System" name="description">
    <title><?php echo h($title); ?></title>
    <link href="img/favicon.ico" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="lib/flaticon/font/flaticon.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet">
    <link href="css/pawfect.css" rel="stylesheet">
</head>
<body data-protected="<?php echo !empty($GLOBALS['PAGE_REQUIRES_LOGIN']) ? '1' : '0'; ?>">
    <div class="container-fluid topbar-wrap">
        <div class="row bg-secondary py-2 px-lg-5">
            <div class="col-lg-6 text-center text-lg-left mb-2 mb-lg-0 text-white small">
                <i class="fas fa-map-marker-alt mr-2"></i>Johor Pet Service Adoption Centre
            </div>
            <div class="col-lg-6 text-center text-lg-right text-white small">
                <i class="fas fa-clock mr-2"></i>Open daily 8.00AM - 9.00PM
                <span class="mx-2">|</span>
                <i class="fas fa-phone mr-2"></i>+012 345 6789
            </div>
        </div>
        <div class="row py-3 px-lg-5 align-items-center bg-white">
            <div class="col-lg-4">
                <a href="<?php echo h($home); ?>" class="navbar-brand d-none d-lg-block">
                    <h1 class="m-0 display-5 text-capitalize"><span class="text-primary">Paw</span>Fect Home</h1>
                </a>
            </div>
            <div class="col-lg-8 text-center text-lg-right">
                <div class="d-inline-flex align-items-center flex-wrap justify-content-center">
                    <div class="d-inline-flex flex-column text-center px-3 border-right">
                        <h6 class="mb-1">Email</h6><p class="m-0 small">pawfecthome@example.com</p>
                    </div>
                    <div class="d-inline-flex flex-column text-center px-3 border-right">
                        <h6 class="mb-1">Service</h6><p class="m-0 small">Adoption & Pet Needs</p>
                    </div>
                    <div class="d-inline-flex flex-column text-center px-3">
                        <h6 class="mb-1">System</h6><p class="m-0 small">Pet Adoption Web App</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid p-0 sticky-nav">
        <nav class="navbar navbar-expand-lg bg-dark navbar-dark py-3 py-lg-0 px-lg-5">
            <a href="<?php echo h($home); ?>" class="navbar-brand d-block d-lg-none">
                <h1 class="m-0 display-5 text-capitalize font-italic text-white"><span class="text-primary">Paw</span>Fect</h1>
            </a>
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse justify-content-between px-3" id="navbarCollapse">
                <div class="navbar-nav mr-auto py-0">
                    <a href="<?php echo h($home); ?>" class="nav-item nav-link <?php echo $active==='home'?'active':''; ?>">Home</a>
                    <a href="about.php" class="nav-item nav-link <?php echo $active==='about'?'active':''; ?>">About</a>
                    <a href="<?php echo h($pets_link); ?>" class="nav-item nav-link <?php echo $active==='pets'?'active':''; ?>">Pets</a>
                    <?php if (!$loggedIn || ($_SESSION['role'] ?? 1) == 1): ?>
                        <a href="wishlist.php" class="nav-item nav-link <?php echo $active==='wishlist'?'active':''; ?>">Wishlist</a>
                        <a href="applications.php" class="nav-item nav-link <?php echo $active==='applications'?'active':''; ?>">Applications</a>
                        <a href="payment_history.php" class="nav-item nav-link <?php echo $active==='payments'?'active':''; ?>">Product Payments</a>
                    <?php endif; ?>
                    <a href="appointment.php" class="nav-item nav-link <?php echo $active==='appointment'?'active':''; ?>">Appointment</a>
                    <?php if ($loggedIn && in_array((int)($_SESSION['role'] ?? 0), [2,3], true)): ?>
                        <a href="manage_products.php" class="nav-item nav-link <?php echo $active==='manage_products'?'active':''; ?>">Manage Pet Needs</a>
                    <?php endif; ?>
                    <a href="contact.php" class="nav-item nav-link <?php echo $active==='contact'?'active':''; ?>">Contact</a>
                </div>
                <div class="nav-item dropdown">
                    <?php if ($loggedIn): ?>
                        <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle mr-2"></i><?php echo h($name); ?></a>
                        <div class="dropdown-menu dropdown-menu-right rounded-0 m-0">
                            <a href="<?php echo h(role_home()); ?>" class="dropdown-item"><i class="fas fa-th-large mr-2"></i>Dashboard</a>
                            <a href="profile.php" class="dropdown-item"><i class="fas fa-user mr-2"></i>Profile</a>
                            <a href="change-password.php" class="dropdown-item"><i class="fas fa-key mr-2"></i>Change Password</a>
                            <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown">Account</a>
                        <div class="dropdown-menu dropdown-menu-right rounded-0 m-0">
                            <a href="signin.php" class="dropdown-item"><i class="fas fa-sign-in-alt mr-2"></i>Sign In</a>
                            <a href="signup.php" class="dropdown-item"><i class="fas fa-user-plus mr-2"></i>Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="container mt-4"><div class="alert alert-success shadow-sm"><?php echo h($msg); ?></div></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="container mt-4"><div class="alert alert-danger shadow-sm"><?php echo h($msg); ?></div></div>
    <?php endif; ?>
<?php
}

function page_title($title, $subtitle = '') { ?>
    <section class="page-hero text-center text-white">
        <div class="container">
            <h1 class="display-4 mb-2"><?php echo h($title); ?></h1>
            <?php if ($subtitle): ?><p class="lead mb-0"><?php echo h($subtitle); ?></p><?php endif; ?>
        </div>
    </section>
<?php }

function page_footer() { ?>
    <footer class="bg-dark text-white mt-5">
        <div class="container py-5">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="text-white"><span class="text-primary">Paw</span>Fect Home</h4>
                    <p class="mb-0">A complete web-based pet adoption system for customers, staff and administrators.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="text-white">Core Modules</h5>
                    <p class="mb-0 small">Authentication, pet browsing, wishlist, adoption application, product payment, appointment, pet management and admin reports.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="text-white">Contact</h5>
                    <p class="mb-1"><i class="fas fa-envelope mr-2"></i>pawfecthome@example.com</p>
                    <p class="mb-0"><i class="fas fa-phone mr-2"></i>+012 345 6789</p>
                </div>
            </div>
        </div>
        <div class="container-fluid text-center border-top border-secondary py-3">
            <p class="m-0">&copy; 2026 PawFect Home Pet Adoption System</p>
        </div>
    </footer>
    <a href="#" class="btn btn-lg btn-primary back-to-top"><i class="fa fa-angle-double-up"></i></a>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        (function () {
            function verifySession() {
                if (!document.body || document.body.getAttribute('data-protected') !== '1') return;
                fetch('auth_status.php?ts=' + Date.now(), {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { 'Cache-Control': 'no-cache' }
                })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.logged_in) {
                        window.location.replace('signin.php?expired=1');
                    }
                })
                .catch(function () {});
            }
            window.addEventListener('pageshow', verifySession);
            window.addEventListener('focus', verifySession);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) verifySession();
            });
        })();
    </script>
</body>
</html>
<?php }
?>