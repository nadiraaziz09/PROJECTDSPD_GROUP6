<?php
include 'layout.php';
require_role(3);
if (isset($_POST['save'])) {
    $id=(int)($_POST['id']??0); $title=trim($_POST['title']); $content=trim($_POST['content']); $expiry=$_POST['expiry_date'] ?: null; $status=$_POST['status'];
    if ($id) { $stmt=mysqli_prepare($conn,"UPDATE announcements SET title=?,content=?,expiry_date=?,status=? WHERE id=?"); mysqli_stmt_bind_param($stmt,'ssssi',$title,$content,$expiry,$status,$id); }
    else { $stmt=mysqli_prepare($conn,"INSERT INTO announcements (title,content,expiry_date,status) VALUES (?,?,?,?)"); mysqli_stmt_bind_param($stmt,'ssss',$title,$content,$expiry,$status); }
    mysqli_stmt_execute($stmt); flash('success','Announcement saved.'); header('Location: announcements.php'); exit();
}
if (isset($_GET['delete'])) { $id=(int)$_GET['delete']; mysqli_query($conn,"DELETE FROM announcements WHERE id=$id"); flash('success','Announcement deleted.'); header('Location: announcements.php'); exit(); }
$edit=null; if(isset($_GET['edit'])){ $id=(int)$_GET['edit']; $edit=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM announcements WHERE id=$id")); }
$result=mysqli_query($conn,"SELECT * FROM announcements ORDER BY created_at DESC");
page_header('Announcements - PawFect Home'); page_title('System Announcements','Admin can create, edit and delete announcements for customers.');
?>
<div class="container py-5"><div class="card-clean p-4 mb-4"><h4><?php echo $edit?'Edit':'Create'; ?> Announcement</h4><form method="post"><input type="hidden" name="id" value="<?php echo (int)($edit['id']??0); ?>"><div class="form-row"><div class="col-md-5 form-group"><label>Title</label><input name="title" class="form-control" value="<?php echo h($edit['title']??''); ?>" required></div><div class="col-md-3 form-group"><label>Expiry Date</label><input type="date" name="expiry_date" class="form-control" value="<?php echo h($edit['expiry_date']??''); ?>"></div><div class="col-md-2 form-group"><label>Status</label><select name="status" class="custom-select"><option <?php echo ($edit['status']??'active')==='active'?'selected':''; ?>>active</option><option <?php echo ($edit['status']??'')==='inactive'?'selected':''; ?>>inactive</option></select></div><div class="col-md-2 form-group d-flex align-items-end"><button name="save" class="btn btn-primary btn-block">Save</button></div></div><textarea name="content" class="form-control" rows="3" required><?php echo h($edit['content']??''); ?></textarea></form></div><div class="table-responsive card-clean"><table class="table mb-0"><thead><tr><th>Title</th><th>Expiry</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php while($a=mysqli_fetch_assoc($result)): ?><tr><td><strong><?php echo h($a['title']); ?></strong><br><small><?php echo h($a['content']); ?></small></td><td><?php echo h($a['expiry_date'] ?: '-'); ?></td><td><?php echo status_badge($a['status']); ?></td><td><a href="announcements.php?edit=<?php echo $a['id']; ?>" class="btn btn-sm btn-primary">Edit</a> <a href="announcements.php?delete=<?php echo $a['id']; ?>" onclick="return confirm('Delete announcement?')" class="btn btn-sm btn-outline-danger">Delete</a></td></tr><?php endwhile; ?>
</tbody></table></div></div>
<?php page_footer(); ?>
