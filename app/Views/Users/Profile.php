<div class='row'>
    <h2>User Profile : <?= $user->username; ?></h2>
    <hr>
    <?= View::fetch('Partials/Messages'); ?>
</div>

<div class="row">
    <div class="col-md-6 col-md-offset-3 col-sm-6 col-sm-offset-3">
        <h3>Change Password</h3>
        <hr>

        <form class="form-horizontal" action="<?= site_url('profile'); ?>" method="POST">
            <div class="form-group <?= $errors->has('current_password') ? 'has-error' : ''; ?>">
                <label class="col-sm-4 control-label" for="current_password">Current Password <font color="#CC0000">*</font></label>
                <div class="col-sm-8">
                    <input type="password" class="input-medium input-block-level form-control" name="current_password" id="current_password" value="<?= Input::old('current_password'); ?>" placeholder="Insert the current Password" title="Insert the current Password">
                    <?php if ($errors->has('current_password')) { ?>
                    <span class="help-block"><?= $errors->first('current_password'); ?></span>
                    <?php } ?>
                </div>
            </div>
            <div class="form-group <?= $errors->has('password') ? 'has-error' : ''; ?>">
                <label class="col-sm-4 control-label" for="password">Password <font color="#CC0000">*</font></label>
                <div class="col-sm-8">
                    <input type="password" class="input-medium input-block-level form-control" name="password" id="password" value="<?= Input::old('password'); ?>" placeholder="Insert the new Password" title="Insert the new Password">
                    <?php if ($errors->has('password')) { ?>
                    <span class="help-block"><?= $errors->first('password'); ?></span>
                    <?php } ?>
                </div>
            </div>
            <div class="form-group <?= $errors->has('password_confirmation') ? 'has-error' : ''; ?>">
                <label class="col-sm-4 control-label" for="password_confirmation">Password Confirmation <font color="#CC0000">*</font></label>
                <div class="col-sm-8">
                    <input type="password" class="input-medium input-block-level form-control" name="password_confirmation" id="password_confirmation" value="<?= Input::old('password_confirmation'); ?>" placeholder="Verify the new Password" title="Verify the new Password">
                    <?php if ($errors->has('password_confirmation')) { ?>
                    <span class="help-block"><?= $errors->first('password_confirmation'); ?></span>
                    <?php } ?>
                </div>
            </div>
            <div class="clearfix"></div>
            <br>
            <font color="#CC0000">*</font>Required field
            <hr>
            <div>
                <button type="submit" class="btn btn-success col-sm-4 pull-right"><i class='fa fa-check'></i> Save</button>
            </div>
            <input type="hidden" name="_token" value="<?= csrf_token(); ?>" />
        </form>
    </div>
</div>
