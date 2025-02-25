<?php 
ob_start();
session_start();
include ("../_init.php");

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url() . '/index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_shipping_charge_report')) {
  redirect(root_url() . '/'.ADMINDIRNAME.'/dashboard.php');
}

// Set Document Title
$document->setTitle(trans('title_shipping_charge_in_purchase_report'));

// Add Script
$document->addScript('../assets/js/uikit-icons.min.js'); //midpos3 stop here
$document->addScript('../assets/js/uikit.min.js');
$document->addScript('../assets/midpos/angular/controllers/ReportPurchaseShippingChargeController.js');

// ADD BODY CLASS
$document->setBodyClass('sidebar-collapse');

// Include Header and Footer
include("header.php"); 
include ("left_sidebar.php") ;
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper" ng-controller="ReportPurchaseShippingChargeController">
<?php include ("rebo.php"); ?>
  <!-- Content Header Start -->
  <section class="content-header">
    <?php include ("../_inc/template/partials/apply_filter.php"); ?>
    <h1>
      <?php echo trans('text_shipping_charge_in_purchase_title'); ?>
      <small>
        <?php echo store('name'); ?>
      </small>
    </h1>
    <ol class="breadcrumb">
      <li>
        <a href="dashboard.php">
          <i class="fa fa-dashboard"></i>
           <?php echo trans('text_dashboard'); ?>
         </a>
       </li>
      <li class="active">
        <?php echo trans('text_shipping_charge_in_purchase_title'); ?>
      </li>
    </ol>
  </section>
  <!-- Content Header End -->

  <!-- Content Start -->
  <section class="content">

    <?php if(DEMO) : ?>
    <div class="box">
      <div class="box-body">
        <div class="alert alert-info mb-0">
          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
      <div class="col-xs-12">
        <div class="box box-success">
          <div class="box-header">
            <h3 class="box-title">
              <?php echo trans('text_shipping_charge_in_purchase_title'); ?>
            </h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">  
              <?php
              $print_columns = '0,1,2';
              $hide_colums = "";?>
              <table id="report-report-list" class="table table-bordered table-striped table-hover" data-hide-colums="<?php echo $hide_colums; ?>" data-print-columns="<?php echo $print_columns;?>">
                <thead>
                  <tr class="bg-gray">
                    <th class="w-30">
                      <?php echo trans('label_created_at'); ?>
                    </th>
                    <th class="w-30">
                      <?php echo trans('label_invoice_id'); ?>
                    </th>
                    <th class="w-40">
                      <?php echo trans('label_shipping_charge'); ?>
                    </th>
                  </tr>
                </thead>
                <tfoot>
                  <tr class="bg-gray">
                    <th class="w-34">
                      <?php echo trans('label_created_at'); ?>
                    </th>
                    <th class="w-30">
                      <?php echo trans('label_invoice_id'); ?>
                    </th>
                    <th class="w-40">
                      <?php echo trans('label_shipping_charge'); ?>
                    </th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Content End -->

</div>
<!-- Content Wrapper End -->

<?php include ("footer.php"); ?>