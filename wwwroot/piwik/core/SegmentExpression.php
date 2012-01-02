<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: SegmentExpression.php 5148 2011-09-11 04:03:30Z matt $
 * 
 * @category Piwik
 * @package Piwik
 */

/**
 *
 * @package Piwik
 */
class Piwik_SegmentExpression
{
    const AND_DELIMITER = ';';
    const OR_DELIMITER = ',';
    
    const MATCH_EQUAL = '==';
    const MATCH_NOT_EQUAL = '!=';
    const MATCH_GREATER_OR_EQUAL = '>=';
    const MATCH_LESS_OR_EQUAL = '<=';
    const MATCH_GREATER = '>';
    const MATCH_LESS = '<';
    const MATCH_CONTAINS = '=@';
    const MATCH_DOES_NOT_CONTAIN = '!@';
    
    const INDEX_BOOL_OPERATOR = 0;
    const INDEX_OPERAND = 1;
    
    function __construct($string)
    {
        $this->string = $string;
        $this->tree = $this->parseTree();
    }
    protected $joins = array();
    protected $valuesBind = array();
    protected $parsedTree = array();
    protected $tree = array();
    
    /**
     * Given the array of parsed filters containing, for each filter, 
     * the boolean operator (AND/OR) and the operand,
     * Will return the array where the filters are in SQL representation
     */
    public function parseSubExpressions()
    {
        $parsedSubExpressions = array();
        foreach($this->tree as $id => $leaf)
        {
            $operand = $leaf[self::INDEX_OPERAND];
            $operator = $leaf[self::INDEX_BOOL_OPERATOR];
            $pattern = '/^(.+?)('	.self::MATCH_EQUAL.'|'
            						.self::MATCH_NOT_EQUAL.'|'
            						.self::MATCH_GREATER_OR_EQUAL.'|'
            						.self::MATCH_GREATER.'|'
            						.self::MATCH_LESS_OR_EQUAL.'|'
            						.self::MATCH_LESS.'|'
            						.self::MATCH_CONTAINS.'|'
            						.self::MATCH_DOES_NOT_CONTAIN
            						.'){1}(.+)/';
            $match = preg_match( $pattern, $operand, $matches );
            if($match == 0)
            {
                throw new Exception('Segment parameter \''.$operand.'\' does not appear to have a valid format.');
            }
//            var_dump($matches);
            
            $leftMember = $matches[1];
            $operation = $matches[2];
            $valueRightMember = $matches[3];
            $parsedSubExpressions[] = array( 
                self::INDEX_BOOL_OPERATOR => $operator,
                self::INDEX_OPERAND => array(
                    $leftMember,
                    $operation, 
                    $valueRightMember, 
            ));
        }
        $this->parsedSubExpressions = $parsedSubExpressions;
        return $parsedSubExpressions;
    }
    
    public function setSubExpressionsAfterCleanup($parsedSubExpressions)
    {
        $this->parsedSubExpressions = $parsedSubExpressions;
    }
    
    public function getSubExpressions()
    {
        return $this->parsedSubExpressions;
    }
    
    public function parseSubExpressionsIntoSqlExpressions(&$availableTables=array())
    {
        $sqlSubExpressions = array();
        $this->valuesBind = array();
        $this->joins = array();
        
        foreach($this->parsedSubExpressions as $leaf)
        {
            $operator = $leaf[self::INDEX_BOOL_OPERATOR];
            $operandDefinition = $leaf[self::INDEX_OPERAND];
            
            $operand = $this->getSqlMatchFromDefinition($operandDefinition, $availableTables);
            
            $this->valuesBind[] = $operand[1];
            $operand = $operand[0];
            $sqlSubExpressions[] = array(
                self::INDEX_BOOL_OPERATOR => $operator,
                self::INDEX_OPERAND => $operand,
                );
        }
        
        $this->tree = $sqlSubExpressions;
    }
    
