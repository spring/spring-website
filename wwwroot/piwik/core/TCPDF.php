<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: TCPDF.php 4679 2011-05-12 04:06:00Z vipsoft $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 * @see libs/tcpdf/tcpdf.php
 */
require_once PIWIK_INCLUDE_PATH . '/libs/tcpdf/tcpdf.php';

/**
 * TCPDF class wrapper.
 *
 * @package Piwik
 */
class Piwik_TCPDF extends TCPDF
{
	protected $footerContent = null;
	protected $currentPageNo = null;

	/**
	 * Render page footer
	 *
	 * @see TCPDF::Footer()
	 */
	function Footer()
	{
		//Don't show footer on the frontPage
		if ($this->currentPageNo > 1)
		{
			$this->SetY(-15);
			$this->SetFont($this->footer_font[0], $this->footer_font[1], $this->footer_font[2]);
			$this->Cell(0, 10, $this->footerContent . Piwik_Translate('PDFReports_Pagination', array($this->getAliasNumPage(), $this->getAliasNbPages())), 0, false, 'C', 0, '', 0, false, 'T', 'M');
		}
	}

	/**
	 * Set current page number
	 */
	function setCurrentPageNo()
	{
		if (empty($this->currentPageNo))
		{
			$this->currentPageNo = 1;
		}
		else
		{
			$this->currentPageNo++;
		}
	}

	/**
	 * Add page to document
	 *
	 * @see TCPDF::AddPage()
	 *
	 * @param string $orientation
	 * @param mixed $format
	 * @param bool $keepmargins
	 * @param bool $tocpage
	 */
	function AddPage($orientation='', $format='', $keepmargins=false, $tocpage=false)
	{
		parent::AddPage($orientation);
		$this->setCurrentPageNo();
	}

	/**
	 * Set footer content
	 *
	 * @param string $footerContent
	 */
	function SetFooterContent($footerContent)
	{
		$this->footerContent = $footerContent;
	}
}
