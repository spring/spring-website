<?php 

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

		if ( !isset($xml_data->params) ) throw new XmlrpcException("Uncomprensible response");

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
