<div style="margin:20px 0 0 10px;">
<?php
// Processing an option submit form. Set global vars from the form values.
if( isset($_POST['updateoptions'])  && $_POST['updateoptions']==1) {
if ( ! (is_numeric(get_option('googlePluscomment-width')) && get_option('googlePluscomment-width')> 0)) {
update_option( 'googlePluscomment-width', 500 );
} 
if( isset($_POST['credit'])  && $_POST['credit']==1) {
update_option( 'googlePluscomment-credit', 1 );
} else {
update_option( 'googlePluscomment-credit', 0 );
}    
if( isset($_POST['dynamicwidth'])  && $_POST['dynamicwidth']==1) {
update_option( 'googlePluscomment-dynamicWidth', 1 );
}else {
update_option( 'googlePluscomment-dynamicWidth', 0 );
}
}
?>
<form method="POST"  action="?page=responsivegooglepluscommentsetup">
<table>
<tr>
<td style="text-align:center;" colspan="2">
<h2 style="">
<span style="color:#fff;font-size: 50px;background: #cc4635;padding:0px 5px;">G+</span> 
<span style="font-size: 25px;">Comment Options</span>
</h2>
</td>
</tr>

<tr>
<td style="text-align:right;">Width:&nbsp;</td>
<td style="padding:10px 5px;">
<input type="text" name="width"  <?php if(get_option('googlePluscomment-dynamicWidth') == 1){ echo "readonly"; } ?> value="<?php echo get_option('googlePluscomment-width')?>" />
</td>
</tr>

<tr>
<td style="text-align:right;">
Dynamic Width:&nbsp;
</td>
<td style="padding:10px 5px;">
<input type="checkbox" name="dynamicwidth" <?php if(get_option('googlePluscomment-dynamicWidth') == 1){echo "checked=\"checked\"";} ?> value="1" /> <i> [Fixes Google plus comment bug for responsive sites]</i>
</td>
</tr>

<tr>
<td style="text-align:right;">Show Credit:&nbsp;</td>
<td style="padding:10px 5px;"><input type="checkbox" name="credit" <?php if(get_option('googlePluscomment-credit') == 1){echo "checked=\"checked\"";} ?> value="1" /></td>
</tr>

<tr>
<td style="text-align:right; padding:20px 0 0 0;">
<input type="hidden" name="updateoptions" value="1" />
<input type="submit" name="Submit" value="Submit" />
</td>
<td></td>
</tr>
</table>
</form>
</div>