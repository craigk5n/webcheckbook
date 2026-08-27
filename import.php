<?php
declare(strict_types=1);

/**
 * Displays a form to import transactions from a CSV file in the checkbook application.
 *
 * @package Checkbook
 */

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}

$Account = get_account_info($acct);

$title = translate('Import');
print_header($title);
print_heading($title);
print_account_info($Account);

?>

<form action="import_handler.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$Account['acct_id']); ?>" />
    <div class="row mb-3">
        <label for="description" class="col-sm-2 col-form-label"><?php echo translate('Description'); ?>:</label>
        <div class="col-sm-6">
            <input type="text" class="form-control" id="description" name="description" />
        </div>
    </div>
    <div class="row mb-3">
        <label for="FileName" class="col-sm-2 col-form-label"><?php echo translate('CSV File'); ?>:</label>
        <div class="col-sm-6">
            <input type="file" class="form-control" id="FileName" name="FileName" accept=".csv" />
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6 offset-sm-2">
            <button type="submit" class="btn btn-primary"><?php echo translate('Import'); ?></button>
        </div>
    </div>
</form>

<?php
print_trailer();
?>
