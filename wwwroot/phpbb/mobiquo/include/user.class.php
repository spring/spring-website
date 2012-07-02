<?php

defined('IN_MOBIQUO') or exit;

class tapa_user extends user
{
    var $time_format = 'Ymd\TH:i:s';
    
    /**
    *
    * @return iso8601 time
    */
    function format_date($gmepoch, $format = false, $forcedate = false)
    {
        // Zone offset
        $zone_offset = $this->timezone + $this->dst;
        $timezone = $this->data['user_timezone'];
        $time = @gmdate($this->time_format, $gmepoch + $zone_offset);
        $time .= sprintf("%+03d:%02d", intval($timezone), abs($timezone - intval($timezone)) * 60);
        
        return $time;
    }
}