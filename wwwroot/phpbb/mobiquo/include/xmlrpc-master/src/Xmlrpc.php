<?php 

class XmlrpcException extends Exception
{
}

/** 
 * XML-RPC encoder
 *
 * @package     Comodojo Spare Parts
 * @author      Marco Giovinazzi <info@comodojo.org>
 * @license     GPL-3.0+
 *
 * LICENSE:
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class XmlrpcEncoder {

	/**
	 * Request/Response encoding
	 *
	 * @var string
	 */
	private $encoding;

	/**
	 * Response XML header
	 *
	 * @var string
	 */
	private $response_header = '<?xml version="1.0" encoding="__ENCODING__"?><methodResponse />';

	/**
	 * Call XML header
	 *
	 * @var string
	 */
	private $call_header = '<?xml version="1.0" encoding="__ENCODING__"?><methodCall />';

	/**
	 * Constructor method
	 */
	final public function __construct() {

		$this->encoding = defined("XMLRPC_DEFAULT_ENCODING") ? strtolower(XMLRPC_DEFAULT_ENCODING) : 'utf-8';

	}

	/**
	 * Set encoding 
	 *
	 * @param	sting	$encoding
	 * @return	Object	$this 
	 */
	final public function setEncoding($encoding) {

		$this->encoding = strtolower($encoding);

		return $this;

	}

	/**
	 * Get encoding 
	 *
	 * @return	string
	 */
	final public function getEncoding($encoding) {

		return $this->encoding;

	}	

	/**
	 * Encode an xmlrpc response
	 *
	 * It expects a scalar, array or NULL as $data and will try to encode it as a valid xmlrpc response.
	 *
	 * @param	mixed	$data
	 *
	 * @return	string	xmlrpc formatted response
	 *
	 * @throws	XmlrpcException | Exception
	 */
	public function encodeResponse($data) {

		$xml = new SimpleXMLElement(str_replace('__ENCODING__', $this->encoding, $this->response_header));

		$params = $xml->addChild("params");

		$param = $params->addChild("param");

		$value = $param->addChild("value");

		try {
			
			$this->encodeValue($value, $data);

		} catch (XmlrpcException $xe) {
			
			throw $xe;

		} catch (Exception $e) {
			
			throw $e;

		}

		return $xml->asXML();

	}

	/**
	 * Encode an xmlrpc call
	 *
	 * It expects an array of values as $data and will try to encode it as a valid xmlrpc call.
	 *
	 * @param	string	$method
	 * @param	array	$data
	 *
	 * @return	string	xmlrpc formatted call
	 *
	 * @throws	XmlrpcException | Exception
	 */
	public function encodeCall($method,$data) {

		$xml = new SimpleXMLElement(str_replace('__ENCODING__', $this->encoding, $this->call_header));

		$xml->addChild("methodName",trim($method));

		$params = $xml->addChild("params");
		
		foreach ($data as $d) {

			$param = $params->addChild("param");

			$value = $param->addChild("value");

			$this->encodeValue($value, $d);

		}

		return $xml->asXML();

	}

	/**
	 * Encode an xmlrpc error
	 *
	 * @param	int		$error_code
	 * @param	string	$error_message
	 *
	 * @return	string	xmlrpc formatted error
	 */
	public function encodeError($error_message) {
		$payload  = '<?xml version="1.0" encoding="'.$this->encoding.'"?>' . "\n";
		$payload .= "<methodResponse>\n";
		$payload .= "<params>\n";
		$payload .= "<param>\n";
		$payload .= "<value><struct>\n";
		$payload .= "<member><name>result</name>\n";
		$payload .= "<value><boolean>0</boolean></value>\n";
		$payload .= "</member>\n";
		$payload .= "<member>\n";
		$payload .= "<name>result_text</name>\n";
		$payload .= "<value><string>".base64_encode($error_message)."</string></value>\n";
		$payload .= "</member>\n";
		$payload .= "</struct></value>\n";
		$payload .= "</param>\n";
		$payload .= "</params>\n";
		$payload .= "</methodResponse>";
		return $payload;
	}
	/**
	 * Encode a value into SimpleXMLElement object $xml
	 *
	 * @param	SimpleXMLElement	$xml
	 * @param	string				$value
	 *
	 * @throws	XmlrpcException
	 */
	private function encodeValue(SimpleXMLElement $xml, $value) {

		if ( $value === NULL ) {

			$xml->addChild("nil");

		} else if ( is_array($value) ) {
			
			if ( !$this->catchStruct($value) ) $this->encodeArray($xml, $value);

			else $this->encodeStruct($xml, $value);

		} else if ( is_bool($value) ) {

			$xml->addChild("boolean", $value ? 1 : 0);

		} else if ( is_double($value) ) {

			$xml->addChild("double", $value);

		} else if ( is_integer($value) ) {
			
			$xml->addChild("int", $value);

		} else if ( is_object($value) ) {

			$this->encodeObject($xml, $value);

		} else if ( is_string($value) ) {

			$xml->addChild("string", str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $value));

		} else throw new XmlrpcException("Unknown type for encoding");
		
	}

	/**
	 * Encode an array into SimpleXMLElement object $xml
	 *
	 * @param	SimpleXMLElement	$xml
	 * @param	string				$value
	 */
	private function encodeArray(SimpleXMLElement $xml, $value) {
		
		$array = $xml->addChild("array");
		
		$data = $array->addChild("data");
		
		foreach ($value as $entry) {
		
			$val = $data->addChild("value");

			$this->encodeValue($val, $entry);

		}

	}

	/**
	 * Encode an object into SimpleXMLElement object $xml
	 *
	 * @param	SimpleXMLElement	$xml
	 * @param	string				$value
	 *
	 * @throws	XmlrpcException
	 */
	private function encodeObject(SimpleXMLElement $xml, $value) {
		switch ($value->xmlrpc_type)
		{
		    case 'base64':
		        $xml->addChild("base64", base64_encode($value->scalar));
		        break;
		    case 'datetime':
		        $xml->addChild("dateTime.iso8601", $value->scalar);
		        break;
		    default: 
		        return;
		}
	}

	/**
	 * Encode a struct into SimpleXMLElement object $xml
	 *
	 * @param	SimpleXMLElement	$xml
	 * @param	string				$value
	 *
	 * @throws	XmlrpcException
	 */
	private function encodeStruct(SimpleXMLElement $xml, $value) {

		$struct = $xml->addChild("struct");

		foreach ($value as $k => $v) {

			$member = $struct->addChild("member");

			$member->addChild("name", $k);

			$val = $member->addChild("value");

			$this->encodeValue($val, $v);

		}

	}

	/**
	 * Return true if $value is a struct, false otherwise
	 *
	 * @param	mixed	$value
	 *
	 * @return	bool
	 */
	private function catchStruct($value) {

		for ( $i = 0; $i < count($value); $i++ ) if ( !array_key_exists($i, $value) ) return true;

		return false;

	}

	/**
	 * Convert timestamp to Iso8601
	 *
	 * @param	int		$timestamp
	 *
	 * @return	string	Iso8601 formatted date
	 */
	private function timestampToIso8601Time($timestamp) {
	
		return date("Ymd\TH:i:s", $timestamp);

	}

}

