<!DOCTYPE html>
<html lang="en">

<?php
session_start();

// if not logged in → force go back login
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}
?>

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="PawFect Home" name="keywords">
    <meta content="PawFect Home - Pet Adoption System" name="description">
    <title>PawFect Home - Pet Adoption System</title>

    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">

    <!-- Flaticon Font -->
    <link href="lib/flaticon/font/flaticon.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/style.css" rel="stylesheet">
    
    <style>
        /* Change the orange color to #af2708 */
        .text-primary {
            color: #af2708 !important;
        }

        .btn-primary {
            background-color: #af2708 !important;
            border-color: #af2708 !important;
        }

        .navbar-dark .navbar-nav .nav-link.active {
            color: #ffffff !important;
        }

        .carousel-caption h1,
        .carousel-caption h5,
        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            color: #af2708;
        }

        .category-card .btn-primary {
            background-color: #af2708 !important;
            border-color: #af2708 !important;
        }

        /* Change green color to #89a07e */
        .bg-secondary {
            background-color: #89a07e !important;
        }

        .text-secondary {
            color: #89a07e !important;
        }

        .btn-outline-secondary {
            color: #89a07e !important;
            border-color: #89a07e !important;
        }

        .btn-outline-secondary:hover {
            background-color: #89a07e !important;
            border-color: #89a07e !important;
            color: #fff !important;
        }

        .border-secondary {
            border-color: #89a07e !important;
        }

        a.text-secondary:hover, a.text-secondary:focus {
            color: #6d8264 !important;
        }

        /* Category card styling */
        .category-card {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 15px;
            padding: 20px;
            transition: transform 0.3s ease;
            background: #fff;
            margin-bottom: 30px;
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .category-card img {
            border-radius: 10px;
            height: 200px;
            width: 100%;
            object-fit: cover;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <!-- Topbar Start -->
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
        <div class="row py-3 px-lg-5">
            <div class="col-lg-4">
                <a href="<?php echo isset($_SESSION['name']) ? 'menu.php' : 'index.php'; ?>" class="navbar-brand d-none d-lg-block">
                    <h1 class="m-0 display-5 text-capitalize"><span class="text-primary">Paw</span>Fect Home</h1>
                </a>
            </div>
            <div class="col-lg-8 text-center text-lg-right">
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
    <!-- Topbar End -->

    <!-- Navbar Start -->
    <div class="container-fluid p-0">
        <nav class="navbar navbar-expand-lg bg-dark navbar-dark py-3 py-lg-0 px-lg-5">
            <a href="<?php echo isset($_SESSION['name']) ? 'menu.php' : 'index.php'; ?>" class="navbar-brand d-block d-lg-none">
                <h1 class="m-0 display-5 text-capitalize font-italic text-white"><span class="text-primary">Paw</span>Fect Home</h1>
            </a>
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-between px-3" id="navbarCollapse">
                <div class="navbar-nav mr-auto py-0">
                    <a href="<?php echo isset($_SESSION['name']) ? 'menu.php' : 'index.php'; ?>" class="nav-item nav-link active">Home</a>
                    <a href="about.php" class="nav-item nav-link">About</a>
                    <a href="pets.php" class="nav-item nav-link">Pets</a>
                    <a href="products.php" class="nav-item nav-link">Products</a>
                    <a href="appointment.php" class="nav-item nav-link">Appointment</a>
                    <a href="contact.php" class="nav-item nav-link">Contact</a>
                </div>

                <!-- Account Dropdown (Shows when clicked) -->
                <div class="nav-item dropdown">
                    <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown">
                        <?php echo $_SESSION['name']; ?>
                    </a>
                    <div class="dropdown-menu rounded-0 m-0">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Profile
                        </a>
                        <a href="index.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                        <a href="change-password.php" class="dropdown-item">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </div>
    <!-- Navbar End -->

    <!-- Hero Section -->
    <div class="container-fluid p-0">
        <div id="header-carousel" class="carousel slide" data-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img class="w-100" src="img/carousel-1.jpg" alt="Image">
                    <div class="carousel-caption d-flex flex-column align-items-center justify-content-center">
                        <div class="p-3" style="max-width: 900px;">
                            <h1 class="display-3 text-white mb-3">Adopt Your New Best Friend</h1>
                            <h5 class="text-white mb-3 d-none d-sm-block">Find your furry companion today!</h5>
                            <a href="pets.php" class="btn btn-lg btn-primary mt-3 mt-md-4 px-4">View Available Pets</a>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <img class="w-100" src="img/carousel-2.jpg" alt="Image">
                    <div class="carousel-caption d-flex flex-column align-items-center justify-content-center">
                        <div class="p-3" style="max-width: 900px;">
                            <h1 class="display-3 text-white mb-3">Provide a Loving Home</h1>
                            <h5 class="text-white mb-3 d-none d-sm-block">Adopt a pet and make a difference in their life.</h5>
                            <a href="pets.php" class="btn btn-lg btn-primary mt-3 mt-md-4 px-4">View Available Pets</a>
                        </div>
                    </div>
                </div>
            </div>
            <a class="carousel-control-prev" href="#header-carousel" data-slide="prev">
                <div class="btn btn-primary rounded" style="width: 45px; height: 45px;">
                    <span class="carousel-control-prev-icon mb-n2"></span>
                </div>
            </a>
            <a class="carousel-control-next" href="#header-carousel" data-slide="next">
                <div class="btn btn-primary rounded" style="width: 45px; height: 45px;">
                    <span class="carousel-control-next-icon mb-n2"></span>
                </div>
            </a>
        </div>
    </div>
    <!-- Hero Section End -->

    <!-- Pet Categories Section -->
    <div class="container py-5">
        <h2 class="text-center mb-5">Choose Your New Best Friend</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="category-card">
                    <img class="card-img-top" src="https://images.pexels.com/photos/1805164/pexels-photo-1805164.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Dog">
                    <h5>Dogs</h5>
                    <a href="pets.php" class="btn btn-primary">View Dogs</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="category-card">
                    <img class="card-img-top" src="https://images.pexels.com/photos/7517786/pexels-photo-7517786.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Cat">
                    <h5>Cats</h5>
                    <a href="pets.php" class="btn btn-primary">View Cats</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="category-card">
                    <img class="card-img-top" src="https://images.pexels.com/photos/4587959/pexels-photo-4587959.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Other Pets">
                    <h5>Other Pets</h5>
                    <a href="pets.php" class="btn btn-primary">View Other Pets</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Pet Categories Section End -->

    <!-- Footer Start -->
    <footer class="bg-dark text-white text-center py-3">
        <p>&copy; 2026 PawFect Home. All rights reserved.</p>
    </footer>
    <!-- Footer End -->

    <!-- Add Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.0.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>