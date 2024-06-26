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

// Check, if user has reading permission or not
// If user have not reading permission return error
if (user_group_id() != 1 && !has_permission('access', 'read_transfer')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

$store_id = store_id();
$user_id = user_id();

// LOAD MODEL
$transfer_model = registry()->get('loader')->model('transfer');

// Validate post data
function validate_request_data($request) 
{
  // Validate items
  if (!isset($request->post['items'])) {
    throw new Exception(trans('error_items'));
  }

  // Validate items
  if (empty($request->post['items'])) {
    throw new Exception(trans('error_products'));
  }

  // Validate status
  if (!validateString($request->post['status'])) {
    throw new Exception(trans('error_status'));
  }

  // Validate From store id
  if (!isset($request->post['from_store_id']) OR !validateInteger($request->post['from_store_id'])) {
    throw new Exception(trans('error_store_id'));
  }

  // Validate To store id
  if (!isset($request->post['to_store_id']) OR !validateInteger($request->post['to_store_id'])) {
    throw new Exception(trans('error_store_id'));
  }
}

function addProductAndQuantity($item_id, $item_quantity, $from_store_id, $to_store_id) 
{
  // Increase product stock
  $statement = db()->prepare("SELECT * FROM `product_to_store` WHERE `product_id` = ? AND `store_id` = ?");
  $statement->execute(array($item_id, $to_store_id));
  $row = $statement->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $statement = db()->prepare("UPDATE `product_to_store` SET `quantity_in_stock` = `quantity_in_stock`+$item_quantity WHERE `product_id` = ? AND `store_id` = ?");
    $statement->execute(array($item_id, $to_store_id));
  } else {
    $statement = db()->prepare("SELECT * FROM `product_to_store` WHERE `product_id` = ? AND `store_id` = ?");
    $statement->execute(array($item_id, $from_store_id));
    $sitem = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = db()->prepare("INSERT INTO `product_to_store` SET `product_id` = ?, `store_id` = ?, `purchase_price` = ?, `sell_price` = ?, `quantity_in_stock` = ?, `alert_quantity` = ?, `box_id` = ?, `taxrate_id` = ?, `tax_method` = ?, `e_date` = ?, `p_date` = ?, `status` = ?, `sort_order` = ?");
    $statement->execute(array($item_id, $to_store_id, $sitem['purchase_price'], $sitem['sell_price'], $item_quantity, $sitem['alert_quantity'], $sitem['box_id'], $sitem['taxrate_id'], $sitem['tax_method'], $sitem['e_date'], date_time(), $sitem['status'], $sitem['sort_order']));

    $suppliers = get_product_suppliers($item_id);
    foreach ($suppliers as $sup) {
      $statement = db()->prepare("SELECT * FROM `product_to_supplier` WHERE `store_id` = ? AND `product_id` = ? AND `sup_id` = ?");
      $statement->execute(array($sup['store_id'], $sup['product_id'], $sup['sup_id']));
      $row = $statement->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $statement = db()->prepare("INSERT INTO `product_to_supplier` SET  `store_id` = ?, `product_id` = ?, `sup_id` = ?");
        $statement->execute(array($sup['store_id'], $sup['product_id'], $sup['sup_id']));
      }
    }
  }

  $product_info = get_the_product($item_id);

  // Transfer category if not exist
    $category_id = $product_info['category_id'];
    $statement = db()->prepare("SELECT * FROM `category_to_store` WHERE `ccategory_id` = ? AND `store_id` = ?");
    $statement->execute(array($category_id, $to_store_id));
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      $statement = db()->prepare("INSERT INTO `category_to_store` SET `ccategory_id` = ?, `store_id` = ?, `status` = ?");
      $statement->execute(array($category_id, $to_store_id, 1));
    }

  // Transfer unit if not exist
    $unit_id = $product_info['unit_id'];
    $statement = db()->prepare("SELECT * FROM `unit_to_store` WHERE `uunit_id` = ? AND `store_id` = ?");
    $statement->execute(array($unit_id, $to_store_id));
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      $statement = db()->prepare("INSERT INTO `unit_to_store` SET `uunit_id` = ?, `store_id` = ?, `status` = ?");
      $statement->execute(array($unit_id, $to_store_id, 1));
    }

  // Transfer box if not exist
    $box_id = $product_info['box_id'];
    $statement = db()->prepare("SELECT * FROM `box_to_store` WHERE `box_id` = ? AND `store_id` = ?");
    $statement->execute(array($box_id, $to_store_id));
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      $statement = db()->prepare("INSERT INTO `box_to_store` SET `box_id` = ?, `store_id` = ?, `status` = ?");
      $statement->execute(array($box_id, $to_store_id, 1));
    }
}

