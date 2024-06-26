<?php 
ob_start();
session_start();
include ("../_init.php");

// Check, if your logged in or not
// If user is not logged in then return an alert message
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

$store_id = store_id();
$banking_model = registry()->get('loader')->model('banking');

function validate_withdraw_post_data($request)
{
  if (!validateInteger($request->post['account_id'])) {
    throw new Exception(trans('error_account'));
  }

  if (!validateInteger($request->post['exp_category_id'])) {
    throw new Exception(trans('error_expense_category'));
  }

  if (empty($request->post['ref_no'])) {
    throw new Exception(trans('error_ref_no'));
  }

  // Validate title
  if (!validateString($request->post['title'])) {
    throw new Exception(trans('error_title'));
  }

  // Validate amount
  if (!validateFloat($request->post['amount'])) {
    throw new Exception(trans('error_amount'));
  }

  if (get_the_account_balance($request->post['account_id']) < $request->post['amount']) {
    throw new Exception(trans('error_insufficient_balance'));
  }
}

// Withdraw from Bank
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'WITHDRAW')
{
  try {

    if (user_group_id() != 1 && !has_permission('access', 'withdraw')) {
      throw new Exception(trans('error_withdraw_permission'));
    }

    validate_withdraw_post_data($request);

    $account_id = $request->post['account_id'];
    $title = $request->post['title'];
    $details = $request->post['details'];
    $image = $request->post['image'];
    $withdraw_amount = $request->post['amount'];
    if ($withdraw_amount > get_the_account_balance($account_id)) {
      throw new Exception(trans('error_insufficient_balance'));
    }
    $ref_no = $request->post['ref_no'];

    // Check for unique bank_transaction
    $transaction_info = $banking_model->getTransactionInfo($ref_no);
    if ($transaction_info) {
      throw new Exception(trans('error_ref_no_exist'));
    }

    $Hooks->do_action('Before_Bank_Withdraw', $ref_no);

    $exp_category_id = $request->post['exp_category_id'];
    $transaction_type = 'withdraw';
    $created_by = user_id();
    $created_at = date_time();

    $statement = db()->prepare("INSERT INTO `bank_transaction_info` (store_id, account_id, exp_category_id, ref_no, transaction_type, title, details, image, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $statement->execute(array($store_id, $account_id, $exp_category_id, $ref_no, $transaction_type, $title, $details, $image, $created_by, $created_at));
    $info_id = db()->lastInsertId();
    
    $statement = db()->prepare("INSERT INTO `bank_transaction_price` (store_id, info_id, ref_no, amount) VALUES (?, ?, ?, ?)");
    $statement->execute(array($store_id, $info_id, $ref_no, $withdraw_amount));

    // Adjust withdraw amount
    $statement = db()->prepare("UPDATE `bank_account_to_store` SET `withdraw` = `withdraw` + $withdraw_amount WHERE `store_id` = ? AND `account_id` = ?");
    $statement->execute(array($store_id, $account_id));

    $statement = db()->prepare("UPDATE `bank_accounts` SET `total_withdraw` = `total_withdraw` + $withdraw_amount WHERE `id` = ?");
    $statement->execute(array($account_id));

    $invoice = $banking_model->getTransactionInfo($ref_no);

    $Hooks->do_action('After_Bank_Withdraw', array('type' => 'bank_withdraw', 'id' => $ref_no, 'amount' => $withdraw_amount));

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_withdraw_success'), 'invoice' => $invoice, 'balance' => currency_format(get_bank_balance(from(), to()))));
    exit();

  } catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

function validate_deposit_post_data($request)
{
  if (!validateInteger($request->post['account_id'])) {
    throw new Exception(trans('error_account'));
  }

  if (!validateInteger($request->post['source_id'])) {
    throw new Exception(trans('error_source'));
  }

  if (empty($request->post['ref_no'])) {
    throw new Exception(trans('error_ref_no'));
  }

  // Validate title
  if (!validateString($request->post['title'])) {
    throw new Exception(trans('error_about'));
  }

  // Validate amount
  if (!validateFloat($request->post['amount'])) {
    throw new Exception(trans('error_amount'));
  }
}

// Deposit to Bank
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'DEPOSIT')
{
  try {

    if (user_group_id() != 1 && !has_permission('access', 'deposit')) {
      throw new Exception(trans('error_deposit_permission'));
    }

    validate_deposit_post_data($request);

    $ref_no = $request->post['ref_no'];

    // Check for unique invoice
    $bank_transaction_info = $banking_model->getTransactionInfo($ref_no);
    if ($bank_transaction_info) {
      throw new Exception(trans('error_ref_no_exist'));
    }

    if (isset($data['capital']) && $data['capital']) {
      if ($data['capital'] <= 0)
      {
        throw new Exception(trans('error_capital_amount'));
      }
    }

    $Hooks->do_action('Before_Bank_Deposit', $ref_no);
    
    $inserted_ref_no = $banking_model->addDeposit($request->post);
    $invoice = $banking_model->getTransactionInfo($inserted_ref_no);

    $Hooks->do_action('After_Bank_Deposit', array('type' => 'bank_deposit'));

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_deposit_success'), 'invoice' => $invoice, 'balance' => currency_format(get_bank_balance(from(), to()))));
    exit();

  } catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

function validate_transfer_post_data($request)
{
  if (!validateInteger($request->post['from_account_id'])) {
    throw new Exception(trans('error_from_account'));
  }

  if (!validateInteger($request->post['to_account_id'])) {
    throw new Exception(trans('error_to_account'));
  }

  if ($request->post['from_account_id'] == $request->post['to_account_id']) {
    throw new Exception(trans('error_same_account'));
  }

  if (empty($request->post['ref_no'])) {
    throw new Exception(trans('error_ref_no'));
  }

  // Validate title
  if (!validateString($request->post['title'])) {
    throw new Exception(trans('error_about'));
  }

  // Validate amount
  if (!validateFloat($request->post['amount'])) {
    throw new Exception(trans('error_amount'));
  }

  if (get_the_account_balance($request->post['from_account_id']) < $request->post['amount']) {
    throw new Exception(trans('error_insufficient_balance'));
  }
}

// Transfer to Bank
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'TRANSFER')
{
  try {

    if (user_group_id() != 1 && !has_permission('access', 'transfer')) {
      throw new Exception(trans('error_transfer_permission'));
    }

    validate_transfer_post_data($request);

    $ref_no = $request->post['ref_no'];

    // Check for unique invoice
    $bank_transaction_info = $banking_model->getTransactionInfo($ref_no);
    if ($bank_transaction_info) {
      throw new Exception(trans('error_ref_no_exist'));
    }

    $Hooks->do_action('Before_Bank_Transfer', $ref_no);
    
    $account_id = $request->post['to_account_id'];
    $from_account_id = $request->post['from_account_id'];
    $title = $request->post['title'];
    $details = $request->post['details'];
    $transfer_amount = $request->post['amount'];
    $transaction_type = 'transfer';
    $created_by = user_id();
    $created_at = date_time();

    $statement = db()->prepare("INSERT INTO `bank_transaction_info` (store_id, account_id, ref_no, transaction_type, title, details, from_account_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $statement->execute(array($store_id, $account_id, $ref_no, $transaction_type, $title, $details, $from_account_id, $created_by, $created_at));
    $info_id = db()->lastInsertId();

    $statement = db()->prepare("INSERT INTO `bank_transaction_price` (store_id, info_id, ref_no, amount) VALUES (?, ?, ?, ?)");
    $statement->execute(array($store_id, $info_id, $ref_no, $transfer_amount));

    // Adjust transfer amount from other
    $statement = db()->prepare("UPDATE `bank_account_to_store` SET `transfer_from_other` = `transfer_from_other` + $transfer_amount WHERE `store_id` = ? AND `account_id` = ?");
    $statement->execute(array($store_id, $account_id));

    $statement = db()->prepare("UPDATE `bank_accounts` SET `total_transfer_from_other` = `total_transfer_from_other` + $transfer_amount WHERE `id` = ?");
    $statement->execute(array($account_id));

    // Adjust transfer amount to other
    $statement = db()->prepare("UPDATE `bank_account_to_store` SET `transfer_to_other` = `transfer_to_other` + $transfer_amount WHERE `store_id` = ? AND `account_id` = ?");
    $statement->execute(array($store_id, $from_account_id));

    $statement = db()->prepare("UPDATE `bank_accounts` SET `total_transfer_to_other` = `total_transfer_to_other` + $transfer_amount WHERE `id` = ?");
    $statement->execute(array($from_account_id));

    $invoice = $banking_model->getTransactionInfo($ref_no);

    $Hooks->do_action('After_Bank_Transfer', array('type' => 'bank_transfer', 'id' => $ref_no, 'amount' => $transfer_amount));

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_transfer_success'), 'invoice' => $invoice, 'balance' => currency_format(get_bank_balance(from(), to()))));
    exit();

  } catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Withdraw Form
if (isset($request->get['action_type']) AND $request->get['action_type'] == 'WITHDRAW') {
    try {
        include('template/banking_withdraw_form.php');
        exit();
    } catch (Exception $e) { 
        header('HTTP/1.1 422 Unprocessable Entity');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('errorMsg' => $e->getMessage()));
        exit();
    }
}

// Deposit Form
if (isset($request->get['action_type']) AND $request->get['action_type'] == 'DEPOSIT') {
    try {
      include('template/banking_deposit_form.php');
      exit();
    } catch (Exception $e) { 
      header('HTTP/1.1 422 Unprocessable Entity');
      header('Content-Type: application/json; charset=UTF-8');
      echo json_encode(array('errorMsg' => $e->getMessage()));
      exit();
    }
}

// Transfer Form
if (isset($request->get['action_type']) AND $request->get['action_type'] == 'TRANSFER') {
    try {
      include('template/bank_transfer_create_form.php');
      exit();
    } catch (Exception $e) { 
      header('HTTP/1.1 422 Unprocessable Entity');
      header('Content-Type: application/json; charset=UTF-8');
      echo json_encode(array('errorMsg' => $e->getMessage()));
      exit();
    }
}

// View Details
if (isset($request->get['action_type']) && $request->get['action_type'] == 'VIEW') 
{
  try {

      if (!isset($request->get['invoice_id'])) {
        throw new Exception(trans('error_ref_no'));
      }

      $ref_no = $request->get['invoice_id'];
      
      if (!isset($request->get['view_type'])) {
        throw new Exception(trans('error_view_type'));
      }
      $view_type =  $request->get['view_type'];

      // Fetch invoice info
      $statement = db()->prepare("SELECT `bank_transaction_info`.*, `bank_transaction_price`.`amount` FROM `bank_transaction_info` LEFT JOIN bank_transaction_price ON (`bank_transaction_info`.`info_id` = `bank_transaction_price`.`info_id`) WHERE `bank_transaction_info`.`ref_no` = ?");
      $statement->execute(array($ref_no));
      $invoice = $statement->fetch(PDO::FETCH_ASSOC);

      include 'template/banking_' . $view_type  . '_view.php';
      exit();

    } catch (Exception $e) { 

        header('HTTP/1.1 422 Unprocessable Entity');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('errorMsg' => $e->getMessage()));
        exit();
    }
}


/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_Bank_Transaction_list');

$account_id = '';
$where_query = "bank_transaction_info.store_id = $store_id AND bank_transaction_info.transaction_type IN ('deposit', 'withdraw')";
if (from()) {
  $from = from();
  $to = to();
  $where_query .= date_range_accounting_filter($from, $to);
}
if (isset($request->get['account_id']) && $request->get['account_id'] != 'null') {
  $account_id = $request->get['account_id'];
  $where_query .= " AND bank_transaction_info.account_id = $account_id";
}
// DB table to use
$table = "(SELECT bank_transaction_info.*, bank_transaction_price.price_id, bank_transaction_price.amount 
  FROM bank_transaction_info 
  LEFT JOIN bank_transaction_price ON bank_transaction_info.info_id = bank_transaction_price.info_id
  WHERE $where_query) as bank_transaction_info";
 
