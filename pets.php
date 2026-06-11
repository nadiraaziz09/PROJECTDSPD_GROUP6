<?php
include 'layout.php';
$type = trim($_GET['type'] ?? '');
$gender = trim($_GET['gender'] ?? '');
$q = trim($_GET['q'] ?? '');
$age = trim($_GET['age'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 6;
$where = ["status='available'"];
$params = [];
$types = '';
if ($type !== '') { $where[] = 'type=?'; $params[] = $type; $types .= 's'; }
if ($gender !== '') { $where[] = 'gender=?'; $params[] = $gender; $types .= 's'; }
if ($q !== '') { $where[] = '(name LIKE ? OR breed LIKE ?)'; $like = "%$q%"; $params[] = $like; $params[] = $like; $types .= 'ss'; }
if ($age === '0-1') $where[] = 'age BETWEEN 0 AND 1';
if ($age === '1-3') $where[] = 'age > 1 AND age <= 3';
if ($age === '3+') $where[] = 'age > 3';
$whereSql = implode(' AND ', $where);

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM pets WHERE $whereSql");
if ($params) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalPets = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalPets / $itemsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $itemsPerPage;

$sql = "SELECT * FROM pets WHERE $whereSql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
$queryParams = $params;
$queryParams[] = $itemsPerPage;
$queryParams[] = $offset;
$queryTypes = $types . 'ii';
mysqli_stmt_bind_param($stmt, $queryTypes, ...$queryParams);
mysqli_stmt_execute($stmt);
$pets = mysqli_stmt_get_result($stmt);

$paginationFilters = [];
if ($q !== '') $paginationFilters['q'] = $q;
if ($type !== '') $paginationFilters['type'] = $type;
if ($gender !== '') $paginationFilters['gender'] = $gender;
if ($age !== '') $paginationFilters['age'] = $age;
page_header('Available Pets - PawFect Home', 'pets'); page_title('Available Pets', 'Search, filter, view details and save pets to your wishlist.');
?>
<div class="container py-4">
    <form class="action-bar mb-4" method="get">
        <div class="form-row align-items-end">
            <div class="col-md-3 mb-2"><label>Search</label><input type="text" name="q" class="form-control" placeholder="Name or breed" value="<?php echo h($q); ?>"></div>
            <div class="col-md-2 mb-2"><label>Type</label><select name="type" class="custom-select"><option value="">All</option><?php foreach(['Dog','Cat','Rabbit','Bird','Other'] as $x): ?><option <?php echo $type===$x?'selected':''; ?>><?php echo $x; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 mb-2"><label>Gender</label><select name="gender" class="custom-select"><option value="">All</option><?php foreach(['Male','Female'] as $x): ?><option <?php echo $gender===$x?'selected':''; ?>><?php echo $x; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 mb-2"><label>Age</label><select name="age" class="custom-select"><option value="">All</option><?php foreach(['0-1','1-3','3+'] as $x): ?><option value="<?php echo $x; ?>" <?php echo $age===$x?'selected':''; ?>><?php echo $x; ?> years</option><?php endforeach; ?></select></div>
            <div class="col-md-3 mb-2"><button class="btn btn-primary btn-block">Filter Pets</button></div>
        </div>
    </form>
    <div class="row">
        <?php if (mysqli_num_rows($pets) === 0): ?><div class="col-12"><div class="alert alert-warning">No matching pets found.</div></div><?php endif; ?>
        <?php while ($pet = mysqli_fetch_assoc($pets)): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card-clean pet-card hover-lift h-100">
                <img src="<?php echo h(pawfect_image_src($pet['photo'], 'img/about-1.jpg')); ?>" alt="<?php echo h($pet['name']); ?>">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-start"><h4><?php echo h($pet['name']); ?></h4><?php echo status_badge($pet['status']); ?></div>
                    <p class="text-muted mb-2"><i class="fas fa-paw mr-2"></i><?php echo h($pet['type']); ?> . <?php echo h($pet['breed']); ?></p>
                    <p class="mb-3"><strong><?php echo h($pet['age']); ?></strong> years . <?php echo h($pet['gender']); ?> . <?php echo h($pet['health_status']); ?></p>
                    <a href="pet_details.php?id=<?php echo (int)$pet['id']; ?>" class="btn btn-primary btn-block">View Details</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav aria-label="Pets page navigation" class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo h(http_build_query(array_merge($paginationFilters, ['page' => max(1, $page - 1)]))); ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo h(http_build_query(array_merge($paginationFilters, ['page' => $i]))); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo h(http_build_query(array_merge($paginationFilters, ['page' => min($totalPages, $page + 1)]))); ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
