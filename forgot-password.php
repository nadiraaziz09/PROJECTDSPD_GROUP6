<!DOCTYPE html>
<html lang="en">

<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];

    $query = "SELECT * FROM account WHERE Email='$email'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {

        $token = bin2hex(random_bytes(50));

        mysqli_query($conn, "
            UPDATE account 
            SET reset_token='$token' 
            WHERE Email='$email'
        ");

        echo "Reset link: http://localhost/reset-password.php?token=$token";

    } else {
        echo "Email not found";
    }
}
?>

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Forgot Password - PawFect Home</title>
    
    <link href="img/favicon.ico" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Nunito Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Full-screen wrapper for background image - SAME as Sign In page */
        .auth-wrapper {
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 15px;
            
            /* --- Pet Shop Background Image - SAME as Sign In & Sign Up --- */
            /* Using the same high-quality, professional pet shop interior image */
            background-image: url('https://images.unsplash.com/photo-1548199973-03cce0bbc87b?q=80&w=2069&auto=format&fit=crop');
            
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            z-index: 1;
        }

        /* Overlay to add blur and darken the pet shop photo for readability - SAME as Sign In */
        .auth-wrapper::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: -1;
        }

        /* Forgot Password Container Styling - SAME as Sign In container */
        .forgot-password-container {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            background-color: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-top: 6px solid #af2708;
            z-index: 2;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .forgot-password-container h2 {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            color: #212529;
            margin-bottom: 15px;
            text-align: center;
        }

        .forgot-password-container p.instruction-text {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* Input Styling - SAME as Sign In */
        .form-control {
            height: 50px;
            border-radius: 10px;
            border: 1px solid #ced4da;
            background-color: rgba(255, 255, 255, 0.95);
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(175, 39, 8, 0.3);
        }

        .back-to-login {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #af2708;
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s;
        }

        .back-to-login:hover {
            text-decoration: underline;
            color: #8f1e06;
        }

        /* Label styling - SAME as Sign In */
        label {
            font-weight: 600;
            color: #333;
        }

        /* Footer space-maker */
        footer {
            margin-top: 0;
        }

        /* Additional global primary color overrides */
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
                    <a class="text-white px-3" href=""><i class="fab fa-facebook-f"></i></a>
                    <a class="text-white px-3" href=""><i class="fab fa-twitter"></i></a>
                    <a class="text-white px-3" href=""><i class="fab fa-instagram"></i></a>
                    <a class="text-white pl-3" href=""><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
        <div class="row py-3 px-lg-5 d-none d-lg-flex">
            <div class="col-lg-4">
                <a href="index.php" class="navbar-brand">
                    <h1 class="m-0 display-5 text-capitalize"><span class="text-primary">Paw</span>Fect Home</h1>
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
                <h1 class="m-0 display-5 text-capitalize text-white"><span class="text-primary">Paw</span>Fect</h1>
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
                    <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown">Account</a>
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
            <h2>Reset Password</h2>
            <p class="instruction-text">Enter your email address and we'll send you a link to reset your password.</p>
            
            <form action="forgot-password.php" method="POST">
                <div class="form-group">
                    <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                </div>

                <button type="submit" name="submit" class="btn btn-primary btn-block mt-4">
                    Send Reset Link
                </button>
            </form>

            <a href="signin.php" class="back-to-login">
                <i class="fa fa-arrow-left mr-2"></i> Back to Sign In
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