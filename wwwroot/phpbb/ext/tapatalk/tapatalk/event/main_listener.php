<?php
/**
*
* @package phpBB Extension - Acme Demo
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace tapatalk\tapatalk\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
    static public function getSubscribedEvents()
    {
        return array(
            'core.user_setup'                       => 'tapa_setup',
            'core.page_footer'                      => 'tapa_page_footer',
            'core.functions.redirect'               => 'tapa_redirect',
            'core.viewforum_modify_topics_data'     => 'tapa_topics',
            'core.viewtopic_modify_post_data'       => 'tapa_threads',
            'core.login_forum_box'                  => 'tapa_login_forum',
            'core.viewonline_overwrite_location'    => 'tapa_viewonline',
            'core.search_modify_tpl_ary'            => 'tapa_search',
            'core.submit_post_end'                  => 'tapa_new_post',
            'core.viewonline_modify_sql'            => 'tapa_showonline',
            'core.posting_modify_template_vars'     => 'tapa_post',
            'core.memberlist_view_profile'          => 'tapa_userinfo',
        );
    }

    /* @var \phpbb\controller\helper */
    protected $helper;

    /* @var \phpbb\template\template */
    protected $template;
    
    /* @var \phpbb\user */
    protected $user;
    
    /* @var \phpbb\auth\auth */
    protected $auth;
    /**
    * Constructor
    *
    * @param \phpbb\controller\helper   $helper     Controller helper object
    * @param \phpbb\template            $template   Template object
    */
    public function __construct(\phpbb\template\template $template, \phpbb\user $user, \phpbb\auth\auth $auth)
    {
        $this->template = $template;
        $this->user = $user;
        $this->auth = $auth;
    }

    public function tapa_login_forum($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            $result = array(
                'result'       => (boolean)0,
                'result_text'  => '',   
            );
            mobi_resp($result);
            exit;
        }
    }
   
    public function tapa_page_footer($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            
            switch($request_method)
            {
                //here we deal with the ERRORS 
                case 'login':
                    if(!tt_get_user_by_name(request_var('username','')))
                    {
                        $status = 2;
                        $response = array(
                            'result'          => false,
                            'result_text'     => preg_replace('/\%s/si','',$this->user->lang['LOGIN_ERROR_USERNAME']),
                            'status'          => (string)$status,
                        );
                    }
                    else
                    {
                        $status = 0;
                        $response = array(
                            'result'          => false,
                            'result_text'     => preg_replace('/\%s/si','',$this->user->lang['LOGIN_ERROR_PASSWORD']),
                            'status'          => (string)$status,
                        );
                    }
                    print_r(mobi_xmlrpc_encode($response,true));
                    break;
                //other final functions
                case 'reply_post':
                    trigger_error($this->user->lang['FLOOD_ERROR']);
                    break;
                case 'get_user_info':
                    trigger_error($this->user->lang['LOGIN_REQUIRED']);
                    break;
                case 'get_thread':
                case 'get_topic':
                case 'get_online_users':
                case 'login_forum':
                    call_user_func($request_method.'_func');
                    break;
                default://if default we output html to show what the page is (for_dev (need_remove (undo
                    return;
            }
            exit;
        }
    }
    
    public function tapa_redirect($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            //here we deal with the redirect $event
            global $request_method;
            $evt = $event->get_data();
            
            switch($request_method)
            {
                case 'get_topic':
                    trigger_error('NO_FORUM');
                    break;
                case 'login':
                case 'reply_post':
                case 'save_raw_post':
                    call_user_func($request_method.'_func',$evt);
                    break;
            }
        }
    }
    
    
    public function tapa_setup($event)
    {
        global $db, $config, $request_method, $request_params;
        global $perpage, $topic_subscribed;
        if(defined('IN_MOBIQUO'))
        {
            //For check_form_key() func.
            $evt = $event->get_data();
            $evt['user_data']['user_form_salt'] = 'TapatalkApp';
            $evt['user_data']['user_lastvisit'] = 0;
            $event->set_data($evt);
            
            switch($request_method)
            {
                case 'get_thread':
                case 'search':
                case 'get_topic':
                    $config['topics_per_page'] = $config['posts_per_page'] = $perpage;
                    break;
                case 'login':
                    $config['max_login_attempts'] = '100';
                    break;
                case 'reply_post':
                    $topic_id = $request_params[1];
                    if ($config['allow_topic_notify'] && $evt['user_data']['is_registered'])
                    {
                    	$sql = 'SELECT topic_id
                    		FROM ' . TOPICS_WATCH_TABLE . '
                    		WHERE topic_id = ' . $topic_id . '
                    			AND user_id = ' . $evt['user_data']['user_id'];
                    	$result = $db->sql_query($sql);
                    	$topic_subscribed = (int) $db->sql_fetchfield('topic_id');
                    	$db->sql_freeresult($result);
                    }
            }
        }
        $lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'tapatalk/tapatalk',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
    }
    
    public function tapa_topics($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            
            switch ($request_method)
            {
                case 'get_topic':
                    call_user_func($request_method.'_sub_func', $event->get_data());
            }
            return;
        }
    }
    
    public function tapa_threads($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            
            switch ($request_method)
            {
                case 'get_thread':
                    call_user_func($request_method.'_sub_func', $event->get_data());
            }
            return;
        }
    }
    
    public function tapa_showonline($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method, $guest_count;
            
            switch($request_method)
            {
                case 'get_online_users':
                    $evt = $event->get_data();
                    $evt['show_guests'] = 0;
                    $guest_count = $evt['guest_counter'];
            }
        }
    }
    
    public function tapa_viewonline($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            
            switch ($request_method)
            {
                case 'get_online_users':
                    call_user_func($request_method.'_sub_func', $event->get_data());
            }
            return;
        }
    }
    
    public function tapa_search($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            
            switch ($request_method)
            {
                case 'search':
                    call_user_func($request_method.'_sub_func', $event->get_data());
            }
            return;
        }
        
    }
    
    public function tapa_new_post($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            $evt = $event->get_data();
            
            switch($request_method)
            {
                case 'new_topic':
                    call_user_func($request_method.'_func',$evt);
                    break;
            }
        }
    }
    
    public function tapa_post($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            
            switch ($request_method)
            {
                case 'get_quote_post':
                case 'get_raw_post':
                    call_user_func($request_method.'_func', $event->get_data());
            }
            return;
        }
    }
    
    public function tapa_userinfo($event)
    {
        if(defined('IN_MOBIQUO'))
        {
            global $request_method;
            
            switch ($request_method)
            {
                case 'get_user_info':
                    call_user_func($request_method.'_func', $event->get_data());
            }
            return;
        }
    }
}
