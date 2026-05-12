<?php
include 'layout.php';
if (isset($_POST['send'])) {
    $name=trim($_POST['name']); $email=trim($_POST['email']); $subject=trim($_POST['subject']); $message=trim($_POST['message']);
    $stmt=mysqli_prepare($conn,"INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)"); mysqli_stmt_bind_param($stmt,'ssss',$name,$email,$subject,$message); mysqli_stmt_execute($stmt);
    flash('success','Message sent successfully. Our team will review it soon.'); header('Location: contact.php'); exit();
}
page_header('Contact - PawFect Home', 'contact'); page_title('Contact Us', 'Need help with adoption, appointments or pet needs orders? Send us a message.');
?>
<div class="container py-5"><div class="row"><div class="col-lg-5 mb-4"><div class="dashboard-card"><i class="fas fa-map-marker-alt"></i><h4>Johor Pet Service</h4><p>Adoption Centre, Johor Bahru, Malaysia</p><p><strong>Email:</strong> pawfecthome@example.com</p><p><strong>Phone:</strong> +012 345 6789</p><p class="mb-0"><strong>Opening Hours:</strong> 8.00AM - 9.00PM</p></div></div><div class="col-lg-7"><div class="card-clean p-4"><form method="post"><div class="form-row"><div class="col-md-6 form-group"><label>Name</label><input name="name" class="form-control" required></div><div class="col-md-6 form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div></div><div class="form-group"><label>Subject</label><input name="subject" class="form-control" required></div><div class="form-group"><label>Message</label><textarea name="message" class="form-control" rows="6" required></textarea></div><button name="send" class="btn btn-primary">Send Message</button></form></div></div></div></div>
<?php page_footer(); ?>
