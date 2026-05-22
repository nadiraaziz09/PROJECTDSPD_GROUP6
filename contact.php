<?php
include 'layout.php';
if (isset($_POST['send'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
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

<div class="container py-5">
    <div class="row">
        <!-- Contact Info -->
        <div class="col-lg-5 mb-4">
            <div class="dashboard-card">
                <i class="fas fa-map-marker-alt"></i>
                <h4>Dhani Pet Store</h4>
                <p>38, 40, Jalan Setia Tropika 1/1, 81200 Johor Bahru, Johor</p>
                <p><strong>Email:</strong> pawfecthome@example.com</p>
                <p><strong>Phone:</strong> +012 345 6789</p>
                <p class="mb-0"><strong>Opening Hours:</strong> 8.00AM - 9.00PM</p>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="col-lg-7">
            <div class="card-clean p-4">
                <form method="post">
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Name</label>
                            <input name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input name="subject" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="6" required></textarea>
                    </div>
                    <button name="send" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Full-width Map -->
<div class="container-fluid px-0">
    <iframe
        width="100%"
        height="400"
        style="border:0;"
        loading="lazy"
        allowfullscreen
        referrerpolicy="no-referrer-when-downgrade"
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3975.7966922857125!2d103.74999871526878!3d1.6232215981646033!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31da7411c484bbf1%3A0xd873f411294d02c8!2s38%2C%2040%20Jalan%20Setia%20Tropika%201%2F1%2C%2081200%20Johor%20Bahru%2C%20Johor%2C%20Malaysia!5e0!3m2!1sen!2sus!4v1697100000000!5m2!1sen!2sus">
    </iframe>
</div>

<?php page_footer(); ?>