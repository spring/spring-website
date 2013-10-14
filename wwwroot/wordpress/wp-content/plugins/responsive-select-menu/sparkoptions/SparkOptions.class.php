<?php
/*
 * SevenSpark Options Framework
 * 
 * Copyright Chris Mavricos, SevenSpark
 * http://sevenspark.com
 * 
 * Version 1.0.1
 */
class ResponsiveMenuSelectOptions{
	
	public $id;
	public $title;
	public $menu_page;
	public $menu_type;
	public $parent_slug;
	public $page_title;
	public $menu_title;
	public $capability;
	public $menu_slug;
	
	public $panels;		//array
	public $ops;		//array
	
	
	public $settings;
	public $updated;
	public $options_key;
	
	public $notification;
	public $warning;
	
	public $config;
	
	public $tour;
	
	
	function __construct( $id, $config = array() , $links = array() ){
		
		$this->id = $id;
		$this->config = $config;
		
		if( is_admin() ){
			//$this->initializeMenuPage( $id, $config );
			add_action( 'admin_menu' , array( $this , 'updateSettings' ) , 100 );
			add_action( 'admin_menu' , array( $this , 'initializeMenuPage' ) , 101 );
			
		}
		
		$this->panels = array();
		$this->ops = array();
		
		$this->options_key = self::generateOptionsKey( $this->id );
		
		$this->links = $links;
		
	}
	
	function initializeMenuPage(){
		
		extract( wp_parse_args( $this->config, array(
			
			'type'			=>	'submenu_page',
			'parent_slug'	=>	'options-general.php',
			'page_title'	=>	'Spark Panel Options',
			'menu_title'	=>	'Spark Panel',
			'capability'	=>	'manage_options',
			'menu_slug'		=>	$this->id,
			
		)));
		
		$this->title = $menu_title;
		$this->menu_type = $type;
		$this->parent_slug = $parent_slug;
		$this->page_title = $page_title;
		$this->menu_title = $menu_title;
		$this->menu_slug = $menu_slug;
		$this->capability = $capability;
		
		switch( $this->menu_type ){
			
			case 'submenu_page':
				
				$this->menu_page = add_submenu_page( 
					$this->parent_slug, 
					$this->page_title , //'sparkoptions', 
					$this->menu_title , //'sparkoptions', 
					$this->capability, 
					$this->menu_slug, 
					array( $this, 'show' ) );
					
				break;
			
		}
		
		$this->loadAssets();
		
	}
	
	function loadAssets(){
		add_action("admin_print_styles-{$this->menu_page}", array( $this , 'loadCSS' ) );
		add_action("admin_print_styles-{$this->menu_page}", array( $this , 'loadJS' ) );
	}
	