// Transfer
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'TRANSFER')
{
  try {

    // Check create permission
    if (user_group_id() != 1 && !has_permission('access', 'add_transfer')) {
      throw new Exception(trans('error_create_permission'));
    }

    // Validate post
    validate_request_data($request);

    // Validate attachment
    if(isset($_FILES["image"]["type"]) && $_FILES["image"]["type"])
    {
        if (!$_FILES["image"]["type"] == "image/jpg" || !$_FILES["image"]["type"] == "application/pdf" || $_FILES["image"]["size"] > 1048576) {  // 1MB  
            throw new Exception(trans('error_size_or_type'));
        }

        if ($_FILES["image"]["error"] > 0) {
            throw new Exception("Return Code: " . $_FILES["image"]["error"]);
        }
    }

    $ref_no = $request->post['ref_no'];
    $status = $request->post['status'];
    switch ($status) {
      case 'complete':
        $is_visible = 1;
        break;
      case 'pending':
        $is_visible = 0;
        break;
      case 'sent':
        $is_visible = 0;
        break;
      default:
        $is_visible = 0;
        break;
    }
    $note = $request->post['note'];
    $from_store_id = $request->post['from_store_id'];
    $to_store_id = $request->post['to_store_id'];
    $attachment = $request->post['image'];

    foreach ($request->post['items'] as $key => $item) 
    {
      $item_invoice_id = $item['invoice_id'];
      $item_id = $item['id'];
      $sup_id = $item['sup_id'];
      $item_variant_name = $item['variant_name'];
      $item_variant_slug = $item['variant_slug'];
      $item_quantity = $item['quantity'];
      $invoice_id = randomNumber(6).'-'.$ref_no;

      $statement = db()->prepare("SELECT * FROM `purchase_item` WHERE `store_id` = ? AND `sup_id` = ? AND `item_id` = ? AND `invoice_id` = ?");
      $statement->execute(array($store_id, $sup_id, $item_id, $item_invoice_id));
      $item_info = $statement->fetch(PDO::FETCH_ASSOC);
      $quantity_exist = $item_info['item_quantity'] - $item_info['total_sell'];
      if ($item_quantity > $quantity_exist) {
        $item_quantity = $quantity_exist;
      }

      $statement = db()->prepare("SELECT * FROM `purchase_info` pi LEFT JOIN `purchase_price` pp ON (`pi`.`invoice_id` = `pp`.`invoice_id`) WHERE `pi`.`invoice_id` = ?");
      $statement->execute(array($item_info['invoice_id']));
      $info = $statement->fetch(PDO::FETCH_ASSOC);
      if ($info['payment_status'] == 'due') {
        throw new Exception("Invoice ID: " . $item_info['invoice_id'] . " has due. Please, paid the due before transfer");
      }

      $ref_invoice_id = $item_info['invoice_id'];
      $item_id = $item_info['item_id'];
      $store_id = $info['store_id'];
      $item_total = $item_info['item_purchase_price'] * $item_quantity;
      $tax = $item_info['tax'];
      $gst = $tax;
      $item_tax = ($tax / 100) * $item_total;
      $tax_method = $item_info['tax_method'];
      $cgst = $item_info['cgst'] > 0 ? $item_tax / 2 : 0;
      $sgst = $item_info['cgst'] > 0 ? $item_tax / 2 : 0;
      $igst = $item_info['igst'] > 0 ? $item_tax : 0;

      $order_tax = $item_tax;
      $payable_amount = $item_total;
      $paid_amount = 0;
      $due = $payable_amount;
      $due_paid = $info['due_paid'];
      $balance = 0;

      $Hooks->do_action('Before_Stock_Transfer', $request);

      $statement = db()->prepare("INSERT INTO `purchase_info` SET `invoice_id` = ?, `inv_type` = ?, `store_id` = ?, `total_item` = ?, `status` = ?, `purchase_date` = ?, `is_visible` = ?, `created_by` = ?, `created_at` = ?");
      $statement->execute(array($invoice_id, 'transfer', $to_store_id, 1, 'stock', $info['purchase_date'], $is_visible, $info['created_by'], date_time()));

      $statement = db()->prepare("INSERT INTO `purchase_price` SET `invoice_id` = ?, `store_id` = ?, `subtotal` = ?, `order_tax` = ?, `item_tax` = ?, `cgst` = ?, `sgst` = ?, `igst` = ?, `payable_amount` = ?, `paid_amount` = ?, `due` = ?");
      $statement->execute(array($invoice_id, $to_store_id, $item_total, $order_tax, $item_tax, $cgst, $sgst, $igst, $payable_amount, $due, 0));      

      $statement = db()->prepare("UPDATE `purchase_price` SET `item_tax` = `item_tax`-$item_tax, `cgst` = `cgst`-$cgst, `sgst` = `sgst`-$sgst, `igst` = `igst`-$igst, `payable_amount` = `payable_amount`-$payable_amount, `paid_amount` = `paid_amount`-$payable_amount WHERE `store_id` = ? AND `invoice_id` = ?");
      $statement->execute(array($info['store_id'], $info['invoice_id']));

      $statement = db()->prepare("INSERT INTO `purchase_item` SET `invoice_id` = ?, `store_id` = ?, `is_visible` = ?, `sup_id` = ?, `item_id` = ?, `category_id` = ?, `item_name` = ?, `item_variant_name` = ?, `item_variant_slug` = ?, `item_purchase_price` = ?, `item_selling_price` = ?, `item_quantity` = ?, `total_sell` = ?, `status` = ?, `item_total` = ?, `item_tax` = ?, `tax_method` = ?, `tax` = ?, `gst` = ?, `cgst` = ?, `sgst` = ?, `igst` = ?");
      $statement->execute(array($invoice_id, $to_store_id, 0, $sup_id, $item_id, $item_info['category_id'], $item_info['item_name'], $item_variant_name, $item_variant_slug, $item_info['item_purchase_price'], $item_info['item_selling_price'], $item_quantity, 0, 'stock', $item_total, $item_tax, $tax_method, $tax, $gst, $cgst, $sgst, $igst));


      $statement = db()->prepare("UPDATE `purchase_item` SET `total_sell` = `total_sell`+$item_quantity, `item_total` = `item_total`-$item_total, `item_tax` = `item_tax`-$item_tax, `cgst` = `cgst`-$cgst, `sgst` = `sgst`-$sgst, `igst` = `igst`-$igst WHERE `id` = ?");
      $statement->execute(array($item_info['id']));


      $statement = db()->prepare("UPDATE `product_to_store` SET `quantity_in_stock` = `quantity_in_stock`-$item_quantity WHERE `product_id` = ? AND `store_id` = ?");
      $statement->execute(array($item_id, $from_store_id));

      if ($payable_amount > 0 && $due_paid >= $payable_amount) {
        $statement = db()->prepare("INSERT INTO `purchase_payments` SET `type` = ?, `store_id` = ?, `invoice_id` = ?, `reference_no` = ?, `amount` = ?, `created_by` = ?");
        $statement->execute(array('transfer', $store_id, $ref_invoice_id, $ref_no, -$payable_amount, $user_id));
      }

      if ($status == 'complete') {

        addProductAndQuantity($item_id, $item_quantity, $from_store_id, $to_store_id);

        $statement = db()->prepare("UPDATE `purchase_item` SET `is_visible` = ? WHERE `id` = ?");
        $statement->execute(array(1, $item_info['id']));

        // Deposit
        if (($account_id = store('deposit_account_id')) && $payable_amount > 0) {
          $ref_no = unique_transaction_ref_no();
          $statement = db()->prepare("SELECT `source_id` FROM `income_sources` WHERE `for_stock_transfer` = ?");
          $statement->execute(array(1));
          $source = $statement->fetch(PDO::FETCH_ASSOC);
          $source_id = $source['source_id'];
          $title = 'Deposit while stock transferring';
          $details = '';
          $image = 'NULL';
          $deposit_amount = $payable_amount;
          $transaction_type = 'deposit';

          $statement = db()->prepare("INSERT INTO `bank_transaction_info` (store_id, account_id, source_id, ref_no, invoice_id, transaction_type, title, details, image, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $statement->execute(array(store_id(), $account_id, $source_id, $ref_no, $invoice_id, $transaction_type, $title, $details, $image, $user_id, date_time()));
		      $info_id = db()->lastInsertId();
		
          $statement = db()->prepare("INSERT INTO `bank_transaction_price` (store_id, info_id, ref_no, amount) VALUES (?, ?, ?, ?)");
          $statement->execute(array($store_id, $info_id, $ref_no, $deposit_amount));

          $statement = db()->prepare("UPDATE `bank_account_to_store` SET `deposit` = `deposit` + $deposit_amount WHERE `store_id` = ? AND `account_id` = ?");
          $statement->execute(array(store_id(),$account_id));

          $statement = db()->prepare("UPDATE `bank_accounts` SET `total_deposit` = `total_deposit` + $deposit_amount WHERE `id` = ?");
          $statement->execute(array($account_id));
        }
      } else {



        $data = get_the_product($item_id);
        $product_id = $item_id;
        $has_promotional_price = 0;
        $preference = serialize(array());


        //--- product to store ---//

          $statement = db()->prepare("SELECT * FROM `product_to_store` WHERE `store_id` = ? AND `product_id` = ? AND `sup_id` = ?");
          $statement->execute(array($to_store_id, $product_id, $sup_id));
          $p2s = $statement->fetch(PDO::FETCH_ASSOC);
          if (!$p2s) {
            $statement = db()->prepare("INSERT INTO `product_to_store` SET `product_id` = ?, `store_id` = ?, `purchase_price` = ?, `sell_price` = ?, `brand_id` = ?, `box_id` = ?, `taxrate_id` = ?, `tax_method` = ?, `has_promotional_price` = ?, `preference` = ?, `e_date` = ?, `alert_quantity` = ?, `p_date` = ?");
            $statement->execute(array($product_id, $to_store_id, $data['purchase_price'], $data['sell_price'], $data['brand_id'], $data['box_id'], $data['taxrate_id'], $data['tax_method'], $has_promotional_price, $preference, $data['e_date'], $data['alert_quantity'], date('Y-m-d')));
          }

        //--- product to supplier ---//

          if (isset($data['suppliers']) && $product_id) {
            foreach ($data['suppliers'] as $sup_id) {
                $statement = db()->prepare("SELECT * FROM `product_to_supplier` WHERE `store_id` = ? AND `product_id` = ? AND `sup_id` = ?");
                $statement->execute(array($to_store_id, $product_id, $sup_id));
                $supplier = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$supplier) {
                  $statement = db()->prepare("INSERT INTO `product_to_supplier` SET `product_id` = ?, `sup_id` = ?, `store_id` = ?");
                $statement->execute(array($product_id, $sup_id, $to_store_id));
                }

                //--- supplier to store ---//
                $statement = db()->prepare("SELECT * FROM `supplier_to_store` WHERE `store_id` = ? AND `sup_id` = ?");
                $statement->execute(array($to_store_id, $sup_id));
                $supplier = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$supplier) {
                  $statement = db()->prepare("INSERT INTO `supplier_to_store` SET `sup_id` = ?, `store_id` = ?");
                $statement->execute(array($sup_id, $to_store_id));
                }
            }
          }




      }

      $statement = db()->prepare("INSERT INTO `transfers` SET `invoice_id` = ?, `ref_no` = ?, `from_store_id` = ?, `to_store_id` = ?, `note` = ?, `total_item` = ?, `total_quantity` = ?, `created_by` = ?, `status` = ?, `attachment` = ?, `created_at` = ?");
      $statement->execute(array($invoice_id, $ref_no, $from_store_id, $to_store_id, $note, 1, $item_quantity, $user_id, $status, $attachment, date_time()));
      $transfer_id = db()->lastInsertId();

      $statement = db()->prepare("INSERT INTO `transfer_items` SET `transfer_id` = ?, `product_id` = ?, `product_name` = ?, `item_variant_name` = ?, `item_variant_slug` = ?, `quantity` = ?, `store_id` = ?");
      $statement->execute(array($transfer_id, $item_id, $item_info['item_name'], $item_variant_name, $item_variant_slug, $item_quantity, $to_store_id));

      $update_due = db()->prepare("UPDATE `supplier_to_store` SET `balance` = `balance` + $payable_amount WHERE `sup_id` = ? AND `store_id` = ?");
      $update_due->execute(array($info['sup_id'], $to_store_id));
    }

    $Hooks->do_action('After_Stock_Transfer', $transfer_id);
    
    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_transfer_success'), 'id' => $transfer_id));
    exit();

  } catch (Exception $e) {
    
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Update transfer
if ($request->server['REQUEST_METHOD'] == 'POST' AND isset($request->post['action_type']) && $request->post['action_type'] == 'UPDATE')
{
  try {

    // Check update permission
    if (user_group_id() != 1 && !has_permission('access', 'update_transfer')) {
      throw new Exception(trans('error_update_permission'));
    }

    // Validate id
    if (!validateInteger($request->post['id'])) {
      throw new Exception(trans('error_id'));
    }

    // Validate id
    if (!validateString($request->post['status'])) {
      throw new Exception(trans('error_status'));
    }

    $status = $request->post['status'];

    $id = $request->post['id'];
    $statement = $statement = db()->prepare("SELECT * FROM `transfers` WHERE `id` = ?"); 
    $statement->execute(array($id));
    $transfer = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$transfer) {
      throw new Exception(trans('error_transfer_not_found'));
    }

    $Hooks->do_action('Before_Update_Stock_Transfer', $id);

    $from_store_id = $transfer['from_store_id'];
    $to_store_id = $transfer['to_store_id'];

    $statement = db()->prepare("UPDATE `transfers` SET `status` =? WHERE `id` = ?");
    $statement->execute(array($status, $id));

    if ($status == 'complete') {
      $statement = $statement = db()->prepare("SELECT * FROM `transfer_items` WHERE `transfer_id` = ?"); 
      $statement->execute(array($transfer['id']));
      $transfer_items = $statement->fetchAll(PDO::FETCH_ASSOC);
      if (!empty($transfer_items)) {
        foreach ($transfer_items as $item) {
          $item_id = $item['product_id'];
          $item_quantity = $item['quantity'];
          addProductAndQuantity($item_id, $item_quantity, $from_store_id, $to_store_id);

          // Update visibility
          $statement = db()->prepare("UPDATE `purchase_item` SET `is_visible` = ? WHERE `store_id` = ? AND `invoice_id` = ? AND `item_id` = ?");
          $statement->execute(array(1, $to_store_id, $transfer['invoice_id'], $item_id));

        }
      }

      $statement = $statement = db()->prepare("SELECT `payable_amount` FROM `purchase_price` WHERE `invoice_id` = ?"); 
      $statement->execute(array($transfer['invoice_id']));
      $row = $statement->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $amount = $row['payable_amount'];
        if (($account_id = store('deposit_account_id')) && $amount > 0) {
          $ref_no = unique_transaction_ref_no();
          $statement = db()->prepare("SELECT `source_id` FROM `income_sources` WHERE `for_stock_transfer` = ?");
          $statement->execute(array(1));
          $source = $statement->fetch(PDO::FETCH_ASSOC);
          $source_id = $source['source_id'];
          $title = 'Deposit while stock transferring';
          $details = '';
          $image = 'NULL';
          $deposit_amount = $amount;
          $transaction_type = 'deposit';

          $statement = db()->prepare("INSERT INTO `bank_transaction_info` (store_id, account_id, source_id, ref_no, invoice_id, transaction_type, title, details, image, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $statement->execute(array(store_id(), $account_id, $source_id, $ref_no, $transfer['invoice_id'], $transaction_type, $title, $details, $image, $user_id, date_time()));
		      $info_id = db()->lastInsertId();
		
          $statement = db()->prepare("INSERT INTO `bank_transaction_price` (store_id, info_id, ref_no, amount) VALUES (?, ?, ?, ?)");
          $statement->execute(array($store_id, $info_id, $ref_no, $deposit_amount));

          $statement = db()->prepare("UPDATE `bank_account_to_store` SET `deposit` = `deposit` + $deposit_amount WHERE `store_id` = ? AND `account_id` = ?");
          $statement->execute(array(store_id(),$account_id));

          $statement = db()->prepare("UPDATE `bank_accounts` SET `total_deposit` = `total_deposit` + $deposit_amount WHERE `id` = ?");
          $statement->execute(array($account_id));
        }

      }
    }

    $Hooks->do_action('After_Update_Stock_Transfer', $transfer['id']);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_update_transfer_status_success'), 'id' => $transfer['id']));
    exit();

  } catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
} 

