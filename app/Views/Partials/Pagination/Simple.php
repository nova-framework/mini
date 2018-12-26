<?php if ($paginator->hasPages()) { ?>
    <ul class="pagination">
        <!-- Previous Page Link -->
        <?php if ($paginator->onFirstPage()) { ?>
            <li class="disabled"><span>&laquo; Previous</span></li>
        <?php } else { ?>
            <li><a href="<?= $paginator->previousPageUrl(); ?>" rel="prev">&laquo; Previous</a></li>
        <?php } ?>

        <!-- Next Page Link -->
        <?php if ($paginator->hasMorePages()) { ?>
            <li><a href="<?= $paginator->nextPageUrl(); ?>" rel="next">Next &raquo;</a></li>
        <?php } else { ?>
            <li class="disabled"><span>Next &raquo;</span></li>
        <?php } ?>
    </ul>
<?php } ?>
