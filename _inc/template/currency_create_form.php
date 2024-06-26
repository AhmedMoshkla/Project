<form id="create-currency-form" class="form-horizontal" action="currency.php" method="post" enctype="multipart/form-data">
  <input type="hidden" id="action_type" name="action_type" value="CREATE">
  <div class="box-body">
    <div class="form-group">
      <label for="title" class="col-sm-3 control-label">
        <?php echo trans('label_currency_name'); ?><i class="required">*</i>
      </label>
      <div class="col-sm-7">
        <input type="text" class="form-control" id="title" name="title" value="" ng-model="currencyName" required>
      </div>
    </div>

    <div class="form-group">
        <label for="code" class="col-sm-3 control-label">
          <?php echo trans('label_code'); ?><i class="required">*</i>
        </label>
        <div class="col-sm-7">
          <input type="text" class="form-control" id="code" value="{{ currencyName | strReplace:' ':'_' | lowercase }}" name="code" required>
        </div>
    </div>

    <div class="form-group">
        <label for="value" class="col-sm-3 control-label">
          <?php echo trans('label_value'); ?><i class="required">*</i>
          <span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_currency_value'); ?>"></span>
        </label>
        <div class="col-sm-7">
          <input type="text" class="form-control" id="value" name="value" onclick="this.select();" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" onKeyUp="if(this.value<0){this.value='1';}" required>
        </div>
    </div>

    <div class="form-group">
      <label for="symbol_left" class="col-sm-3 control-label">
          <?php echo trans('label_symbol_left'); ?>
          <span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_symbol_left'); ?>"></span>
      </label>
      <div class="col-sm-7">
        <input type="text" class="form-control" id="symbol_left" value="<?php echo isset($request->post['symbol_left']) ? $request->post['symbol_left'] : null; ?>" name="symbol_left">
      </div>
    </div>

    <div class="form-group">
      <label for="symbol_right" class="col-sm-3 control-label">
        <?php echo trans('label_symbol_right'); ?>
        <span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_symbol_right'); ?>"></span>
      </label>
      <div class="col-sm-7">
        <input type="text" class="form-control" id="symbol_right" value="<?php echo isset($request->post['symbol_right']) ? $request->post['symbol_right'] : null; ?>" name="symbol_right">
      </div>
    </div>

    <div class="form-group">
      <label for="decimal_place" class="col-sm-3 control-label">
        <?php echo trans('label_decimal_place'); ?><i class="required">*</i>
        <span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_decimal_place'); ?>"></span>
      </label>
      <div class="col-sm-7">
        <input type="number" class="form-control" id="decimal_place" value="<?php echo isset($request->post['decimal_place']) ? $request->post['decimal_place'] : null; ?>" name="decimal_place" required>
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-3 control-label">
        <?php echo trans('label_store'); ?><i class="required">*</i>
      </label>
      <div class="col-sm-7 store-selector">
        <div class="checkbox selector">
          <label>
            <input type="checkbox" onclick="$('input[name*=\'currency_store\']').prop('checked', this.checked);"> Select / Deselect
          </label>
        </div>
        <div class="filter-searchbox">
          <input ng-model="search_store" class="form-control" type="text" id="search_store" placeholder="<?php echo trans('search'); ?>">
        </div>
        <div class="well well-sm store-well">
          <div filter-list="search_store">
            <?php foreach(get_stores() as $the_store) : ?>                    
              <div class="checkbox">
                <label>                         
                  <input type="checkbox" name="currency_store[]" value="<?php echo $the_store['store_id']; ?>" <?php echo $the_store['store_id'] == store_id() ? 'checked' : null; ?>>
                  <?php echo $the_store['name']; ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label for="status" class="col-sm-3 control-label">
        <?php echo trans('label_status'); ?><i class="required">*</i>
      </label>
      <div class="col-sm-7">
        <select id="status" class="form-control" name="status" >
          <option <?php echo isset($request->post['status']) && $request->post['status'] == '1' ? 'selected' : null; ?> value="1">
            <?php echo trans('text_active'); ?>
          </option>
          <option <?php echo isset($request->post['status']) && $request->post['status'] == '0' ? 'selected' : null; ?> value="0">
            <?php echo trans('text_in_active'); ?>
          </option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="sort_order" class="col-sm-3 control-label">
        <?php echo sprintf(trans('label_sort_order'), null); ?><i class="required">*</i>
      </label>
      <div class="col-sm-7">
        <input type="number" class="form-control" id="sort_order" value="<?php echo isset($request->post['sort_order']) ? $request->post['sort_order'] : 0; ?>" name="sort_order" required>
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-3 control-label"></label>
      <div class="col-sm-7">
        <button class="btn btn-info" id="create-currency-submit" type="submit" name="create-currency-submit" data-form="#create-currency-form" data-loading-text="Saving...">
          <span class="fa fa-fw fa-save"></span>
          <?php echo trans('button_save'); ?>
        </button>  
        <button type="reset" class="btn btn-danger" id="reset" name="reset">
          <span class="fa fa-fw fa-circle"></span> 
          <?php echo trans('button_reset'); ?>
        </button>
      </div>
    </div>
    
  </div>
</form>