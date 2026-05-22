<?php
include 'layout.php';
require_role([2,3]);

// DELETE PET
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Get pet photo first
    $pet_result = mysqli_query($conn, "SELECT photo FROM pets WHERE id = $id");
    $pet = mysqli_fetch_assoc($pet_result);

    if ($pet) {

        // Delete related records first to avoid database constraint problem
        mysqli_query($conn, "DELETE FROM pet_health_records WHERE pet_id = $id");
        mysqli_query($conn, "DELETE FROM wishlist WHERE pet_id = $id");
        mysqli_query($conn, "DELETE FROM adoption_applications WHERE pet_id = $id");

        // For appointments, do not delete the appointment.
        // Just remove the pet link.
        mysqli_query($conn, "UPDATE appointments SET pet_id = NULL WHERE pet_id = $id");

        // Delete pet record
        $delete_pet = mysqli_query($conn, "DELETE FROM pets WHERE id = $id");

        if ($delete_pet) {

            // Delete photo file only if it is a local file
            if (!empty($pet['photo']) && strpos($pet['photo'], 'http') !== 0 && file_exists($pet['photo'])) {
                unlink($pet['photo']);
            }

            flash('success', 'Pet record deleted successfully.');
        } else {
            flash('error', 'Failed to delete pet record: ' . mysqli_error($conn));
        }

    } else {
        flash('error', 'Pet not found.');
    }

    header('Location: manage_pets.php');
    exit();
}

// FETCH PETS
$result = mysqli_query($conn, "SELECT * FROM pets ORDER BY created_at DESC");

page_header('Pet Management - PawFect Home','pets'); 
page_title('Pet Management','Add, edit and update pet profiles. Pets are for adoption only, so no price is used.');
?>

<div class="container py-5">
    <div class="mb-3">
        <a href="pet_form.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Add New Pet
        </a>
    </div>

    <div class="table-responsive card-clean">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Type / Breed</th>
                    <th>Age</th>
                    <th>Health</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php while($p = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>
                            <img src="<?php echo h($p['photo']); ?>" 
                                 style="width:70px;height:50px;object-fit:cover;border-radius:8px">
                        </td>

                        <td><?php echo h($p['name']); ?></td>

                        <td>
                            <?php echo h($p['type']); ?> / <?php echo h($p['breed']); ?>
                        </td>

                        <td><?php echo h($p['age']); ?> years</td>

                        <td><?php echo h($p['health_status']); ?></td>

                        <td><?php echo status_badge($p['status']); ?></td>

                        <td>
                            <a href="pet_form.php?id=<?php echo $p['id']; ?>" 
                               class="btn btn-sm btn-primary">
                                Edit
                            </a>

                            <a href="pet_health.php?pet_id=<?php echo $p['id']; ?>" 
                               class="btn btn-sm btn-outline-secondary">
                                Health
                            </a>

                            <a href="manage_pets.php?delete=<?php echo $p['id']; ?>" 
                               onclick="return confirm('Delete this pet?')" 
                               class="btn btn-sm btn-outline-danger">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php page_footer(); ?>