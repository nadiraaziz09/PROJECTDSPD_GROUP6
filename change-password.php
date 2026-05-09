<!DOCTYPE html>
<html lang="en">

<?php
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check Email
    $query = "SELECT * FROM account WHERE Email='$email'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {

        // Generate Reset Token
        $token = bin2hex(random_bytes(50));

        // Save Token Into Database
        $update = "
            UPDATE account 
            SET reset_token='$token'
            WHERE Email='$email'
        ";

        mysqli_query($conn, $update);

        // Reset Link
        $reset_link = "http://localhost/Project/reset-password.php?token=$token";

        // Success Message
        $message = "
            <div class='alert alert-success text-center'>

                <h5 class='mb-3'>
                    Reset Link Generated Successfully!
                </h5>

                <p>
                    Click the button below to reset your password.
                </p>

                <a href='$reset_link' class='btn btn-primary mt-2'>
                    Reset Password
                </a>

            </div>
        ";

    } else {

        $message = "
            <div class='alert alert-danger text-center'>
                Email not found.
            </div>
        ";
    }
}
?>

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>Change Password - PawFect Home</title>

    <link href="img/favicon.ico" rel="icon">

    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <link href="css/style.css" rel="stylesheet">

    <style>

        body {
            background-color: #f8f9fa;
            font-family: 'Nunito Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        .auth-wrapper {
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 15px;

            background-image: url('https://images.unsplash.com/photo-1548199973-03cce0bbc87b?q=80&w=2069&auto=format&fit=crop');

            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;

            position: relative;
            z-index: 1;
        }

        .auth-wrapper::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;

            width: 100%;
            height: 100%;

            background-color: rgba(0,0,0,0.5);

            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);

            z-index: -1;
        }

        .forgot-password-container {

            width: 100%;
            max-width: 500px;

            padding: 40px;

            background-color: rgba(255,255,255,0.96);

            border-radius: 20px;

            box-shadow: 0 20px 40px rgba(0,0,0,0.3);

            border-top: 6px solid #af2708;

            z-index: 2;
        }

        .forgot-password-container h2 {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            color: #212529;
            margin-bottom: 15px;
            text-align: center;
        }

        .instruction-text {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-control {
            height: 50px;
            border-radius: 10px;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #af2708;
        }

        .btn-primary {
            background-color: #af2708;
            border-color: #af2708;
            height: 50px;
            font-weight: 700;
            border-radius: 10px;
            transition: 0.3s;
        }

        .btn-primary:hover {
            background-color: #8f1e06;
            border-color: #8f1e06;
        }

        .back-to-home {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #af2708;
            font-weight: 700;
            text-decoration: none;
        }

        .back-to-home:hover {
            text-decoration: underline;
        }

        .text-primary {
            color: #af2708 !important;
        }

        .bg-primary {
            background-color: #af2708 !important;
        }

    </style>
</head>

<body>

    <div class="container-fluid">

        <div class="row bg-secondary py-2 px-lg-5">

            <div class="col-lg-6 text-center text-lg-left mb-2 mb-lg-0">

                <div class="d-inline-flex align-items-center">
                    <a class="text-white pr-3" href="">FAQs</a>

                    <span class="text-white">|</span>

                    <a class="text-white px-3" href="">Help</a>

                    <span class="text-white">|</span>

                    <a class="text-white pl-3" href="">Support</a>
                </div>

            </div>

            <div class="col-lg-6 text-center text-lg-right">

                <div class="d-inline-flex align-items-center">

                    <a class="text-white px-3" href="">
                        <i class="fab fa-facebook-f"></i>
                    </a>

                    <a class="text-white px-3" href="">
                        <i class="fab fa-twitter"></i>
                    </a>

                    <a class="text-white px-3" href="">
                        <i class="fab fa-instagram"></i>
                    </a>

                    <a class="text-white pl-3" href="">
                        <i class="fab fa-youtube"></i>
                    </a>

                </div>

            </div>

        </div>

        <div class="row py-3 px-lg-5 d-none d-lg-flex">

            <div class="col-lg-4">

                <a href="index.php" class="navbar-brand">
                    <h1 class="m-0 display-5 text-capitalize">
                        <span class="text-primary">Paw</span>Fect Home
                    </h1>
                </a>

            </div>

            <div class="col-lg-8 text-right">

                <div class="d-inline-flex align-items-center">

                    <div class="d-inline-flex flex-column text-center pr-3 border-right">
                        <h6>Opening Hours</h6>
                        <p class="m-0">8.00AM - 9.00PM</p>
                    </div>

                    <div class="d-inline-flex flex-column text-center px-3 border-right">
                        <h6>Email Us</h6>
                        <p class="m-0">info@pawfecthome.com</p>
                    </div>

                    <div class="d-inline-flex flex-column text-center pl-3">
                        <h6>Call Us</h6>
                        <p class="m-0">+012 345 6789</p>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <div class="container-fluid p-0">

        <nav class="navbar navbar-expand-lg bg-dark navbar-dark py-3 py-lg-0 px-lg-5">

            <a href="index.php" class="navbar-brand d-block d-lg-none">
                <h1 class="m-0 display-5 text-capitalize text-white">
                    <span class="text-primary">Paw</span>Fect
                </h1>
            </a>

            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-between px-3" id="navbarCollapse">

                <div class="navbar-nav mr-auto py-0">

                    <a href="index.php" class="nav-item nav-link">Home</a>

                    <a href="about.php" class="nav-item nav-link">About</a>

                    <a href="pets.php" class="nav-item nav-link">Pets</a>

                    <a href="products.php" class="nav-item nav-link">Products</a>

                    <a href="contact.php" class="nav-item nav-link">Contact</a>

                </div>

                <div class="nav-item dropdown">

                    <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown">
                        Account
                    </a>

                    <div class="dropdown-menu rounded-0 m-0">

                        <a href="signin.php" class="dropdown-item">Sign In</a>

                        <a href="signup.php" class="dropdown-item">Sign Up</a>

                    </div>

                </div>

            </div>

        </nav>

    </div>

    <div class="auth-wrapper">

        <div class="forgot-password-container">

            <h2>Change Password</h2>

            <p class="instruction-text">
                Enter your email address to generate a password reset link.
            </p>

            <?php echo $message; ?>

            <form action="" method="POST">

                <div class="form-group">

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter Your Email"
                        required
                    >

                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4">
                    Generate Reset Link
                </button>

            </form>

            <a href="menu.php" class="back-to-home">
                <i class="fa fa-arrow-left mr-2"></i>
                Back to Home
            </a>

        </div>

    </div>

    <footer class="bg-dark text-white text-center py-4">
        <p class="m-0">&copy; 2026 PawFect Home. All rights reserved.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.0.6/dist/umd/popper.min.js"></script>

    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>