    /**
     * Given an array representing one filter operand ( left member , operation , right member)
     * Will return an array containing 
     * - the SQL substring, 
     * - the values to bind to this substring
     * 
     */
    // @todo case insensitive?
    protected function getSqlMatchFromDefinition($def, &$availableTables)
    {
    	$field = $def[0];
    	$matchType = $def[1];
        $value = $def[2];
        
        $sqlMatch = '';
        switch($matchType)
        {
        	case self::MATCH_EQUAL:
        		$sqlMatch = '=';
        		break;
        	case self::MATCH_NOT_EQUAL:
        		$sqlMatch = '<>';
        		break;
        	case self::MATCH_GREATER:
        		$sqlMatch = '>';
        		break;
        	case self::MATCH_LESS:
        		$sqlMatch = '<';
        		break;
        	case self::MATCH_GREATER_OR_EQUAL:
        		$sqlMatch = '>=';
        		break;
        	case self::MATCH_LESS_OR_EQUAL:
        		$sqlMatch = '<=';
        		break;
        	case self::MATCH_CONTAINS:
        		$sqlMatch = 'LIKE';
        		$value = '%'.$this->escapeLikeString($value).'%';
        		break;
        	case self::MATCH_DOES_NOT_CONTAIN:
        		$sqlMatch = 'NOT LIKE';
        		$value = '%'.$this->escapeLikeString($value).'%';
        		break;
        	default:
        		throw new Exception("Filter contains the match type '".$matchType."' which is not supported");
        		break;
        }
        
        $sqlExpression = "$field $sqlMatch ?";
        
        $this->checkFieldIsAvailable($field, $availableTables);
        
        return array($sqlExpression, $value);
    }
    
    /**
     * Check whether the field is available
     * If not, add it to the available tables
     */
    private function checkFieldIsAvailable($field, &$availableTables)
    {
        $fieldParts = explode('.', $field);
        
        $table = count($fieldParts) == 2 ? $fieldParts[0] : false;
        
        // remove sql functions from field name
        // example: `HOUR(log_visit.visit_last_action_time)` gets `HOUR(log_visit` => remove `HOUR(` 
        $table = preg_replace('/^[A-Z_]+\(/', '', $table);
        $tableExists = !$table || in_array($table, $availableTables);
        
        if (!$tableExists)
        {
        	$availableTables[] = $table;
        }
    }
    
    private function escapeLikeString($str)
    {
    	$str = str_replace("%", "\%", $str);
    	$str = str_replace("_", "\_", $str);
    	return $str;
    }
    
    /**
     * Given a filter string, 
     * will parse it into an array where each row contains the boolean operator applied to it, 
     * and the operand 
     */
    protected function parseTree()
    {
        $string = $this->string;
        if(empty($string)) {
            return array();
        }
        $tree = array();
        $i = 0;
        $length = strlen($string);
        $isBackslash = false;
        $operand = '';
        while($i <= $length)
        {
            $char = $string[$i];

            $isAND = ($char == self::AND_DELIMITER);
            $isOR = ($char == self::OR_DELIMITER);
            $isEnd = ($length == $i+1);
            
            if($isEnd)
            {
        	    if($isBackslash && ($isAND || $isOR))
        	    {
        	        $operand = substr($operand, 0, -1);
        	    }
                $operand .= $char;
                $tree[] = array(self::INDEX_BOOL_OPERATOR => '', self::INDEX_OPERAND => $operand);
                break;
            }
            
            if($isAND && !$isBackslash)
            {
            	$tree[] = array(self::INDEX_BOOL_OPERATOR => 'AND', self::INDEX_OPERAND => $operand);
            	$operand = '';
        	}
        	elseif($isOR && !$isBackslash)
        	{
        	    $tree[] = array(self::INDEX_BOOL_OPERATOR => 'OR', self::INDEX_OPERAND => $operand);
            	$operand = '';
        	}
        	else
        	{
        	    if($isBackslash && ($isAND || $isOR))
        	    {
        	        $operand = substr($operand, 0, -1);
        	    }
            	$operand .= $char;
        	}
            $isBackslash = ($char == "\\");
            $i++;
        }
        return $tree;
    }
    
    /**
     * Given the array of parsed boolean logic, will return
     * an array containing the full SQL string representing the filter, 
     * the neede joins and the values to bind to the query
     * 
     * @return array SQL Query, Joins and Bind parameters
     */
    public function getSql()
    {
        if(count($this->tree) == 0) 
        {
            throw new Exception("Invalid segment, please specify a valid segment.");
        }
        $bind = array();
        $sql = '';
        $subExpression = false;
        foreach($this->tree as $expression)
        {
            $operator = $expression[self::INDEX_BOOL_OPERATOR];
            $operand = $expression[self::INDEX_OPERAND];
        
            if($operator == 'OR'
                && !$subExpression)
            {
                $sql .= ' (';
                $subExpression = true;
            }
            else
            {
                $sql .= ' ';
            }
            
            $sql .= $operand;
            
            if($operator == 'AND'
                && $subExpression)
            {
                $sql .= ')';
                $subExpression = false;
            }
            
            $sql .= " $operator";
        }
        if($subExpression)
        {
            $sql .= ')';
        }
        return array(
        	'where' => $sql, 
        	'bind' => $this->valuesBind,
        	'join' => implode(' ', $this->joins)
        );
    }
}