// View transfer details
if (isset($request->get['transfer_id']) && isset($request->get['action_type']) && $request->get['action_type'] == 'VIEW') 
{
    $transfer_id = $request->get['transfer_id'];
    $transfer = $transfer_model->getTransferInfo($transfer_id);
    $transfer_items = $transfer_model->getTransferItems($transfer_id);
    include ROOT.'/_inc/template/transfer_view.php';
    exit();
}

// Transfer edit form
if (isset($request->get['id']) AND isset($request->get['action_type']) && $request->get['action_type'] == 'EDIT') 
{
  $statement = $statement = db()->prepare("SELECT * FROM `transfers` WHERE `id` = ?"); 
  $statement->execute(array($request->get['id']));
  $transfer = $statement->fetch(PDO::FETCH_ASSOC);
  include 'template/transfer_edit_form.php';
  exit();
}


/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_Transfer_List');

$where_query = "(transfers.from_store_id = $store_id OR transfers.to_store_id = $store_id)";

// Filtering
$from = from();
$to = to();
$from = $from ? $from : date('Y-m-d');
$to = $to ? $to : date('Y-m-d');
if (($from && ($to == false)) || ($from == $to)) {
  $day = date('d', strtotime($from));
  $month = date('m', strtotime($from));
  $year = date('Y', strtotime($from));
  $where_query .= " AND DAY(`transfers`.`created_at`) = '{$day}'";
  $where_query .= " AND MONTH(`transfers`.`created_at`) = '{$month}'";
  $where_query .= " AND YEAR(`transfers`.`created_at`) = '{$year}'";
} else {
  $from = date('Y-m-d H:i:s', strtotime($from.' '. '00:00:00')); 
  $to = date('Y-m-d H:i:s', strtotime($to.' '. '23:59:59'));
  $where_query .= " AND transfers.created_at >= '{$from}' AND transfers.created_at <= '{$to}'";
}
if (isset($request->get['type']) && $request->get['type'] == 'receive') {
  $where_query .= " AND transfers.to_store_id = $store_id";
} else {
  $where_query .= " AND transfers.from_store_id = $store_id";
}