	function loadCSS(){
		$tmp = plugins_url().'/'.str_replace( basename( __FILE__ ),"",plugin_basename( __FILE__ ));
		wp_enqueue_style('sparkoptions-css', 	$tmp.'/sparkoptions.css', 	false, '1.1', 'all');
		
		do_action( 'sparkoptions_load_css_'.$this->id );
	
	}
	function loadJS(){
		$tmp = plugins_url().'/'.str_replace( basename( __FILE__ ),"",plugin_basename( __FILE__ ));
		wp_enqueue_script( 'jquery' );	// Load jQuery
		wp_enqueue_script( 'sparkoptions-js', 	$tmp.'/sparkoptions.js', 	false, '1.1', 'all');
		
		do_action( 'sparkoptions_load_js_'.$this->id );
	}
	
	
	function show(){
		
		if ( !current_user_can( $this->capability ) ) {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		
		?>
		<div class="wrap spark-controlPanel">
			
			<?php do_action( 'sparkoptions_before_settings_panel_'.$this->id ); ?>
	
			<div class="spark-settings-panel">
				<div class="spark-nav">
					
					<h2><?php echo $this->title; ?></h2>
					<h5>Control Panel</h5>
					
					<ul>
					<?php
						foreach($this->panels as $panel_id => $config){
							?>
						<li><a href="#spark-<?php echo $panel_id; ?>"><?php echo $config['name']; ?></a></li>
							<?php
						}	
						?>
					</ul>
					
					<br/>
					<div class="spark-nav-footer">
						
						<?php 
							foreach( $this->links as $l ):?>
							
							<a href="<?php echo $l['href']; ?>" 
								class="<?php echo $l['class']; ?>" 
								title="<?php echo $l['title']; ?>"
								target="_blank" ><?php echo $l['text']; ?></a>					
												
						<?php 
							endforeach; 
							if( $this->tour ){
								echo $this->tour->resetTourButton();
							}
						?>
						
						<a href="http://sevenspark.com" class="spark-attribution" target="_blank">by SevenSpark</a>
					</div>
					
				</div>
				<div class="spark-panels">
					<form method="post" id="spark-options">
						<?php
						
						$class = '';
						$start_panel = isset( $this->settings['current-panel-id'] ) ? $this->op('current-panel-id') : 'basic-config';
						
						foreach($this->panels as $panel_id => $config){		
							?>
							
							<div id="spark-<?php echo $panel_id; ?>" class="spark-panel">
								
								<?php if( $this->notification && $panel_id == $start_panel ): ?>
									<div class="spark-infobox"><?php echo $this->notification; ?></div>					
								<?php endif; ?>
								
								<?php if( $this->warning && $panel_id == $start_panel ): ?>
									<br/>
									<div class="spark-infobox spark-infobox-warning"><?php echo $this->warning; ?></div>					
								<?php endif; ?>
								
								<h3><?php echo $config['name']; ?></h3>
								
								<?php
								$reset = false;
								foreach( $config['ops'] as $id ){
									
									$op = $this->ops[$id];

									if( $op['type'] == 'reset' ){
										$reset = array( 'id' => $id, 'op' => $op );
										continue;
									}
								
									if($op['type'] == 'header-2') $class = 'wpmega-config-section';	//TODO
									echo $this->showAdminOption( $id, $op, $class );
									if($op['type'] == 'header-2') $class = 'sub-container sub-container-'.$id;
								}
								?>
								
								<input type="submit" name="<?php echo $this->id; ?>-sparkops_submit" value="Save All Settings" class="button save-button"/>

								<?php 
								if( $reset ){
									echo $this->showAdminOption( $reset['id'], $reset['op'], '' );
								}
								?>
							</div>
							<?php
						}
						
						?>
						
						<?php wp_nonce_field( $this->options_key , '_sparkoptions-nonce' ); ?>
						
					</form>
					
				</div> 
				
				
			</div> <!-- end spark-settings-panel -->
		</div> <!-- end spark-controlPanel -->	
	
	<?php
		
		
	}

	function showAdminOption($id, $config, $class=''){
		
		extract( wp_parse_args( $config, array(
			'title'	=>	'',
			'type'	=>	'text',
			'desc'	=>	'',
			'units'	=>	'',
			'ops'	=>	null,
			'default'=> '',
			'special_class'	=>	'',
			'gradient'	=> false,
			'default_all' => 'off',
		)));
		
		$settings = $this->getSettings();
		
		$class.= ' '.$special_class;
			
		$html = '<div id="container-'.$id.'" class="spark-admin-op container-type-'.$type.' '.$class.'">';
		if(!empty($before)) $html.= $before; 
		
		$val = isset( $settings[$id] ) ? $settings[$id] : '';
		if(!is_numeric($val) && empty($val)) $val = $default;		//must check numeric otherwise we can't use 0
		
		$title = '<label class="spark-admin-op-title" for="'.$id.'">'.$title.'</label>';
		$desc = empty($desc) ? '' : '<span class="spark-admin-op-desc">'.$desc.'</span>';
		$units = '<span class="spark-admin-op-units">'.$units.'</span>';
		
		switch($type){
			
			case 'text':
				$html.= $title;
				$html.= '<input type="text" id="'.$id.'" name="'.$id.'" value="'.stripslashes( $val ).'"/>';
				$html.= $units;
				$html.= $desc;
							
				break;
				
			case 'textarea':
				$html.= $title;
				$html.= $desc;
				$html.= '<textarea type="text" id="'.$id.'" name="'.$id.'" >'.stripslashes( $val ).'</textarea>';
				
							
				break;
				
			case 'checkbox':
				
				if(empty($val)) $ischecked = $default == 'on' ? true : false;
				else $ischecked = $val == 'on' ? true : false;			
				
				$html.= $title;
				$html.= '<input type="checkbox" id="'.$id.'" name="'.$id.'" '.checked($ischecked, true, false).'/>';
				$html.= $desc;
				$html.= '<div class="clear"></div>';
				
				break;

			case 'checklist':

				$html.= '<label class="spark-admin-op-title">'.$config['title'].'</label>'; //$title;
				$html.= $desc;
				$html.= '<div class="spark-admin-checklist">';

				if(!is_array($ops)) $ops = $ops();	//if it's not an array it's a function that produces an array

				if(is_array($ops)){

					$val = '';
					$multi = false;

					if( isset( $settings[$id] ) ){
						$val = $settings[$id];
						if( is_array( $val ) ){
							$multi = true;
						}
					}
					//print_r( $val );

					$k = 0;
					foreach($ops as $opVal => $op){
						//echo "val = $val";
						$checked = '';
						if( $multi ){
							$checked = in_array( $opVal, $val ) ? 'checked="checked"' : '';
						}
						else $checked = $opVal == $val ? 'checked="checked"' : '';
						$input_id = $id.'-'.$k;
						$html.= '<label class="spark-admin-op-title" for="'.$input_id.'">'.$op.'</label> <input type="checkbox" value="'.$opVal.'" '.$checked.' name="'.$id.'[]" id="'.$input_id.'" />';
						$k++;
					}
				}

				$html.= '</div>';

				break;

				
			case 'select':
				
				$html.= '<label class="spark-admin-op-title">'.$config['title'].'</label>'; //$title;

				$html.= '<select id="'.$id.'" name="'.$id.'" >';
				
				if(!is_array($ops)) $ops = $ops();	//if it's not an array it's a function that produces an array
				
				if(is_array($ops)){
					foreach($ops as $opVal => $op){
						$selected = $opVal == $val ? 'selected="selected"' : '';
						
						$html.= '<option value="'.$opVal.'" '.$selected.' >'.$op.'</option>';
					}
				}
				
				$html.= '</select>';
				
				break;
				
			case 'radio':
				
				$html.= $title;
				if(is_array($ops)){
					foreach($ops as $opVal => $op){
						
						$ischecked = $val == $opVal ? true : false;
						
						$html.= '<div class="spark-admin-op-radio">';
						$html.= '<input type="radio" id="'.$id.'_'.$opVal.'" name="'.$id.'" value="'.$opVal.'" '.checked($ischecked, true, false).' />';
						$html.= '<label for="'.$id.'_'.$opVal.'">'.$op.'</label>';
						$html.= '</div>';
					}
				}			
				break;
				
			case 'color':
				$html.= $title;
				$html.= '<input class="colorPicker-color" type="text" id="'.$id.'" name="'.$id.'" value="'.$val.'" />';
				
				if( $gradient ){
					$c2 = isset( $settings[$id.'-color2'] ) ? $settings[$id.'-color2'] : '';
					$html.= '<input class="colorPicker-color colorPicker-color2" type="text" id="'.$id.'-color2" name="'.$id.'-color2" value="'.$c2.'" />';
				}
				
				$html.= '<span class="clearColor" title="Clear" >&nbsp;</span>';
				
				$html.= '<span class="ss-admin-op-desc">'.$desc.'</span>';
				break;
				
			case 'header':
				$html.= '<h3>'.$title.'</h3>';
				break;
				
			case 'header-2':
				$html.= '<h4>'.$title.'</h4>';
				break;
			
			case 'infobox':
				$html.= '<div class="spark-infobox '.$special_class.'">';
				if($config['title'] != '') $html.= '<h4>'.$title.'</h4>';
				$html.= $desc.'</div>';
				
				break;
				
			case 'reset':
				$html.= $title;
				$html.= '<input type="submit" id="'.$id.'" name="'.$this->id.'-reset-options" value="Reset" class="button reset-button" 
							onClick="return confirm(\'WARNING: Are you sure you want to reset all UberMenu options?\');" />';
				$html.= $desc;
							
				break;
			
			case 'flush-rewrite':
				$html.= $title;
				$html.= '<input type="submit" id="'.$id.'" name="'.$this->id.'-flush-rewrite" value="Flush Rewrite Rules" class="button reset-button" 
							/>';
				$html.= $desc;
							
				break;	
				
			case 'custom':
				$html.= call_user_func( array( $this, $config['func'] ) );
				break;

			case '_custom':
				$html.= call_user_func( $config['func'] );
				break;
				
			case 'hidden':
				$html.= '<input type="hidden" id="'.$id.'" name="'.$id.'" value="'.stripslashes( $val ).'"/>';
				break;


			
		}
		$html.= '</div>';
		
		return $html;
	}
	
	
	
	/* INPUT TYPES */
	
	/**
	 * Add a text input
	 * 
	 * @param panel_id String - the ID of the panel to add the option to
	 * @param id String - the ID of this option
	 * @param title String - the label for the text input
	 * @param desc String - the description of the option
	 * @param special_class String - the class to add to the dialog, like 'spark-infobox-warning'
	 */
	function addTextInput( $panel_id, $id, $title , $desc = '' , $default = '' , $special_class = '' , $units = '' ){
		
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'default'	=>	$default,
			'special_class' => $special_class,
			'units'		=>	$units,			
			
			'type'		=>	'text',
		);
		
		$this->addToPanel( $panel_id , $id );
	}

