<?php if ($paginator->hasPages()) { ?>
    <ul class="pagination">
        <!-- Previous Page Link -->
        <?php if ($paginator->onFirstPage()) { ?>
            <li class="disabled"><span>&laquo;</span></li>
        <?php } else { ?>
            <li><a href="<?= $paginator->previousPageUrl(); ?>" rel="prev">&laquo;</a></li>
        <?php } ?>

        <!-- Pagination Elements -->
        <?php foreach ($elements as $element) { ?>
            <!-- "Three Dots" Separator -->
            <?php if (is_string($element)) { ?>
                <li class="disabled"><span><?= $element; ?></span></li>
            <?php } ?>

            <!-- Array Of Links -->
            <?php if (is_array($element)) { ?>
                <?php foreach ($element as $page => $url) { ?>
                    <?php if ($page == $paginator->currentPage()) { ?>
                        <li class="active"><span><?= $page; ?></span></li>
                    <?php } else { ?>
                        <li><a href="{{ $url }}"><?= $page; ?></a></li>
                    <?php } ?>
                <?php } ?>
            <?php } ?>
        <?php } ?>

        <!-- Next Page Link -->
        <?php if ($paginator->hasMorePages()) { ?>
            <li><a href="<?= $paginator->nextPageUrl(); ?>" rel="next">&raquo;</a></li>
        <?php } else { ?>
            <li class="disabled"><span>&raquo;</span></li>
        <?php } ?>
    </ul>
<?php } ?>