// Table's primary key
$primaryKey = 'info_id';

// Indexes
$columns = array(
    array(
        'db' => 'ref_no',
        'dt' => 'DT_RowId',
        'formatter' => function( $d, $row ) {
            return 'row_'.$d;
        }
    ),
    array( 'db' => 'invoice_id', 'dt' => 'invoice_id' ),
    array( 'db' => 'price_id', 'dt' => 'price_id' ),
    array( 
      'db' => 'transaction_type',   
      'dt' => 'transaction_type' ,
      'formatter' => function($d, $row) {
        if ($row['transaction_type'] == 'withdraw') {
          return '<label class="label label-danger">WITHDRAW</label>';
        }
        return '<label class="label label-success">DEPOSIT</label>';
      }
    ),
    array(
        'db'        => 'ref_no',
        'dt'        => 'ref_no',
        'formatter' => function($d, $row) {
          return $row['ref_no'];
        }
    ),
    array(
        'db'        => 'ref_no',
        'dt'        => 'banking_id',
        'formatter' => function($d, $row) {
          if ($row['invoice_id']) {
            return $row['invoice_id'];
          }
          return $row['ref_no'];
        }
    ),
    array( 
      'db' => 'created_at',   
      'dt' => 'created_at' ,
      'formatter' => function($d, $row) {
          return $row['created_at'];
      }
    ),
    array( 
      'db' => 'account_id',   
      'dt' => 'title' ,
      'formatter' => function($d, $row) {
          return get_the_bank_account($row['account_id'], 'account_name');
      }
    ),
    array( 
      'db' => 'amount',   
      'dt' => 'deposit',
      'formatter' => function($d, $row) {
        if ($row['transaction_type'] == 'withdraw') {
          return;
        }
        $amount = $row['amount'];
        return currency_format($amount);
      }
    ),
    array( 
      'db' => 'amount',   
      'dt' => 'withdraw',
      'formatter' => function($d, $row) {
        if ($row['transaction_type'] != 'withdraw') {
          return;
        }
        $withdraw = $row['amount'];
        return currency_format($withdraw);
      }
    ),
    array( 
      'db' => 'ref_no',   
      'dt' => 'balance',
      'formatter' => function($d, $row) use($banking_model, $account_id) {
        $balance = 0;
        $ref_no = $row['price_id'];
        $previous_balance = $banking_model->getPrevBalance($ref_no, store_id(), $account_id);
        $previous_withdraw = $banking_model->gePrevWithdraw($ref_no, store_id(), $account_id);
        if ($row['transaction_type'] == 'withdraw') {
          $balance =  $previous_balance - ((float)$row['amount'] + $previous_withdraw);
        } else {
          $balance = $previous_balance + ((float)$row['amount'] - $previous_withdraw);
        }
        return currency_format($balance);
      }
    ),
    array( 
      'db' => 'ref_no',   
      'dt' => 'btn_view' ,
      'formatter' => function($d, $row) {
          return '<button class="btn btn-sm btn-block btn-primary view-' . $row['transaction_type'] . '" type="button" title="'.trans('button_view').'"><i class="fa fa-eye"></i></button>';
      }
    ),
);

echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_Bank_Transaction_list');

/**
 *===================
 * END DATATABLE
 *===================
 */
 