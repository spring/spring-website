/**
 * Piwik - Open source web analytics
 * 
 * @license released under BSD License http://www.opensource.org/licenses/bsd-license.php
 * @version $Id: PiwikException.java 5079 2011-08-07 18:58:40Z vipsoft $
 * @link http://piwik.org/docs/tracking-api/
 *
 * @category Piwik
 * @package PiwikTracker
 */
package org.piwik;

/**
 *
 * @author Martin Fochler
 * @version 1.0
 */
public class PiwikException extends Exception {

    public PiwikException(final String message) {
        super(message);
    }

    public PiwikException(final String message, final Throwable e) {
        super(message,e);
    }
}
