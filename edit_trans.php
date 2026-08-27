<?php
declare(strict_types=1);

/**
 * Displays a form to edit a transaction in the checkbook application.
 *
 * Reconciled transactions may still have their date and check number corrected,
 * but the user has to confirm the change first.  Type, description, and amount
 * stay locked so the reconciled balance keeps matching the bank.
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
$trans = getIntValue('trans', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}
if (empty($trans)) {
    fatalError(translate('No transaction specified'));
}

$Account = get_account_info($acct);

// Get transaction info using prepared statement
$sql = 'SELECT chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled ' .
       'FROM chk_trans WHERE chk_acct_id = ? AND chk_trans_id = ?';
$res = dbi_execute($sql, [$acct, $trans]);
$transaction = [];
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $transaction = [
            'type' => (int)$row[0],
            'num' => $row[1] !== null ? (string)$row[1] : '',
            'amount' => (float)$row[2],
            'date' => (string)$row[3],
            'description' => (string)$row[4],
            'reconciled' => (string)$row[5],
        ];
        // Adjust amount for non-deposit transactions
        if ($transaction['type'] !== 1) {
            $transaction['amount'] = -$transaction['amount'];
        }
        dbi_free_result($res);
    } else {
        fatalError(translate('No such transaction: ') . $trans);
    }
} else {
    fatalError(translate('Database error') . ': Unable to retrieve transaction information.');
}

$isReconciled = $transaction['reconciled'] === 'Y';

// For a reconciled transaction, show what the bank actually reported so the
// user can see exactly what they are about to diverge from.
$bankTrans = $isReconciled ? get_matched_bank_trans($acct, $trans) : null;
$bankSummary = $bankTrans !== null ? format_bank_trans_summary($bankTrans) : '';

$dateValue = date_to_str($transaction['date'], '__mm__/__dd__/__yyyy__', false);

$title = translate('Account') . ': ' . htmlentities($Account['name']);
print_header($title);
print_heading($title);
print_account_info($Account);

?>

<form action="edit_trans_handler.php" method="POST" id="editTransForm">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$Account['acct_id']); ?>" />
    <input type="hidden" name="trans" value="<?php echo htmlspecialchars((string)$trans); ?>" />
    <!-- Shared by the reconciled-edit and delete confirmations. -->
    <input type="hidden" name="confirm" id="confirmField" value="" />

    <?php if ($isReconciled): ?>
        <div class="alert alert-warning d-flex" role="alert">
            <i class="bi bi-shield-lock-fill fs-4 me-3" aria-hidden="true"></i>
            <div>
                <h6 class="alert-heading mb-1"><?php echo translate('This transaction has been reconciled'); ?></h6>
                <p class="mb-0 small">
                    <?php echo translate('Only the date and check number can be corrected. Type, description, and amount are locked so your reconciled balance keeps matching the bank.'); ?>
                </p>
                <?php if ($bankTrans !== null): ?>
                    <p class="mb-0 small mt-2">
                        <strong><?php echo translate('Matched bank statement line'); ?>:</strong>
                        <?php echo htmlentities($bankSummary); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row mb-3">
        <label for="date" class="col-sm-2 col-form-label"><?php echo translate('Date'); ?>:</label>
        <div class="col-sm-3">
            <input type="text" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($dateValue); ?>" />
        </div>
    </div>
    <div class="row mb-3">
        <label for="type" class="col-sm-2 col-form-label"><?php echo translate('Type'); ?>:</label>
        <div class="col-sm-3">
            <div class="input-group">
                <select class="form-select" id="type" name="type" <?php echo $isReconciled ? 'disabled aria-describedby="lockedHelp"' : ''; ?>>
                    <option value="2" <?php echo $transaction['type'] === 2 ? 'selected' : ''; ?>><?php echo translate('Debit'); ?></option>
                    <option value="3" <?php echo $transaction['type'] === 3 ? 'selected' : ''; ?>><?php echo translate('Check'); ?></option>
                    <option value="4" <?php echo $transaction['type'] === 4 ? 'selected' : ''; ?>><?php echo translate('Charge/Fee'); ?></option>
                    <option value="1" <?php echo $transaction['type'] === 1 ? 'selected' : ''; ?>><?php echo translate('Deposit'); ?></option>
                </select>
                <?php if ($isReconciled): ?>
                    <span class="input-group-text" title="<?php echo tooltip('Locked because this transaction is reconciled'); ?>"><i class="bi bi-lock-fill" aria-hidden="true"></i><span class="visually-hidden"><?php echo translate('Locked'); ?></span></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <label for="num" class="col-sm-2 col-form-label"><?php echo translate('ChkNo'); ?>:</label>
        <div class="col-sm-2">
            <input type="text" class="form-control" id="num" name="num" inputmode="numeric" value="<?php echo htmlspecialchars($transaction['num']); ?>" />
        </div>
    </div>
    <div class="row mb-3">
        <label for="description" class="col-sm-2 col-form-label"><?php echo translate('Description'); ?>:</label>
        <div class="col-sm-6">
            <div class="input-group">
                <input type="text" class="form-control<?php echo $isReconciled ? ' bg-body-secondary' : ''; ?>" id="description" name="description" value="<?php echo htmlspecialchars($transaction['description']); ?>" <?php echo $isReconciled ? 'readonly aria-describedby="lockedHelp"' : ''; ?> />
                <?php if ($isReconciled): ?>
                    <span class="input-group-text" title="<?php echo tooltip('Locked because this transaction is reconciled'); ?>"><i class="bi bi-lock-fill" aria-hidden="true"></i><span class="visually-hidden"><?php echo translate('Locked'); ?></span></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <label for="amount" class="col-sm-2 col-form-label"><?php echo translate('Amount'); ?>:</label>
        <div class="col-sm-3">
            <div class="input-group">
                <input type="text" class="form-control<?php echo $isReconciled ? ' bg-body-secondary' : ''; ?>" id="amount" name="amount" value="<?php echo htmlspecialchars(sprintf('%.2f', $transaction['amount'])); ?>" <?php echo $isReconciled ? 'readonly aria-describedby="lockedHelp"' : ''; ?> />
                <?php if ($isReconciled): ?>
                    <span class="input-group-text" title="<?php echo tooltip('Locked because this transaction is reconciled'); ?>"><i class="bi bi-lock-fill" aria-hidden="true"></i><span class="visually-hidden"><?php echo translate('Locked'); ?></span></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($isReconciled): ?>
        <div class="row mb-3">
            <div class="col-sm-6 offset-sm-2">
                <div id="lockedHelp" class="form-text"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i><?php echo translate('Locked fields cannot be changed while this transaction is reconciled.'); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-sm-6 offset-sm-2 d-flex gap-2">
            <?php if ($isReconciled): ?>
                <button type="submit" class="btn btn-warning" id="saveReconciledBtn">
                    <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i><?php echo translate('Save Changes'); ?>
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary"><?php echo translate('Save'); ?></button>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="list.php?acct=<?php echo (int)$acct; ?>"><?php echo translate('Cancel'); ?></a>
            <button type="submit" formaction="delete_trans_handler.php" formnovalidate
                    class="btn btn-outline-danger ms-auto" id="deleteTransBtn" data-confirm-delete="1">
                <i class="bi bi-trash me-1" aria-hidden="true"></i><?php echo translate('Delete'); ?>
            </button>
        </div>
    </div>
</form>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteLabel">
                    <i class="bi bi-exclamation-octagon-fill text-danger me-1" aria-hidden="true"></i><?php echo translate('Delete this transaction?'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo tooltip('Cancel'); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo translate('This cannot be undone.'); ?></p>
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?php echo translate('Date'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlentities($dateValue); ?></dd>
                    <dt class="col-sm-4"><?php echo translate('ChkNo'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlentities($transaction['num'] === '' ? translate('(none)') : $transaction['num']); ?></dd>
                    <dt class="col-sm-4"><?php echo translate('Description'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlentities($transaction['description']); ?></dd>
                    <dt class="col-sm-4"><?php echo translate('Amount'); ?></dt>
                    <dd class="col-sm-8"><?php echo htmlentities(sprintf('%.2f', $transaction['amount'])); ?></dd>
                </dl>
                <?php if ($isReconciled): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong><?php echo translate('This transaction is reconciled'); ?>.</strong>
                        <?php echo translate('The bank statement line it was matched to will go back to unreconciled so you can match it again.'); ?>
                        <?php if ($bankTrans !== null): ?>
                            <br><span class="small"><?php echo htmlentities($bankSummary); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo translate('Cancel'); ?></button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><?php echo translate('Delete transaction'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php if ($isReconciled): ?>
<div class="modal fade" id="confirmReconciledModal" tabindex="-1" aria-labelledby="confirmReconciledLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmReconciledLabel">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1" aria-hidden="true"></i><?php echo translate('Change a reconciled transaction?'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo tooltip('Cancel'); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo translate('This transaction was already matched to a bank statement. Saving these changes will make it differ from what the bank reported.'); ?></p>
                <ul class="list-group list-group-flush mb-0" id="reconciledChangeList"></ul>
                <?php if ($bankTrans !== null): ?>
                    <p class="small text-muted mt-3 mb-0">
                        <strong><?php echo translate('Bank reported'); ?>:</strong>
                        <?php echo htmlentities($bankSummary); ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo translate('Cancel'); ?></button>
                <button type="button" class="btn btn-warning" id="confirmReconciledSave"><?php echo translate('Save changes anyway'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="js/checkbook.js"></script>
<script>
// print_trailer() emits the Bootstrap bundle further down the page, so wait for
// the document to finish parsing before wiring up the modals.
document.addEventListener("DOMContentLoaded", function () {
    initDeleteTransaction({
        formId: "editTransForm",
        deleteButtonId: "deleteTransBtn",
        confirmFieldId: "confirmField",
        modalId: "confirmDeleteModal",
        confirmButtonId: "confirmDeleteBtn",
        action: "delete_trans_handler.php"
    });
<?php if ($isReconciled): ?>
    initReconciledEdit({
        formId: "editTransForm",
        saveButtonId: "saveReconciledBtn",
        confirmFieldId: "confirmField",
        modalId: "confirmReconciledModal",
        confirmButtonId: "confirmReconciledSave",
        changeListId: "reconciledChangeList",
        labels: {
            date: <?php echo json_encode(translate('Date')); ?>,
            num: <?php echo json_encode(translate('ChkNo')); ?>,
            none: <?php echo json_encode(translate('(none)')); ?>
        }
    });
<?php endif; ?>
});
</script>

<?php
print_trailer();
?>