/** 
 * XML-RPC decoder
 *
 * @package     Comodojo Spare Parts
 * @author      Marco Giovinazzi <info@comodojo.org>
 * @license     GPL-3.0+
 *
 * LICENSE:
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class XmlrpcDecoder {

	/**
	 * Decode an xmlrpc response
	 *
	 * @param	string	$response
	 *
	 * @return	array
	 *
	 * @throws	XmlrpcException
	 */
	public function decodeResponse($response) {

		$xml_data = simplexml_load_string($response);

		//if ( !isset($xml_data->params) ) throw new XmlrpcException("Uncomprensible response");

		$data = array();

		try {

			foreach ($xml_data->params->param as $param) array_push( $data, $this->decodeValue($param->value) );

		} catch (XmlrpcException $xe) {
			
			throw $xe;
			
		}

		return $data;

	}

	/**
	 * Decode an xmlrpc request
	 *
	 * @param	string	$request
	 *
	 * @return	array	( [method], [data] )
	 *
	 * @throws	XmlrpcException
	 */
	public function decodeCall($request) {

		$xml_data = simplexml_load_string($request);

		if ( !isset($xml_data->methodName) ) throw new XmlrpcException("Uncomprensible request");
			
		$method_name = $this->decodeString($xml_data->methodName[0]);

		$data = array();

		try {
		
			foreach ($xml_data->params->param as $param) $data[] = $this->decodeValue($param->value);

		} catch (XmlrpcException $xe) {
			
			throw $xe;

		}

		return array($method_name, $data);

	}

	/**
	 * Decode an xmlrpc multicall
	 *
	 * @param	string	$request
	 *
	 * @return	array
	 *
	 * @throws	XmlrpcException
	 */
	public function decodeMulticall($request) {

		$xml_data = simplexml_load_string($request);

		if ( !isset($xml_data->methodName) ) throw new XmlrpcException("Uncomprensible multicall request");

		if ( $this->decodeString($xml_data->methodName[0]) != "system.multicall" ) throw new XmlrpcException("Invalid multicall request");

		$data = array();

		try {

			foreach ($xml_data->params->param as $param) {
				
				$children = $param->value->children();

				$child = $children[0];

				$call = $this->decodeArray($child);

				$data[] = array($call['methodName'], $call['params']);

			}

		} catch (XmlrpcException $xe) {
			
			throw $xe;

		}
		
		return $data;

	}

	/**
	 * Decode a value from xmlrpc data
	 *
	 * @param	mixed	$value
	 *
	 * @return	mixed
	 *
	 * @throws	XmlrpcException
	 */
	private function decodeValue($value) {

		$children = $value->children();

		if (count($children) != 1) throw new XmlrpcException("Cannot decode value: invalid value element");

		$child = $children[0];

		$child_type = $child->getName();

		switch ($child_type) {

			case "i4":
			case "int":
				$return_value = $this->decodeInt($child);
			break;

			case "double":
				$return_value = $this->decodeDouble($child);
			break;

			case "boolean":
				$return_value = $this->decodeBool($child);
			break;

			case "base64":
				$return_value = $this->decodeBase($child);
			break;
			
			case "dateTime.iso8601":
				$return_value = $this->decodeIso8601Datetime($child);
			break;

			case "string":
				$return_value = $this->decodeString($child);
			break;

			case "array":
				$return_value = $this->decodeArray($child);
			break;
			
			case "struct":
				$return_value = $this->decodeStruct($child);
			break;
			
			case "nil":
			case "ex:nil":
				$return_value = $this->decodeNil();
			break;
			
			default:
				throw new XmlrpcException("Cannot decode value: invalid value type");
			break;

		}

		return $return_value;

	}

	/**
	 * Decode an XML-RPC <base64> element
	 */
	private function decodeBase($base64) {

		return base64_decode($this->decodeString($base64));

	}

	/**
	 * Decode an XML-RPC <boolean> element
	 */
	private function decodeBool($boolean) {

		return filter_var($boolean, FILTER_VALIDATE_BOOLEAN);

	}

	/**
	 * Decode an XML-RPC <dateTime.iso8601> element
	 */
	private function decodeIso8601Datetime($date_time) {
		
		return strtotime($date_time);

	}

	/**
	 * Decode an XML-RPC <double> element
	 */
	private function decodeDouble($double) {

		return (double)($this->decodeString($double));

	}

	/**
	 * Decode an XML-RPC <int> or <i4> element
	 */
	private function decodeInt($int) {

		return filter_var($int, FILTER_VALIDATE_INT);

	}

	/**
	 * Decode an XML-RPC <string>
	 */
	private function decodeString($string) {

		return (string)$string;

	}

	/**
	 * Decode an XML-RPC <nil/>
	 */
	private function decodeNil() {

		return null;

	}

	/**
	 * Decode an XML-RPC <struct>
	 */
	private function decodeStruct($struct) {

		$return_value = array();

		foreach ($struct->member as $member) {

			$name = $member->name . "";
			$value = $this->decodeValue($member->value);
			$return_value[$name] = $value;

		}

		return $return_value;

	}

	/** 
	 * Decode an XML-RPC <array> element
	 */
	private function decodeArray($array) {

		$return_value = array();

		foreach ($array->data->value as $value) {

			$return_value[] = $this->decodeValue($value);

		}

		return $return_value;

	}

}
