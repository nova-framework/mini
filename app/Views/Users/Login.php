<div class='row-responsive'>
    <h2>User Login</h2>
    <hr>
    <?= View::fetch('Partials/Messages'); ?>
</div>

<div class="row">
    <div class="col-md-4 col-md-offset-4">
        <div class="login-panel panel panel-primary" style="margin-top: 30px">
            <div class="panel-heading text-center">
                <h3 class="panel-title">User Login</h3>
            </div>
            <div class="panel-body">
                <form class="form-horizontal" action="<?= site_url('login'); ?>" method="POST">
                    <div class="form-control-container" style="margin-bottom: 15px;">
                        <input type="text" class="input-medium input-block-level form-control" name="username" placeholder="Username" title="Username">
                    </div>
                    <div class="form-control-container" style="margin-bottom: 10px;">
                        <input type="password" class="input-medium input-block-level form-control" name="password" placeholder="Password" title="Password">
                    </div>
                    <div class="form-control-container">
                        <div class="icheck-primary icheck-inline">
                            <input name="remember" type="checkbox" id="remember" />
                            <label for="remember">Remember me</label>
                        </div>
                    </div>
                    <hr style="margin-top: 10px; margin-bottom: 15px;">
                    <div class="form-control-container">
                        <button type="submit" class="btn btn-success col-lg-6 pull-right"><i class='fa fa-sign-in'></i> Sign In</button>
                    </div>
                    <input type="hidden" name="_token" value="<?= csrf_token(); ?>" />
                </form>
            </div>
        </div>
    </div>
</div>
