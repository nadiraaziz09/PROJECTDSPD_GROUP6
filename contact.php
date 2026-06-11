<?php
include 'layout.php';
if (isset($_POST['send'])) {
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $subject, $message);
    mysqli_stmt_execute($stmt);

    flash('success', 'Message sent successfully. Our team will review it soon.');
    header('Location: contact.php');
    exit();
}

page_header('Contact - PawFect Home', 'contact');
page_title('Contact Us', 'Need help with adoption, appointments or pet needs orders? Send us a message.');
?>

<?php
$branches = [
    [
        'label'   => 'Main Branch',
        'name'    => 'Dhani Pet Store',
        'address' => '38, 40, Jalan Setia Tropika 1/1, Setia Tropika, 81200 Johor Bahru, Johor',
        'phone'   => '+607-244 4013',
        'hours'   => '9.00 AM – 10.00 PM',
        'rating'  => '4.7 ★ (724 reviews)',
        'map'     => 'https://maps.google.com/maps?q=1.544241,103.709819&z=17&output=embed',
    ],
    [
        'label'   => 'Branch 2',
        'name'    => 'MR PET STORE – Taman Molek',
        'address' => '21, 42, Jalan Molek 1, Taman Molek, 81100 Johor Bahru, Johor',
        'phone'   => '+6016-308 4310',
        'hours'   => '10.00 AM – 9.00 PM',
        'rating'  => '5.0 ★ (167 reviews)',
        'map'     => 'https://maps.google.com/maps?q=1.5244739,103.7843241&z=17&output=embed',
    ],
    [
        'label'   => 'Branch 3',
        'name'    => 'Mystique Creatures',
        'address' => 'No. F01, 05, Jln Harmonium 24/2, Taman Desa Tebrau, 81100 Johor Bahru, Johor',
        'phone'   => '+6018-986 4023',
        'hours'   => '11.00 AM – 8.30 PM',
        'rating'  => '4.5 ★ (353 reviews)',
        'map'     => 'https://maps.google.com/maps?q=1.5508814,103.7956896&z=17&output=embed',
    ],
];
$active = 0;
?>

