<?php
/*
Plugin Name: Responsive Lightbox
Description: Responsive Lightbox allows users to view larger versions of images and galleries in a lightbox (overlay) effect optimized for mobile devices.
Version: 1.3.6
Author: dFactory
Author URI: http://www.dfactory.eu/
Plugin URI: http://www.dfactory.eu/plugins/responsive-lightbox/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: responsive-lightbox
Domain Path: /languages

Responsive Lightbox
Copyright (C) 2013, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/


class Responsive_Lightbox
{
	private $defaults = array(
		'settings' => array(
			'script' => 'swipebox',
			'selector' => 'lightbox',
			'galleries' => TRUE,
			'videos' => TRUE,
			'image_links' => TRUE,
			'images_as_gallery' => FALSE,
			'deactivation_delete' => FALSE,
			'enable_custom_events' => FALSE,
			'custom_events' => 'ajaxComplete'
		),
		'configuration' => array(
			'prettyphoto' => array(
				'animation_speed' => 'normal',
				'slideshow' => FALSE,
				'slideshow_delay' => 5000,
				'slideshow_autoplay' => FALSE,
				'opacity' => 75,
				'show_title' => TRUE,
				'allow_resize' => TRUE,
				'allow_expand' => true,
				'width' => 1080,
				'height' => 720,
				'separator' => '/',
				'theme' => 'pp_default',
				'horizontal_padding' => 20,
				'hide_flash' => FALSE,
				'wmode' => 'opaque',
				'video_autoplay' => FALSE,
				'modal' => FALSE,
				'deeplinking' => FALSE,
				'overlay_gallery' => TRUE,
				'keyboard_shortcuts' => TRUE,
				'social' => FALSE
			),
			'swipebox' => array(
				'animation' => 'css',
				'force_png_icons' => FALSE,
				'hide_bars' => TRUE,
				'hide_bars_delay' => 5000,
				'video_max_width' => 1080
			),
			'fancybox' => array(
				'modal' => FALSE,
				'show_overlay' => TRUE,
				'show_close_button' => TRUE,
				'enable_escape_button' => TRUE,
				'hide_on_overlay_click' => TRUE,
				'hide_on_content_click' => FALSE,
				'cyclic' => FALSE,
				'show_nav_arrows' => TRUE,
				'auto_scale' => TRUE,
				'scrolling' => 'yes',
				'center_on_scroll' => TRUE,
				'opacity' => TRUE,
				'overlay_opacity' => 70,
				'overlay_color' => '#666',
				'title_show' => TRUE,
				'title_position' => 'outside',
				'transitions' => 'fade',
				'easings' => 'swing',
				'speeds' => 300,
				'change_speed' => 300,
				'change_fade' => 100,
				'padding' => 5,
				'margin' => 5,
				'video_width' => 1080,
				'video_height' => 720
			),
			'nivo' => array(
				'effect' => 'fade',
				'keyboard_nav' => TRUE,
				'error_message' => 'The requested content cannot be loaded. Please try again later.'
			)
		),
		'version' => '1.3.6'
	);
	private $scripts = array();
	private $options = array();
	private $tabs = array();
	private $gallery_no = 0;


	public function __construct()
	{
		register_activation_hook(__FILE__, array(&$this, 'multisite_activation'));
		register_deactivation_hook(__FILE__, array(&$this, 'multisite_deactivation'));

		//changes from older versions
		$db_version = get_option('responsive_lightbox_version');

		if(version_compare(($db_version === FALSE ? '1.0.0' : $db_version), '1.0.5', '<'))
		{
			if(($array = get_option('rl_settings')) !== FALSE)
			{
				update_option('responsive_lightbox_settings', $array);
				delete_option('rl_settings');
			}

			if(($array = get_option('rl_configuration')) !== FALSE)
			{
				update_option('responsive_lightbox_configuration', $array);
				delete_option('rl_configuration');
			}
		}

		//update plugin version
		update_option('responsive_lightbox_version', $this->defaults['version'], '', 'no');

		$this->options['settings'] = array_merge($this->defaults['settings'], (($array = get_option('responsive_lightbox_settings')) === FALSE ? array() : $array));

		//for multi arrays we have to merge them separately
		$db_conf_opts = (($base = get_option('responsive_lightbox_configuration')) === FALSE ? array() : $base);

		foreach($this->defaults['configuration'] as $script => $settings)
		{
			$this->options['configuration'][$script] = array_merge($settings, (isset($db_conf_opts[$script]) ? $db_conf_opts[$script] : array()));
		}

		//actions
		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_action('plugins_loaded', array(&$this, 'load_defaults'));
		add_action('admin_init', array(&$this, 'register_settings'));
		add_action('admin_menu', array(&$this, 'admin_menu_options'));
		add_action('wp_enqueue_scripts', array(&$this, 'front_scripts_styles'));
		add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts_styles'));

		//filters
		add_filter('plugin_action_links', array(&$this, 'plugin_settings_link'), 10, 2);
		add_filter('plugin_row_meta', array(&$this, 'plugin_extend_links'), 10, 2);
		add_filter('post_gallery', array(&$this, 'gallery_attributes'), 1000);

		if($this->options['settings']['galleries'] === TRUE)
			add_filter('wp_get_attachment_link', array(&$this, 'add_gallery_lightbox_selector'), 1000, 6);

		if($this->options['settings']['videos'] === TRUE)
			add_filter('the_content', array(&$this, 'add_videos_lightbox_selector'));

		if($this->options['settings']['image_links'] === TRUE || $this->options['settings']['images_as_gallery'] === TRUE)
			add_filter('the_content', array(&$this, 'add_links_lightbox_selector'));
	}


	public function add_videos_lightbox_selector($content)
	{
		preg_match_all('/<a(.*?)href=(?:\'|")((?:(?:http|https):\/\/)?(?:www\.)?((youtube\.com\/watch\?v=[a-z0-9_\-]+)|(vimeo\.com\/[0-9]{8,})))(?:\'|")(.*?)>/i', $content, $links);

		if(isset($links[0]))
		{
			foreach($links[0] as $id => $link)
			{
				if(preg_match('/<a.*?rel=(?:\'|")(.*?)(?:\'|").*?>/', $link, $result) === 1)
				{
					if(isset($result[1]))
					{
						$new_rels = array();
						$rels = explode(' ', $result[1]);

						if(in_array($this->options['settings']['selector'], $rels, TRUE))
						{
							foreach($rels as $no => $rel)
							{
								if($rel !== $this->options['settings']['selector'])
									$new_rels[] = $rel;
							}

							$content = str_replace($link, preg_replace('/rel=(?:\'|")(.*?)(?:\'|")/', 'rel="'.(!empty($new_rel) ? simplode(' ', $new_rels).' ' : '').$this->options['settings']['selector'].'-video-'.$id.'"', $link), $content);
						}
						else
							$content = str_replace($link, preg_replace('/rel=(?:\'|")(.*?)(?:\'|")/', 'rel="'.($result[1] !== '' ? $result[1].' ' : '').$this->options['settings']['selector'].'-video-'.$id.'"', $link), $content);
					}
				}
				else
					$content = str_replace($link, '<a'.$links[1][$id].'href="'.$links[2][$id].'"'.$links[6][$id].' rel="'.$this->options['settings']['selector'].'-video-'.$id.'">', $content);
			}
		}

		return $content;
	}


	public function add_links_lightbox_selector($content)
	{
		preg_match_all('/<a(.*?)href=(?:\'|")([^<]*?).(bmp|gif|jpeg|jpg|png)(?:\'|")(.*?)>/i', $content, $links);

		if(isset($links[0]))
		{
			if($this->options['settings']['images_as_gallery'] === TRUE)
				$rel_hash = '[gallery-'.wp_generate_password(4, FALSE, FALSE).']';

			foreach($links[0] as $id => $link)
			{
				if(preg_match('/<a.*?rel=(?:\'|")(.*?)(?:\'|").*?>/', $link, $result) === 1)
				{
					if($this->options['settings']['images_as_gallery'] === TRUE)
					{
						$content = str_replace($link, preg_replace('/rel=(?:\'|")(.*?)(?:\'|")/', 'rel="'.$this->options['settings']['selector'].$rel_hash.'"', $link), $content);
					}
					else
					{
						if(isset($result[1]))
						{
							$new_rels = array();
							$rels = explode(' ', $result[1]);

							if(in_array($this->options['settings']['selector'], $rels, TRUE))
							{
								foreach($rels as $no => $rel)
								{
									if($rel !== $this->options['settings']['selector'])
										$new_rels[] = $rel;
								}

								$content = str_replace($link, preg_replace('/rel=(?:\'|")(.*?)(?:\'|")/', 'rel="'.(!empty($new_rels) ? implode(' ', $new_rels).' ' : '').$this->options['settings']['selector'].'-'.$id.'"', $link), $content);
							}
							else
								$content = str_replace($link, preg_replace('/rel=(?:\'|")(.*?)(?:\'|")/', 'rel="'.($result[1] !== '' ? $result[1].' ' : '').$this->options['settings']['selector'].'-'.$id.'"', $link), $content);
						}
					}
				}
				else
					$content = str_replace($link, '<a'.$links[1][$id].'href="'.$links[2][$id].'.'.$links[3][$id].'"'.$links[4][$id].' rel="'.$this->options['settings']['selector'].($this->options['settings']['images_as_gallery'] === TRUE ? $rel_hash : '-'.$id).'">', $content);
			}
		}

		return $content;
	}


	public function gallery_attributes($style)
	{
		++$this->gallery_no;

		return $style;
	}


	public function add_gallery_lightbox_selector($link, $id, $size, $permalink, $icon, $text)
	{
		$link = (preg_match('/<a.*? rel=("|\').*?("|\')>/', $link) === 1 ? preg_replace('/(<a.*? rel=(?:"|\').*?)((?:"|\').*?>)/', '$1 '.$this->options['settings']['selector'].'[gallery-'.$this->gallery_no.']'.'$2', $link) : preg_replace('/(<a.*?)>/', '$1 rel="'.$this->options['settings']['selector'].'[gallery-'.$this->gallery_no.']'.'">', $link));

		return (preg_match('/<a.*? href=("|\').*?("|\')>/', $link) === 1 ? preg_replace('/(<a.*? href=(?:"|\')).*?((?:"|\').*?>)/', '$1'.wp_get_attachment_url($id).'$2', $link) : preg_replace('/(<a.*?)>/', '$1 href="'.wp_get_attachment_url($id).'">', $link));
	}


	public function load_defaults()
	{
		$this->scripts = array(
			'prettyphoto' => array(
				'name' => __('prettyPhoto', 'responsive-lightbox'),
				'animation_speeds' => array(
					'slow' => __('slow', 'responsive-lightbox'),
					'normal' => __('normal', 'responsive-lightbox'),
					'fast' => __('fast', 'responsive-lightbox')
				),
				'themes' => array(
					'pp_default' => __('default', 'responsive-lightbox'),
					'light_rounded' => __('light rounded', 'responsive-lightbox'),
					'dark_rounded' => __('dark rounded', 'responsive-lightbox'),
					'light_square' => __('light square', 'responsive-lightbox'),
					'dark_square' => __('dark square', 'responsive-lightbox'),
					'facebook' => __('facebook', 'responsive-lightbox')
				),
				'wmodes' => array(
					'window' => __('window', 'responsive-lightbox'),
					'transparent' => __('transparent', 'responsive-lightbox'),
					'opaque' => __('opaque', 'responsive-lightbox'),
					'direct' => __('direct', 'responsive-lightbox'),
					'gpu' => __('gpu', 'responsive-lightbox')
				)
			),
			'swipebox' => array(
				'name' => __('SwipeBox', 'responsive-lightbox'),
				'animations' => array(
					'css' => __('CSS', 'responsive-lightbox'),
					'jquery' => __('jQuery', 'responsive-lightbox')
				)
			),
			'fancybox' => array(
				'name' => __('FancyBox', 'responsive-lightbox'),
				'transitions' => array(
					'elastic' => __('elastic', 'responsive-lightbox'),
					'fade' => __('fade', 'responsive-lightbox'),
					'none' => __('none', 'responsive-lightbox')
				),
				'scrollings' => array(
					'auto' => __('auto', 'responsive-lightbox'),
					'yes' => __('yes', 'responsive-lightbox'),
					'no' => __('no', 'responsive-lightbox')
				),
				'easings' => array(
					'swing' => __('swing', 'responsive-lightbox'),
					'linear' => __('linear', 'responsive-lightbox')
				),
				'positions' => array(
					'outside' => __('outside', 'responsive-lightbox'),
					'inside' => __('inside', 'responsive-lightbox'),
					'over' => __('over', 'responsive-lightbox')
				)
			),
			'nivo' => array(
				'name' => __('Nivo Lightbox', 'responsive-lightbox'),
				'effects' => array(
					'fade' => __('fade', 'responsive-lightbox'),
					'fadeScale' => __('fade scale', 'responsive-lightbox'),
					'slideLeft' => __('slide left', 'responsive-lightbox'),
					'slideRight' => __('slide right', 'responsive-lightbox'),
					'slideUp' => __('slide up', 'responsive-lightbox'),
					'slideDown' => __('slide down', 'responsive-lightbox'),
					'fall' => __('fall', 'responsive-lightbox')
				)
			)
		);

		$this->choices = array(
			'yes' => __('Enable', 'responsive-lightbox'),
			'no' => __('Disable', 'responsive-lightbox')
		);

		$this->tabs = array(
			'general-settings' => array(
				'name' => __('General settings', 'responsive-lightbox'),
				'key' => 'responsive_lightbox_settings',
				'submit' => 'save_rl_settings',
				'reset' => 'reset_rl_settings',
			),
			'configuration' => array(
				'name' => __('Lightbox settings', 'responsive-lightbox'),
				'key' => 'responsive_lightbox_configuration',
				'submit' => 'save_rl_configuration',
				'reset' => 'reset_rl_configuration'
			)
		);
	}


	public function multisite_activation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$activated_blogs = array();
			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->activate_single();
				$activated_blogs[] = (int)$blog_id;
			}

			switch_to_blog($current_blog_id);
			update_site_option('responsive_lightbox_activated_blogs', $activated_blogs, array());
		}
		else
			$this->activate_single();
	}


	public function activate_single()
	{
		add_option('responsive_lightbox_settings', $this->defaults['settings'], '', 'no');
		add_option('responsive_lightbox_configuration', $this->defaults['configuration'], '', 'no');
		add_option('responsive_lightbox_version', $this->defaults['version'], '', 'no');
	}


	public function multisite_deactivation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			if(($activated_blogs = get_site_option('responsive_lightbox_activated_blogs', FALSE, FALSE)) === FALSE)
				$activated_blogs = array();

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->deactivate_single(TRUE);

				if(in_array((int)$blog_id, $activated_blogs, TRUE))
					unset($activated_blogs[array_search($blog_id, $activated_blogs)]);
			}

			switch_to_blog($current_blog_id);
			update_site_option('responsive_lightbox_activated_blogs', $activated_blogs);
		}
		else
			$this->deactivate_single();
	}


	public function deactivate_single($multi = FALSE)
	{
		if($multi === TRUE)
		{
			$options = get_option('responsive_lightbox_settings');
			$check = $options['deactivation_delete'];
		}
		else
			$check = $this->options['settings']['deactivation_delete'];

		if($check === TRUE)
		{
			delete_option('responsive_lightbox_settings');
			delete_option('responsive_lightbox_configuration');
			delete_option('responsive_lightbox_version');
		}
	}


	public function register_settings()
	{
		register_setting('responsive_lightbox_settings', 'responsive_lightbox_settings', array(&$this, 'validate_options'));

		//general settings
		add_settings_section('responsive_lightbox_settings', __('General settings', 'responsive-lightbox'), '', 'responsive_lightbox_settings');
		add_settings_field('rl_script', __('Lightbox script', 'responsive-lightbox'), array(&$this, 'rl_script'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');
		add_settings_field('rl_selector', __('Selector', 'responsive-lightbox'), array(&$this, 'rl_selector'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');
		add_settings_field('rl_galleries', __('Galleries', 'responsive-lightbox'), array(&$this, 'rl_galleries'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');
		add_settings_field('rl_videos', __('Video links', 'responsive-lightbox'), array(&$this, 'rl_videos'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');
		add_settings_field('rl_image_links', __('Image links', 'responsive-lightbox'), array(&$this, 'rl_image_links'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');
		add_settings_field('rl_images_as_gallery', __('Single images as gallery', 'responsive-lightbox'), array(&$this, 'rl_images_as_gallery'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');
		add_settings_field('rl_enable_custom_events', __('Custom events', 'responsive-lightbox'), array(&$this, 'rl_enable_custom_events'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');
		add_settings_field('rl_deactivation_delete', __('Deactivation', 'responsive-lightbox'), array(&$this, 'rl_deactivation_delete'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');

		//configuration
		register_setting('responsive_lightbox_configuration', 'responsive_lightbox_configuration', array(&$this, 'validate_options'));
		add_settings_section('responsive_lightbox_configuration', __('Lightbox settings', 'responsive-lightbox').': '.$this->scripts[$this->options['settings']['script']]['name'], '', 'responsive_lightbox_configuration');

		if($this->options['settings']['script'] === 'swipebox')
		{
			add_settings_field('rl_sb_animation', __('Animation type', 'responsive-lightbox'), array(&$this, 'rl_sb_animation'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_sb_force_png_icons', __('Force PNG icons', 'responsive-lightbox'), array(&$this, 'rl_sb_force_png_icons'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_sb_hide_bars', __('Top and bottom bars', 'responsive-lightbox'), array(&$this, 'rl_sb_hide_bars'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_sb_video_max_width', __('Video max width', 'responsive-lightbox'), array(&$this, 'rl_sb_video_max_width'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
		}
		elseif($this->options['settings']['script'] === 'prettyphoto')
		{
			add_settings_field('rl_pp_animation_speed', __('Animation speed', 'responsive-lightbox'), array(&$this, 'rl_pp_animation_speed'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_slideshow', __('Slideshow', 'responsive-lightbox'), array(&$this, 'rl_pp_slideshow'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_slideshow_autoplay', __('Slideshow autoplay', 'responsive-lightbox'), array(&$this, 'rl_pp_slideshow_autoplay'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_opacity', __('Opacity', 'responsive-lightbox'), array(&$this, 'rl_pp_opacity'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_title', __('Show title', 'responsive-lightbox'), array(&$this, 'rl_pp_title'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_allow_resize', __('Allow resize big images', 'responsive-lightbox'), array(&$this, 'rl_pp_allow_resize'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_allow_expand', __('Allow expand', 'responsive-lightbox'), array(&$this, 'rl_pp_allow_expand'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_width', __('Video width', 'responsive-lightbox'), array(&$this, 'rl_pp_width'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_height', __('Video height', 'responsive-lightbox'), array(&$this, 'rl_pp_height'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_theme', __('Theme', 'responsive-lightbox'), array(&$this, 'rl_pp_theme'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_horizontal_padding', __('Horizontal padding', 'responsive-lightbox'), array(&$this, 'rl_pp_horizontal_padding'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_hide_flash', __('Hide Flash', 'responsive-lightbox'), array(&$this, 'rl_pp_hide_flash'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_wmode', __('Flash Window Mode (wmode)', 'responsive-lightbox'), array(&$this, 'rl_pp_wmode'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_video_autoplay', __('Video autoplay', 'responsive-lightbox'), array(&$this, 'rl_pp_video_autoplay'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_modal', __('Modal', 'responsive-lightbox'), array(&$this, 'rl_pp_modal'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_deeplinking', __('Deeplinking', 'responsive-lightbox'), array(&$this, 'rl_pp_deeplinking'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_overlay_gallery', __('Overlay gallery', 'responsive-lightbox'), array(&$this, 'rl_pp_overlay_gallery'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_keyboard_shortcuts', __('Keyboard shortcuts', 'responsive-lightbox'), array(&$this, 'rl_pp_keyboard_shortcuts'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_social', __('Social (Twitter, Facebook)', 'responsive-lightbox'), array(&$this, 'rl_pp_social'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
		}
		elseif($this->options['settings']['script'] === 'fancybox')
		{
			add_settings_field('rl_fb_modal', __('Modal', 'responsive-lightbox'), array(&$this, 'rl_fb_modal'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_show_overlay', __('Show overlay', 'responsive-lightbox'), array(&$this, 'rl_fb_show_overlay'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_show_close_button', __('Show close button', 'responsive-lightbox'), array(&$this, 'rl_fb_show_close_button'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_enable_escape_button', __('Enable escape button', 'responsive-lightbox'), array(&$this, 'rl_fb_enable_escape_button'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_hide_on_overlay_click', __('Hide on overlay click', 'responsive-lightbox'), array(&$this, 'rl_fb_hide_on_overlay_click'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_hide_on_content_click', __('Hide on content click', 'responsive-lightbox'), array(&$this, 'rl_fb_hide_on_content_click'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_cyclic', __('Cyclic', 'responsive-lightbox'), array(&$this, 'rl_fb_cyclic'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_show_nav_arrows', __('Show nav arrows', 'responsive-lightbox'), array(&$this, 'rl_fb_show_nav_arrows'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_auto_scale', __('Auto scale', 'responsive-lightbox'), array(&$this, 'rl_fb_auto_scale'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_scrolling', __('Scrolling (in/out)', 'responsive-lightbox'), array(&$this, 'rl_fb_scrolling'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_center_on_scroll', __('Center on scroll', 'responsive-lightbox'), array(&$this, 'rl_fb_center_on_scroll'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_opacity', __('Opacity', 'responsive-lightbox'), array(&$this, 'rl_fb_opacity'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_overlay_opacity', __('Overlay opacity', 'responsive-lightbox'), array(&$this, 'rl_fb_overlay_opacity'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_overlay_color', __('Overlay color', 'responsive-lightbox'), array(&$this, 'rl_fb_overlay_color'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_title_show', __('Title show', 'responsive-lightbox'), array(&$this, 'rl_fb_title_show'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_title_position', __('Title position', 'responsive-lightbox'), array(&$this, 'rl_fb_title_position'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_transitions', __('Transition (in/out)', 'responsive-lightbox'), array(&$this, 'rl_fb_transitions'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_easings', __('Easings (in/out)', 'responsive-lightbox'), array(&$this, 'rl_fb_easings'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_speeds', __('Speed (in/out)', 'responsive-lightbox'), array(&$this, 'rl_fb_speeds'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_change_speed', __('Change speed', 'responsive-lightbox'), array(&$this, 'rl_fb_change_speed'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_change_fade', __('Change fade', 'responsive-lightbox'), array(&$this, 'rl_fb_change_fade'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_padding', __('Padding', 'responsive-lightbox'), array(&$this, 'rl_fb_padding'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_margin', __('Margin', 'responsive-lightbox'), array(&$this, 'rl_fb_margin'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_video_width', __('Video width', 'responsive-lightbox'), array(&$this, 'rl_fb_video_width'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_fb_video_height', __('Video height', 'responsive-lightbox'), array(&$this, 'rl_fb_video_height'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
		}
		elseif($this->options['settings']['script'] === 'nivo')
		{
			add_settings_field('rl_nv_effect', __('Effect', 'responsive-lightbox'), array(&$this, 'rl_nv_effect'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_nv_keyboard_nav', __('Keyboard navigation', 'responsive-lightbox'), array(&$this, 'rl_nv_keyboard_nav'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_nv_error_message', __('Error message', 'responsive-lightbox'), array(&$this, 'rl_nv_error_message'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
		}
	}


	public function rl_script()
	{
		echo '
		<div id="rl_script" class="wplikebtns">';

		foreach($this->scripts as $val => $trans)
		{
			echo '
			<input id="rl-script-'.$val.'" type="radio" name="responsive_lightbox_settings[script]" value="'.esc_attr($val).'" '.checked($val, $this->options['settings']['script'], FALSE).' />
			<label for="rl-script-'.$val.'">'.$trans['name'].'</label>';
		}

		echo '
			<p class="description">'.__('Select your preffered ligthbox effect script.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_selector()
	{
		echo '
		<div id="rl_selector">
			<input type="text" value="'.esc_attr($this->options['settings']['selector']).'" name="responsive_lightbox_settings[selector]" />
			<p class="description">'.__('Select to which rel selector lightbox effect will be applied to.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_enable_custom_events()
	{
		echo '
		<div id="rl_enable_custom_events" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-enable-custom-events-'.$val.'" type="radio" name="responsive_lightbox_settings[enable_custom_events]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['enable_custom_events'], FALSE).' />
			<label for="rl-enable-custom-events-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Enable triggering lightbox on custom jquery events.', 'responsive-lightbox').'</p>
			<div id="rl_custom_events"'.($this->options['settings']['enable_custom_events'] === FALSE ? ' style="display: none;"' : '').'>
				<input type="text" name="responsive_lightbox_settings[custom_events]" value="'.esc_attr($this->options['settings']['custom_events']).'" />
				<p class="description">'.__('Enter a space separated list of events.', 'responsive-lightbox').'</p>
			</div>
		</div>';
	}


	public function rl_galleries()
	{
		echo '
		<div id="rl_galleries" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-galleries-'.$val.'" type="radio" name="responsive_lightbox_settings[galleries]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['galleries'], FALSE).' />
			<label for="rl-galleries-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Add lightbox to WordPress image galleries by default.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_videos()
	{
		echo '
		<div id="rl_videos" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-videos-'.$val.'" type="radio" name="responsive_lightbox_settings[videos]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['videos'], FALSE).' />
			<label for="rl-videos-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Add lightbox to YouTube and Vimeo video links by default.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_image_links()
	{
		echo '
		<div id="rl_image_links" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-image-links-'.$val.'" type="radio" name="responsive_lightbox_settings[image_links]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['image_links'], FALSE).' />
			<label for="rl-image-links-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Add lightbox to WordPress image links by default.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_images_as_gallery()
	{
		echo '
		<div id="rl_images_as_gallery" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-images-as-gallery-'.$val.'" type="radio" name="responsive_lightbox_settings[images_as_gallery]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['images_as_gallery'], FALSE).' />
			<label for="rl-images-as-gallery-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display single post images as a gallery.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_deactivation_delete()
	{
		echo '
		<div id="rl_deactivation_delete" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-deactivation-delete-'.$val.'" type="radio" name="responsive_lightbox_settings[deactivation_delete]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['deactivation_delete'], FALSE).' />
			<label for="rl-deactivation-delete-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Delete settings on plugin deactivation.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_sb_animation()
	{
		echo '
		<div id="rl_sb_animation" class="wplikebtns">';

		foreach($this->scripts['swipebox']['animations'] as $val => $trans)
		{
			echo '
			<input id="rl-sb-animation-'.$val.'" type="radio" name="responsive_lightbox_configuration[swipebox][animation]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['swipebox']['animation'], FALSE).' />
			<label for="rl-sb-animation-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select a method of applying a lightbox effect.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_sb_hide_bars()
	{
		echo '
		<div id="rl_sb_hide_bars" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-sb-hide-bars-'.$val.'" type="radio" name="responsive_lightbox_configuration[swipebox][hide_bars]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['swipebox']['hide_bars'], FALSE).' />
			<label for="rl-sb-hide-bars-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Disable if you don\'t want to top and bottom bars to be hidden after a period of time.', 'responsive-lightbox').'</p>
			<div id="rl_sb_hide_bars_delay"'.($this->options['configuration']['swipebox']['hide_bars'] === FALSE ? ' style="display: none;"' : '').'>
				<input type="text" name="responsive_lightbox_configuration[swipebox][hide_bars_delay]" value="'.esc_attr($this->options['configuration']['swipebox']['hide_bars_delay']).'" />
				<p class="description">'.__('Enter the time after which the top and bottom bars will be hidden (when hiding is enabled).', 'responsive-lightbox').'</p>
			</div>
		</div>';
	}


	public function rl_sb_video_max_width()
	{
		echo '
		<div id="rl_sb_video_max_width">
			<input type="text" name="responsive_lightbox_configuration[swipebox][video_max_width]" value="'.esc_attr($this->options['configuration']['swipebox']['video_max_width']).'" />
			<p class="description">'.__('Enter the max video width in a lightbox.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_sb_force_png_icons()
	{
		echo '
		<div id="rl_sb_force_png_icons" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-sb-force-png-icons-'.$val.'" type="radio" name="responsive_lightbox_configuration[swipebox][force_png_icons]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['swipebox']['force_png_icons'], FALSE).' />
			<label for="rl-sb-force-png-icons-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Enable this if you\'re having problems with navigation icons not visible on some devices.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_animation_speed()
	{
		echo '
		<div id="rl_pp_animation_speed" class="wplikebtns">';

		foreach($this->scripts['prettyphoto']['animation_speeds'] as $val => $trans)
		{
			echo '
			<input id="rl-pp-animation-speed-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][animation_speed]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['prettyphoto']['animation_speed'], FALSE).' />
			<label for="rl-pp-animation-speed-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select animation speed for lightbox effect.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_slideshow()
	{
		echo '
		<div id="rl_pp_slideshow" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-slideshow-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][slideshow]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['slideshow'], FALSE).' />
			<label for="rl-pp-slideshow-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display images as slideshow.', 'responsive-lightbox').'</p>
			<div id="rl_pp_slideshow_delay"'.($this->options['configuration']['prettyphoto']['slideshow'] === FALSE ? ' style="display: none;"' : '').'>
				<input type="text" name="responsive_lightbox_configuration[prettyphoto][slideshow_delay]" value="'.esc_attr($this->options['configuration']['prettyphoto']['slideshow_delay']).'" />
				<p class="description">'.__('Enter time (in miliseconds).', 'responsive-lightbox').'</p>
			</div>
		</div>';
	}


	public function rl_pp_slideshow_autoplay()
	{
		echo '
		<div id="rl_pp_slideshow_autoplay" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-slideshow-autoplay-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][slideshow_autoplay]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['slideshow_autoplay'], FALSE).' />
			<label for="rl-pp-slideshow-autoplay-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Automatically start slideshow.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_opacity()
	{
		echo '
		<div id="rl_pp_opacity">
			<input type="text" id="rl_pp_opacity_input" class="hide-if-js" name="responsive_lightbox_configuration[prettyphoto][opacity]" value="'.esc_attr($this->options['configuration']['prettyphoto']['opacity']).'" />
			<div class="wplike-slider">
				<span class="left hide-if-no-js">0</span><span class="middle" id="rl_pp_opacity_span" title="'.esc_attr($this->options['configuration']['prettyphoto']['opacity']).'"></span><span class="right hide-if-no-js">100</span>
			</div>
			<p class="description">'.__('Value between 0 and 100, 100 for no opacity.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_title()
	{
		echo '
		<div id="rl_pp_title" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-show-title-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][show_title]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['show_title'], FALSE).' />
			<label for="rl-pp-show-title-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display image tiltle.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_allow_resize()
	{
		echo '
		<div id="rl_pp_allow_resize" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-allow-resize-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][allow_resize]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['allow_resize'], FALSE).' />
			<label for="rl-pp-allow-resize-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Resize the photos bigger than viewport.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_allow_expand()
	{
		echo '
		<div id="rl_pp_allow_expand" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-allow-expand-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][allow_expand]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['allow_expand'], FALSE).' />
			<label for="rl-pp-allow-expand-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Expands something.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_width()
	{
		echo '
		<div id="rl_pp_width">
			<input type="text" name="responsive_lightbox_configuration[prettyphoto][width]" value="'.esc_attr($this->options['configuration']['prettyphoto']['width']).'" />
			<p class="description">'.__('in pixels', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_height()
	{
		echo '
		<div id="rl_pp_height">
			<input type="text" name="responsive_lightbox_configuration[prettyphoto][height]" value="'.esc_attr($this->options['configuration']['prettyphoto']['height']).'" />
			<p class="description">'.__('in pixels', 'responsive-lightbox').'</p>
		</div>';
	}
	

	public function rl_pp_theme()
	{
		echo '
		<div id="rl_pp_theme" class="wplikebtns">';

		foreach($this->scripts['prettyphoto']['themes'] as $val => $trans)
		{
			echo '
			<input id="rl-pp-theme-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][theme]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['prettyphoto']['theme'], FALSE).' />
			<label for="rl-pp-theme-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select theme for lightbox effect.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_horizontal_padding()
	{
		echo '
		<div id="rl_pp_horizontal_padding">
			<input type="text" name="responsive_lightbox_configuration[prettyphoto][horizontal_padding]" value="'.esc_attr($this->options['configuration']['prettyphoto']['horizontal_padding']).'" />
			<p class="description">'.__('Horizontal padding (in pixels).', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_hide_flash()
	{
		echo '
		<div id="rl_pp_hide_flash" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-hide-flash-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][hide_flash]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['hide_flash'], FALSE).' />
			<label for="rl-pp-hide-flash-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Hides all the flash object on a page. Enable this if flash appears over prettyPhoto.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_wmode()
	{
		echo '
		<div id="rl_pp_wmode" class="wplikebtns">';

		foreach($this->scripts['prettyphoto']['wmodes'] as $val => $trans)
		{
			echo '
			<input id="rl-pp-wmode-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][wmode]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['prettyphoto']['wmode'], FALSE).' />
			<label for="rl-pp-wmode-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select flash window mode.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_video_autoplay()
	{
		echo '
		<div id="rl_pp_video_autoplay" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-video-autoplay-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][video_autoplay]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['video_autoplay'], FALSE).' />
			<label for="rl-pp-video-autoplay-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Automatically start videos.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_modal()
	{
		echo '
		<div id="rl_pp_modal" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-modal-close-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][modal]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['modal'], FALSE).' />
			<label for="rl-pp-modal-close-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('If set to true, only the close button will close the window.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_deeplinking()
	{
		echo '
		<div id="rl_pp_deeplinking" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-deeplinking-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][deeplinking]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['deeplinking'], FALSE).' />
			<label for="rl-pp-deeplinking-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Allow prettyPhoto to update the url to enable deeplinking.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_overlay_gallery()
	{
		echo '
		<div id="rl_pp_overlay_gallery" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-overlay-gallery-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][overlay_gallery]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['overlay_gallery'], FALSE).' />
			<label for="rl-pp-overlay-gallery-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('If enabled, a gallery will overlay the fullscreen image on mouse over.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_keyboard_shortcuts()
	{
		echo '
		<div id="rl_pp_keyboard_shortcuts" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-keyboard-shortcuts-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][keyboard_shortcuts]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['keyboard_shortcuts'], FALSE).' />
			<label for="rl-pp-keyboard-shortcuts-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Set to false if you open forms inside prettyPhoto.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_social()
	{
		echo '
		<div id="rl_pp_social" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-pp-social-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][social]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['social'], FALSE).' />
			<label for="rl-pp-social-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display links to Facebook and Twitter.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_transitions()
	{
		echo '
		<div id="rl_fb_transition" class="wplikebtns">';

		foreach($this->scripts['fancybox']['transitions'] as $val => $trans)
		{
			echo '
			<input id="rl-fb-transitions-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][transitions]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['fancybox']['transitions'], FALSE).' />
			<label for="rl-fb-transitions-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('The transition type.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_padding()
	{
		echo '
		<div id="rl_fb_padding">
			<input type="text" name="responsive_lightbox_configuration[fancybox][padding]" value="'.esc_attr($this->options['configuration']['fancybox']['padding']).'" />
			<p class="description">'.__('Space between FancyBox wrapper and content.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_margin()
	{
		echo '
		<div id="rl_fb_margin">
			<input type="text" name="responsive_lightbox_configuration[fancybox][margin]" value="'.esc_attr($this->options['configuration']['fancybox']['margin']).'" />
			<p class="description">'.__('Space between viewport and FancyBox wrapper.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_modal()
	{
		echo '
		<div id="rl_fb_modal" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-modal-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][modal]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['modal'], FALSE).' />
			<label for="rl-fb-modal-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('When true, "overlayShow" is set to TRUE and "hideOnOverlayClick", "hideOnContentClick", "enableEscapeButton", "showCloseButton" are set to FALSE.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_show_overlay()
	{
		echo '
		<div id="rl_fb_show_overlay" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-show-overlay-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][show_overlay]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['show_overlay'], FALSE).' '.disabled($this->options['configuration']['fancybox']['modal'], TRUE, FALSE).' />
			<label for="rl-fb-show-overlay-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Toggle overlay.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_show_close_button()
	{
		echo '
		<div id="rl_fb_show_close_button" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-show-close-button-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][show_close_button]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['show_close_button'], FALSE).' '.disabled($this->options['configuration']['fancybox']['modal'], TRUE, FALSE).' />
			<label for="rl-fb-show-close-button-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Toggle close button.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_enable_escape_button()
	{
		echo '
		<div id="rl_fb_enable_escape_button" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-enable-escape-button-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][enable_escape_button]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['enable_escape_button'], FALSE).' '.disabled($this->options['configuration']['fancybox']['modal'], TRUE, FALSE).' />
			<label for="rl-fb-enable-escape-button-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Toggle if pressing Esc button closes FancyBox.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_hide_on_overlay_click()
	{
		echo '
		<div id="rl_fb_hide_on_overlay_click" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-hide-on-overlay-click-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][hide_on_overlay_click]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['hide_on_overlay_click'], FALSE).' '.disabled($this->options['configuration']['fancybox']['modal'], TRUE, FALSE).' />
			<label for="rl-fb-hide-on-overlay-click-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Toggle if clicking the overlay should close FancyBox.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_hide_on_content_click()
	{
		echo '
		<div id="rl_fb_hide_on_content_click" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-hide-on-content-click-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][hide_on_content_click]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['hide_on_content_click'], FALSE).' '.disabled($this->options['configuration']['fancybox']['modal'], TRUE, FALSE).' />
			<label for="rl-fb-hide-on-content-click-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Toggle if clicking the content should close FancyBox.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_cyclic()
	{
		echo '
		<div id="rl_fb_cyclic" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-cyclic-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][cyclic]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['cyclic'], FALSE).' />
			<label for="rl-fb-cyclic-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('When true, galleries will be cyclic, allowing you to keep pressing next/back.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_show_nav_arrows()
	{
		echo '
		<div id="rl_fb_show_nav_arrows" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-show-nav-arrows-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][show_nav_arrows]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['show_nav_arrows'], FALSE).' />
			<label for="rl-fb-show-nav-arrows-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Toggle navigation arrows.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_auto_scale()
	{
		echo '
		<div id="rl_fb_auto_scale" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-auto-scale-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][auto_scale]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['auto_scale'], FALSE).' />
			<label for="rl-fb-auto-scale-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('If true, FancyBox is scaled to fit in viewport.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_scrolling()
	{
		echo '
		<div id="rl_fb_scrolling" class="wplikebtns">';

		foreach($this->scripts['fancybox']['scrollings'] as $val => $trans)
		{
			echo '
			<input id="rl-fb-scrolling-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][scrolling]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['fancybox']['scrolling'], FALSE).' />
			<label for="rl-fb-scrolling-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Set the overflow CSS property to create or hide scrollbars.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_center_on_scroll()
	{
		echo '
		<div id="rl_fb_center_on_scroll" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-center-on-scroll-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][center_on_scroll]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['center_on_scroll'], FALSE).' />
			<label for="rl-fb-center-on-scroll-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('When true, FancyBox is centered while scrolling page.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_opacity()
	{
		echo '
		<div id="rl_fb_opacity" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-opacity-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][opacity]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['opacity'], FALSE).' />
			<label for="rl-fb-opacity-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('When true, transparency of content is changed for elastic transitions.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_overlay_opacity()
	{
		echo '
		<div id="rl_fb_overlay_opacity">
			<input type="text" id="rl_fb_overlay_opacity_input" class="hide-if-js" name="responsive_lightbox_configuration[fancybox][overlay_opacity]" value="'.esc_attr($this->options['configuration']['fancybox']['overlay_opacity']).'" />
			<div class="wplike-slider">
				<span class="left hide-if-no-js">0</span><span class="middle" id="rl_fb_overlay_opacity_span" title="'.esc_attr($this->options['configuration']['fancybox']['overlay_opacity']).'"></span><span class="right hide-if-no-js">100</span>
			</div>
			<p class="description">'.__('Opacity of the overlay.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_overlay_color()
	{
		echo '
		<div id="rl_fb_overlay_color">
			<input type="text" value="'.esc_attr($this->options['configuration']['fancybox']['overlay_color']).'" id="rl_fb_overlay_color_input" name="responsive_lightbox_configuration[fancybox][overlay_color]" data-default-color="'.$this->defaults['configuration']['fancybox']['overlay_color'].'" />
			<p class="description">'.__('Color of the overlay.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_title_show()
	{
		echo '
		<div id="rl_fb_title_show" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-fb-title-show-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][title_show]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['fancybox']['title_show'], FALSE).' />
			<label for="rl-fb-title-show-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Toggle title.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_title_position()
	{
		echo '
		<div id="rl_fb_title_position" class="wplikebtns">';

		foreach($this->scripts['fancybox']['positions'] as $val => $trans)
		{
			echo '
			<input id="rl-fb-title-position-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][title_position]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['fancybox']['title_position'], FALSE).' />
			<label for="rl-fb-title-position-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('The position of title.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_easings()
	{
		echo '
		<div id="rl_fb_easings" class="wplikebtns">';

		foreach($this->scripts['fancybox']['easings'] as $val => $trans)
		{
			echo '
			<input id="rl-fb-easings-'.$val.'" type="radio" name="responsive_lightbox_configuration[fancybox][easings]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['fancybox']['easings'], FALSE).' />
			<label for="rl-fb-easings-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Easing used for elastic animations.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_speeds()
	{
		echo '
		<div id="rl_fb_speeds">
			<input type="text" value="'.esc_attr($this->options['configuration']['fancybox']['speeds']).'" name="responsive_lightbox_configuration[fancybox][speeds]" />
			<p class="description">'.__('Speed of the fade and elastic transitions, in milliseconds.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_change_speed()
	{
		echo '
		<div id="rl_fb_change_speed">
			<input type="text" value="'.esc_attr($this->options['configuration']['fancybox']['change_speed']).'" name="responsive_lightbox_configuration[fancybox][change_speed]" />
			<p class="description">'.__('Speed of resizing when changing gallery items, in milliseconds.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_change_fade()
	{
		echo '
		<div id="rl_fb_change_fade">
			<input type="text" value="'.esc_attr($this->options['configuration']['fancybox']['change_fade']).'" name="responsive_lightbox_configuration[fancybox][change_fade]" />
			<p class="description">'.__('Speed of the content fading while changing gallery items.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_video_width()
	{
		echo '
		<div id="rl_fb_video_width">
			<input type="text" value="'.esc_attr($this->options['configuration']['fancybox']['video_width']).'" name="responsive_lightbox_configuration[fancybox][video_width]" />
			<p class="description">'.__('Width of the video.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_fb_video_height()
	{
		echo '
		<div id="rl_fb_video_height">
			<input type="text" value="'.esc_attr($this->options['configuration']['fancybox']['video_height']).'" name="responsive_lightbox_configuration[fancybox][video_height]" />
			<p class="description">'.__('Height of the video.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_nv_effect()
	{
		echo '
		<div id="rl_nv_effect" class="wplikebtns">';

		foreach($this->scripts['nivo']['effects'] as $val => $trans)
		{
			echo '
			<input id="rl-nv-effect-'.$val.'" type="radio" name="responsive_lightbox_configuration[nivo][effect]" value="'.esc_attr($val).'" '.checked($val, $this->options['configuration']['nivo']['effect'], FALSE).' />
			<label for="rl-nv-effect-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('The effect to use when showing the lightbox.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_nv_keyboard_nav()
	{
		echo '
		<div id="rl_nv_keyboard_nav" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-nv-keyboard-nav-'.$val.'" type="radio" name="responsive_lightbox_configuration[nivo][keyboard_nav]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['nivo']['keyboard_nav'], FALSE).' />
			<label for="rl-nv-keyboard-nav-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Enable/Disable keyboard navigation (left/right/escape).', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_nv_error_message()
	{
		echo '
		<div id="rl_nv_error_message">
			<input type="text" value="'.esc_attr($this->options['configuration']['nivo']['error_message']).'" name="responsive_lightbox_configuration[nivo][error_message]" />
			<p class="description">'.__('Error message if the content cannot be loaded.', 'responsive-lightbox').'</p>
		</div>';
	}


	/**
	 * Validates settings
	*/
	public function validate_options($input)
	{
		if(isset($_POST['save_rl_settings']))
		{
			//script
			$input['script'] = (isset($input['script']) && in_array($input['script'], array_keys($this->scripts)) ? $input['script'] : $this->defaults['settings']['script']);

			//selector
			$input['selector'] = sanitize_text_field(isset($input['selector']) && $input['selector'] !== '' ? $input['selector'] : $this->defaults['settings']['selector']);

			//enable custom events
			$input['enable_custom_events'] = (isset($input['enable_custom_events']) && in_array($input['enable_custom_events'], array_keys($this->choices)) ? ($input['enable_custom_events'] === 'yes' ? TRUE : FALSE) : $this->defaults['settings']['enable_custom_events']);

			//custom events
			if($input['enable_custom_events'] === TRUE)
			{
				$input['custom_events'] = sanitize_text_field(isset($input['custom_events']) && $input['custom_events'] !== '' ? $input['custom_events'] : $this->defaults['settings']['custom_events']);
			}

			//checkboxes
			$input['galleries'] = (isset($input['galleries']) && in_array($input['galleries'], array_keys($this->choices)) ? ($input['galleries'] === 'yes' ? TRUE : FALSE) : $this->defaults['settings']['galleries']);
			$input['videos'] = (isset($input['videos']) && in_array($input['videos'], array_keys($this->choices)) ? ($input['videos'] === 'yes' ? TRUE : FALSE) : $this->defaults['settings']['videos']);
			$input['image_links'] = (isset($input['image_links']) && in_array($input['image_links'], array_keys($this->choices)) ? ($input['image_links'] === 'yes' ? TRUE : FALSE) : $this->defaults['settings']['image_links']);
			$input['images_as_gallery'] = (isset($input['images_as_gallery']) && in_array($input['images_as_gallery'], array_keys($this->choices)) ? ($input['images_as_gallery'] === 'yes' ? TRUE : FALSE) : $this->defaults['settings']['images_as_gallery']);
			$input['deactivation_delete'] = (isset($input['deactivation_delete']) && in_array($input['deactivation_delete'], array_keys($this->choices)) ? ($input['deactivation_delete'] === 'yes' ? TRUE : FALSE) : $this->defaults['settings']['deactivation_delete']);
		}
		elseif(isset($_POST['save_rl_configuration']))
		{
			if($this->options['settings']['script'] === 'swipebox' && $_POST['script_r'] === 'swipebox')
			{
				//animation
				$input['swipebox']['animation'] = (isset($input['swipebox']['animation']) && in_array($input['swipebox']['animation'], array_keys($this->scripts['swipebox']['animations'])) ? $input['swipebox']['animation'] : $this->defaults['configuration']['swipebox']['animation']);

				//force png icons
				$input['swipebox']['force_png_icons'] = (isset($input['swipebox']['force_png_icons']) && in_array($input['swipebox']['force_png_icons'], array_keys($this->choices)) ? ($input['swipebox']['force_png_icons'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['swipebox']['force_png_icons']);

				//bars
				$input['swipebox']['hide_bars'] = (isset($input['swipebox']['hide_bars']) && in_array($input['swipebox']['hide_bars'], array_keys($this->choices)) ? ($input['swipebox']['hide_bars'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['swipebox']['hide_bars']);
				$input['swipebox']['hide_bars_delay'] = (int)($input['swipebox']['hide_bars_delay'] > 0 ? $input['swipebox']['hide_bars_delay'] : $this->defaults['configuration']['swipebox']['hide_bars_delay']);

				//video width
				$input['swipebox']['video_max_width'] = (int)($input['swipebox']['video_max_width'] > 0 ? $input['swipebox']['video_max_width'] : $this->defaults['configuration']['swipebox']['video_max_width']);
			}
			elseif($this->options['settings']['script'] === 'prettyphoto' && $_POST['script_r'] === 'prettyphoto')
			{
				//animation speed
				$input['prettyphoto']['animation_speed'] = (isset($input['prettyphoto']['animation_speed']) && in_array($input['prettyphoto']['animation_speed'], array_keys($this->scripts['prettyphoto']['animation_speeds'])) ? $input['prettyphoto']['animation_speed'] : $this->defaults['configuration']['prettyphoto']['animation_speed']);

				//slideshows
				$input['prettyphoto']['slideshow'] = (isset($input['prettyphoto']['slideshow']) && in_array($input['prettyphoto']['slideshow'], array_keys($this->choices)) ? ($input['prettyphoto']['slideshow'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['slideshow']);
				$input['prettyphoto']['slideshow_delay'] = (int)($input['prettyphoto']['slideshow_delay'] > 0 ? $input['prettyphoto']['slideshow_delay'] : $this->defaults['configuration']['prettyphoto']['slideshow_delay']);
				$input['prettyphoto']['slideshow_autoplay'] = (isset($input['prettyphoto']['slideshow_autoplay']) && in_array($input['prettyphoto']['slideshow_autoplay'], array_keys($this->choices)) ? ($input['prettyphoto']['slideshow_autoplay'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['slideshow_autoplay']);

				//opacity
				$input['prettyphoto']['opacity'] = (int)$input['prettyphoto']['opacity'];

				if($input['prettyphoto']['opacity'] < 0 || $input['prettyphoto']['opacity'] > 100)
					$input['prettyphoto']['opacity'] = $this->defaults['configuration']['prettyphoto']['opacity'];

				//title
				$input['prettyphoto']['show_title'] = (isset($input['prettyphoto']['show_title']) && in_array($input['prettyphoto']['show_title'], array_keys($this->choices)) ? ($input['prettyphoto']['show_title'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['show_title']);

				//resize
				$input['prettyphoto']['allow_resize'] = (isset($input['prettyphoto']['allow_resize']) && in_array($input['prettyphoto']['allow_resize'], array_keys($this->choices)) ? ($input['prettyphoto']['allow_resize'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['allow_resize']);

				//expand
				$input['prettyphoto']['allow_expand'] = (isset($input['prettyphoto']['allow_expand']) && in_array($input['prettyphoto']['allow_expand'], array_keys($this->choices)) ? ($input['prettyphoto']['allow_expand'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['allow_expand']);

				//dimensions
				$input['prettyphoto']['width'] = (int)($input['prettyphoto']['width'] > 0 ? $input['prettyphoto']['width'] : $this->defaults['configuration']['prettyphoto']['width']);
				$input['prettyphoto']['height'] = (int)($input['prettyphoto']['height'] > 0 ? $input['prettyphoto']['height'] : $this->defaults['configuration']['prettyphoto']['height']);

				//separator
				$input['prettyphoto']['separator'] = sanitize_text_field(isset($input['prettyphoto']['separator']) && $input['prettyphoto']['separator'] !== '' ? $input['prettyphoto']['separator'] : $this->defaults['configuration']['prettyphoto']['separator']);

				//theme
				$input['prettyphoto']['theme'] = (isset($input['prettyphoto']['theme']) && in_array($input['prettyphoto']['theme'], array_keys($this->scripts['prettyphoto']['themes'])) ? $input['prettyphoto']['theme'] : $this->defaults['configuration']['prettyphoto']['theme']);

				//padding
				$input['prettyphoto']['horizontal_padding'] = (int)($input['prettyphoto']['horizontal_padding'] > 0 ? $input['prettyphoto']['horizontal_padding'] : $this->defaults['configuration']['prettyphoto']['horizontal_padding']);

				//flash
				$input['prettyphoto']['hide_flash'] = (isset($input['prettyphoto']['hide_flash']) && in_array($input['prettyphoto']['hide_flash'], array_keys($this->choices)) ? ($input['prettyphoto']['hide_flash'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['hide_flash']);
				$input['prettyphoto']['wmode'] = (isset($input['prettyphoto']['wmode']) && in_array($input['prettyphoto']['wmode'], array_keys($this->scripts['prettyphoto']['wmodes'])) ? $input['prettyphoto']['wmode'] : $this->defaults['configuration']['prettyphoto']['wmode']);

				//video autoplay
				$input['prettyphoto']['video_autoplay'] = (isset($input['prettyphoto']['video_autoplay']) && in_array($input['prettyphoto']['video_autoplay'], array_keys($this->choices)) ? ($input['prettyphoto']['video_autoplay'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['video_autoplay']);

				//modal
				$input['prettyphoto']['modal'] = (isset($input['prettyphoto']['modal']) && in_array($input['prettyphoto']['modal'], array_keys($this->choices)) ? ($input['prettyphoto']['modal'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['modal']);

				//deeplinking
				$input['prettyphoto']['deeplinking'] = (isset($input['prettyphoto']['deeplinking']) && in_array($input['prettyphoto']['deeplinking'], array_keys($this->choices)) ? ($input['prettyphoto']['deeplinking'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['deeplinking']);

				//overlay gallery
				$input['prettyphoto']['overlay_gallery'] = (isset($input['prettyphoto']['overlay_gallery']) && in_array($input['prettyphoto']['overlay_gallery'], array_keys($this->choices)) ? ($input['prettyphoto']['overlay_gallery'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['overlay_gallery']);

				//keyboard shortcuts
				$input['prettyphoto']['keyboard_shortcuts'] = (isset($input['prettyphoto']['keyboard_shortcuts']) && in_array($input['prettyphoto']['keyboard_shortcuts'], array_keys($this->choices)) ? ($input['prettyphoto']['keyboard_shortcuts'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['keyboard_shortcuts']);

				//social
				$input['prettyphoto']['social'] = (isset($input['prettyphoto']['social']) && in_array($input['prettyphoto']['social'], array_keys($this->choices)) ? ($input['prettyphoto']['social'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['prettyphoto']['social']);
			}
			elseif($this->options['settings']['script'] === 'fancybox' && $_POST['script_r'] === 'fancybox')
			{
				//modal
				$input['fancybox']['modal'] = (isset($input['fancybox']['modal']) && in_array($input['fancybox']['modal'], array_keys($this->choices)) ? ($input['fancybox']['modal'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['modal']);

				//show overlay
				$input['fancybox']['show_overlay'] = (isset($input['fancybox']['show_overlay']) && in_array($input['fancybox']['show_overlay'], array_keys($this->choices)) ? ($input['fancybox']['show_overlay'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['show_overlay']);

				//show close button
				$input['fancybox']['show_close_button'] = (isset($input['fancybox']['show_close_button']) && in_array($input['fancybox']['show_close_button'], array_keys($this->choices)) ? ($input['fancybox']['show_close_button'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['show_close_button']);

				//enable escape button
				$input['fancybox']['enable_escape_button'] = (isset($input['fancybox']['enable_escape_button']) && in_array($input['fancybox']['enable_escape_button'], array_keys($this->choices)) ? ($input['fancybox']['enable_escape_button'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['enable_escape_button']);

				//hide on overlay click
				$input['fancybox']['hide_on_overlay_click'] = (isset($input['fancybox']['hide_on_overlay_click']) && in_array($input['fancybox']['hide_on_overlay_click'], array_keys($this->choices)) ? ($input['fancybox']['hide_on_overlay_click'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['hide_on_overlay_click']);

				//hide on content click
				$input['fancybox']['hide_on_content_click'] = (isset($input['fancybox']['hide_on_content_click']) && in_array($input['fancybox']['hide_on_content_click'], array_keys($this->choices)) ? ($input['fancybox']['hide_on_content_click'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['hide_on_content_click']);

				//cyclic
				$input['fancybox']['cyclic'] = (isset($input['fancybox']['cyclic']) && in_array($input['fancybox']['cyclic'], array_keys($this->choices)) ? ($input['fancybox']['cyclic'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['cyclic']);

				//show nav arrows
				$input['fancybox']['show_nav_arrows'] = (isset($input['fancybox']['show_nav_arrows']) && in_array($input['fancybox']['show_nav_arrows'], array_keys($this->choices)) ? ($input['fancybox']['show_nav_arrows'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['show_nav_arrows']);

				//auto scale
				$input['fancybox']['auto_scale'] = (isset($input['fancybox']['auto_scale']) && in_array($input['fancybox']['auto_scale'], array_keys($this->choices)) ? ($input['fancybox']['auto_scale'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['auto_scale']);

				//scrolling
				$input['fancybox']['scrolling'] = (isset($input['fancybox']['scrolling']) && in_array($input['fancybox']['scrolling'], array_keys($this->scripts['fancybox']['scrollings'])) ? $input['fancybox']['scrolling'] : $this->defaults['configuration']['fancybox']['scrolling']);

				//center on scroll
				$input['fancybox']['center_on_scroll'] = (isset($input['fancybox']['center_on_scroll']) && in_array($input['fancybox']['center_on_scroll'], array_keys($this->choices)) ? ($input['fancybox']['center_on_scroll'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['center_on_scroll']);

				//opacity
				$input['fancybox']['opacity'] = (isset($input['fancybox']['opacity']) && in_array($input['fancybox']['opacity'], array_keys($this->choices)) ? ($input['fancybox']['opacity'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['opacity']);

				//title_show
				$input['fancybox']['title_show'] = (isset($input['fancybox']['title_show']) && in_array($input['fancybox']['title_show'], array_keys($this->choices)) ? ($input['fancybox']['title_show'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['fancybox']['title_show']);

				//overlay opacity
				$input['fancybox']['overlay_opacity'] = (int)$input['fancybox']['overlay_opacity'];

				if($input['fancybox']['overlay_opacity'] < 0 || $input['fancybox']['overlay_opacity'] > 100)
					$input['fancybox']['overlay_opacity'] = $this->defaults['configuration']['fancybox']['overlay_opacity'];

				//overlay color
				$input['fancybox']['overlay_color'] = sanitize_text_field($input['fancybox']['overlay_color']);

				//title position
				$input['fancybox']['title_position'] = (isset($input['fancybox']['title_position']) && in_array($input['fancybox']['title_position'], array_keys($this->scripts['fancybox']['positions'])) ? $input['fancybox']['title_position'] : $this->defaults['configuration']['fancybox']['title_position']);

				//transitions
				$input['fancybox']['transitions'] = (isset($input['fancybox']['transitions']) && in_array($input['fancybox']['transitions'], array_keys($this->scripts['fancybox']['transitions'])) ? $input['fancybox']['transitions'] : $this->defaults['configuration']['fancybox']['transitions']);

				//easings
				$input['fancybox']['easings'] = (isset($input['fancybox']['easings']) && in_array($input['fancybox']['easings'], array_keys($this->scripts['fancybox']['easings'])) ? $input['fancybox']['easings'] : $this->defaults['configuration']['fancybox']['easings']);

				//speeds
				$input['fancybox']['speeds'] = (int)($input['fancybox']['speeds'] > 0 ? $input['fancybox']['speeds'] : $this->defaults['configuration']['fancybox']['speeds']);

				//change speed
				$input['fancybox']['change_speed'] = (int)($input['fancybox']['change_speed'] > 0 ? $input['fancybox']['change_speed'] : $this->defaults['configuration']['fancybox']['change_speed']);

				//change fade
				$input['fancybox']['change_fade'] = (int)($input['fancybox']['change_fade'] > 0 ? $input['fancybox']['change_fade'] : $this->defaults['configuration']['fancybox']['change_fade']);
				
				//padding
				$input['fancybox']['padding'] = (int)($input['fancybox']['padding'] > 0 ? $input['fancybox']['padding'] : $this->defaults['configuration']['fancybox']['padding']);

				//margin
				$input['fancybox']['margin'] = (int)($input['fancybox']['margin'] > 0 ? $input['fancybox']['margin'] : $this->defaults['configuration']['fancybox']['margin']);

				//video width
				$input['fancybox']['video_width'] = (int)($input['fancybox']['video_width'] > 0 ? $input['fancybox']['video_width'] : $this->defaults['configuration']['fancybox']['video_width']);

				//video height
				$input['fancybox']['video_height'] = (int)($input['fancybox']['video_height'] > 0 ? $input['fancybox']['video_height'] : $this->defaults['configuration']['fancybox']['video_height']);
			}
			elseif($this->options['settings']['script'] === 'nivo' && $_POST['script_r'] === 'nivo')
			{
				//effect
				$input['nivo']['effect'] = (isset($input['nivo']['effect']) && in_array($input['nivo']['effect'], array_keys($this->scripts['nivo']['effects'])) ? $input['nivo']['effect'] : $this->defaults['configuration']['nivo']['effect']);

				//keyboard navigation
				$input['nivo']['keyboard_nav'] = (isset($input['nivo']['keyboard_nav']) && in_array($input['nivo']['keyboard_nav'], array_keys($this->choices)) ? ($input['nivo']['keyboard_nav'] === 'yes' ? TRUE : FALSE) : $this->defaults['configuration']['nivo']['keyboard_nav']);

				//error message
				$input['nivo']['error_message'] = sanitize_text_field($input['nivo']['error_message']);
			}
			else
			{
				//clear input to not change settings
				$input = array();

				add_settings_error('save_script_settings', 'invalid_script_page', __('Changes were not saved because there was attempt to save settings of inactive script. The site has been reloaded to the proper script settings.', 'responsive-lightbox'), 'error');
			}

			//we have to merge rest of the scripts settings
			$input = array_merge($this->options['configuration'], $input);
		}
		elseif(isset($_POST['reset_rl_settings']))
		{
			$input = $this->defaults['settings'];

			add_settings_error('reset_general_settings', 'general_reset', __('Settings restored to defaults.', 'responsive-lightbox'), 'updated');
		}
		elseif(isset($_POST['reset_rl_configuration']))
		{
			if($this->options['settings']['script'] === 'swipebox' && $_POST['script_r'] === 'swipebox')
			{
				$input['swipebox'] = $this->defaults['configuration']['swipebox'];

				add_settings_error('reset_swipebox_settings', 'swipebox_reset', __('Settings of SwipeBox script were restored to defaults.', 'responsive-lightbox'), 'updated');
			}
			elseif($this->options['settings']['script'] === 'prettyphoto' && $_POST['script_r'] === 'prettyphoto')
			{
				$input['prettyphoto'] = $this->defaults['configuration']['prettyphoto'];

				add_settings_error('reset_prettyphoto_settings', 'prettyphoto_reset', __('Settings of prettyPhoto script were restored to defaults.', 'responsive-lightbox'), 'updated');
			}
			elseif($this->options['settings']['script'] === 'fancybox' && $_POST['script_r'] === 'fancybox')
			{
				$input['fancybox'] = $this->defaults['configuration']['fancybox'];

				add_settings_error('reset_fancybox_settings', 'fancybox_reset', __('Settings of FancyBox script were restored to defaults.', 'responsive-lightbox'), 'updated');
			}
			elseif($this->options['settings']['script'] === 'nivo' && $_POST['script_r'] === 'nivo')
			{
				$input['nivo'] = $this->defaults['configuration']['nivo'];

				add_settings_error('reset_nivo_settings', 'nivo_reset', __('Settings of Nivo script were restored to defaults.', 'responsive-lightbox'), 'updated');
			}
			else
			{
				add_settings_error('reset_script_settings', 'reset_invalid_script_page', __('Changes were not set to defaults because there was attempt to reset settings of inactive script. The site has been reloaded to the proper script settings.', 'responsive-lightbox'), 'error');
			}

			//we have to merge rest of the scripts settings
			$input = array_merge($this->options['configuration'], $input);
		}

		return $input;
	}


	public function admin_menu_options()
	{
		add_options_page(
			__('Responsive Lightbox', 'responsive-lightbox'),
			__('Responsive Lightbox', 'responsive-lightbox'),
			'manage_options',
			'responsive-lightbox',
			array(&$this, 'options_page')
		);
	}


	public function options_page()
	{
		$tab_key = (isset($_GET['tab']) ? $_GET['tab'] : 'general-settings');

		echo '
		<div class="wrap">'.screen_icon().'
			<h2>'.__('Responsive Lightbox', 'responsive-lightbox').'</h2>
			<h2 class="nav-tab-wrapper">';

		foreach($this->tabs as $key => $name)
		{
			echo '
			<a class="nav-tab '.($tab_key == $key ? 'nav-tab-active' : '').'" href="'.esc_url(admin_url('options-general.php?page=responsive-lightbox&tab='.$key)).'">'.$name['name'].'</a>';
		}

		echo '
			</h2>
			<div class="responsive-lightbox-settings">
			
				<div class="df-credits">
					<h3 class="hndle">'.__('Responsive Lightbox', 'responsive-lightbox').' '.$this->defaults['version'].'</h3>
					<div class="inside">
						<h4 class="inner">'.__('Need support?', 'responsive-lightbox').'</h4>
						<p class="inner">'.__('If you are having problems with this plugin, please talk about them in the', 'responsive-lightbox').' <a href="http://www.dfactory.eu/support/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=support" target="_blank" title="'.__('Support forum', 'responsive-lightbox').'">'.__('Support forum', 'responsive-lightbox').'</a></p>
						<hr />
						<h4 class="inner">'.__('Do you like this plugin?', 'responsive-lightbox').'</h4>
						<p class="inner"><a href="http://wordpress.org/support/view/plugin-reviews/responsive-lightbox" target="_blank" title="'.__('Rate it 5', 'responsive-lightbox').'">'.__('Rate it 5', 'responsive-lightbox').'</a> '.__('on WordPress.org', 'responsive-lightbox').'<br />'.
						__('Blog about it & link to the', 'responsive-lightbox').' <a href="http://www.dfactory.eu/plugins/responsive-lightbox/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=blog-about" target="_blank" title="'.__('plugin page', 'responsive-lightbox').'">'.__('plugin page', 'responsive-lightbox').'</a><br />'.
						__('Check out our other', 'responsive-lightbox').' <a href="http://www.dfactory.eu/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=other-plugins" target="_blank" title="'.__('WordPress plugins', 'responsive-lightbox').'">'.__('WordPress plugins', 'responsive-lightbox').'</a>
						</p>            
						<hr />
						<p class="df-link inner">Created by <a href="http://www.dfactory.eu/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=created-by" target="_blank" title="dFactory - Quality plugins for WordPress"><img src="'.plugins_url('/images/logo-dfactory.png' , __FILE__ ).'" title="dFactory - Quality plugins for WordPress" alt="dFactory - Quality plugins for WordPress" /></a></p>
					</div>
				</div>
			
				<form action="options.php" method="post">
					<input type="hidden" name="script_r" value="'.esc_attr($this->options['settings']['script']).'" />';

		wp_nonce_field('update-options');
		settings_fields($this->tabs[$tab_key]['key']);
		do_settings_sections($this->tabs[$tab_key]['key']);

		echo '
					<p class="submit">';

		submit_button('', 'primary', $this->tabs[$tab_key]['submit'], FALSE);

		echo ' ';
		echo submit_button(__('Reset to defaults', 'responsive-lightbox'), 'secondary', $this->tabs[$tab_key]['reset'], FALSE);

		echo '
					</p>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
	}


	public function admin_scripts_styles($page)
	{
		if($page === 'settings_page_responsive-lightbox')
		{
			wp_register_script(
				'responsive-lightbox-admin',
				plugins_url('js/admin.js', __FILE__),
				array('jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-ui-slider', 'wp-color-picker')
			);

			wp_enqueue_script('responsive-lightbox-admin');

			wp_localize_script(
				'responsive-lightbox-admin',
				'rlArgs',
				array(
					'resetSettingsToDefaults' => __('Are you sure you want to reset these settings to defaults?', 'responsive-lightbox'),
					'resetScriptToDefaults' => __('Are you sure you want to reset scripts settings to defaults?', 'responsive-lightbox'),
					'opacity_pp' => $this->options['configuration']['prettyphoto']['opacity'],
					'opacity_fb' => $this->options['configuration']['fancybox']['overlay_opacity']
				)
			);

			wp_enqueue_style('wp-color-picker');

			wp_register_style(
				'responsive-lightbox-admin',
				plugins_url('css/admin.css', __FILE__)
			);

			wp_enqueue_style('responsive-lightbox-admin');

			wp_register_style(
				'responsive-lightbox-wplike',
				plugins_url('css/wp-like-ui-theme.css', __FILE__)
			);

			wp_enqueue_style('responsive-lightbox-wplike');
		}
	}


	public function front_scripts_styles()
	{
		$args = apply_filters('rl_lightbox_args', array(
			'script' => $this->options['settings']['script'],
			'selector' => $this->options['settings']['selector'],
			'custom_events' => ($this->options['settings']['enable_custom_events'] === TRUE ? ' '.$this->options['settings']['custom_events'] : ''),
			'activeGalleries' => $this->getBooleanValue($this->options['settings']['galleries'])
		));

		if($args['script'] === 'prettyphoto')
		{
			wp_register_script(
				'responsive-lightbox-prettyphoto',
				plugins_url('assets/prettyphoto/js/jquery.prettyPhoto.js', __FILE__),
				array('jquery')
			);

			wp_enqueue_script('responsive-lightbox-prettyphoto');

			wp_register_style(
				'responsive-lightbox-prettyphoto-front',
				plugins_url('assets/prettyphoto/css/prettyPhoto.css', __FILE__)
			);

			wp_enqueue_style('responsive-lightbox-prettyphoto-front');

			$args = array_merge(
				$args,
				array(
					'animationSpeed' => $this->options['configuration']['prettyphoto']['animation_speed'],
					'slideshow' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['slideshow']),
					'slideshowDelay' => $this->options['configuration']['prettyphoto']['slideshow_delay'],
					'slideshowAutoplay' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['slideshow_autoplay']),
					'opacity' => sprintf('%.2f', ($this->options['configuration']['prettyphoto']['opacity'] / 100)),
					'showTitle' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['show_title']),
					'allowResize' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['allow_resize']),
					'allowExpand' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['allow_expand']),
					'width' => $this->options['configuration']['prettyphoto']['width'],
					'height' => $this->options['configuration']['prettyphoto']['height'],
					'separator' => $this->options['configuration']['prettyphoto']['separator'],
					'theme' => $this->options['configuration']['prettyphoto']['theme'],
					'horizontalPadding' => $this->options['configuration']['prettyphoto']['horizontal_padding'],
					'hideFlash' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['hide_flash']),
					'wmode' => $this->options['configuration']['prettyphoto']['wmode'],
					'videoAutoplay' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['video_autoplay']),
					'modal' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['modal']),
					'deeplinking' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['deeplinking']),
					'overlayGallery' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['overlay_gallery']),
					'keyboardShortcuts' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['keyboard_shortcuts']),
					'social' => $this->getBooleanValue($this->options['configuration']['prettyphoto']['social'])
				)
			);
		}
		elseif($args['script'] === 'swipebox')
		{
			wp_register_script(
				'responsive-lightbox-swipebox',
				plugins_url('assets/swipebox/source/jquery.swipebox.min.js', __FILE__),
				array('jquery')
			);

			wp_enqueue_script('responsive-lightbox-swipebox');

			wp_register_style(
				'responsive-lightbox-swipebox-front',
				plugins_url('assets/swipebox/source/swipebox.css', __FILE__)
			);

			wp_enqueue_style('responsive-lightbox-swipebox-front');

			$args = array_merge(
				$args,
				array(
					'animation' => $this->getBooleanValue(($this->options['configuration']['swipebox']['animation'] === 'css' ? TRUE : FALSE)),
					'hideBars' => $this->getBooleanValue($this->options['configuration']['swipebox']['hide_bars']),
					'hideBarsDelay' => $this->options['configuration']['swipebox']['hide_bars_delay'],
					'videoMaxWidth' => $this->options['configuration']['swipebox']['video_max_width']
				)
			);

			if($this->options['configuration']['swipebox']['force_png_icons'] === TRUE)
			{
				wp_add_inline_style(
					'responsive-lightbox-swipebox-front',
					'#swipebox-action #swipebox-prev, #swipebox-action #swipebox-next, #swipebox-action #swipebox-close { background-image: url('.plugins_url('assets/swipebox/source/img/icons.png' , __FILE__).') !important; }'
				);
			}
		}
		elseif($args['script'] === 'fancybox')
		{
			wp_register_script(
				'responsive-lightbox-fancybox',
				plugins_url('assets/fancybox/jquery.fancybox-1.3.4.js', __FILE__),
				array('jquery')
			);

			wp_enqueue_script('responsive-lightbox-fancybox');

			wp_register_style(
				'responsive-lightbox-fancybox-front',
				plugins_url('assets/fancybox/jquery.fancybox-1.3.4.css', __FILE__)
			);

			wp_enqueue_style('responsive-lightbox-fancybox-front');

			$args = array_merge(
				$args,
				array(
					'modal' => $this->getBooleanValue($this->options['configuration']['fancybox']['modal']),
					'showOverlay' => $this->getBooleanValue($this->options['configuration']['fancybox']['show_overlay']),
					'showCloseButton' => $this->getBooleanValue($this->options['configuration']['fancybox']['show_close_button']),
					'enableEscapeButton' => $this->getBooleanValue($this->options['configuration']['fancybox']['enable_escape_button']),
					'hideOnOverlayClick' => $this->getBooleanValue($this->options['configuration']['fancybox']['hide_on_overlay_click']),
					'hideOnContentClick' => $this->getBooleanValue($this->options['configuration']['fancybox']['hide_on_content_click']),
					'cyclic' => $this->getBooleanValue($this->options['configuration']['fancybox']['cyclic']),
					'showNavArrows' => $this->getBooleanValue($this->options['configuration']['fancybox']['show_nav_arrows']),
					'autoScale' => $this->getBooleanValue($this->options['configuration']['fancybox']['auto_scale']),
					'scrolling' => $this->options['configuration']['fancybox']['scrolling'],
					'centerOnScroll' => $this->getBooleanValue($this->options['configuration']['fancybox']['center_on_scroll']),
					'opacity' => $this->getBooleanValue($this->options['configuration']['fancybox']['opacity']),
					'overlayOpacity' => $this->options['configuration']['fancybox']['overlay_opacity'],
					'overlayColor' => $this->options['configuration']['fancybox']['overlay_color'],
					'titleShow' => $this->getBooleanValue($this->options['configuration']['fancybox']['title_show']),
					'titlePosition' => $this->options['configuration']['fancybox']['title_position'],
					'transitions' => $this->options['configuration']['fancybox']['transitions'],
					'easings' => $this->options['configuration']['fancybox']['easings'],
					'speeds' => $this->options['configuration']['fancybox']['speeds'],
					'changeSpeed' => $this->options['configuration']['fancybox']['change_speed'],
					'changeFade' => $this->options['configuration']['fancybox']['change_fade'],
					'padding' => $this->options['configuration']['fancybox']['padding'],
					'margin' => $this->options['configuration']['fancybox']['margin'],
					'videoWidth' => $this->options['configuration']['fancybox']['video_width'],
					'videoHeight' => $this->options['configuration']['fancybox']['video_height']
				)
			);
		}
		elseif($args['script'] === 'nivo')
		{
			wp_register_script(
				'responsive-lightbox-nivo',
				plugins_url('assets/nivo/nivo-lightbox.js', __FILE__),
				array('jquery')
			);

			wp_enqueue_script('responsive-lightbox-nivo');

			wp_register_style(
				'responsive-lightbox-nivo-front',
				plugins_url('assets/nivo/nivo-lightbox.css', __FILE__)
			);

			wp_enqueue_style('responsive-lightbox-nivo-front');

			wp_register_style(
				'responsive-lightbox-nivo-front-template',
				plugins_url('assets/nivo/themes/default/default.css', __FILE__)
			);

			wp_enqueue_style('responsive-lightbox-nivo-front-template');

			$args = array_merge(
				$args,
				array(
					'effect' => $this->options['configuration']['nivo']['effect'],
					'keyboardNav' => $this->getBooleanValue($this->options['configuration']['nivo']['keyboard_nav']),
					'errorMessage' => esc_attr($this->options['configuration']['nivo']['error_message'])
				)
			);
		}

		wp_register_script(
			'responsive-lightbox-front',
			plugins_url('js/front.js', __FILE__),
			array('jquery')
		);

		wp_enqueue_script('responsive-lightbox-front');

		wp_add_inline_style(
			'responsive-lightbox-swipebox',
			'#swipebox-action #swipebox-close, #swipebox-action #swipebox-prev, #swipebox-action #swipebox-next { background-image: url(\'assets/swipebox/source/img/icons.png\') !important; }'
		);

		wp_localize_script(
			'responsive-lightbox-front',
			'rlArgs',
			$args
		);
	}


	/**
	 * 
	*/
	private function getBooleanValue($option)
	{
		return ($option === TRUE ? 1 : 0);
	}


	/**
	 * Loads textdomain
	*/
	public function load_textdomain()
	{
		load_plugin_textdomain('responsive-lightbox', FALSE, dirname(plugin_basename(__FILE__)).'/languages/');
	}


	/**
	 * Add links to Support Forum
	*/
	public function plugin_extend_links($links, $file) 
	{
		if(!current_user_can('install_plugins'))
			return $links;

		$plugin = plugin_basename(__FILE__);

		if($file == $plugin) 
		{
			return array_merge(
				$links,
				array(sprintf('<a href="http://www.dfactory.eu/support/forum/responsive-lightbox/" target="_blank">%s</a>', __('Support', 'responsive-lightbox')))
			);
		}

		return $links;
	}


	/**
	 * Add links to Settings page
	*/
	function plugin_settings_link($links, $file) 
	{
		if(!is_admin() || !current_user_can('manage_options'))
			return $links;

		static $plugin;

		$plugin = plugin_basename(__FILE__);

		if($file == $plugin) 
		{
			$settings_link = sprintf('<a href="%s">%s</a>', admin_url('options-general.php').'?page=responsive-lightbox', __('Settings', 'responsive-lightbox'));
			array_unshift($links, $settings_link);
		}

		return $links;
	}
}

$responsive_lightbox = new Responsive_Lightbox();
?>