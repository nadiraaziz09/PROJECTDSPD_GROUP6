<!DOCTYPE html>
<html lang="en">
    
<?php
session_start();
?>

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>PawFect Home - Available Pets</title>

    <link href="img/favicon.ico" rel="icon">

    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">

    <link href="lib/flaticon/font/flaticon.css" rel="stylesheet">

    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <link href="css/style.css" rel="stylesheet">
    
    <style>
        .category-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: white;
            padding: 0;
            text-align: center;
            height: 100%;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .category-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .category-card h5 {
            padding: 15px 0 10px;
            margin: 0;
            font-weight: bold;
            color: #333;
        }
        .category-card .btn {
            margin-bottom: 20px;
            width: calc(100% - 40px);
            margin-left: auto;
            margin-right: auto;
            border-radius: 25px;
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
                    <a href="<?php echo isset($_SESSION['name']) ? 'menu.php' : 'index.php'; ?>" class="nav-item nav-link">
                        Home
                    </a>
                    <a href="about.php" class="nav-item nav-link">About</a>
                    <a href="pets.php" class="nav-item nav-link active">Pets</a>
                    <a href="products.php" class="nav-item nav-link">Products</a>
                    <a href="appointment.php" class="nav-item nav-link">Appointment</a>
                    <a href="contact.php" class="nav-item nav-link">Contact</a>
                </div>

                <?php if (isset($_SESSION['name']) && !empty($_SESSION['name'])): ?>

                <!-- Logged in -->
                <div class="nav-item dropdown">
                    <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown">
                        <?php echo htmlspecialchars($_SESSION['name']); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right rounded-0 m-0">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Profile
                        </a>
                        <a href="change-password.php" class="dropdown-item">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </a>
                        <a href="index.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>

                <?php else: ?>

                <!-- Not logged in -->
                <div class="nav-item dropdown">
                    <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown">
                        Account
                    </a>
                        <div class="dropdown-menu dropdown-menu-right rounded-0 m-0">
                            <a href="signin.php" class="dropdown-item">
                                <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                            </a>
                            <a href="signup.php" class="dropdown-item">
                                <i class="fas fa-user-plus mr-2"></i> Sign Up
                            </a>
                        </div>
                </div>

                <?php endif; ?>
            </div>
        </nav>
    </div>
    
    <div class="container py-5">
        <h2 class="text-center mb-5">🐾 Explore Our Adoptable Pets 🐾</h2>
        <div class="row">
            <!-- Cat Card with Picture -->
            <div class="col-md-3 mb-4">
                <div class="category-card">
                    <img src="https://images.pexels.com/photos/617278/pexels-photo-617278.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Adorable Cat">
                    <h5>🐱 Cats</h5>
                    <a href="cats.php" class="btn btn-primary">View Cats →</a>
                </div>
            </div>

            <!-- Dog Card with Picture -->
            <div class="col-md-3 mb-4">
                <div class="category-card">
                    <img src="https://images.pexels.com/photos/1805164/pexels-photo-1805164.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Happy Dog">
                    <h5>🐶 Dogs</h5>
                    <a href="dogs.php" class="btn btn-primary">View Dogs →</a>
                </div>
            </div>

            <!-- Bird Card with Picture - FIXED! -->
            <div class="col-md-3 mb-4">
                <div class="category-card">
                    <img src="https://images.pexels.com/photos/2575321/pexels-photo-2575321.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Beautiful Bird">
                    <h5>🐦 Birds</h5>
                    <a href="birds.php" class="btn btn-primary">View Birds →</a>
                </div>
            </div>

            <!-- Rabbit Card with Picture -->
            <div class="col-md-3 mb-4">
                <div class="category-card">
                    <img src="https://images.pexels.com/photos/326012/pexels-photo-326012.jpeg?auto=compress&cs=tinysrgb&w=600" alt="Cute Rabbit">
                    <h5>🐰 Rabbits</h5>
                    <a href="rabbits.php" class="btn btn-primary">View Rabbits →</a>
                </div>
            </div>
        </div>
        
        <!-- Additional cute adoption message -->
        <div class="row mt-5">
            <div class="col-12 text-center">
                <div class="alert alert-success">
                    <h4><i class="fas fa-heart"></i> Find Your Furry Friend Today! <i class="fas fa-heart"></i></h4>
                    <p>Every pet deserves a loving home. Visit us to meet these wonderful animals!</p>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-dark text-white text-center py-3">
        <p>&copy; 2026 PawFect Home. All rights reserved. | Made with <i class="fas fa-heart text-danger"></i> for pets</p>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.0.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>