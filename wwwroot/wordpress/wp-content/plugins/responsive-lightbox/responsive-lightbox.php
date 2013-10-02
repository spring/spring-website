<?php
/*
Plugin Name: Responsive Lightbox
Description: Responsive Lightbox allows users to view larger versions of images and galleries in a lightbox (overlay) effect optimized for mobile devices.
Version: 1.1.2
Author: dFactory
Author URI: http://www.dfactory.eu/
Plugin URI: http://www.dfactory.eu/plugins/responsive-lightbox/
License: MIT License
License URI: http://opensource.org/licenses/MIT

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
			'deactivation_delete' => FALSE
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
				'hide_bars' => TRUE,
				'hide_bars_delay' => 5000,
				'video_max_width' => 1080
			)
		),
		'version' => '1.0.5'
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
		if(version_compare((($db_version = get_option('responsive_lightbox_version')) === FALSE ? '1.0.0' : $db_version), '1.0.5', '<'))
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

			update_option('responsive_lightbox_version', $this->defaults['version']);
		}

		$this->options['settings'] = array_merge($this->defaults['settings'], (($array = get_option('responsive_lightbox_settings')) === FALSE ? array() : $array));
		$this->options['configuration'] = array_merge($this->defaults['configuration'], (($array = get_option('responsive_lightbox_configuration')) === FALSE ? array() : $array));

		//actions
		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_action('plugins_loaded', array(&$this, 'load_defaults'));
		add_action('admin_init', array(&$this, 'register_settings'));
		add_action('admin_menu', array(&$this, 'admin_menu_options'));
		add_action('wp_enqueue_scripts', array(&$this, 'front_comments_scripts_styles'));
		add_action('admin_enqueue_scripts', array(&$this, 'admin_comments_scripts_styles'));

		//filters
		add_filter('plugin_action_links', array(&$this, 'plugin_settings_link'), 10, 2);
		add_filter('plugin_row_meta', array(&$this, 'plugin_extend_links'), 10, 2);
		add_filter('post_gallery', array(&$this, 'gallery_attributes'), 1000);

		if($this->options['settings']['galleries'] === TRUE)
			add_filter('wp_get_attachment_link', array(&$this, 'add_gallery_lightbox_selector'), 1000, 6);

		if($this->options['settings']['videos'] === TRUE)
			add_filter('the_content', array(&$this, 'add_videos_lightbox_selector'));

		if($this->options['settings']['image_links'] === TRUE)
			add_filter('the_content', array(&$this, 'add_links_lightbox_selector'));
	}


	public function add_videos_lightbox_selector($content)
	{
		preg_match_all('/<a(.*?)href=(?:\'|")(http:\/\/(?:www\.)?((youtube\.com\/watch\?v=[a-z0-9]{11})|(vimeo\.com\/[0-9]{8,})))(?:\'|")(.*?)>/i', $content, $links);

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
				$rel_hash = '[gallery-'.wp_generate_password(4).']';

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
				'submit' => 'save_rl_settings'
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

			if(($activated_blogs = get_site_option('responsive_lightbox_activated', FALSE, FALSE)) === FALSE)
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
		add_settings_field('rl_deactivation_delete', __('Deactivation', 'responsive-lightbox'), array(&$this, 'rl_deactivation_delete'), 'responsive_lightbox_settings', 'responsive_lightbox_settings');

		//configuration
		register_setting('responsive_lightbox_configuration', 'responsive_lightbox_configuration', array(&$this, 'validate_options'));
		add_settings_section('responsive_lightbox_configuration', __('Lightbox settings', 'responsive-lightbox').': '.$this->scripts[$this->options['settings']['script']]['name'], '', 'responsive_lightbox_configuration');

		if($this->options['settings']['script'] === 'swipebox')
		{
			add_settings_field('rl_sw_animation', __('Animation type', 'responsive-lightbox'), array(&$this, 'rl_sw_animation'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_sw_hide_bars', __('Top and bottom bars', 'responsive-lightbox'), array(&$this, 'rl_sw_hide_bars'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_video_max_width', __('Video max width', 'responsive-lightbox'), array(&$this, 'rl_video_max_width'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
		}
		elseif($this->options['settings']['script'] === 'prettyphoto')
		{
			add_settings_field('rl_pp_animation_speed', __('Animation speed', 'responsive-lightbox'), array(&$this, 'rl_pp_animation_speed'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_slideshow', __('Slideshow', 'responsive-lightbox'), array(&$this, 'rl_pp_slideshow'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_slideshow_autoplay', __('Slideshow autoplay', 'responsive-lightbox'), array(&$this, 'rl_pp_slideshow_autoplay'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_opacity', __('Opacity', 'responsive-lightbox'), array(&$this, 'rl_pp_opacity'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_title', __('Show title', 'responsive-lightbox'), array(&$this, 'rl_pp_title'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
			add_settings_field('rl_pp_allow_resize', __('Allow resize big images', 'responsive-lightbox'), array(&$this, 'rl_pp_allow_resize'), 'responsive_lightbox_configuration', 'responsive_lightbox_configuration');
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
	}


	public function rl_script()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->scripts as $val => $trans)
		{
			echo '
			<input id="rl-script-'.$val.'" type="radio" name="responsive_lightbox_settings[script]" value="'.$val.'" '.checked($val, $this->options['settings']['script'], FALSE).' />
			<label for="rl-script-'.$val.'">'.$trans['name'].'</label>';
		}

		echo '
			<p class="description">'.__('Select your preffered ligthbox effect script.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_selector()
	{
		echo '
		<div>
			<input type="text" value="'.$this->options['settings']['selector'].'" name="responsive_lightbox_settings[selector]" />
			<p class="description">'.__('Select to which rel selector lightbox effect will be applied to.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_galleries()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-galleries-'.$val.'" type="radio" name="responsive_lightbox_settings[galleries]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['galleries'], FALSE).' />
			<label for="rl-galleries-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Add lightbox to WordPress image galleries by default', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_videos()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-videos-'.$val.'" type="radio" name="responsive_lightbox_settings[videos]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['videos'], FALSE).' />
			<label for="rl-videos-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Add lightbox to YouTube and Vimeo video links by default', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_image_links()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-image-links-'.$val.'" type="radio" name="responsive_lightbox_settings[image_links]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['image_links'], FALSE).' />
			<label for="rl-image-links-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Add lightbox to WordPress image links by default', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_images_as_gallery()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-images-as-gallery-'.$val.'" type="radio" name="responsive_lightbox_settings[images_as_gallery]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['images_as_gallery'], FALSE).' />
			<label for="rl-images-as-gallery-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display single post images as a gallery', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_deactivation_delete()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-deactivation-delete-'.$val.'" type="radio" name="responsive_lightbox_settings[deactivation_delete]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['deactivation_delete'], FALSE).' />
			<label for="rl-deactivation-delete-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Delete settings on plugin deactivation', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_sw_animation()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->scripts['swipebox']['animations'] as $val => $trans)
		{
			echo '
			<input id="rl-animation-'.$val.'" type="radio" name="responsive_lightbox_configuration[swipebox][animation]" value="'.$val.'" '.checked($val, $this->options['configuration']['swipebox']['animation'], FALSE).' />
			<label for="rl-animation-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select a method of applying a lightbox effect.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_sw_hide_bars()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-hide-bars-'.$val.'" type="radio" name="responsive_lightbox_configuration[swipebox][hide_bars]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['swipebox']['hide_bars'], FALSE).' />
			<label for="rl-hide-bars-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Disable if you don\'t want to top and bottom bars to be hidden after a period of time.', 'responsive-lightbox').'</p>
		</div>
		<div id="rl_sw_hide_bars_delay"'.($this->options['configuration']['swipebox']['hide_bars'] === FALSE ? ' style="display: none;"' : '').'>
			<input type="text" name="responsive_lightbox_configuration[swipebox][hide_bars_delay]" value="'.$this->options['configuration']['swipebox']['hide_bars_delay'].'" />
			<p class="description">'.__('Enter the time after which the top and bottom bars will be hidden (when hiding is enabled).', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_video_max_width()
	{
		echo '
		<div>
			<input type="text" name="responsive_lightbox_configuration[swipebox][video_max_width]" value="'.$this->options['configuration']['swipebox']['video_max_width'].'" />
			<p class="description">'.__('Enter the max video width in a lightbox', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_animation_speed()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->scripts['prettyphoto']['animation_speeds'] as $val => $trans)
		{
			echo '
			<input id="rl-animation-speed-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][animation_speed]" value="'.$val.'" '.checked($val, $this->options['configuration']['prettyphoto']['animation_speed'], FALSE).' />
			<label for="rl-animation-speed-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select animation speed for lightbox effect', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_slideshow()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-slideshow-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][slideshow]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['slideshow'], FALSE).' />
			<label for="rl-slideshow-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display images as slideshow', 'responsive-lightbox').'</p>
		</div>
		<div id="rl_pp_slideshow_delay"'.($this->options['configuration']['prettyphoto']['slideshow'] === FALSE ? ' style="display: none;"' : '').'>
			<input type="text" name="responsive_lightbox_configuration[prettyphoto][slideshow_delay]" value="'.$this->options['configuration']['prettyphoto']['slideshow_delay'].'" />
			<p class="description">'.__('Enter time (in miliseconds)', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_slideshow_autoplay()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-slideshow-autoplay-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][slideshow_autoplay]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['slideshow_autoplay'], FALSE).' />
			<label for="rl-slideshow-autoplay-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Automatically start slideshow', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_opacity()
	{
		echo '
		<div>
			<input type="text" id="rl_pp_opacity_input" class="hide-if-js" name="responsive_lightbox_configuration[prettyphoto][opacity]" value="'.$this->options['configuration']['prettyphoto']['opacity'].'" />
			<div class="wplike-slider">
				<span class="left hide-if-no-js">0</span><span class="middle" id="rl_pp_opacity_span"></span><span class="right hide-if-no-js">100</span>
			</div>
			<p class="description">'.__('Value between 0 and 100, 100 for no opacity', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_title()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-show-title-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][show_title]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['show_title'], FALSE).' />
			<label for="rl-show-title-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display image tiltle', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_allow_resize()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-allow-resize-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][allow_resize]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['allow_resize'], FALSE).' />
			<label for="rl-allow-resize-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Resize the photos bigger than viewport.', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_width()
	{
		echo '
		<div>
			<input type="text" name="responsive_lightbox_configuration[prettyphoto][width]" value="'.$this->options['configuration']['prettyphoto']['width'].'" />
			<p class="description">'.__('in pixels', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_height()
	{
		echo '
		<div>
			<input type="text" name="responsive_lightbox_configuration[prettyphoto][height]" value="'.$this->options['configuration']['prettyphoto']['height'].'" />
			<p class="description">'.__('in pixels', 'responsive-lightbox').'</p>
		</div>';
	}
	

	public function rl_pp_theme()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->scripts['prettyphoto']['themes'] as $val => $trans)
		{
			echo '
			<input id="rl-theme-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][theme]" value="'.$val.'" '.checked($val, $this->options['configuration']['prettyphoto']['theme'], FALSE).' />
			<label for="rl-theme-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select theme for lightbox effect', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_horizontal_padding()
	{
		echo '
		<div>
			<input type="text" name="responsive_lightbox_configuration[prettyphoto][horizontal_padding]" value="'.$this->options['configuration']['prettyphoto']['horizontal_padding'].'" />
			<p class="description">'.__('Horizontal padding (in pixels)', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_hide_flash()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-hide-flash-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][hide_flash]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['hide_flash'], FALSE).' />
			<label for="rl-hide-flash-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Hides all the flash object on a page. Enable this if flash appears over prettyPhoto', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_wmode()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->scripts['prettyphoto']['wmodes'] as $val => $trans)
		{
			echo '
			<input id="rl-wmode-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][wmode]" value="'.$val.'" '.checked($val, $this->options['configuration']['prettyphoto']['wmode'], FALSE).' />
			<label for="rl-wmode-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Select flash window mode', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_video_autoplay()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-video-autoplay-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][video_autoplay]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['video_autoplay'], FALSE).' />
			<label for="rl-video-autoplay-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Automatically start videos', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_modal()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-modal-close-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][modal]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['modal'], FALSE).' />
			<label for="rl-modal-close-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('If set to true, only the close button will close the window', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_deeplinking()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-deeplinking-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][deeplinking]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['deeplinking'], FALSE).' />
			<label for="rl-deeplinking-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Allow prettyPhoto to update the url to enable deeplinking', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_overlay_gallery()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-overlay-gallery-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][overlay_gallery]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['overlay_gallery'], FALSE).' />
			<label for="rl-overlay-gallery-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('If enabled, a gallery will overlay the fullscreen image on mouse over', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_keyboard_shortcuts()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-keyboard-shortcuts-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][keyboard_shortcuts]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['keyboard_shortcuts'], FALSE).' />
			<label for="rl-keyboard-shortcuts-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Set to false if you open forms inside prettyPhoto', 'responsive-lightbox').'</p>
		</div>';
	}


	public function rl_pp_social()
	{
		echo '
		<div class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			echo '
			<input id="rl-social-'.$val.'" type="radio" name="responsive_lightbox_configuration[prettyphoto][social]" value="'.$val.'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['configuration']['prettyphoto']['social'], FALSE).' />
			<label for="rl-social-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Display links to Facebook and Twitter', 'responsive-lightbox').'</p>
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
			$input['script'] = (isset($input['script']) && in_array($input['script'], array_keys($this->scripts)) ? $input['script'] : $this->options['settings']['script']);

			//selector
			$input['selector'] = sanitize_text_field(isset($input['selector']) && $input['selector'] !== '' ? $input['selector'] : $this->options['settings']['selector']);

			//checkboxes
			$input['galleries'] = (isset($input['galleries']) && in_array($input['galleries'], array_keys($this->choices)) ? ($input['galleries'] === 'yes' ? TRUE : FALSE) : $this->options['settings']['galleries']);
			$input['videos'] = (isset($input['videos']) && in_array($input['videos'], array_keys($this->choices)) ? ($input['videos'] === 'yes' ? TRUE : FALSE) : $this->options['settings']['videos']);
			$input['image_links'] = (isset($input['image_links']) && in_array($input['image_links'], array_keys($this->choices)) ? ($input['image_links'] === 'yes' ? TRUE : FALSE) : $this->options['settings']['image_links']);
			$input['images_as_gallery'] = (isset($input['images_as_gallery']) && in_array($input['images_as_gallery'], array_keys($this->choices)) ? ($input['images_as_gallery'] === 'yes' ? TRUE : FALSE) : $this->options['settings']['images_as_gallery']);
			$input['deactivation_delete'] = (isset($input['deactivation_delete']) && in_array($input['deactivation_delete'], array_keys($this->choices)) ? ($input['deactivation_delete'] === 'yes' ? TRUE : FALSE) : $this->options['settings']['deactivation_delete']);
		}
		elseif(isset($_POST['save_rl_configuration']))
		{
			if($this->options['settings']['script'] === 'swipebox' && $_POST['script_r'] === 'swipebox')
			{
				//animation
				$input['swipebox']['animation'] = (isset($input['swipebox']['animation']) && in_array($input['swipebox']['animation'], array_keys($this->scripts['swipebox']['animations'])) ? $input['swipebox']['animation'] : $this->options['configuration']['swipebox']['animation']);

				//hide bars
				$input['swipebox']['hide_bars'] = (isset($input['swipebox']['hide_bars']) && in_array($input['swipebox']['hide_bars'], array_keys($this->choices)) ? ($input['swipebox']['hide_bars'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['swipebox']['hide_bars']);
				$input['swipebox']['hide_bars_delay'] = (int)($input['swipebox']['hide_bars_delay'] > 0 ? $input['swipebox']['hide_bars_delay'] : $this->options['configuration']['swipebox']['hide_bars_delay']);
				$input['swipebox']['video_max_width'] = (int)($input['swipebox']['video_max_width'] > 0 ? $input['swipebox']['video_max_width'] : $this->options['configuration']['swipebox']['video_max_width']);
			}
			elseif($this->options['settings']['script'] === 'prettyphoto' && $_POST['script_r'] === 'prettyphoto')
			{
				//animation speed
				$input['prettyphoto']['animation_speed'] = (isset($input['prettyphoto']['animation_speed']) && in_array($input['prettyphoto']['animation_speed'], array_keys($this->scripts['prettyphoto']['animation_speeds'])) ? $input['prettyphoto']['animation_speed'] : $this->options['configuration']['prettyphoto']['animation_speed']);

				//slideshows
				$input['prettyphoto']['slideshow'] = (isset($input['prettyphoto']['slideshow']) && in_array($input['prettyphoto']['slideshow'], array_keys($this->choices)) ? ($input['prettyphoto']['slideshow'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['slideshow']);
				$input['prettyphoto']['slideshow_delay'] = (int)($input['prettyphoto']['slideshow_delay'] > 0 ? $input['prettyphoto']['slideshow_delay'] : $this->options['configuration']['prettyphoto']['slideshow_delay']);
				$input['prettyphoto']['slideshow_autoplay'] = (isset($input['prettyphoto']['slideshow_autoplay']) && in_array($input['prettyphoto']['slideshow_autoplay'], array_keys($this->choices)) ? ($input['prettyphoto']['slideshow_autoplay'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['slideshow_autoplay']);

				//opacity
				$input['prettyphoto']['opacity'] = (int)$input['prettyphoto']['opacity'];

				if($input['prettyphoto']['opacity'] < 0 || $input['prettyphoto']['opacity'] > 100)
					$input['prettyphoto']['opacity'] = $this->options['configuration']['prettyphoto']['opacity'];

				//title
				$input['prettyphoto']['show_title'] = (isset($input['prettyphoto']['show_title']) && in_array($input['prettyphoto']['show_title'], array_keys($this->choices)) ? ($input['prettyphoto']['show_title'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['show_title']);

				//resize
				$input['prettyphoto']['allow_resize'] = (isset($input['prettyphoto']['allow_resize']) && in_array($input['prettyphoto']['allow_resize'], array_keys($this->choices)) ? ($input['prettyphoto']['allow_resize'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['allow_resize']);

				//dimensions
				$input['prettyphoto']['width'] = (int)($input['prettyphoto']['width'] > 0 ? $input['prettyphoto']['width'] : $this->options['configuration']['prettyphoto']['width']);
				$input['prettyphoto']['height'] = (int)($input['prettyphoto']['height'] > 0 ? $input['prettyphoto']['height'] : $this->options['configuration']['prettyphoto']['height']);

				//separator
				$input['prettyphoto']['separator'] = sanitize_text_field(isset($input['prettyphoto']['separator']) && $input['prettyphoto']['separator'] !== '' ? $input['prettyphoto']['separator'] : $this->options['configuration']['prettyphoto']['separator']);

				//theme
				$input['prettyphoto']['theme'] = (isset($input['prettyphoto']['theme']) && in_array($input['prettyphoto']['theme'], array_keys($this->scripts['prettyphoto']['themes'])) ? $input['prettyphoto']['theme'] : $this->options['configuration']['prettyphoto']['theme']);

				//padding
				$input['prettyphoto']['horizontal_padding'] = (int)($input['prettyphoto']['horizontal_padding'] > 0 ? $input['prettyphoto']['horizontal_padding'] : $this->options['configuration']['prettyphoto']['horizontal_padding']);

				//flash
				$input['prettyphoto']['hide_flash'] = (isset($input['prettyphoto']['hide_flash']) && in_array($input['prettyphoto']['hide_flash'], array_keys($this->choices)) ? ($input['prettyphoto']['hide_flash'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['hide_flash']);
				$input['prettyphoto']['wmode'] = (isset($input['prettyphoto']['wmode']) && in_array($input['prettyphoto']['wmode'], array_keys($this->scripts['prettyphoto']['wmodes'])) ? $input['prettyphoto']['wmode'] : $this->options['configuration']['prettyphoto']['wmode']);

				//video autoplay
				$input['prettyphoto']['video_autoplay'] = (isset($input['prettyphoto']['video_autoplay']) && in_array($input['prettyphoto']['video_autoplay'], array_keys($this->choices)) ? ($input['prettyphoto']['video_autoplay'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['video_autoplay']);

				//modal
				$input['prettyphoto']['modal'] = (isset($input['prettyphoto']['modal']) && in_array($input['prettyphoto']['modal'], array_keys($this->choices)) ? ($input['prettyphoto']['modal'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['modal']);

				//deeplinking
				$input['prettyphoto']['deeplinking'] = (isset($input['prettyphoto']['deeplinking']) && in_array($input['prettyphoto']['deeplinking'], array_keys($this->choices)) ? ($input['prettyphoto']['deeplinking'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['deeplinking']);

				//overlay gallery
				$input['prettyphoto']['overlay_gallery'] = (isset($input['prettyphoto']['overlay_gallery']) && in_array($input['prettyphoto']['overlay_gallery'], array_keys($this->choices)) ? ($input['prettyphoto']['overlay_gallery'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['overlay_gallery']);

				//keyboard shortcuts
				$input['prettyphoto']['keyboard_shortcuts'] = (isset($input['prettyphoto']['keyboard_shortcuts']) && in_array($input['prettyphoto']['keyboard_shortcuts'], array_keys($this->choices)) ? ($input['prettyphoto']['keyboard_shortcuts'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['keyboard_shortcuts']);

				//social
				$input['prettyphoto']['social'] = (isset($input['prettyphoto']['social']) && in_array($input['prettyphoto']['social'], array_keys($this->choices)) ? ($input['prettyphoto']['social'] === 'yes' ? TRUE : FALSE) : $this->options['configuration']['prettyphoto']['social']);
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
		$watermark_settings_page = add_options_page(
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
			<div class="metabox-holder postbox-container responsive-lightbox-settings">
				<form action="options.php" method="post">
					<input type="hidden" name="script_r" value="'.$this->options['settings']['script'].'" />';

		wp_nonce_field('update-options');
		settings_fields($this->tabs[$tab_key]['key']);
		do_settings_sections($this->tabs[$tab_key]['key']);

		echo '
					<p class="submit">';

		submit_button('', 'primary', $this->tabs[$tab_key]['submit'], FALSE);

		echo ' ';
		echo ($tab_key === 'configuration' ? submit_button(__('Reset to defaults', 'responsive-lightbox'), 'secondary', $this->tabs[$tab_key]['reset'], FALSE) : '');

		echo '
					</p>
				</form>
			</div>
			<div class="df-credits postbox-container">
				<h3 class="metabox-title">'.__('Responsive Lightbox', 'responsive-lightbox').'</h3>
				<div class="inner">
					<h3>'.__('Need support?', 'responsive-lightbox').'</h3>
					<p>'.__('If you are having problems with this plugin, please talk about them in the', 'responsive-lightbox').' <a href="http://dfactory.eu/support/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=support" target="_blank" title="'.__('Support forum', 'responsive-lightbox').'">'.__('Support forum', 'responsive-lightbox').'</a></p>
					<hr />
					<h3>'.__('Do you like this plugin?', 'responsive-lightbox').'</h3>
					<p><a href="http://wordpress.org/support/view/plugin-reviews/responsive-lightbox" target="_blank" title="'.__('Rate it 5', 'responsive-lightbox').'">'.__('Rate it 5', 'responsive-lightbox').'</a> '.__('on WordPress.org', 'responsive-lightbox').'<br />'.
					__('Blog about it & link to the', 'responsive-lightbox').' <a href="http://dfactory.eu/plugins/responsive-lightbox/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=blog-about" target="_blank" title="'.__('plugin page', 'responsive-lightbox').'">'.__('plugin page', 'responsive-lightbox').'</a><br />'.
					__('Check out our other', 'responsive-lightbox').' <a href="http://dfactory.eu/plugins/responsive-lightbox/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=other-plugins" target="_blank" title="'.__('WordPress plugins', 'responsive-lightbox').'">'.__('WordPress plugins', 'responsive-lightbox').'</a>
					</p>            
					<hr />
					<p class="df-link">Created by <a href="http://www.dfactory.eu/?utm_source=responsive-lightbox-settings&utm_medium=link&utm_campaign=created-by" target="_blank" title="dFactory - Quality plugins for WordPress"><img src="'.plugins_url('/images/logo-dfactory.png' , __FILE__ ).'" title="dFactory - Quality plugins for WordPress" alt="dFactory - Quality plugins for WordPress" /></a></p>
				</div>
			</div>
			<div class="clear"></div>
		</div>';
	}


	public function admin_comments_scripts_styles($page)
	{
		if($page === 'settings_page_responsive-lightbox')
		{
			wp_enqueue_script(
				'responsive-lightbox-admin',
				plugins_url('js/admin.js', __FILE__),
				array('jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-ui-slider')
			);

			wp_localize_script(
				'responsive-lightbox-admin',
				'rlArgs',
				array(
					'resetScriptToDefaults' => __('Are you sure you want to reset scripts settings to defaults?', 'responsive-lightbox'),
					'opacity' => $this->options['configuration']['prettyphoto']['opacity']
				)
			);

			wp_enqueue_style(
				'responsive-lightbox-admin',
				plugins_url('css/admin.css', __FILE__)
			);

			wp_enqueue_style(
				'responsive-lightbox-wplike',
				plugins_url('css/wp-like-ui-theme.css', __FILE__)
			);
		}
	}


	public function front_comments_scripts_styles()
	{
		$args = array(
			'script' => $this->options['settings']['script'],
			'selector' => $this->options['settings']['selector'],
			'activeGalleries' => $this->getBooleanValue($this->options['settings']['galleries'])
		);

		if($this->options['settings']['script'] === 'prettyphoto')
		{
			wp_enqueue_script(
				'responsive-lightbox-prettyphoto',
				plugins_url('assets/prettyphoto/js/jquery.prettyPhoto.js', __FILE__),
				array('jquery')
			);

			wp_enqueue_style(
				'responsive-lightbox-front',
				plugins_url('assets/prettyphoto/css/prettyPhoto.css', __FILE__)
			);

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
		elseif($this->options['settings']['script'] === 'swipebox')
		{
			wp_enqueue_script(
				'responsive-lightbox-swipebox',
				plugins_url('assets/swipebox/source/jquery.swipebox.min.js', __FILE__),
				array('jquery')
			);

			wp_enqueue_style(
				'responsive-lightbox-front',
				plugins_url('assets/swipebox/source/swipebox.css', __FILE__)
			);

			$args = array_merge(
				$args,
				array(
					'animation' => ($this->options['configuration']['swipebox']['animation'] === 'css' ? TRUE : FALSE),
					'hideBars' => $this->getBooleanValue($this->options['configuration']['swipebox']['hide_bars']),
					'hideBarsDelay' => $this->options['configuration']['swipebox']['hide_bars_delay'],
					'videoMaxWidth' => $this->options['configuration']['swipebox']['video_max_width']
				)
			);
		}

		wp_enqueue_script(
			'responsive-lightbox-front',
			plugins_url('js/front.js', __FILE__),
			array('jquery')
		);

		wp_localize_script('responsive-lightbox-front', 'rlArgs', $args);
	}


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