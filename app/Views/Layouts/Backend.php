<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= isset($title) ? $title : 'Page'; ?> - <?= $siteName = Config::get('app.name'); ?></title>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/icheck-bootstrap@3.0.1/icheck-bootstrap.min.css">

    <!-- Local customizations -->
    <link rel="stylesheet" type="text/css" href="<?= asset_url('css/bootstrap-xl-mod.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('css/style.css'); ?>">
</head>
<body>

<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
    <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="<?= site_url('dashboard'); ?>"><strong><?= $siteName; ?></strong></a>
        </div>
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <?php if (Auth::check()) { ?>
                <li <?= ($currentUri == 'dashboard') ? 'class="active"' : ''; ?>>
                    <a href="<?= site_url('dashboard'); ?>"><i class='fa fa-dashboard'></i> Dashboard</a>
                </li>
                <?php } ?>
            </ul>
            <ul class="nav navbar-nav navbar-right" style="margin-right: 5px;">
                <?php if (Auth::check()) { ?>
                <li <?= ($currentUri == 'profile') ? 'class="active"' : ''; ?>>
                    <a href="<?= site_url('profile'); ?>"><i class='fa fa-user'></i> Profile</a>
                </li>
                <li>
                    <a href="<?= site_url('logout'); ?>" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class='fa fa-sign-out'></i> Sign out
                    </a>
                    <form id="logout-form" action="<?= site_url('logout'); ?>" method="POST" style="display: none;">
                        <input type="hidden" name="_token" value="<?= csrf_token(); ?>" />
                    </form>
                </li>
                <?php } else { ?>
                <li <?= ($currentUri == 'login') ? 'class="active"' : ''; ?>>
                    <a href="<?= site_url('login'); ?>"><i class='fa fa-sign-in'></i> Sign in</a>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <p><img src="<?= asset_url('images/nova.png'); ?>"  style="max-height: 180px; width: auto;" alt="<?= $siteName; ?>"></p>

    <?= $content; ?>
</div>

<footer class="footer">
    <div class="container-fluid">
        <div class="row" style="margin: 15px 0 0;">
            <div class="col-lg-4">
                Mini MVC Framework <strong><?= App::version(); ?></strong>
            </div>
            <div class="col-lg-8">
                <p class="text-muted pull-right">
                    <small><!-- DO NOT DELETE! - Profiler --></small>
                </p>
            </div>
        </div>
    </div>
</footer>

<script type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

</body>
</html>
