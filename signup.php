<!DOCTYPE html>
<html lang="en">

<?php
include 'db.php';

if (isset($_POST['register'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check email
    $check = "SELECT * FROM account WHERE Email='$email'";
    $result = mysqli_query($conn, $check);

    if (mysqli_num_rows($result) > 0) {
        $error = "❌ Email already registered!";
    } else {

        // 1 = customer
        $query = "INSERT INTO account (Name, Email, Password, Account_Type)
                  VALUES ('$name', '$email', '$password', 1)";

        if (mysqli_query($conn, $query)) {
            $success = "✅ Account created! Please login.";
        } else {
            $error = "❌ Error creating account!";
        }
    }
}
?>

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Sign Up - PawFect Home</title>
    
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
            
            /* --- Pet Shop Background Image - SAME as Sign In --- */
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

        /* Sign Up Container Styling - SAME as Sign In container */
        .sign-up-container {
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

        .sign-up-container h2 {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            color: #212529;
            margin-bottom: 30px;
            text-align: center;
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

        /* Social Login and Divider Styling - SAME as Sign In */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #777;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 30px 0;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #ccc;
        }

        .divider:not(:empty)::before { margin-right: 15px; }
        .divider:not(:empty)::after { margin-left: 15px; }

        .social-login-btns {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 25px;
        }

        .social-login-btn {
            flex: 1;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none !important;
            border: 1px solid #e0e0e0;
            background-color: rgba(255, 255, 255, 0.95);
            color: #555;
        }

        .social-login-btn img {
            width: 18px;
            height: 18px;
            margin-right: 10px;
        }

        .social-login-btn:hover {
            background-color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            color: #212529;
            border-color: #af2708;
        }

        /* Disclaimer Section - SAME as Sign In */
        .terms-text {
            text-align: center;
            margin-top: 20px;
            line-height: 1.6;
            font-size: 13px;
            color: #555;
        }

        .terms-text a {
            color: #af2708;
            font-weight: 600;
            text-decoration: none;
        }

        .terms-text a:hover {
            text-decoration: underline;
        }

        .sign-in-link {
            color: #af2708;
            font-weight: 700;
            text-decoration: none;
        }

        .sign-in-link:hover {
            text-decoration: underline;
        }

        /* Additional elements with color updates - SAME as Sign In */
        .text-primary {
            color: #af2708 !important;
        }

        .bg-primary {
            background-color: #af2708 !important;
        }

        .btn-outline-primary {
            color: #af2708;
            border-color: #af2708;
        }

        .btn-outline-primary:hover {
            background-color: #af2708;
            border-color: #af2708;
        }

        a.text-primary:hover, a.text-primary:focus {
            color: #8f1e06 !important;
        }

        .custom-control-input:checked ~ .custom-control-label::before {
            border-color: #af2708;
            background-color: #af2708;
        }

        .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
            background-color: #af2708;
            border-color: #af2708;
        }

        /* GREEN COLOR CHANGED TO #89a07e */
        .bg-secondary {
            background-color: #89a07e !important;
        }
        
        .text-secondary {
            color: #89a07e !important;
        }
        
        .btn-secondary {
            background-color: #89a07e !important;
            border-color: #89a07e !important;
        }
        
        .btn-secondary:hover {
            background-color: #6d8264 !important;
            border-color: #6d8264 !important;
        }
        
        .border-secondary {
            border-color: #89a07e !important;
        }
        
        a.text-secondary:hover, a.text-secondary:focus {
            color: #6d8264 !important;
        }

        /* Label styling - SAME as Sign In */
        label {
            font-weight: 600;
            color: #333;
        }

        /* Small text adjustments - SAME as Sign In */
        .small.text-muted {
            color: #6c757d !important;
            transition: color 0.2s;
        }

        .small.text-muted:hover {
            color: #af2708 !important;
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
                    <a class="text-white px-3" href=""><i class="fab fa-linkedin-in"></i></a>
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
                        <p class="m-0">pawfecthome@example.com</p>
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
                        <a href="signup.php" class="dropdown-item active">Sign Up</a>
                    </div>
                </div>
            </div>
        </nav>
    </div>

    <div class="auth-wrapper">
        <div class="sign-up-container">
            <h2>Create Account</h2>
            
            <form method="POST">

    <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control" required>
    </div>

    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <button type="submit" name="register" class="btn btn-primary btn-block">
        Sign Up
    </button>

</form>

            <div class="divider">OR</div>

            <div class="social-login-btns">
                <a href="#" class="social-login-btn">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google">
                    Google
                </a>
                <a href="#" class="social-login-btn">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/b9/2023_Facebook_icon.svg" alt="Facebook">
                    Facebook
                </a>
            </div>

            <div class="terms-text">
                <p>By logging in, you agree to PawFect Home's <a href="#">Terms of Service</a> & <a href="#">Privacy Policy</a></p>
            </div>

            <div class="text-center mt-4 border-top pt-3">
                <p class="mb-0">Already have an account? <a href="signin.php" class="sign-in-link">Sign In</a></p>
            </div>
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