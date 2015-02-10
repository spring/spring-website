<?php

namespace tapatalk\tapatalk\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return($this->config['mobiquo_version']=='1.0.0');
	}

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\alpha2');
	}

	public function update_data()
	{
		return array(
		    array('config.add', array('mobiquo_version', '1.0.0')),
			array('config.add', array('mobiquo_hide_forum_id', 0)),
			array('config.add', array('tapatalkdir', '')),
			array('config.add', array('tapatalk_push_key', '')),
			array('config.add', array('tapatalk_forum_read_only', 0)),
			array('config.add', array('tapatalk_ad_filter', '')),
			array('config.add', array('tapatalk_custom_replace', '')),
			array('config.add', array('tapatalk_app_ads_enable', 1)),
			array('config.add', array('tapatalk_app_banner_msg', '')),
			array('config.add', array('tapatalk_app_ios_id', '')),
			array('config.add', array('tapatalk_android_url', '')),
			array('config.add', array('tapatalk_kindle_url', '')),
			array('config.add', array('tapatalk_register_status', '')),
			array('config.add', array('mobiquo_reg_url', '')),
			array('config.add', array('tapatalk_register_group', '')),
			array('config.add', array('tapatalk_spam_status', '')),
			
            array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_MOBIQUO_TITLE'
			)),

			array('module.add', array(
				'acp',
				'ACP_MOBIQUO_TITLE',
				array(
					'module_basename'	=> '\tapatalk\tapatalk\acp\main_module',
					'modes'     => array('mobiquo'),//'mobiquo_rebranding','mobiquo_register'),
			    ),
		    )),
	    );
	}
}
