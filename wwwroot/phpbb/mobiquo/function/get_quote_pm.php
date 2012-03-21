<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_quote_pm_func($xmlrpc_params)
{
    global $db, $auth, $user;
    
    $user->setup('ucp');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get msg id from parameters
    $msg_id = intval($params[0]);
    if (!$msg_id) trigger_error('NO_MESSAGE');
    if (!$auth->acl_get('u_sendpm')) trigger_error('NO_AUTH_SEND_MESSAGE');
    
    $sql = 'SELECT p.*, u.username as quote_username
            FROM ' . PRIVMSGS_TABLE . ' p, ' . USERS_TABLE . ' u
            WHERE p.author_id = u.user_id
            AND p.msg_id = '.$msg_id;

    $result = $db->sql_query($sql);
    $post = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    $msg_id = (int)$post['msg_id'];
    
    if (!$post) trigger_error('NO_MESSAGE');
    
    if ((!$post['author_id'] || ($post['author_id'] == ANONYMOUS && $action != 'delete')) && $msg_id)
    {
        trigger_error('NO_AUTHOR');
    }

    $message_subject = ((!preg_match('/^Re:/', $post['message_subject'])) ? 'Re: ' : '') . censor_text($post['message_subject']);
    decode_message($post['message_text'], $post['bbcode_uid']);
    $message = '[quote=&quot;' . $post['quote_username'] . '&quot;]' . censor_text(trim($post['message_text'])) . "[/quote]\n";
    
    return new xmlrpcresp(
        new xmlrpcval(array(
                'msg_id'        => new xmlrpcval($msg_id),
                'msg_subject'   => new xmlrpcval(html_entity_decode(strip_tags($message_subject)), 'base64'),
                'text_body'     => new xmlrpcval(html_entity_decode($message), 'base64'),
            ), 'struct'
        )
    );
}
