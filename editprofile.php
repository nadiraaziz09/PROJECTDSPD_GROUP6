<!DOCTYPE html>
<html lang="en">

<?php
session_start();
include("db.php");

if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

$email = $_SESSION['email'];

/* UPDATE PROFILE */
if (isset($_POST['update'])) {

    $name = $_POST['name'];
    $age = $_POST['age'];
    $salary = $_POST['salary'];

    $update = "UPDATE account 
               SET Name='$name',
                   Age='$age',
                   Salary='$salary'
               WHERE Email='$email'";

    mysqli_query($conn, $update);

    $_SESSION['name'] = $name;

    header("Location: profile.php");
    exit();
}

/* GET USER */
$sql = "SELECT Name, Email, Account_Type, Age, Salary 
        FROM account 
        WHERE Email='$email'";

$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);
?>

<head>
    <meta charset="utf-8">
    <title>Edit Profile - PawFect Home</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <link href="img/favicon.ico" rel="icon">

    <!-- Fonts + Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet"> 
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">

    <!-- CSS -->
    <link href="css/style.css" rel="stylesheet">

    <style>
        .bg-secondary { background-color: #89a07e !important; }
        .text-secondary { color: #89a07e !important; }
    </style>
</head>

<body>

<!-- ================= TOPBAR (FIXED SPACING SAME AS ORIGINAL) ================= -->
<div class="container-fluid">

    <div class="row bg-secondary py-2 px-lg-5">
        <div class="col-lg-6 text-center text-lg-left mb-2 mb-lg-0">
            <div class="d-inline-flex align-items-center">
                <a class="text-white pr-3" href="#">FAQs</a>
                <span class="text-white">|</span>
                <a class="text-white px-3" href="#">Help</a>
                <span class="text-white">|</span>
                <a class="text-white pl-3" href="#">Support</a>
            </div>
        </div>

        <div class="col-lg-6 text-center text-lg-right">
            <div class="d-inline-flex align-items-center">
                <a class="text-white px-3"><i class="fab fa-facebook-f"></i></a>
                <a class="text-white px-3"><i class="fab fa-twitter"></i></a>
                <a class="text-white px-3"><i class="fab fa-linkedin-in"></i></a>
                <a class="text-white px-3"><i class="fab fa-instagram"></i></a>
                <a class="text-white pl-3"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>

    <!-- ================= HEADER (FIXED SPACING EXACT ORIGINAL) ================= -->
    <div class="row py-3 px-lg-5">

        <div class="col-lg-4">
            <a href="menu.php" class="navbar-brand d-none d-lg-block">
                <h1 class="m-0 display-5 text-capitalize">
                    <span class="text-primary">Paw</span>Fect Home
                </h1>
            </a>
        </div>

        <div class="col-lg-8 text-center text-lg-right">
            <div class="d-inline-flex align-items-center">

                <div class="d-inline-flex flex-column text-center pr-4 border-right">
                    <h6 class="mb-1">Opening Hours</h6>
                    <p class="m-0">8.00AM - 9.00PM</p>
                </div>

                <div class="d-inline-flex flex-column text-center px-4 border-right">
                    <h6 class="mb-1">Email Us</h6>
                    <p class="m-0">info@pawfecthome.com</p>
                </div>

                <div class="d-inline-flex flex-column text-center pl-4">
                    <h6 class="mb-1">Call Us</h6>
                    <p class="m-0">+012 345 6789</p>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- ================= NAVBAR (FIXED MATCH ORIGINAL DESIGN) ================= -->
<div class="container-fluid p-0">
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark py-3 py-lg-0 px-lg-5">

        <a href="menu.php" class="navbar-brand d-block d-lg-none">
            <h1 class="m-0 display-5 text-capitalize text-white">
                <span class="text-primary">Paw</span>Fect Home
            </h1>
        </a>

        <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-between px-3" id="navbarCollapse">

            <div class="navbar-nav mr-auto py-0">
                <a href="menu.php" class="nav-item nav-link">Home</a>
                <a href="about.php" class="nav-item nav-link">About</a>
                <a href="pets.php" class="nav-item nav-link">Pets</a>
                <a href="products.php" class="nav-item nav-link">Products</a>
                <a href="appointment.php" class="nav-item nav-link">Appointment</a>
                <a href="contact.php" class="nav-item nav-link">Contact</a>
            </div>

            <!-- USER DROPDOWN -->
            <div class="nav-item dropdown">
                <a href="#" class="btn btn-lg btn-primary px-3 dropdown-toggle" data-toggle="dropdown">
                    <?php echo htmlspecialchars($user['Name']); ?>
                </a>

                <div class="dropdown-menu dropdown-menu-right rounded-0 m-0">
                    <a href="profile.php" class="dropdown-item"><i class="fas fa-user mr-2"></i> Profile</a>
                    <a href="editprofile.php" class="dropdown-item"><i class="fas fa-edit mr-2"></i> Edit Profile</a>
                    <a href="change-password.php" class="dropdown-item"><i class="fas fa-key mr-2"></i> Change Password</a>
                    <a href="index.php" class="dropdown-item"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                </div>
            </div>

        </div>
    </nav>
</div>

<!-- ================= EDIT PROFILE ================= -->
<div class="container py-5">
    <div class="row py-5">

        <div class="col-lg-7 px-3 px-lg-5">

            <h4 class="text-secondary mb-3">Edit Profile</h4>
            <h1 class="display-4 mb-4">
                <span class="text-primary">Update</span> Profile
            </h1>

            <form method="POST">

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control"
                        value="<?php echo $user['Name']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Email (Read Only)</label>
                    <input type="text" class="form-control"
                        value="<?php echo $user['Email']; ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <input type="text" class="form-control"
                        value="<?php echo ($user['Account_Type'] == 1) ? 'User' : 'Admin'; ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" class="form-control"
                        value="<?php echo $user['Age']; ?>">
                </div>

                <div class="form-group">
                    <label>Salary</label>
                    <input type="number" step="0.01" name="salary" class="form-control"
                        value="<?php echo $user['Salary']; ?>">
                </div>

                <button type="submit" name="update" class="btn btn-primary btn-lg mt-3 px-4">
                    Save Changes
                </button>

                <a href="profile.php" class="btn btn-secondary btn-lg mt-3 px-4">
                    Cancel
                </a>

            </form>

        </div>

        <div class="col-lg-5">
            <div class="row px-3">
                <div class="col-12 p-0">
                    <img class="img-fluid w-100" src="img/about-1.jpg">
                </div>
                <div class="col-6 p-0">
                    <img class="img-fluid w-100" src="img/about-2.jpg">
                </div>
                <div class="col-6 p-0">
                    <img class="img-fluid w-100" src="img/about-3.jpg">
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>

</body>
</html>