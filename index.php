<?php include 'layout.php'; page_header('PawFect Home - Pet Adoption System', 'home'); ?>
<section class="hero-gradient text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge badge-light text-primary mb-3 px-3 py-2">Pet Adoption & Pet Needs Store</span>
                <h1 class="display-3 font-weight-bold mb-3">Adopt Your New Best Friend</h1>
                <p class="lead mb-4">PawFect Home helps pet lovers browse available pets, submit adoption applications, book shelter visits and buy basic pet needs in one organized website.</p>
                <a href="pets.php" class="btn btn-primary btn-lg px-4 mr-2">View Available Pets</a>
                <a href="products.php" class="btn btn-light btn-lg px-4">Shop Pet Needs</a>
            </div>
            <div class="col-lg-5 mt-5 mt-lg-0">
                <div class="card-clean p-4 text-dark">
                    <h4 class="mb-3">Adoption Process</h4>
                    <div class="timeline-step" data-step="1"><strong>Browse Pets</strong><br><span class="text-muted">Search by type, breed, age or gender.</span></div>
                    <div class="timeline-step" data-step="2"><strong>Apply & Track</strong><br><span class="text-muted">Submit an adoption application and check the decision status.</span></div>
                    <div class="timeline-step" data-step="3"><strong>Book Visit</strong><br><span class="text-muted">Meet the pet at the shelter before completing adoption arrangements.</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$stats = [];
foreach ([
    'Available Pets' => "SELECT COUNT(*) total FROM pets WHERE status='available'",
    'Applications' => "SELECT COUNT(*) total FROM adoption_applications",
    'Appointments' => "SELECT COUNT(*) total FROM appointments",
    'Pet Needs' => "SELECT COUNT(*) total FROM products"
] as $label => $sql) {
    $row = mysqli_fetch_assoc(mysqli_query($conn, $sql));
    $stats[$label] = (int)$row['total'];
}
?>
<section class="container py-5">
    <div class="row">
        <?php foreach ($stats as $label => $value): ?>
        <div class="col-md-3 mb-4">
            <div class="stat-card text-center">
                <h2 class="text-primary font-weight-bold mb-1"><?php echo $value; ?></h2>
                <p class="mb-0 text-muted"><?php echo h($label); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="soft-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Website Modules</h2>
            <p class="text-muted">Clear customer, staff and admin features for the pet adoption system.</p>
        </div>
        <div class="row">
            <?php
            $modules = [
                ['fas fa-user-lock','Authentication & Profile','Sign up, sign in, reset password, sign out and manage profile.'],
                ['fas fa-paw','Pet Browsing','View pets, pet details, search/filter and wishlist.'],
                ['fas fa-file-signature','Adoption Application','Submit, track, approve and reject adoption requests.'],
                ['fas fa-shopping-bag','Pet Needs Store','Browse pet food, toys, care items and accessories.'],
                ['fas fa-credit-card','Product Payment','Pay for pet needs and print payment receipts.'],
                ['fas fa-calendar-check','Appointment Booking','Book, edit, cancel and manage shelter visits.']
            ];
            foreach ($modules as $m): ?>
            <div class="col-md-4 mb-4"><div class="dashboard-card"><i class="<?php echo $m[0]; ?>"></i><h5><?php echo h($m[1]); ?></h5><p class="text-muted mb-0"><?php echo h($m[2]); ?></p></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0"><img src="img/about-2.jpg" class="img-fluid rounded shadow" alt="Real pets waiting for adoption"></div>
        <div class="col-lg-6">
            <h2>Centralized Pet Adoption Management</h2>
            <p class="text-muted">Instead of depending on manual records or scattered social media posts, this system keeps pet profiles, applications, appointments, announcements and product payments in one place.</p>
            <a href="pets.php" class="btn btn-primary px-4 mr-2">Start Exploring</a>
            <a href="about.php" class="btn btn-outline-primary px-4">Learn More</a>
        </div>
    </div>
</section>
<?php page_footer(); ?>