	function addTextArea( $panel_id, $id, $title , $desc = '' , $default = '' , $special_class = '' ){
		
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'default'	=>	$default,
			'special_class' => $special_class,		
			
			'type'		=>	'textarea',
		);
		
		$this->addToPanel( $panel_id , $id );
	}
	
	function addCheckbox( $panel_id , $id , $title , $desc = '' , $default = 'off' , $special_class = '' ){
		
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'default'	=>	$default,
			'special_class' => $special_class,	
			
			'type'		=>	'checkbox',
		);
		
		$this->addToPanel( $panel_id , $id );
		
	}

	function addChecklist( $panel_id , $id , $title , $desc = '' , $ops = array(), $default = '' , $default_all='off', $special_class = '' ){
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'ops'		=>	$ops,
			'default'	=>	$default,
			'default_all' => $default_all,
			'special_class' => $special_class,	

			'type'		=>	'checklist',
		);
		
		$this->addToPanel( $panel_id , $id );
	}
	
	function addRadio( $panel_id , $id , $title , $desc = '' , $ops = array() , $default = '' , $special_class = '' ){
				
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'ops'		=>	$ops,
			'default'	=>	$default,
			'special_class' => $special_class,	
			
			'type'		=>	'radio',
		);
		
		$this->addToPanel( $panel_id , $id );

	}
	
	function addSelect( $panel_id , $id , $title , $desc = '' , $ops = array() , $default = '' , $special_class = '' ){
				
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'ops'		=>	$ops,
			'default'	=>	$default,
			'special_class' => $special_class,	
			
			'type'		=>	'select',
		);
		
		$this->addToPanel( $panel_id , $id );

	}
	
	function addColorPicker( $panel_id , $id , $title , $desc = '' , $gradient = true ){
		
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'gradient'	=>	$gradient,
			
			'type'		=>	'color',
		);
		
		$this->addToPanel( $panel_id , $id );
		
	}
	
	function addSubHeader( $panel_id, $id, $title, $desc = '', $special_class = '' ){
			
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'special_class' => $special_class,	
			
			'type'		=>	'header-2',
		);
		
		$this->addToPanel( $panel_id , $id );
	}
	
	/**
	 * Add an information box
	 * 
	 * @param panel_id string - the ID of the panel to add the option to
	 * @param id string - the ID of this option
	 * @param title string - the title of the dialog
	 * @param desc string - the text of the dialog
	 * @param special_class - the class to add to the dialog, like 'spark-infobox-warning'
	 */
	function addInfobox( $panel_id , $id , $title , $desc = '' , $special_class = '' ){
		
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'special_class' => $special_class,	
			
			'type'		=>	'infobox',
		);
		
		$this->addToPanel( $panel_id , $id );
		
	}
	
	function addResetButton( $panel_id , $id , $title , $desc = '' , $special_class = '' ){
		
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'special_class' => $special_class,	
			
			'type'		=>	'reset',
		);
		
		$this->addToPanel( $panel_id , $id );
		
	}
	
	function addFlushRewriteButton( $panel_id , $id , $title , $desc = '' , $special_class = '' ){
		
		$this->ops[$id] = array(
			'title'		=>	$title,
			'desc'		=>	$desc,
			'special_class' => $special_class,	
			
			'type'		=>	'flush-rewrite',
		);
		
		$this->addToPanel( $panel_id , $id );
		
	}
	
	function addCustomField( $panel_id , $id , $func ){
		
		$this->ops[$id] = array(
			'func'		=>	$func,
			'type'		=>	'custom',
		);
		
		$this->addToPanel( $panel_id , $id );
		
	}

	function addCustom( $panel_id , $id , $func ){
		
		$this->ops[$id] = array(
			'func'		=>	$func,
			'type'		=>	'_custom',
		);
		
		$this->addToPanel( $panel_id , $id );
		
	}
	
	function addHidden( $panel_id , $id , $value ){
		
		$this->ops[$id] = array(
			'type'		=>	'hidden',
			'default'	=>	$value
		);
		
		$this->addToPanel( $panel_id , $id );
	}
	
	
	function addToPanel( $panel_id , $option_id ){
		
		if( !isset( $this->panels[$panel_id] ) ){
			//echo "panel $panel_id not registered";
			return;
		}		
		
		$this->panels[$panel_id]['ops'][] = $option_id;
		
	}
	
	function registerPanel( $panel_id , $name ){
		
		$this->panels[$panel_id] = array();
		$this->panels[$panel_id]['name'] = $name;
		$this->panels[$panel_id]['ops'] = array();
		
	}

	function getSettings(){
		
		if( !$this->settings ){
			$this->settings = get_option( $this->options_key );
			$this->settings = apply_filters( $this->id.'_settings_filter' , $this->settings );
		}
		
		return $this->settings;
	}
	
	function op( $id ){
		
		$this->getSettings();
		
		//return the value or the default
		$val;
		if( isset( $this->settings[$id] ) ){
			$val = $this->settings[$id];
		}
		else if( isset( $this->ops[$id]['default'] ) ){
			$val = $this->ops[$id]['default'];
		}
		//this option doesn't exist, or doesn't have a default
		else{ 
			return '';
		}
		
		//translate to true/false for checkboxes
		
		switch( $this->ops[$id]['type'] ){
			case 'checkbox':
				return $val == 'on' ? true : false;
				break;
				
			case 'hidden':
				if( $val == 'on') return true;
				else if( $val == 'off' ) return false;
				return $val;
			
			case 'textarea':
				return stripslashes( $val );
				break;
				
			case 'text':
				return stripslashes( $val );
				break;
		}
		
		return $val;
		
	}
	
	
	
	
	function updateSettings(){
		
		//Resetting Options
		if( isset( $_POST[$this->id.'-reset-options'] ) ){
			
			if( check_admin_referer( $this->options_key , '_sparkoptions-nonce' ) ){
			
				delete_option( $this->options_key );
				$this->settings = array();	//reset the local settings
				$this->notification = "Settings reset to factory defaults!";
				
			}
			
		}
		
		//Flush rewrite rules
		if( isset( $_POST[$this->id.'-flush-rewrite'] ) ){
		
			if( check_admin_referer( $this->options_key , '_sparkoptions-nonce' ) ){
			
				flush_rewrite_rules( true );
				$this->notification = "Rewrite rules flushed!";
				
			}
			
		}
		
		
		//Only do this on form submission
		if( !isset( $_POST[$this->id.'-sparkops_submit'] ) ) return false;
		
		if( !check_admin_referer( $this->options_key , '_sparkoptions-nonce' ) ){
			//Can't ever actually reach here, as function will die above if nonce is invalid
			die( 'No can dosville, baby' );
			return false;
		}
		
		
		// go through settings, if checkbox and not set, set to 'off'		
		$saveOps = array();
			
		foreach( $this->ops as $key => $o ){
			
			$val = isset( $_POST[$key] ) ? $_POST[$key] : '';
		
			switch( $o['type'] ){
				case 'checkbox':
					if(empty($val)) $val = 'off'; // empty($o['default']) ? 'off' : $o['default']; Don't set to default or we can never have 'off'
					break;
				case 'header':
				case 'header-2':
				case 'infobox':
					continue;
					break;
				case 'color':
					if( isset( $_POST[$key.'-color2'] ) ){
						$saveOps[$key.'-color2'] = $_POST[$key.'-color2'];
					}
					break;
			}
			
			$saveOps[$key] = $val;
			
		}

		
		$this->settings = $saveOps;		//setup new settings, in case getSettings() has already been run
		
		//Give the plugin a go to do something extra special
		do_action( 'sparkoptions_update_settings_'.$this->id , $saveOps );
		
		//Here is where we actually update all the Settings 
		update_option( $this->options_key , $this->settings );
		
		//Notify user of great success!
		$this->notification = "Settings saved!";
				
		return true;
		
	}
	
	public static function generateOptionsKey( $id ){
		return 'sparkops_'.$id;
	}
	
	function addTour( $tour ){
		$this->tour = $tour;
	}
	
}
