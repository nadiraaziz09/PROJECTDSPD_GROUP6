<?php
// Presentation mode: keep PHP notices/warnings out of the website UI.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);

// PawFect Home database connection
// XAMPP default: username root, empty password. Change only if your MySQL login is different.
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "project2026";

$conn = mysqli_connect($servername, $username, $password, $dbname);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

function table_exists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

function column_exists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function ensure_column($conn, $table, $column, $definition) {
    if (!column_exists($conn, $table, $column)) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function sync_product_stock_quantity($conn) {
    if (!table_exists($conn, 'products')) return;
    ensure_column($conn, 'products', 'quantity', "INT NOT NULL DEFAULT 0 AFTER `stock`");
    // Older project pages use stock, while the updated requirement uses quantity.
    // Keep both columns in sync so existing pages continue to work safely.
    mysqli_query($conn, "UPDATE products SET quantity = stock WHERE (quantity IS NULL OR quantity = 0) AND stock > 0");
    mysqli_query($conn, "UPDATE products SET stock = quantity WHERE (stock IS NULL OR stock = 0) AND quantity > 0");
}

function set_product_stock($conn, $productId, $qty) {
    sync_product_stock_quantity($conn);
    $productId = (int)$productId;
    $qty = max(0, (int)$qty);
    if ($productId > 0) {
        mysqli_query($conn, "UPDATE products SET stock=$qty, quantity=$qty WHERE id=$productId");
    }
}

function decrease_product_stock($conn, $productId, $qty) {
    sync_product_stock_quantity($conn);
    $productId = (int)$productId;
    $qty = max(1, (int)$qty);
    if ($productId > 0) {
        mysqli_query($conn, "UPDATE products SET stock=GREATEST(stock-$qty,0), quantity=GREATEST(quantity-$qty,0) WHERE id=$productId");
    }
}

function mark_product_payment_completed($conn, $paymentId, $completed = true) {
    if (!table_exists($conn, 'product_payments')) return;
    ensure_column($conn, 'product_payments', 'payment_completed', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    $paymentId = (int)$paymentId;
    $value = $completed ? 1 : 0;
    if ($paymentId > 0) {
        mysqli_query($conn, "UPDATE product_payments SET payment_completed=$value WHERE id=$paymentId");
    }
}

function ensure_pawfect_schema($conn) {
    // Keep the original account table and extend it for profile management.
    if (table_exists($conn, 'account')) {
        ensure_column($conn, 'account', 'Phone', "varchar(30) DEFAULT NULL");
        ensure_column($conn, 'account', 'Address', "varchar(255) DEFAULT NULL");
        ensure_column($conn, 'account', 'Profile_Photo', "varchar(255) DEFAULT NULL");
        ensure_column($conn, 'account', 'Status', "varchar(20) NOT NULL DEFAULT 'active'");
        ensure_column($conn, 'account', 'reset_token_expiry', "DATETIME DEFAULT NULL");
    }

    if (table_exists($conn, 'appointments')) {
        ensure_column($conn, 'appointments', 'appointment_type', "varchar(40) NOT NULL DEFAULT 'general' AFTER `pet_id`");
    }

    if (table_exists($conn, 'products')) {
        sync_product_stock_quantity($conn);
    }

    if (table_exists($conn, 'orders')) {
        ensure_column($conn, 'orders', 'payment_completed', "TINYINT(1) NOT NULL DEFAULT 0");
    }

    if (table_exists($conn, 'product_payments')) {
        ensure_column($conn, 'product_payments', 'payment_completed', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
        mysqli_query($conn, "UPDATE product_payments SET payment_completed=1 WHERE LOWER(status)='completed'");
        mysqli_query($conn, "UPDATE product_payments SET payment_completed=0 WHERE LOWER(status)<>'completed'");
        ensure_column($conn, 'product_payments', 'bank_name', "varchar(80) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'bank_reference', "varchar(80) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'payer_name', "varchar(120) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'gateway_provider', "varchar(80) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'gateway_bill_code', "varchar(80) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'cart_items', "TEXT DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'receipt_file', "varchar(255) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'paid_amount', "DECIMAL(10,2) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'refund_status', "varchar(40) NOT NULL DEFAULT 'not required'");
        ensure_column($conn, 'product_payments', 'refund_amount', "DECIMAL(10,2) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'refund_qr_file', "varchar(255) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'refund_receipt_file', "varchar(255) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'refund_user_note', "TEXT DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'refund_issue_reported_at', "DATETIME DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'refund_updated_at', "DATETIME DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'underpay_status', "varchar(40) NOT NULL DEFAULT 'not required'");
        ensure_column($conn, 'product_payments', 'underpay_amount', "DECIMAL(10,2) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'underpay_message', "TEXT DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'topup_receipt_file', "varchar(255) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'topup_paid_amount', "DECIMAL(10,2) DEFAULT NULL");
        ensure_column($conn, 'product_payments', 'underpay_updated_at', "DATETIME DEFAULT NULL");
    }

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        breed VARCHAR(100) NOT NULL,
        age DECIMAL(4,1) NOT NULL DEFAULT 0,
        gender VARCHAR(20) NOT NULL,
        health_status VARCHAR(120) NOT NULL,
        description TEXT NOT NULL,
        photo VARCHAR(255) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pet_health_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pet_id INT NOT NULL,
        vaccination TEXT NOT NULL,
        medical_history TEXT NOT NULL,
        health_status VARCHAR(120) NOT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS wishlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pet_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_wishlist (user_id, pet_id),
        FOREIGN KEY (user_id) REFERENCES account(ID) ON DELETE CASCADE,
        FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS adoption_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pet_id INT NOT NULL,
        applicant_name VARCHAR(100) NOT NULL,
        contact VARCHAR(30) NOT NULL,
        reason TEXT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        staff_note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES account(ID) ON DELETE CASCADE,
        FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pet_id INT NULL,
        appointment_type VARCHAR(40) NOT NULL DEFAULT 'general',
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'booked',
        note TEXT NULL,
        staff_note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES account(ID) ON DELETE CASCADE,
        FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category VARCHAR(80) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        quantity INT NOT NULL DEFAULT 0,
        photo VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Product payments are for pet needs/products only. Pets do not have prices.
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        bank_name VARCHAR(80) DEFAULT NULL,
        bank_reference VARCHAR(80) DEFAULT NULL,
        payer_name VARCHAR(120) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending verification',
        payment_completed TINYINT(1) NOT NULL DEFAULT 0,
        transaction_id VARCHAR(80) NOT NULL,
        gateway_provider VARCHAR(80) DEFAULT NULL,
        gateway_bill_code VARCHAR(80) DEFAULT NULL,
        cart_items TEXT DEFAULT NULL,
        receipt_file VARCHAR(255) DEFAULT NULL,
        paid_amount DECIMAL(10,2) DEFAULT NULL,
        refund_status VARCHAR(40) NOT NULL DEFAULT 'not required',
        refund_amount DECIMAL(10,2) DEFAULT NULL,
        refund_qr_file VARCHAR(255) DEFAULT NULL,
        refund_receipt_file VARCHAR(255) DEFAULT NULL,
        refund_user_note TEXT DEFAULT NULL,
        refund_issue_reported_at DATETIME DEFAULT NULL,
        refund_updated_at DATETIME DEFAULT NULL,
        underpay_status VARCHAR(40) NOT NULL DEFAULT 'not required',
        underpay_amount DECIMAL(10,2) DEFAULT NULL,
        underpay_message TEXT DEFAULT NULL,
        topup_receipt_file VARCHAR(255) DEFAULT NULL,
        topup_paid_amount DECIMAL(10,2) DEFAULT NULL,
        underpay_updated_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES account(ID) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        content TEXT NOT NULL,
        expiry_date DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(120) NOT NULL,
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM pets"));
    if ((int)$count['total'] === 0) {
        $pets = [
            ['Toby','Dog','Nova Scotia Duck Tolling Retriever Mix',1.5,'Male','Vaccinated and healthy','Friendly young male dog from the provided photo. He is playful, gentle and ready for a loving home.','img/about-1.jpg','available'],
            ['Milo','Dog','Golden Retriever',2,'Male','Vaccinated and healthy','Friendly, playful and suitable for families with children.','img/carousel-2.jpg','available'],
            ['Buddy','Dog','Beagle Mix',3,'Male','Neutered and vaccinated','Energetic dog that enjoys outdoor walks and human attention.','img/price-2.jpg','available'],
            ['Sky','Dog','Border Collie',2.5,'Female','Vaccinated','Intelligent and active dog suitable for owners who enjoy training and exercise.','img/about-2.jpg','available'],
            ['Snowy','Dog','Mixed Breed',0.8,'Male','Healthy','Small cheerful puppy that loves people and outdoor playtime.','img/blog-2.jpg','available'],
            ['Luna','Cat','Domestic Shorthair',1,'Female','Vaccinated','Calm indoor cat that enjoys quiet spaces and gentle care.','img/carousel-1.jpg','available'],
            ['Bella','Cat','Persian Mix',2,'Female','Vaccinated','Beautiful long-haired cat that needs regular grooming.','img/price-3.jpg','available'],
            ['Simba','Cat','Bengal Mix',1.5,'Male','Healthy','Curious and active cat with bright markings and confident personality.','img/price-1.jpg','available'],
            ['Mimi','Cat','Tabby Kitten',0.6,'Female','Dewormed','Sweet kitten that is suitable for indoor living and gentle handling.','img/about-3.jpg','available'],
            ['Sameon','Cat','Orange Domestic Shorthair',2.0,'Male','Healthy','Gentle orange cat from the provided photo. Sameon is calm, curious and suitable for indoor living.','img/sameon-cat.png','available'],
            ['Coco','Rabbit','Mini Lop',1.0,'Female','Healthy','Soft and gentle rabbit that loves leafy vegetables and quiet handling.','https://images.pexels.com/photos/326012/pexels-photo-326012.jpeg?auto=compress&cs=tinysrgb&w=800','available'],
            ['Rio','Bird','Parakeet',1.0,'Male','Healthy','Bright and active bird suitable for experienced pet owners.','https://images.pexels.com/photos/2575321/pexels-photo-2575321.jpeg?auto=compress&cs=tinysrgb&w=800','available']
        ];
        $stmt = mysqli_prepare($conn, "INSERT INTO pets (name,type,breed,age,gender,health_status,description,photo,status) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($pets as $p) {
            mysqli_stmt_bind_param($stmt, 'sssdsssss', $p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7],$p[8]);
            mysqli_stmt_execute($stmt);
        }
    }

    // Make sure important sample pets remain available even if the database already had older data.
    $mustPets = [
        ['Sameon','Cat','Orange Domestic Shorthair',2.0,'Male','Healthy','Gentle orange cat from the provided photo. Sameon is calm, curious and suitable for indoor living.','img/sameon-cat.png','available'],
        ['Coco','Rabbit','Mini Lop',1.0,'Female','Healthy','Soft and gentle rabbit that loves leafy vegetables and quiet handling.','https://images.pexels.com/photos/326012/pexels-photo-326012.jpeg?auto=compress&cs=tinysrgb&w=800','available'],
        ['Rio','Bird','Parakeet',1.0,'Male','Healthy','Bright and active bird suitable for experienced pet owners.','https://images.pexels.com/photos/2575321/pexels-photo-2575321.jpeg?auto=compress&cs=tinysrgb&w=800','available']
    ];
    foreach ($mustPets as $mp) {
        $safeName = mysqli_real_escape_string($conn, $mp[0]);
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM pets WHERE name='$safeName'"));
        if ((int)$exists['total'] === 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO pets (name,type,breed,age,gender,health_status,description,photo,status) VALUES (?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sssdsssss', $mp[0],$mp[1],$mp[2],$mp[3],$mp[4],$mp[5],$mp[6],$mp[7],$mp[8]);
            mysqli_stmt_execute($stmt);
        }
    }

    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products"));
    if ((int)$count['total'] === 0) {
        $products = [
            ['Premium Dog Food Pack','Food','Dry food pack for dogs. Suitable as a starter food pack for newly adopted dogs.',45.00,30,'img/premium-dog-food-pack.jpg'],
            ['Cat Food & Treats Set','Food','Basic cat food and treat set for daily feeding support.',39.00,25,'img/cat-food.jpg'],
            ['Pet Grooming Kit','Care','Brush, nail clipper and basic grooming tools for simple home care.',38.00,20,'img/pet-grooming-kit.jpg'],
            ['Comfort Pet Bed','Accessories','Soft, washable bed for cats and small dogs, offering a cozy resting spot.',75.00,15,'img/comfort-pet-bed.jpg'],
            ['Toy Bundle','Toys','Safe toys designed to keep pets active, entertained, and happy throughout the day.',25.00,50,'img/toy-bundle.jpg'],
            ['Feeding Bowl Set','Accessories','Simple feeding bowl set for food and water, ideal for daily use and easy cleaning.',28.00,40,'img/feeding-bowl-set.jpg']
        ];
        $stmt = mysqli_prepare($conn, "INSERT INTO products (name,category,description,price,stock,quantity,photo) VALUES (?,?,?,?,?,?,?)");
        foreach ($products as $p) {
            mysqli_stmt_bind_param($stmt, 'sssdiis', $p[0],$p[1],$p[2],$p[3],$p[4],$p[4],$p[5]);
            mysqli_stmt_execute($stmt);
        }
    }

    // Keep only these three requested product descriptions updated on existing databases.
    mysqli_query($conn, "UPDATE products SET description='Soft, washable bed for cats and small dogs, offering a cozy resting spot.' WHERE LOWER(name) IN ('comfort pet bed','pet bed')");
    mysqli_query($conn, "UPDATE products SET description='Simple feeding bowl set for food and water, ideal for daily use and easy cleaning.' WHERE LOWER(name) IN ('feeding bowl set','bowl set')");
    mysqli_query($conn, "UPDATE products SET description='Safe toys designed to keep pets active, entertained, and happy throughout the day.' WHERE LOWER(name)='toy bundle'");
    sync_product_stock_quantity($conn);

    $defaultProductPhotos = [
        'Premium Dog Food Pack' => ['img/premium-dog-food-pack.jpg', 'img/blog-3.jpg'],
        'Cat Food & Treats Set' => ['img/cat-food.jpg', 'img/about-3.jpg'],
        'Pet Grooming Kit' => ['img/pet-grooming-kit.jpg', 'img/blog-1.jpg'],
        'Comfort Pet Bed' => ['img/comfort-pet-bed.jpg', 'img/about-1.jpg'],
        'Toy Bundle' => ['img/toy-bundle.jpg', 'img/blog-2.jpg'],
        'Feeding Bowl Set' => ['img/feeding-bowl-set.jpg', 'img/feature.jpg']
    ];
    foreach ($defaultProductPhotos as $productName => $paths) {
        $stmt = mysqli_prepare($conn, "UPDATE products SET photo=? WHERE name=? AND photo=?");
        mysqli_stmt_bind_param($stmt, 'sss', $paths[0], $productName, $paths[1]);
        mysqli_stmt_execute($stmt);
    }

    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM announcements"));
    if ((int)$count['total'] === 0) {
        mysqli_query($conn, "INSERT INTO announcements (title, content, expiry_date, status) VALUES
            ('Weekend Adoption Drive', 'Visit our shelter this weekend and meet newly rescued pets ready for adoption.', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active'),
            ('Pet Needs Corner', 'Customers can now buy food, toys and basic care items through the Pet Needs page.', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 'active')");
    }
}

ensure_pawfect_schema($conn);
?>
