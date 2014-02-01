<?php
/**
 * Comments Template
 *
 * @file           comments.php
 * @package        Responsive Google+ Comment
 * @author         Bhardwaja <avinash@bhardwaja.com)
 * @copyright      2013 Bhardwaja
 * @license        GPLv3+
 * @license_file   LICENSE
 */
// Exit if accessed directly
if ( !defined('ABSPATH')) exit;

/**
 * Please do not modify this file directly, make a copy in your template directory
 * and name it gplus-comments.php and it will have priority over the default one
 */
?>
<?php if (post_password_required()) { ?>
<p class="nocomments"><?php _e('This post is password protected. Enter the password to view any comments.'); ?></p>
<?php return; } ?>

<div id="gpluscommentCredit" style="height: 20px;font-family: 'lucida grande',tahoma,verdana,arial,sans-serif;font-size:10px;line-height:20px;margin:0;padding:0;"><i><a style="text-decoration:none;" href="http://responsivecodes.com/plugins/responsive-google-comments-widget/">Responsive Google+ comments widget</a> by <a style="text-decoration:none;" href="http://bhardwaja.com">bhardwaja</a></i></div>
<?php if(get_option('googlePluscomment-credit')==0) { ?>
<script type="text/javascript">var credit=document.getElementById("gpluscommentCredit");if(credit){credit.style.display="none";}</script>
<?php } ?>
     
<div id="googlepluscommentsarea" >
<script type="text/javascript">	
  window.___gcfg = {lang: 'en'};(function() {var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;po.src = 'https://apis.google.com/js/plusone.js';var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);})();
</script>
	
<?php if(get_option('googlePluscomment-dynamicWidth')==1) { ?>
    <script type="text/javascript">
        var w=document.getElementById('googlepluscommentsarea').offsetWidth;
            document.write('<g:comments href="<?php echo the_permalink(); ?>" width="'+w+'" height="50" first_party_property="BLOGGER" view_type="FILTERED_POSTMOD"></g:comments>');
    </script>
    <?php } else { ?>
        <g:comments href="<?php echo the_permalink(); ?>" width="<?php echo get_option('googlePluscomment-width'); ?>" first_party_property="BLOGGER" view_type="FILTERED_POSTMOD"></g:comments>
    <?php } ?>
</div>