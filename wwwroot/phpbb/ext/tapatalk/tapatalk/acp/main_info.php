<?php
/**
* @package module_install
*/

namespace tapatalk\tapatalk\acp;

class main_info
{
    function module()
    {     
        return array(
            'filename'  => '\tapatalk\tapatalk\acp\main_module',
            'title'     => 'Tapatalk',    
        	'version'	=> '4.5.3',  
            'modes'     => array(
            	'mobiquo'  => array(
            		'title' => 'ACP_MOBIQUO_SETTINGS', 
            		'auth' => 'ext_tapatalk/tapatalk && acl_a_board', 
            		'cat' => array('ACP_MOBIQUO')
        		),
        		'mobiquo_rebranding' => array(
					'title'	=> 'ACP_TAPATALK_REBRANDING',
					'auth'  => 'ext_tapatalk/tapatalk && acl_a_board',
        			'cat'   => array('ACP_MOBIQUO')
        		),
        		'mobiquo_register' => array(
					'title'	=> 'ACP_MOBIQUO_REGISTER_SETTINGS',
					'auth'  => 'ext_tapatalk/tapatalk && acl_a_board',
        			'cat'   => array('ACP_MOBIQUO')
        		)
            ),
        );
    }
}
?>