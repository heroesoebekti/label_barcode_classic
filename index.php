<?php
/**
 * @Created by          : Heru Subekti (heroe.soebekti@gmail.com)
 * @Date                : 26/11/20
 * @File name           : index.php
 */

// key to authenticate
defined('INDEX_AUTH') OR die('Direct access not allowed!');

$php_self = $_SERVER['PHP_SELF'].'?'.http_build_query($_GET);

// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');

if (!$can_read) {
  die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$max_print = 10;
$plugin_name = 'label_barcode_classic';

/* SAVE SETTINGS */
if(isset($_POST['saveData'])){
      global $dbs;
      $sql_op = new simbio_dbop($dbs);        
      foreach ($_POST['_data'] as $key => $val) {
        $settings[$key] = trim(str_replace(array('\n', '<p>','</p>','\r','\t', '\\'), '', $val));;
      }
      $data['setting_value'] = $dbs->escape_string(serialize($settings));
      $query = $dbs->query("SELECT setting_value FROM setting WHERE setting_name = '{$plugin_name}'");
      if ($query->num_rows > 0) {
        // update
        $update = $sql_op->update('setting', $data, "setting_name='{$plugin_name}'");
        if (!$update) {
          return $dbs->error;
        }else{
          utility::jsAlert('Settings saved!');
        }
      } else {
        // insert
        $data['setting_name'] = $plugin_name;
        $insert = $sql_op->insert('setting', $data);
        if (!$insert) {
          return $dbs->error;
        }else{
          utility::jsAlert('Settings Saved!');
        }
      }  
    unset($_POST); 
    die();
}

/* RECORD OPERATION */
if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
  if (!$can_read) {
    die();
  }
  if (!is_array($_POST['itemID'])) {
    // make an array
    $_POST['itemID'] = array((integer)$_POST['itemID']);
  }
  // loop array
  if (isset($_SESSION['barcodes'])) {
    $print_count = count($_SESSION['barcodes']);
  } else {
    $print_count = 0;
  }
  // barcode size
  $size = 2;
  // create AJAX request
  echo '<script type="text/javascript" src="'.JWB.'jquery.js"></script>';
  echo '<script type="text/javascript">';
  // loop array
  foreach ($_POST['itemID'] as $itemID) {
    if ($print_count == $max_print) {
      $limit_reach = true;
      break;
    }
    if (isset($_SESSION['barcodes'][$itemID])) {
      continue;
    }
    if (!empty($itemID)) {
      $barcode_text = trim($itemID);
      /* replace space */
      $barcode_text = str_replace(array(' ', '/', '\/'), '_', $barcode_text);
      /* replace invalid characters */
      $barcode_text = str_replace(array(':', ',', '*', '@'), '', $barcode_text);
      // send ajax request
      echo 'jQuery.ajax({ url: \''.SWB.'lib/phpbarcode/barcode.php?code='.$itemID.'&encoding='.$sysconf['barcode_encoding'].'&scale='.$size.'&mode=png\', type: \'GET\', error: function() { alert(\'Error creating barcode!\'); } });'."\n";
      // add to sessions
      $_SESSION['barcodes'][$itemID] = $itemID;
      $print_count++;
    }
  }
  echo 'top.$(\'#queueCount\').html(\''.$print_count.'\')';
  echo '</script>';
  // update print queue count object
  sleep(2);
  if (isset($limit_reach)) {
    $msg = str_replace('{max_print}', $max_print, __('Selected items NOT ADDED to print queue. Only {max_print} can be printed at once'));
      utility::jsToastr('Label Barcode Lawasan', $msg,'warning');
  } else {
      utility::jsToastr('Label Barcode Lawasan', __('Selected items added to print queue'),'success');
  }
  exit();
}

// clean print queue
if (isset($_GET['action']) AND $_GET['action'] == 'clear') {
  // update print queue count object
  echo '<script type="text/javascript">top.$(\'#queueCount\').html(\'0\');</script>';
  utility::jsToastr('Label Barcode Lawasan', __('Print queue cleared!'));
  unset($_SESSION['barcodes']);
  exit();
}

