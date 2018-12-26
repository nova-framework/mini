<?php foreach (array('info', 'success', 'warning', 'danger') as $type) { ?>
    <?php if (Session::has($type)) { ?>
<div class="alert alert-<?= $type; ?> alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true"><span aria-hidden="true">&times;</span></button>
    <?= Session::pull($type); ?>
</div>
    <?php } ?>
<?php } ?>
