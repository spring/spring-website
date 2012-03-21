<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function create_message_func($xmlrpc_params)
{
    global $db, $user, $auth, $config, $phpbb_root_path, $phpEx;
    
    $user->setup('ucp');
    
    include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
    include_once($phpbb_root_path . 'includes/ucp/ucp_pm_compose.' . $phpEx);
    
    if (!$user->data['is_registered']) trigger_error('LOGIN_EXPLAIN_UCP');
    if (!$config['allow_privmsg']) trigger_error('Module not accessible');
    
    // Flood check
    $current_time = time();
    $last_post_time = $user->data['user_lastpost_time'];

    if ($last_post_time && ($current_time - $last_post_time) < intval($config['flood_interval']))
    {
        trigger_error('FLOOD_ERROR');
    }
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    if (!is_array($params[0]) || empty($params[0]) || !isset($params[1]) || utf8_clean_string($params[1]) === '' || !isset($params[2]))
    {
        trigger_error('Required paramerter missing');
    }
    else
    {
        $user_name  = $params[0];
        $_REQUEST['subject'] = $params[1];
        $_REQUEST['message'] = $params[2];
        $subject    = utf8_normalize_nfc(request_var('subject', '', true));
        $text_body  = utf8_normalize_nfc(request_var('message', '', true));
    }
    
    $action = 'post';   // default action
    if (isset($params[3]))
    {
        if ($params[3] == 1)
        {
            $action = 'reply';
            $msg_id = intval($params[4]);
        }
        else if ($params[3] == 2)
        {
            $action = 'forword';
            $msg_id = intval($params[4]);
        }
        
        if (!$msg_id) trigger_error('NO_MESSAGE');
    }
    
    if (($action == 'post' || $action == 'reply')  && (!$auth->acl_get('u_sendpm')))
    {
        trigger_error('NO_AUTH_SEND_MESSAGE');
    }
    
    if ($action == 'forward' && (!$config['forward_pm'] || !$auth->acl_get('u_pm_forward')))
    {
        trigger_error('NO_AUTH_FORWARD_MESSAGE');
    }    
    
    // Do NOT use request_var or specialchars here
    $address_list = array('u' => array());
    
    foreach($user_name as $msg_to_name)
    {
        $user_id = get_user_id_by_name(trim($msg_to_name));

        if ($user_id)
        {
            $address_list['u'][$user_id] = 'to';
        }
        else
        {
            trigger_error('PM_NO_USERS');
        }
    }
    
    $sql = '';
    
    // What is all this following SQL for? Well, we need to know
    // some basic information in all cases before we do anything.
    if ($action != 'post')
    {
        $sql = 'SELECT t.folder_id, p.*, u.username as quote_username
                FROM ' . PRIVMSGS_TO_TABLE . ' t, ' . PRIVMSGS_TABLE . ' p, ' . USERS_TABLE . ' u
                WHERE t.user_id = ' . $user->data['user_id'] . "
                AND p.author_id = u.user_id
                AND t.msg_id = p.msg_id
                AND p.msg_id = $msg_id";
    }

    if ($sql)
    {
        $result = $db->sql_query($sql);
        $post = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if (!$post)
        {
            trigger_error('NO_MESSAGE');
        }

        if (!$post['author_id'] || $post['author_id'] == ANONYMOUS)
        {
            trigger_error('NO_AUTHOR');
        }
    }
    
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
    $message_parser = new parse_message();

    // Get maximum number of allowed recipients
    $sql = 'SELECT MAX(g.group_max_recipients) as max_recipients
        FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
        WHERE ug.user_id = ' . $user->data['user_id'] . '
            AND ug.user_pending = 0
            AND ug.group_id = g.group_id';
    $result = $db->sql_query($sql);
    $max_recipients = (int) $db->sql_fetchfield('max_recipients');
    $db->sql_freeresult($result);

    $max_recipients = (!$max_recipients) ? $config['pm_max_recipients'] : $max_recipients;

    // If this is a quote/reply "to all"... we may increase the max_recpients to the number of original recipients
    if (($action == 'reply' || $action == 'quote') && $max_recipients)
    {
        // We try to include every previously listed member from the TO Header
        $list = rebuild_header(array('to' => $post['to_address']));

        // Can be an empty array too ;)
        $list = (!empty($list['u'])) ? $list['u'] : array();
        $list[$post['author_id']] = 'to';

        if (isset($list[$user->data['user_id']]))
        {
            unset($list[$user->data['user_id']]);
        }

        $max_recipients = ($max_recipients < sizeof($list)) ? sizeof($list) : $max_recipients;

        unset($list);
    }

    // Check mass pm to group permission
    if ((!$config['allow_mass_pm'] || !$auth->acl_get('u_masspm_group')) && !empty($address_list['g']))
    {
        $address_list = array();
        trigger_error('NO_AUTH_GROUP_MESSAGE');
    }

    // Check mass pm to users permission
    if ((!$config['allow_mass_pm'] || !$auth->acl_get('u_masspm')) && num_recipients($address_list) > 1)
    {
        $address_list = get_recipients($address_list, 1);
        trigger_error('TOO_MANY_RECIPIENTS');
    }

    // Check for too many recipients
    if (!empty($address_list['u']) && $max_recipients && sizeof($address_list['u']) > $max_recipients)
    {
        $address_list = get_recipients($address_list, $max_recipients);
        trigger_error('TOO_MANY_RECIPIENTS');
    }
    
    $enable_bbcode    = ($config['allow_bbcode'] && $config['auth_bbcode_pm'] && $auth->acl_get('u_pm_bbcode')) ? true : false;
    $enable_smilies    = ($config['allow_smilies'] && $config['auth_smilies_pm'] && $auth->acl_get('u_pm_smilies')) ? true : false;
    $img_status        = ($config['auth_img_pm'] && $auth->acl_get('u_pm_img')) ? true : false;
    $flash_status    = ($config['auth_flash_pm'] && $auth->acl_get('u_pm_flash')) ? true : false;
    $enable_urls         = true;
    $enable_sig            = false;

    $message_parser->message = $text_body;

    // Parse message
    $message_parser->parse($enable_bbcode, ($config['allow_post_links']) ? $enable_urls : false, $enable_smilies, $img_status, $flash_status, true, $config['allow_post_links']);

    $pm_data = array(
        'msg_id'                => (int) $msg_id,
        'from_user_id'            => $user->data['user_id'],
        'from_user_ip'            => $user->ip,
        'from_username'            => $user->data['username'],
        'reply_from_root_level'    => (isset($post['root_level'])) ? (int) $post['root_level'] : 0,
        'reply_from_msg_id'        => (int) $msg_id,
        'icon_id'                => 0,
        'enable_sig'            => (bool) $enable_sig,
        'enable_bbcode'            => (bool) $enable_bbcode,
        'enable_smilies'        => (bool) $enable_smilies,
        'enable_urls'            => (bool) $enable_urls,
        'bbcode_bitfield'        => $message_parser->bbcode_bitfield,
        'bbcode_uid'            => $message_parser->bbcode_uid,
        'message'                => $message_parser->message,
        'attachment_data'        => $message_parser->attachment_data,
        'filename_data'            => $message_parser->filename_data,
        'address_list'            => $address_list
    );
    
    $msg_id = submit_pm($action, $subject, $pm_data);

    $result = new xmlrpcval(array('result' => new xmlrpcval($msg_id ? true : false, 'boolean')), 'struct');
    
    return new xmlrpcresp($result);
}
