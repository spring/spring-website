<?php
/**
*
* This file is part of Tapatalk Software package.
*
* Translate data structure Array to XMLRPC 
* 
*/

global $base64Set,$datetimeSet;

$base64Set = array(
    'get_forum' => array(
        'forum_name',
        'description',
    ),
    
    'get_thread' => array(
        'forum_name',
        'topic_title',
        'topic_author_name',
        'prefix',
        'forum_name',
        'post_title',
        'post_content',
        'post_author_name',
        'editor_name',
        'edit_reason',
        'filename',
        'username',
    ),
    
    'get_topic' => array(
        'topic_title',
        'topic_author_name',
        'short_content',
        'forum_name',
    ),
    
    'login' => array(
        'username',
        'login_name',
        'email',
        'user_type',
    ),
    
    'reply_post' => array(
        'post_title',
        'post_content',
        'post_author_name',
    ),
    
    'get_user_info' => array(
        'name',
        'value',
        'username',
        'user_type',
        'display_text',
        'current_activity',
        'current_action',
    ),
    
    'search' => array(
        'forum_name',
        'topic_title',
        'post_title',
        'post_author_name',
        'user_type',
        'short_content',
    ),
    'get_quote_post' => array(
        'post_title',
        'post_content',
    ),
    
    'get_raw_post' => array(
        'post_title',
        'post_content',
        'edit_reason',
    ),
    'save_raw_post' => array(
        'post_title',
        'post_content',
        'edit_reason',
    ),
    
    'logout_user' => array(
        'result_text',
    ),
    
    'subscribe_forum' => array(
        'result_text',
    ),
    
    'subscribe_topic' => array(
        'result_text',
    ),
    
    'unsubscribe_forum' => array(
        'result_text',
    ),
    
    'unsubscribe_topic' => array(
        'result_text',
    ),
    'mark_all_as_read' => array(
        'result_text',
    ),
    'login_forum' => array(
        'result_text',
    ),
    'get_online_users' => array(
        'username',
        'user_name',
        'from',
        'display_text',
    ),
    'get_subscribed_forum' => array(
        'forum_name',
    )
);

$datetimeSet = array(
    'get_thread'  => array(
        'post_time',
    ),
    'get_topic'   => array(
        'last_reply_time',
    ),
    'reply_post'  => array(
        'post_time',
    ), 
    'get_user_info'=> array(
        'reg_time',
        'last_activity_time',
    ),
    'search' => array(
        'post_time'
    ),
);


function mobi_parse_request()
{
    global $request_method, $request_params, $params_num;

    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }
    
    if(isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
        $content_encoding = str_replace('x-', '', $_SERVER['HTTP_CONTENT_ENCODING']);
    } else {
        $content_encoding = '';
    }

    if($content_encoding != '' && strlen($data)) {
        if($content_encoding == 'deflate' || $content_encoding == 'gzip') {
            // if decoding works, use it. else assume data wasn't gzencoded
            if(function_exists('gzinflate')) {
                if ($content_encoding == 'deflate' && $degzdata = @gzuncompress($data)) {
                    $data = $degzdata;
                } elseif ($degzdata = @gzinflate(substr($data, 10))) {
                    $data = $degzdata;
                }
            }
        }
    }
    if (isset($_FILES['attachment']['name']))
    {
        $request_name = 'upload_attach';
        $request_params = array();
        $params_num = 0;
    }
    else
    {
        $parser = new XmlrpcDecoder;
        $call   = $parser->decodeCall($data);
        $request_method = $call[0];
        $request_params = $call[1];
        $params_num = count($request_params);
    }
    
}

function mobi_shutdown()
{
    global $server_param, $request_method;
    if(isset($server_param[$request_method]))
        $result = call_user_func($server_param[$request_method]['function']);
    if(empty($result))
    {
        $result = array
        (
            'result'      => false,
            'result_text' => 'this function returns an empty structure',
        );
        $result = mobi_xmlrpc_encode($result);
    }
    if(!empty($result))
    {
        $result = mobi_xmlrpc_encode($result);
    }
    print_r($result);
    exit;
}

function mobi_resp($data, $err = false)
{
    //here we set the header mobiquo_is_login:true if user is registed and 
    global $user;
    if (!defined('MOBIQUO_LOGINED') && $user->data['is_registered']){
        header('Mobiquo_is_login:true');
        define('MOBIQUO_LOGINED');
    }
    //xmlrpc_encode
    print_r(mobi_xmlrpc_encode($data,$err));
    exit;
}

function mobi_xmlrpc_encode($str,$err = false)
{
    $_str = mobi_pre_xmlrpc($str,$err);
    $parser = new XmlrpcEncoder;
    $result = $parser->encodeResponse($_str);
    
    //parse the special close tags (like <base64/>) to the app standard one (<base64></base64>)
    $valuetags = array('int','boolean','string','name','base64','dateTime.iso8601','data');
    foreach($valuetags as $tag)
    {
        $result = preg_replace("/<$tag\s*\/>/si","<$tag></$tag>",$result);
    }
    $result = preg_replace("/<nil\/>/si","",$result);
    
    //Just for debug
    $othertags = array('params','param','value','member','methodResponse','struct','array','data');
    foreach($valuetags as $tag)
    {
        $result = preg_replace("/<$tag>(.*?)<\/$tag>/si","$0\n",$result);
    }
    
    foreach($othertags as $tag)
    {
        $result = preg_replace("/<\/*$tag>/si","$0\n",$result);
    }
    
    ob_clean();
    
    return $result;
}

function mobi_pre_xmlrpc($struct,$err)
{
    global $request_method, $base64Set, $datetimeSet;
    
    if($err)
    {
        $tmp = new \stdClass();
        $tmp->scalar = $struct['result_text'];
        $tmp->xmlrpc_type = 'base64';
        $struct['result_text'] = $tmp;
        return $struct;
    }
    
    if(!is_array($struct)) 
        return $struct;
    
    $result = array();
    foreach($struct as $key => $value)
    {
        if (is_array($value))
            $value = mobi_pre_xmlrpc($value,$err);
            
        $trans = false;
        if (!empty($base64Set[$request_method]) && !is_numeric($key) && in_array($key,$base64Set[$request_method]))
        {
            $tmp = new \stdClass();
            $tmp->scalar = $value;
            $tmp->xmlrpc_type = 'base64';
            $trans = true;
        }
        if (!empty($datetimeSet[$request_method]) && !is_numeric($key) && in_array($key,$datetimeSet[$request_method]))
        {
            $tmp = new \stdClass();
            $tmp->scalar = $value;
            $tmp->xmlrpc_type = 'datetime';
            $trans = true;
        }
        
        $result[$key]=($trans) ? $tmp : $value;
    }
    return $result;
}