<div class="container py-5">

    <!-- Branch List + Map -->
    <div class="row mb-5" style="min-height:460px;">

        <!-- LEFT: Branch list -->
        <div class="col-lg-5 mb-4 mb-lg-0">
            <h5 class="font-weight-bold mb-4" style="color:#1f2428;">
                <i class="fas fa-map-marker-alt mr-2" style="color:var(--paw-primary);"></i>Our Branches
            </h5>

            <?php foreach ($branches as $i => $b): ?>
            <div class="branch-item card-clean p-3 mb-3 d-flex align-items-start"
                 style="cursor:pointer;border-left:4px solid <?= $i === $active ? 'var(--paw-primary)' : 'transparent' ?>;transition:.2s;"
                 onclick="selectBranch(<?= $i ?>)"
                 id="branch-<?= $i ?>">
                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                     style="width:42px;height:42px;background:var(--paw-soft);">
                    <i class="fas fa-store" style="color:var(--paw-primary);font-size:.95rem;"></i>
                </div>
                <div style="min-width:0;">
                    <span class="badge badge-pill mb-1" style="background:#fff0ec;color:var(--paw-primary);font-size:.7rem;font-weight:700;letter-spacing:.04em;">
                        <?= htmlspecialchars($b['label']) ?>
                    </span>
                    <div class="font-weight-bold" style="color:#1f2428;font-size:.97rem;">
                        <?= htmlspecialchars($b['name']) ?>
                    </div>
                    <div class="text-muted mt-1" style="font-size:.84rem;line-height:1.5;">
                        <i class="fas fa-map-marker-alt mr-1" style="font-size:.75rem;"></i><?= htmlspecialchars($b['address']) ?>
                    </div>
                    <div class="mt-1" style="font-size:.82rem;color:#6c757d;">
                        <i class="fas fa-phone-alt mr-1" style="font-size:.75rem;"></i>
                        <a href="tel:<?= preg_replace('/\s+/','',$b['phone']) ?>" class="text-muted"><?= htmlspecialchars($b['phone']) ?></a>
                        &nbsp;&middot;&nbsp;
                        <i class="fas fa-clock mr-1" style="font-size:.75rem;"></i><?= htmlspecialchars($b['hours']) ?>
                    </div>
                    <div class="mt-1" style="font-size:.8rem;color:#f5a623;font-weight:600;">
                        <?= htmlspecialchars($b['rating']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- RIGHT: Map -->
        <div class="col-lg-7">
            <div class="card-clean overflow-hidden" style="height:100%;min-height:420px;">
                <?php foreach ($branches as $i => $b): ?>
                <iframe id="map-<?= $i ?>"
                    src="<?= htmlspecialchars($b['map']) ?>"
                    width="100%" height="100%"
                    style="border:0;display:<?= $i === $active ? 'block' : 'none' ?>;min-height:420px;"
                    loading="lazy" allowfullscreen
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- /branch row -->

    <!-- Divider -->
    <hr class="my-5">

    <!-- Contact Form -->
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="text-center mb-4">
                <h4 class="font-weight-bold" style="color:#1f2428;">
                    <i class="fas fa-envelope mr-2" style="color:var(--paw-primary);"></i>Send Us a Message
                </h4>
                <p class="text-muted" style="font-size:.93rem;">Have a question or concern? Fill in the form and our team will respond within 1 business day.</p>
            </div>
            <div class="card-clean p-4 p-md-5">
                <form method="post">
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark" style="font-size:.9rem;">Full Name</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" style="background:#fafafa;border-right:0;">
                                        <i class="fas fa-user text-muted" style="font-size:.85rem;"></i>
                                    </span>
                                </div>
                                <input name="name" class="form-control" style="border-left:0;" placeholder="e.g. Ahmad Razif" required>
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark" style="font-size:.9rem;">Email Address</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" style="background:#fafafa;border-right:0;">
                                        <i class="fas fa-envelope text-muted" style="font-size:.85rem;"></i>
                                    </span>
                                </div>
                                <input type="email" name="email" class="form-control" style="border-left:0;" placeholder="you@example.com" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-dark" style="font-size:.9rem;">Subject</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#fafafa;border-right:0;">
                                    <i class="fas fa-tag text-muted" style="font-size:.85rem;"></i>
                                </span>
                            </div>
                            <input name="subject" class="form-control" style="border-left:0;" placeholder="e.g. Adoption Enquiry" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-dark" style="font-size:.9rem;">Message</label>
                        <textarea name="message" class="form-control" rows="6" placeholder="Write your message here…" required></textarea>
                    </div>
                    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;">
                        <p class="text-muted mb-0" style="font-size:.82rem;">
                            <i class="fas fa-lock mr-1"></i> Your details are kept private and never shared.
                        </p>
                        <button name="send" class="btn btn-primary px-5 py-2" style="border-radius:10px;font-weight:700;">
                            <i class="fas fa-paper-plane mr-2"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- /container -->

<script>
const branchKeys = <?= json_encode(array_keys($branches)) ?>;

function selectBranch(index) {
    branchKeys.forEach(function(i) {
        var card = document.getElementById('branch-' + i);
        var map  = document.getElementById('map-' + i);
        if (i === index) {
            card.style.borderLeftColor = 'var(--paw-primary)';
            card.style.boxShadow = '0 12px 30px rgba(175,39,8,.12)';
            map.style.display = 'block';
        } else {
            card.style.borderLeftColor = 'transparent';
            card.style.boxShadow = '';
            map.style.display = 'none';
        }
    });
}

document.querySelectorAll('.branch-item').forEach(function(el) {
    el.addEventListener('mouseenter', function() {
        if (this.style.borderLeftColor !== 'var(--paw-primary)') {
            this.style.borderLeftColor = 'rgba(175,39,8,.3)';
        }
    });
    el.addEventListener('mouseleave', function() {
        if (this.style.borderLeftColor !== 'var(--paw-primary)') {
            this.style.borderLeftColor = 'transparent';
        }
    });
});
</script>

<?php page_footer(); ?>