//restore default settings
if (isset($_GET['action']) AND $_GET['action'] == 'settings_reset') {
      global $dbs;
      $sql_op = new simbio_dbop($dbs);        
      $delete = $sql_op->delete('setting',  "setting_name='{$plugin_name}'");  
      utility::jsAlert('Settings restore to default!');   
      echo '<script type="text/javascript">location.reload();</script>';
      die();    
}

//load settings
if (isset($_GET['action']) AND $_GET['action'] == 'settings') {
      utility::loadSettings($dbs);
      include_once __DIR__.'/tinfo.inc.php';  

      ob_start();
      $form = new simbio_form_table_AJAX('mainForm', $php_self, 'post');
      $form->submit_button_attr = 'name="updateData" value="' . __('Save Settings') . '" class="s-btn btn btn-default"';
      // form table attributes
      $form->table_attr = 'id="dataList" class="s-table table"';
      $form->table_header_attr = ' class="alterCell font-weight-bold"';
      $form->table_content_attr = 'class="alterCell2"';
      $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="btn btn-default"';
      foreach ($sysconf['plugin']['option'][$plugin_name] as $fid => $cfield) {
        // custom field properties
        $cf_dbfield = '_data['.$cfield['dbfield'].']';
        $cf_label = $cfield['label'];
        $cf_default = $cfield['default'];
        $cf_class = $cfield['class']??'';
        $cf_data = (isset($cfield['data']) && $cfield['data']) ? $cfield['data'] : array();
        $cf_width = isset($cfield['width']) ? $cfield['width'] : '50';

        if (in_array($cfield['type'], array('text', 'longtext', 'numeric'))) {
          $cf_max = isset($cfield['max']) ? $cfield['max'] : '200';
          $form->addTextField(($cfield['type'] == 'longtext') ? 'textarea' : 'text', $cf_dbfield, $cf_label, isset($sysconf['plugin'][$cf_dbfield]) ? $sysconf['plugin'][$cf_dbfield] : $cf_default, ' class="form-control '.$cf_class.'" style="width: ' . $cf_width . '%;" maxlength="' . $cf_max . '"');
        } else if ($cfield['type'] == 'dropdown') {
          $form->addSelectList($cf_dbfield, $cf_label, $cf_data, isset($sysconf['plugin'][$cf_dbfield]) ? $sysconf['plugin'][$cf_dbfield] : $cf_default, 'class="form-control"');
        } else if ($cfield['type'] == 'checklist') {
          $form->addCheckBox($cf_dbfield, $cf_label, $cf_data, isset($sysconf['plugin'][$cf_dbfield]) ? $sysconf['plugin'][$cf_dbfield] : $cf_default, ' class="form-control"');
        } else if ($cfield['type'] == 'choice') {
          $form->addRadio($cf_dbfield, $cf_label, $cf_data, isset($sysconf['plugin'][$cf_dbfield]) ? $sysconf['plugin'][$cf_dbfield] : $cf_default, ' class="form-control"');
        } else if ($cfield['type'] == 'date') {
          $form->addDateField($cf_dbfield, $cf_label, isset($sysconf['plugin'][$cf_dbfield]) ? $sysconf['plugin'][$cf_dbfield] : $cf_default, ' class="form-control"');
        } else if ($cfield['type'] == 'anything') {
          $form->addAnything($cf_label,$cf_default);
        }
      }
      echo $form->printOut();
      $content = ob_get_clean();
      $css = '<link rel="stylesheet" href="'.SWB.'css/bootstrap-colorpicker.min.css"/>';
      $js  = '<script type="text/javascript" src="'.JWB.'bootstrap-colorpicker.min.js"></script>';
      $js .= '<script type="text/javascript" src="'.JWB.'/ckeditor/ckeditor.js"></script>';
      $js .= '<script type="text/javascript">$(function () {  $(\'.colorpicker\').colorpicker() })</script>';
      $js .= "<script type=\"text/javascript\">CKEDITOR.config.enterMode = CKEDITOR.ENTER_BR;CKEDITOR.config.toolbar = [['Bold','Italic','Underline']] ;</script>";     
      require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
  exit();
}

