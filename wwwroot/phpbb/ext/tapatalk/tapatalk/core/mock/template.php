<?php
/**
*
* @package phpBB Extension - Acme Demo
* @copyright (c) 2014 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace tapatalk\tapatalk\core\mock;

/**
* Template Mock
* @package phpBB3
*/
class template implements \phpbb\template\template
{
	protected $template_data;

	public function __construct()
	{
	}

	public function get_template_vars()
	{
		return $this->template_data;
	}

	public function clear_cache()
	{
	}

	public function set_filenames(array $filename_array)
	{
	}

	public function get_user_style()
	{
	}

	public function set_style($style_directories = array('styles'))
	{
	}

	public function set_custom_style($names, $paths)
	{
	}

	public function destroy()
	{
	}

	public function destroy_block_vars($blockname)
	{
	}

	public function display($handle)
	{
	}

	public function assign_display($handle, $template_var = '', $return_content = true)
	{
	}

	public function assign_vars(array $vararray)
	{
	}

	public function assign_var($varname, $varval)
	{
	}

	public function append_var($varname, $varval)
	{
	}

	public function assign_block_vars($blockname, array $vararray)
	{
	}

	public function assign_block_vars_array($blockname, array $block_vars_array)
	{
	}

	public function alter_block_array($blockname, array $vararray, $key = false, $mode = 'insert')
	{
	}

	public function get_source_file_for_handle($handle)
	{
	}
}
