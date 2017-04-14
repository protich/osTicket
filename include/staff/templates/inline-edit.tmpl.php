<!-- Help Topic Edit Modal -->

<?php
global $cfg;

// var_dump('form name is ');
// var_dump($form->getId());

$form_type = get_class($form);
// var_dump('form type in tmpl is');
// var_dump($form_type);
//
// var_dump('the info is');
// var_dump($info);

$form = $form ?: $form_type::instantiate($info);
$options = array('template' => 'simple', 'form_id' => $form->getId());

?>
<h3 class="drag-handle"><?php echo $info[':title']; ?></h3>
<b><a class="close" href="#"><i class="icon-remove-circle"></i></a></b>
<div class="clear"></div>
<hr/>
<?php
if ($info['error']) {
    echo sprintf('<p id="msg_error">%s</p>', $info['error']);
} elseif ($info['warn']) {
    echo sprintf('<p id="msg_warning">%s</p>', $info['warn']);
} elseif ($info['msg']) {
    echo sprintf('<p id="msg_notice">%s</p>', $info['msg']);
} elseif ($info['notice']) {
   echo sprintf('<p id="msg_info"><i class="icon-info-sign"></i> %s</p>',
           $info['notice']);
}

$action = $info[':action'] ?: ('#');
?>
<div style="display:block; margin:5px;">
<form method="post" name="inline_update" id="inline_update"
    class="mass-action"
    action="<?php echo $action; ?>">
    <table width="100%">
        <?php
        if ($info[':extra']) {
            ?>
        <tbody>
            <tr><td colspan="2"><strong><?php echo $info[':extra'];
            ?></strong></td> </tr>
        </tbody>
        <?php //var_dump($action); ?>
        <?php
        }
       ?>
        <tbody>
            <tr><td colspan=2>
             <?php
              $form->render($options);
             ?>
             <!-- <table class="form_table dynamic-forms" width="940" border="0" cellspacing="0" cellpadding="2">
                     <?php if ($form)
                        $form->render($options, true, false, array('mode'=>'edit','width'=>160,'entry'=>$form));
                     ?>
             </table> -->
            </td> </tr>
        </tbody>
    </table>
    <hr>
    <p class="full-width">
        <span class="buttons pull-left">
            <!-- <input type="reset" value="<?php echo __('Reset'); ?>"> -->
            <input type="button" name="cancel" class="close"
            value="<?php echo __('Cancel'); ?>">
        </span>
        <span class="buttons pull-right">
            <input type="submit" value="<?php
            echo $verb ?: __('Update'); ?>">
        </span>
     </p>
</form>
</div>
<div class="clear"></div>