// barcode pdf download
if (isset($_GET['action']) AND $_GET['action'] == 'print') {
  // check if label session array is available
  if (!isset($_SESSION['barcodes']) || count($_SESSION['barcodes']) < 1) {
    utility::jsToastr('Label Barcode Lawasan', __('There is no data to print!'), 'warning');
    die();
  }

  // concat all ID together
  $item_ids = '';
  foreach ($_SESSION['barcodes'] as $id) {
    $item_ids .= '\''.$id.'\',';
  }
  // strip the last comma
  $item_ids = substr_replace($item_ids, '', -1);
  // send query to database
  $item_q = $dbs->query('SELECT b.title, i.item_code, b.call_number, i.call_number FROM item AS i
    LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
    WHERE i.item_code IN('.$item_ids.')');
  $item_data_array = array();
  while ($item_d = $item_q->fetch_row()) {
    if ($item_d[0]) {
      $item_data_array[] = $item_d;
    }
  }

  utility::loadSettings($dbs);
  if(!isset($sysconf[$plugin_name])){
    include_once __DIR__.'/tinfo.inc.php';  
    foreach ($sysconf['plugin']['option'][$plugin_name] as $fid => $cfield) {
        $sysconf[$plugin_name][$cfield['dbfield']] = $cfield['default'];
    }    
  }

  // get header color
  foreach ($sysconf[$plugin_name] as $key => $value) {
    if(preg_match('/class_/', $key)){
      $header_color[str_replace('class_', '',$key)] = $value;
    }
  }

  // chunk barcode array
  $chunked_barcode_arrays = array_chunk($item_data_array, $sysconf[$plugin_name]['barcode_items_per_row']);
  $lebar        = $sysconf[$plugin_name]['barcode_box_width'];
  $tinggi       = $sysconf[$plugin_name]['barcode_box_height'];
  $barcode      = $sysconf[$plugin_name]['barcode_col_size'];
  $border       = $sysconf[$plugin_name]['barcode_border_size'];
   
  // create html ouput
  $html_str  = '<!DOCTYPE html>'."\n";
  $html_str .= '<html><head><title>Label Barcode Label Print Result</title>'."\n";
  $html_str .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
  $html_str .= '<meta http-equiv="Pragma" content="no-cache" /><meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0" /><meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />';
  $html_str .= '<script type="text/javascript" src="../plugins/label_barcode_classic/src/qrcode.min.js"></script>';
  $html_str .= '<style type="text/css">'."\n";
  $html_str .= 'body { padding: 0; margin: 1mm; font-family: '.$sysconf[$plugin_name]['barcode_fonts'].'; font-size: '.$sysconf[$plugin_name]['barcode_font_size'].'pt; background: #fff; }'."\n";
  $html_str .= '.box{  width: '.$lebar.'mm;  height: '.$tinggi.'mm;  border: solid '.$border.'px '.$sysconf[$plugin_name]['barcode_border_color'].';}'."\n";
  $html_str .= '.header{  font-size:'.$sysconf[$plugin_name]['header_font_size'].'pt; border-bottom: solid '.$border.'px '.$sysconf[$plugin_name]['barcode_border_color'].';  text-align: center;  height: auto;  position: relative;  padding: 10px 0px 10px 0px;  top: -'.$tinggi.'mm;}'."\n";
  $html_str .= '.barcode{  width: '.$barcode.'mm;  height: '.$tinggi.'mm;  text-align: center;}'."\n";
  $html_str .= '.callNum{ font-size:'.$sysconf[$plugin_name]['callnumber_font_size'].'pt; text-align: center;  position: relative;  top: -'.$tinggi.'mm;  padding-top: 10px;}'."\n";
  $html_str .= '.cc{  height: '.$barcode.'mm;  width:  '.$tinggi.'mm;  -ms-transform: rotate(90deg);  -webkit-transform: rotate(90deg);transform: rotate(90deg); position: absolute; margin-top: '.str_replace(',', '.', (($tinggi-$barcode)/2)).'mm;margin-left: -'.str_replace(',', '.', (($tinggi-$barcode)/2)).'mm;}'."\n";
  $html_str .= '.cw{  width:  '.$tinggi.'mm;  height:  '.$barcode.'mm;  -ms-transform: rotate(-90deg);  -webkit-transform: rotate(-90deg); transform: rotate(-90deg); position: absolute; margin-top: '.str_replace(',', '.', (($tinggi-$barcode)/2)).'mm; margin-left: -'.str_replace(',', '.', (($tinggi-$barcode)/2)).'mm;}'."\n";
  $html_str .= '.right > .barcode{  margin-left: '.($lebar-$barcode).'mm;  border-left: solid '.$border.'px '.$sysconf[$plugin_name]['barcode_border_color'].';}'."\n";
  $html_str .= '.right > .header{  margin-left: 0px;  width: '.($lebar-$barcode).'mm;}'."\n";

  $margin_r = $sysconf[$plugin_name]['callnumber_align']=='left'?$sysconf[$plugin_name]['callnumber_padding_size']:($sysconf[$plugin_name]['callnumber_align']=='right'?('-'.$sysconf[$plugin_name]['callnumber_padding_size']):'0');

  $html_str .= '.right > .callNum{  text-align:'.$sysconf[$plugin_name]['callnumber_align'].';margin-left: '.$margin_r.'mm;  width:  '.($lebar-$barcode).'mm;}'."\n";
  $html_str .= '.left > .barcode{  margin-left: 0px;  border-right: solid '.$border.'px '.$sysconf[$plugin_name]['barcode_border_color'].';}'."\n";
  $html_str .= '.left > .header{  margin-left: '.$barcode.'mm;  width: '.($lebar-$barcode).'mm;}'."\n";

  $margin_l = $sysconf[$plugin_name]['callnumber_align']=='left'?($barcode+$sysconf[$plugin_name]['callnumber_padding_size']):($sysconf[$plugin_name]['callnumber_align']=='right'?($barcode-$sysconf[$plugin_name]['callnumber_padding_size']):$barcode);

  $html_str .= '.left > .callNum{  text-align:'.$sysconf[$plugin_name]['callnumber_align'].'; margin-left: '.$margin_l.'mm;  width: '.($lebar-$barcode).'mm;}'."\n";
  $barcode_r = $sysconf[$plugin_name]['barcode_rotate']==''?'margin-top:10px; ':'';
  $html_str .= '.img_code{ width: '.$sysconf[$plugin_name]['barcode_scale'].'%; height:75%; padding:-5px; border:0px; '.$barcode_r.' }'."\n";
  $html_str .= '.title{  font-size:8pt;}'."\n";
  $html_str .= '</style>'."\n";
  $html_str .= '</head>'."\n";
  $html_str .= '<body>'."\n";
  $html_str .= '<a href="#" onclick="window.print()">Print Again</a>'."\n";
  $html_str .= '<table cellspacing="'.$sysconf[$plugin_name]['barcode_items_margin'].'" cellpadding="2">'."\n";

  // loop the chunked arrays to row
  foreach ($chunked_barcode_arrays as $barcode_rows) {
    $html_str .= '<tr>'."\n";
    foreach ($barcode_rows as $barcode) {
       $html_str .= '<td><div class="box '.$sysconf[$plugin_name]['barcode_position'].'">'."\n";
       $html_str .= '<div class="barcode">'."\n";
       $html_str .= '<div class="'.($sysconf[$plugin_name]['barcode_type']=='bar'?$sysconf[$plugin_name]['barcode_rotate']:'').'">'."\n";
       $title_cut = strlen($barcode[0])>$sysconf[$plugin_name]['barcode_cut_title']?substr($barcode[0], 0,$sysconf[$plugin_name]['barcode_cut_title']).' ...':$barcode[0];
       $html_str .= '<div class="title" style="'.($sysconf[$plugin_name]['barcode_rotate']==''||$sysconf[$plugin_name]['barcode_type']!='bar'?'padding-top:10px;padding-bottom:5px;':'').'">'.$title_cut.'</div>'."\n";
       if($sysconf[$plugin_name]['barcode_type']=='bar'){
          $html_str .= '<img class="img_code" src="'.SWB.IMG.'/barcodes/'.str_replace(array(' '), '_', $barcode[1]).'.png"/>'."\n";
       }else{       
       $qr = $sysconf[$plugin_name]['barcode_box_height']<$sysconf[$plugin_name]['barcode_col_size']?
       array('size'=>$sysconf[$plugin_name]['barcode_box_height']*2.5, 'width'=>30):
       array('size'=>$sysconf[$plugin_name]['barcode_col_size']*3, 'width'=>80);
       $html_str .= '<div class="img_code" id="qrcode_'.$barcode[1].'" style="display: block; margin-left: auto;margin-right: auto;width: '.$qr['width'].'%;"></div><div class="title">'.$barcode[1].'</div><script type="text/javascript">new QRCode("qrcode_'.$barcode[1].'", { text: "'.$barcode[1].'", width: '.$qr['size'].', height: '.$qr['size'].', correctLevel : QRCode.CorrectLevel.L });</script>';
       }
       $html_str .= '</div>'."\n";
       $html_str .= '</div>'."\n";

       $callnumb = $barcode[3]==''?$barcode[2]:$barcode[3];
       if($sysconf[$plugin_name]['barcode_include_header_text']=='1'){
            //get header color
            $clr = $sysconf[$plugin_name]['color_header']=='0'?'none':
            ($header_color[substr($callnumb,0,1)]!=''?$header_color[substr($callnumb,0,1)]:$sysconf[$plugin_name]['class_other']);
            $html_str .=  '<div class="header" style="background-color:'.$clr.';z-index:-2;border-bottom:solid 3px '.$sysconf[$plugin_name]['barcode_border_color'].';">'.($sysconf[$plugin_name]['barcode_header_text']?$sysconf[$plugin_name]['barcode_header_text']:$sysconf['library_name']).'</div>'."\n";
       }
       $margin = $sysconf[$plugin_name]['callnumber_align']=='center'?
       '':($sysconf[$plugin_name]['callnumber_align']=='left'?
       'text-align:left;margin-left:20px':'text-align:right;margin-left:-20px');
       $html_str .=  '<div class="callNum">'."\n";

            $cn = preg_split("/((?<=\w)\s+(?=\D))|((?<=\D)\s+(?=\d))/m",$callnumb);
            foreach ($cn as $key=>$val) {
              if($key==1){ 
                $html_str .=  '<b>'.strtoupper($val).'</b><br/>'; }
              else{ 
                $html_str .=  $val.'<br/>';  } 
            }
       $html_str .= '</div>'."\n";
       $html_str .= '</div>'."\n";
       $html_str .= '</td>'."\n";
    }
    $html_str .= '</tr>'."\n";
  }
  $html_str .= '</table>'."\n";
  $html_str .= '<script type="text/javascript">self.print();</script>'."\n";
  $html_str .= '</body></html>'."\n";

  // unset the session
  unset($_SESSION['barcodes']);

  // write to file
  $print_file_name = 'label_barcode_lawasan_print_result_'.strtolower(str_replace(' ', '_', $_SESSION['uname'])).'.html';
  $file_write = @file_put_contents(UPLOAD.$print_file_name, $html_str);
  if ($file_write) {
    // update print queue count object
    echo '<script type="text/javascript">parent.$(\'#queueCount\').html(\'0\');</script>';
    // open result in window
    echo '<script type="text/javascript">top.$.colorbox({href: "'.SWB.FLS.'/'.$print_file_name.'", iframe: true, width: 800, height: 500, title: "'.__('Label Barcodes Lawasan').'"})</script>';
  } else { utility::jsAlert('ERROR! Label barcodes failed to generate, possibly because '.SB.FLS.' directory is not writable'); }
  exit();
}

?>
<fieldset class="menuBox">
<div class="menuBoxInner printIcon">
  <div class="per_title">
      <h2><?= __('Label Barcode Classic'); ?></h2>
  </div>
  <div class="sub_section">
      <div class="btn-group">
      <a target="blindSubmit" href="<?= $php_self; ?>&action=clear" class="notAJAX btn btn-default"><i class="glyphicon glyphicon-trash"></i>&nbsp;<?= __('Clear Print Queue'); ?></a>
      <a target="blindSubmit" href="<?= $php_self; ?>&action=print" class="notAJAX btn btn-default"><i class="glyphicon glyphicon-print"></i>&nbsp;<?= __('Print Barcodes for Selected Data');?></a>
        <a href="<?= $php_self; ?>&action=settings" class="notAJAX btn btn-info openPopUp" title="<?= __('Change label barcode settings'); ?>"><i class="fa fa-gear"></i></a>
      </div>
    <form name="search" action="<?= $php_self; ?>" id="search" method="get" class="form-inline"><?= __('Search'); ?> :
    <input type="text" name="keywords" size="30" class="form-control col-md-5"/>
    <input type="submit" id="doSearch" value="<?= __('Search'); ?>" class="btn btn-default" />
    </form>
  </div>
  <div class="infoBox">
  <?php
  echo __('Maximum').' <font style="color: #f00">'.$max_print.'</font> '.__('records can be printed at once. Currently there is').' ';
  if (isset($_SESSION['barcodes'])) {
    echo '<font id="queueCount" style="color: #f00">'.count($_SESSION['barcodes']).'</font>';
  } else { echo '<font id="queueCount" style="color: #f00">0</font>'; }
  echo ' '.__('in queue waiting to be printed.');
  ?>
  </div>
</div>
</fieldset>
<?php
/* search form end */

// create datagrid
$datagrid = new simbio_datagrid();
/* ITEM LIST */
require SIMBIO.'simbio_UTILS/simbio_tokenizecql.inc.php';
require LIB.'biblio_list_model.inc.php';
// index choice
if ($sysconf['index']['type'] == 'index' || ($sysconf['index']['type'] == 'sphinx' && file_exists(LIB.'sphinx/sphinxapi.php'))) {
  if ($sysconf['index']['type'] == 'sphinx') {
    require LIB.'sphinx/sphinxapi.php';
    require LIB.'biblio_list_sphinx.inc.php';
  } else {
    require LIB.'biblio_list_index.inc.php';
  }
  // table spec
  $table_spec = 'item LEFT JOIN search_biblio AS `index` ON item.biblio_id=`index`.biblio_id';
  $datagrid->setSQLColumn('item.item_code',
    'item.item_code AS \''.__('Item Code').'\'',
    'IF(item.call_number!=\'NULL\',item.call_number,index.call_number) AS \''.__('Call Number').'\'',
    'index.title AS \''.__('Title').'\'');
} else {
  require LIB.'biblio_list.inc.php';
  // table spec
  $table_spec = 'item LEFT JOIN biblio ON item.biblio_id=biblio.biblio_id';
  $datagrid->setSQLColumn('item.item_code',
    'item.item_code AS \''.__('Item Code').'\'',
    'IF(item.call_number!=\'NULL\',item.call_number,biblio.call_number) AS \''.__('Call Number').'\'',
    'biblio.title AS \''.__('Title').'\'');
}
$datagrid->setSQLorder('item.last_update DESC');
// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
  $keywords = $dbs->escape_string(trim($_GET['keywords']));
  $searchable_fields = array('title', 'author', 'subject', 'itemcode');
  $search_str = '';
  // if no qualifier in fields
  if (!preg_match('@[a-z]+\s*=\s*@i', $keywords)) {
    foreach ($searchable_fields as $search_field) {
      $search_str .= $search_field.'='.$keywords.' OR ';
    }
  } else {
    $search_str = $keywords;
  }
  $biblio_list = new biblio_list($dbs, 20);
  $criteria = $biblio_list->setSQLcriteria($search_str);
}
if (isset($criteria)) {
  $datagrid->setSQLcriteria('('.$criteria['sql_criteria'].')');
}
// set table and table header attributes
$datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// edit and checkbox property
$datagrid->edit_property = false;
$datagrid->chbox_property = array('itemID', __('Add'));
$datagrid->chbox_action_button = __('Add To Print Queue');
$datagrid->chbox_confirm_msg = __('Add to print queue?');
$datagrid->column_width = array('10%', '20%','70%');
// set checkbox action URL
$datagrid->chbox_form_URL = $php_self;
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, $can_read);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
  $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords'));
  echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>';
}
echo $datagrid_result;
/* main content end */
