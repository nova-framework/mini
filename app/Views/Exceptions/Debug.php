<div class="row page-header">
    <h1>Whoops!</h1>
</div>

<div class="row">
    <p>
        <?= $exception->getMessage(); ?> in <?= $exception->getFile(); ?> on line <?= $exception->getLine(); ?>
    </p>
    <br>
    <pre style="margin-bottom: 100px;"><?= $exception->getTraceAsString(); ?></pre>
</div>
