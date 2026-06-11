<?php
include 'layout.php';
require_role([2,3]); // Staff & Admin only

// Mark message as read
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];

    mysqli_query($conn,
        "UPDATE contact_messages 
         SET status='Read' 
         WHERE id=$id"
    );

    header('Location: manage_contact.php');
    exit();
}

// Delete message
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    mysqli_query($conn,
        "DELETE FROM contact_messages 
         WHERE id=$id"
    );

    flash('success', 'Message deleted successfully.');

    header('Location: manage_contact.php');
    exit();
}

$result = mysqli_query($conn, "
    SELECT * 
    FROM contact_messages 
    ORDER BY created_at DESC
");

page_header('Manage Contact Messages', 'contact');
page_title('Contact Messages', 'Messages sent by users');
?>

<style>
.table-header-red th{
    background:#af2708 !important;
    color:#ffffff !important;
    border-color:#af2708 !important;
}

.table td,
.table th{
    vertical-align: middle;
}

.message-box{
    max-width:300px;
    white-space: normal;
    word-wrap: break-word;
}

.badge-unread{
    background:#dc3545;
    color:white;
    padding:6px 10px;
    border-radius:6px;
    font-size:12px;
}

.badge-read{
    background:#28a745;
    color:white;
    padding:6px 10px;
    border-radius:6px;
    font-size:12px;
}
</style>

<div class="container py-4">

    <div class="card-clean p-4">

        <?php if(mysqli_num_rows($result) > 0): ?>

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-header-red">
                        <tr>
                            <th width="70">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th width="100">Status</th>
                            <th width="170">Date</th>
                            <th width="160">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while($row = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?= $row['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['email']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['subject']) ?>
                                </td>

                                <td class="message-box">
                                    <?= nl2br(htmlspecialchars($row['message'])) ?>
                                </td>

                                <td>

                                    <?php if($row['status'] == 'Unread'): ?>

                                        <span class="badge-unread">
                                            Unread
                                        </span>

                                    <?php else: ?>

                                        <span class="badge-read">
                                            Read
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= date(
                                        'd M Y h:i A',
                                        strtotime($row['created_at'])
                                    ) ?>
                                </td>

                                <td>

                                    <?php if($row['status'] == 'Unread'): ?>

                                        <a href="?read=<?= $row['id'] ?>"
                                           class="btn btn-sm btn-primary mb-1">
                                            Mark Read
                                        </a>

                                    <?php endif; ?>

                                    <a href="?delete=<?= $row['id'] ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Delete this message?')">
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="alert alert-info mb-0">
                No contact messages found.
            </div>

        <?php endif; ?>

    </div>

</div>

<?php page_footer(); ?>