// DB table to use
$table = "(SELECT transfers.* FROM transfers WHERE $where_query) as transfers";
 
// Table's primary key
$primaryKey = 'id';

// indexes
$columns = array(
    array(
        'db' => 'id',
        'dt' => 'DT_RowId',
        'formatter' => function( $d, $row ) {
            return 'row_'.$d;
        }
    ),
    array( 'db' => 'id', 'dt' => 'id' ),
    array( 'db' => 'ref_no', 'dt' => 'ref_no' ),
    array( 
      'db' => 'created_at',   
      'dt' => 'created_at' ,
      'formatter' => function($d, $row) {
          return $row['created_at'];
      }
    ),
    array( 
      'db' => 'from_store_id',   
      'dt' => 'from_store' ,
      'formatter' => function($d, $row) {
          return store_id() == $row['from_store_id'] ? store_field('name', $row['from_store_id']).' (This Store)' : store_field('name', $row['from_store_id']);
      }
    ),
    array( 
      'db' => 'to_store_id',   
      'dt' => 'to_store' ,
      'formatter' => function($d, $row) {
          return store_id() == $row['to_store_id'] ? store_field('name', $row['to_store_id']).' (This Store)' : store_field('name', $row['to_store_id']);
      }
    ),
    array( 
      'db' => 'total_item',   
      'dt' => 'total_item' ,
      'formatter' => function($d, $row) {
          return $row['total_item'];
      }
    ),
    array( 
      'db' => 'total_quantity',   
      'dt' => 'total_quantity' ,
      'formatter' => function($d, $row) {
          return $row['total_quantity'];
      }
    ),
    array( 
      'db' => 'status',   
      'dt' => 'btn_view' ,
      'formatter' => function($d, $row) {
        return '<button class="btn btn-sm btn-block btn-success view-details" title="'.trans('button_view').'"><span class="fa fa-eye"></span></button>';
      }
    ),
    array( 
      'db' => 'status',   
      'dt' => 'btn_edit' ,
      'formatter' => function($d, $row) {
        if ($row['status'] == 'pending') {
          return '<button class="btn btn-sm btn-block btn-danger" id="transfer-edit" title="'.trans('button_transfer_edit').'">'.ucfirst($row['status']).' <i class="fa fa-fw fa-edit"></i></button>';
        } elseif ($row['status'] == 'sent') {
          return '<button class="btn btn-sm btn-block btn-warning" id="transfer-edit" title="'.trans('button_transfer_edit').'">'.ucfirst($row['status']).' <i class="fa fa-fw fa-edit"></i></button>';
        } else {
          return '<button class="btn btn-sm btn-block btn-success" title="'.trans('button_transfer_edit').'" disabled>'.ucfirst($row['status']).' <i class="fa fa-fw fa-edit"></i></button>';
        }
      }
    ),
);

echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('Aftere_Showing_Transfer_List');

/**
 *===================
 * END DATATABLE
 *===================
 */
 