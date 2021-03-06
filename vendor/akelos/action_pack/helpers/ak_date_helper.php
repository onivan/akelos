<?php

/**
* The Date Helper primarily creates select/option tags for different kinds of dates and date elements. All of the select-type methods
* share a number of common options that are as follows:
*
* * <tt>:prefix</tt> - overwrites the default prefix of "date" used for the select names. So specifying "birthday" would give
*   birthday[month] instead of date[month] if passed to the select_month method.
* * <tt>:include_blank</tt> - set to true if it should be possible to set an empty date.
* * <tt>:discard_type</tt> - set to true if you want to discard the type part of the select name. If set to true, the select_month
*   method would use simply "date" (which can be overwritten using <tt>:prefix</tt>) instead of "date[month]".
*/
class AkDateHelper extends AkBaseHelper
{

    /**
    * Reports the approximate distance in time between two times given in seconds
    * or in a valid ISO string like.
    * For example, if the distance is 47 minutes, it'll return
    * "about 1 hour". See the source for the complete wording list.
    *
    * Integers are interpreted as seconds. So,
    * <tt>$date_helper->distance_of_time_in_words(50)</tt> returns "less than a minute".
    *
    * Set <tt>include_seconds</tt> to true if you want more detailed approximations if distance < 1 minute
    */
    static function distance_of_time_in_words($from_time, $to_time = 0, $include_seconds = false) {
        $from_time = is_numeric($from_time) ? $from_time : Ak::getTimestamp($from_time);
        $to_time = is_numeric($to_time) ? $to_time : Ak::getTimestamp($to_time);
        $distance_in_minutes = round((abs($to_time - $from_time))/60);
        $distance_in_seconds = round(abs($to_time - $from_time));

        if($distance_in_minutes <= 1){
            if($include_seconds){
                if($distance_in_seconds < 5){
                    return Ak::t('less than %seconds seconds',array('%seconds'=>5),'localize/date');
                }elseif($distance_in_seconds < 10){
                    return Ak::t('less than %seconds seconds',array('%seconds'=>10),'localize/date');
                }elseif($distance_in_seconds < 20){
                    return Ak::t('less than %seconds seconds',array('%seconds'=>20),'localize/date');
                }elseif($distance_in_seconds < 40){
                    return Ak::t('half a minute',array(),'localize/date');
                }elseif($distance_in_seconds < 60){
                    return Ak::t('less than a minute',array(),'localize/date');
                }else {
                    return Ak::t('1 minute',array(),'localize/date');
                }
            }
            return ($distance_in_minutes===0) ? Ak::t('less than a minute', array(),'localize/date') : Ak::t('1 minute', array(),'localize/date');
        }elseif ($distance_in_minutes <= 45){
            return Ak::t('%minutes minutes',array('%minutes'=>$distance_in_minutes),'localize/date');
        }elseif ($distance_in_minutes <= 90){
            return Ak::t('about 1 hour',array(),'localize/date');
        }elseif ($distance_in_minutes <= 1440){
            return Ak::t('about %hours hours',array('%hours'=>round($distance_in_minutes/60)),'localize/date');
        }elseif ($distance_in_minutes <= 2880){
            return Ak::t('1 day',array(),'localize/date');
        }else{
            return Ak::t('%days days',array('%days'=>round($distance_in_minutes/1440)),'localize/date');
        }
    }

    /**
      * Like distance_of_time_in_words, but where <tt>to_time</tt> is fixed to <tt>timestamp()</tt>.
      */
    static function time_ago_in_words($from_time, $include_seconds = false) {
        return AkDateHelper::distance_of_time_in_words($from_time, Ak::time(), $include_seconds);
    }
    static function distance_of_time_in_words_to_now($from_time, $include_seconds = false) {
        return AkDateHelper::time_ago_in_words($from_time, $include_seconds);
    }

    /**
      * Returns a set of select tags (one for year, month, and day) pre-selected for accessing a specified date-based attribute (identified by
      * +column_name+) on an object assigned to the template (identified by +object+). It's possible to tailor the selects through the +options+ array,
      * which accepts all the keys that each of the individual select builders do (like 'use_month_numbers' for select_month) as well as a range of
      * discard options. The discard options are <tt>'discard_year'</tt>, <tt>'discard_month'</tt> and <tt>'discard_day'</tt>. Set to true, they'll
      * drop the respective select. Discarding the month select will also automatically discard the day select. It's also possible to explicitly
      * set the order of the tags using the <tt>'order'</tt> option with an array(<tt>'year'</tt>, <tt>'month'</tt> and <tt>'day')</tt> in
      * the desired order.
      *
      * Passing 'disabled' => true as part of the +options+ will make elements inaccessible for change.
      *
      * NOTE: Discarded selects will default to 1. So if no month select is available, January will be assumed.
      *
      * Examples:
      *
      *   $date_helper->date_select("post", "written_on");
      *   $date_helper->date_select("post", "written_on", array('start_year' => 1995));
      *   $date_helper->date_select("post", "written_on", array('start_year' => 1995, 'use_month_numbers' => true,
      *                                     'discard_day' => true, 'include_blank' => true)));
      *   $date_helper->date_select("post", "written_on", array('order' => array('day', 'month', 'year')));
      *   $date_helper->date_select("user", "birthday",   array('order' => array('month', 'day')));
      *
      * The selects are prepared for multi-parameter assignment to an Active Record object.
      */
    public function date_select($object_name, $column_name, $options = array()) {
        $defaults = array('discard_type' => true);
        $options  = array_merge($defaults, $options);

        if(!empty($this->_controller->$object_name) && $column_name[0] != '_' && method_exists($this->_controller->$object_name,$column_name)){
            $date = $this->_controller->$object_name->$column_name();
        }elseif(!empty($this->_controller->$object_name)) {
            $date = $this->_controller->$object_name->get($column_name);
        }elseif(!empty($this->_object[$object_name])){
            $date = $this->_object[$object_name]->get($column_name);
        }

        $date = !empty($options['include_blank']) ? (!empty($date) ? $date : 0) : (!empty($date) ? $date : Ak::getDate());

        $options['order'] = empty($options['order']) ? $this->_get_default_order_for_date_select() : $options['order'];

        $discard = array(
        'year'=>!empty($options['discard_year']),
        'month'=>!empty($options['discard_month']),
        'day'=>!empty($options['discard_day']) || !empty($options['discard_month'])
        );

        $date_select = '';
        $codes = array('year'=>'1i','month'=>'2i','day'=>'3i');
        foreach ($options['order'] as $param){
            if(empty($discard[$param])){
                $helper_method = 'select_'.$param;
                $date_select .= AkDateHelper::$helper_method($date, array_merge($options,array('prefix' => $object_name.'['.$column_name.'('.$codes[$param].')]')))."\n";
            }
        }
        return $date_select;
    }

    static function _get_default_order_for_date_select() {
        $default_order = Ak::locale('long_date_format');
        $tokens = array(
        'year' => array('Y','y', 'o'),
        'month' => array('m','F', 'M', 'n'),
        'day' => array('d','D', 'j', 'l', 'N', 'w', 'z'),
        );

        $date_select_order = array();
        if(preg_match('/[^\w]?([\w])[^\w]?([\w])[^\w]?([\w])[^\w]?/', $default_order, $matches)){
            array_shift($matches);
            foreach ($matches as $match){
                foreach ($tokens as $type => $shotcuts){
                    if(in_array($match, $shotcuts)){
                        $date_select_order[] = $type;
                    }
                }
            }
        }
        return $date_select_order;
    }

    /**
      * Returns a set of select tags (one for year, month, day, hour, and minute) pre-selected for accessing a specified datetime-based
      * attribute (identified by +column_name+) on an object assigned to the template (identified by +object+). Examples:
      *
      *   datetime_select("post", "written_on");
      *   datetime_select("post", "written_on", array('start_year' => 1995));
      *
      * The selects are prepared for multi-parameter assignment to an Active Record object.
      */
    public function datetime_select($object_name, $column_name, $options = array()) {
        $defaults = array('discard_type' => true, 'order'=>explode(',',Ak::t('year,month,day,hour,minute',array(),'localize/date')));
        $options  = array_merge($defaults, $options);

        if(!empty($this->_controller->$object_name) && $column_name[0] != '_' && method_exists($this->_controller->$object_name,$column_name)){
            $date = $this->_controller->$object_name->$column_name();
        }elseif(!empty($this->_controller->$object_name)) {
            $date = $this->_controller->$object_name->get($column_name);
        }elseif(!empty($this->_object[$object_name])) {
            $date = $this->_object[$object_name]->get($column_name);
        }

        $date = !empty($options['include_blank']) ? (!empty($date) ? $date : 0) : (!empty($date) ? $date : Ak::getDate());

        $discard = array(
        'year'  =>!empty($options['discard_year']),
        'month' =>!empty($options['discard_month']),
        'day'   =>!empty($options['discard_day']) || !empty($options['discard_month']),
        'hour'  =>!empty($options['discard_hour']),
        'minute'=>!empty($options['discard_hour']) || !empty($options['discard_minute'])
        );

        $datetime_select = '';
        $codes = array('year'=>'1i','month'=>'2i','day'=>'3i','hour'=>'4i','minute'=>'5i');
        foreach ($options['order'] as $param){
            if(empty($discard[$param])){
                $helper_method = 'select_'.$param;
                $datetime_select .= ($param == 'hour' ? ' &mdash; ' : ($param == 'minute' ? ' : ' : '')).
                AkDateHelper::$helper_method($date, array_merge($options,array('prefix' => $object_name.'['.$column_name.'('.$codes[$param].')]')))."\n";
            }
        }
        return $datetime_select;
    }

    /**
      * Returns a set of html select-tags (one for year, month, and day) pre-selected with the +date+.
      */
    static function select_date($date = null, $options = array()) {
        $date = empty($date) ? Ak::getDate() : $date;
        return AkDateHelper::select_year($date, $options) . AkDateHelper::select_month($date, $options) . AkDateHelper::select_day($date, $options);
    }

    /**
      * Returns a set of html select-tags (one for year, month, day, hour, and minute) pre-selected with the +datetime+.
      */
    static function select_datetime($datetime = null, $options = array()) {
        $datetime = empty($datetime) ? Ak::getDate() : $datetime;
        return AkDateHelper::select_date($datetime, $options) . AkDateHelper::select_time($datetime, $options);
    }

    /**
      * Returns a set of html select-tags (one for hour and minute)
      */
    static function select_time($datetime = null, $options = array()) {
        $datetime = empty($datetime) ? Ak::getDate() : $datetime;
        return AkDateHelper::select_hour($datetime, $options) . AkDateHelper::select_minute($datetime, $options) .
        (!empty($options['include_seconds']) ? AkDateHelper::select_second($datetime, $options) : '');
    }

    /**
      * Returns a select tag with options for each of the seconds 0 through 59 with the current second selected.
      * The <tt>second</tt> can also be substituted for a second number.
      *
      * If you want to customize a bit this select by adding some text in the first option this is the way.
      * The following example will set '-- Select --' as the first option. If date is null or empty first
      * option will be selected, otherwise datetime passed will be selected.
      *   $date_helper->select_second(['' | null | Ak::getDate()], array('prompt' => '-- Select --'));
      *
      * Override the field name using the <tt>field_name</tt> option, 'second' by default.
      */
    static function select_second($datetime, $options = array()) {
        return AkDateHelper::_select_for('second',range(0,59),'s',$datetime, $options);
    }

    /**
      * Returns a select tag with options for each of the minutes 0 through 59 with the current minute selected.
      * Also can return a select tag with options by <tt>minute_step</tt> from 0 through 59 with the 00 minute selected
      * The <tt>minute</tt> can also be substituted for a minute number.
      *
      * If you want to customize a bit this select by adding some text in the first option this is the way.
      * The following example will set '-- Select --' as the first option. If date is null or empty first
      * option will be selected, otherwise datetime passed will be selected.
      *   $date_helper->select_second(['' | null | Ak::getDate()], array('prompt' => '-- Select --'));
      *
      * Override the field name using the <tt>field_name</tt> option, 'minute' by default.
      */
    static function select_minute($datetime, $options = array()) {
        return AkDateHelper::_select_for('minute',range(0,59),'i',$datetime, $options);
    }

    /**
      * Returns a select tag with options for each of the hours 0 through 23 with the current hour selected.
      * The <tt>hour</tt> can also be substituted for a hour number.
      *
      * If you want to customize a bit this select by adding some text in the first option this is the way.
      * The following example will set '-- Select --' as the first option. If date is null or empty first
      * option will be selected, otherwise datetime passed will be selected.
      *   $date_helper->select_hour(['' | null | Ak::getDate()], array('prompt' => '-- Select --'));
      *
      * Override the field name using the <tt>:field_name</tt> option, 'hour' by default
      */
    static function select_hour($datetime, $options = array()) {
        return AkDateHelper::_select_for('hour',range(0,23),'H',$datetime, $options);
    }

    /**
      * Returns a select tag with options for each of the days 1 through 31 with the current day selected.
      * The <tt>date</tt> can also be substituted for a hour number.
      * Override the field name using the <tt>field_name</tt> option, 'day' by default.
      *
      * If you want to customize a bit this select by adding some text in the first option this is the way.
      * The following example will set '-- Select --' as the first option. If date is null or empty first
      * option will be selected, otherwise datetime passed will be selected.
      *   $date_helper->select_day(['' | null | Ak::getDate()], array('prompt' => '-- Select --'));
      *
      */
    static function select_day($date, $options = array()) {
        return AkDateHelper::_select_for('day',range(1,31),'j',$date, $options,false);
    }

    /**
      * Returns a select tag with options for each of the months January through December with the current month selected.
      * The month names are presented as keys (what's shown to the user) and the month numbers (1-12) are used as values
      * (what's submitted to the server). It's also possible to use month numbers for the presentation instead of names --
      * set the <tt>use_month_numbers</tt> key in +options+ to true for this to happen. If you want both numbers and names,
      * set the <tt>add_month_numbers</tt> key in +options+ to true. Examples:
      *
      *   $date_helper->select_month(Ak::getDate()); // Will use keys like "January", "March"
      *   $date_helper->select_month(Ak::getDate(), array('use_month_numbers' => true)); // Will use keys like "1", "3"
      *   $date_helper->select_month(Ak::getDate(), array('add_month_numbers' => true)); // Will use keys like "1 - January", "3 - March"
      *
      * If you want to customize a bit this select by adding some text in the first option this is the way.
      * The following example will set '-- Select --' as the first option. If date is null or empty first
      * option will be selected, otherwise datetime passed will be selected.
      *   $date_helper->select_month(['' | null | Ak::getDate()], array('prompt' => '-- Select --'));
      *
      * Override the field name using the <tt>field_name</tt> option, 'month' by default.
      *
      * If you would prefer to show month names as abbreviations, set the
      * <tt>use_short_month</tt> key in +options+ to true.
      */
    static function select_month($date=null, $options = array()) {

        if(preg_match_all('/(\d{4}\-)?(\d{1,2})(\-\d{1,2})?/', $date, $matches) && !empty($matches[2][0])){
            $date = $matches[2][0];
            $ts = Ak::getTimestamp(isset($date) ? date('Y').'-'.$date.'-01' : null);
            $date = Ak::getDate($ts);
        }

        if(!empty($options['use_month_numbers'])){
            $month_details = '1,2,3,4,5,6,7,8,9,10,11,12';
        }elseif(!empty($options['add_month_numbers']) && empty($options['use_short_month'])){
            $month_details = Ak::t('1 - January,2 - February,3 - March,4 - April,5 - May,6 - June,7 - July,8 - August,'.
            '9 - September,10 - October,11 - November,12 - December',array(),'localize/date');
        }elseif(!empty($options['add_month_numbers']) && !empty($options['use_short_month'])){
            $month_details = Ak::t('1 - Jan,2 - Feb,3 - Mar,4 - Apr,5 - May,6 - Jun,7 - Jul,8 - Aug,9 - Sep,10 - Oct,11 - Nov,12 - Dec',array(),'localize/date');
        }elseif(empty($options['add_month_numbers']) && !empty($options['use_short_month'])){
            $month_details = Ak::t('Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec',array(),'localize/date');
        }else{
            $month_details = Ak::t('January,February,March,April,May,June,July,August,September,October,November,December',array(),'localize/date');
        }

        return AkDateHelper::_select_for('month', explode(',',$month_details),'n', $date, $options,'_add_one');
    }

    /**
      * Returns a select tag with options for each of the five years on each side of the current, which is selected. The five year radius
      * can be changed using the <tt>:start_year</tt> and <tt>:end_year</tt> keys in the +options+. Both ascending and descending year
      * lists are supported by making <tt>start_year</tt> less than or greater than <tt>end_year</tt>. The <tt>date</tt> can also be
      * substituted for a year given as a number. Example:
      *
      *   $date_helper->select_year(Ak::getDate(), array('start_year' => 1992, 'end_year' => 2007)); // ascending year values
      *   $date_helper->select_year(Ak::getDate(), array('start_year' => 2005, 'end_year' => 1900)); //  descending year values
      *
      * If you want to customize a bit this select by adding some text in the first option this is the way.
      * The following example will set '-- Select --' as the first option. If date is null or empty first
      * option will be selected, otherwise datetime passed will be selected.
      *   $date_helper->select_year(['' | null | Ak::getDate()], array('prompt' => '-- Select --'));
      *
      * Override the field name using the <tt>field_name</tt> option, 'year' by default.
      */
    static function select_year($date = null, $options = array()) {
        if(!empty($date)){
            $ts = Ak::getTimestamp(isset($date) ? substr($date, 0, 4).'-01-01' : null);
            $date = Ak::getDate($ts);
            $year = date('Y', $ts);
        }else{
            $year = Ak::getDate(Ak::getTimestamp(isset($date) ? $date.'-01-01' : null),'Y');
        }

        $start_year = !empty($options['start_year']) ? $options['start_year'] : $year-5;
        $end_year = !empty($options['end_year']) ? $options['end_year'] : $year+5;

        $range = range($start_year,$end_year);
        $start_year < $end_year ? array_reverse($range): null;

        return AkDateHelper::_select_for('year',$range,'Y',$date, $options,false);
    }

    static function _select_for($select_type, $range, $date_format, $datetime, $options = array(), $unit_format_callback = '_leading_zero_on_single_digits') {
        $options_array = array();
        if((!empty($options['prompt']) && ($datetime == null || empty($datetime))) || (!empty($options['include_blank']) && $datetime == 0)){
            $datetime_unit = "";
            $date_blank = true;
        }else{
            $datetime = empty($datetime) && empty($options['prompt']) ? Ak::getDate() : $datetime;
            $datetime_unit = Ak::getDate(Ak::getTimestamp($datetime),$date_format);
            $date_blank = false;
        }
        if (!empty($options['prompt'])){
            $options_array[] = '<option value=""'.(empty($datetime_unit) ? ' selected="selected"' : '').'>'.$options['prompt'].'</option>';
        }
        foreach ($range as $k=>$time_unit){
            if(is_string($time_unit)){
                $k = !empty($unit_format_callback) ? AkDateHelper::$unit_format_callback($k) : $k;
                $options_array[] = '<option value="'.$k.'"'.($k == $datetime_unit ? ' selected="selected"' : '').">$time_unit</option>";
            }else{
                $time_unit = !empty($unit_format_callback) ? AkDateHelper::$unit_format_callback($time_unit) : $time_unit;
                $options_array[] = '<option value="'.$time_unit.'"'.($time_unit == $datetime_unit ? ' selected="selected"' : '').">$time_unit</option>";
            }
        }
        return AkDateHelper::_select_html(empty($options['field_name']) ? $select_type : $options['field_name'],
        $options_array, @$options['prefix'], @$options['include_blank'], @$options['discard_type'], @$options['disabled'], $date_blank, @$options['id']);
    }

    static function _select_html($type, $options, $prefix = null, $include_blank = false, $discard_type = false, $disabled = false, $date_blank = false,$id = null) {
        $name = (empty($prefix) ? AK_DATE_HELPER_DEFAULT_PREFIX : $prefix).($discard_type ? '' : $type);
        return '<select name="'.$name.'"'.
        (!empty($id) ? ' id="'.$id.'"' : '').
        ($disabled ? ' disabled="disabled"' : '').">\n".
        ($include_blank && $date_blank ? "<option value=\"\" selected=\"selected\"></option>\n" : '').
        ($include_blank && !$date_blank ? "<option value=\"\"></option>\n" : '').
        (!empty($options) ? join("\n",$options) : '')."\n</select>\n";
    }

    static function _leading_zero_on_single_digits($number) {
        return $number > 9 ? $number : '0'.$number;
    }

    static function _add_one($number) {
        return $number+1;
    }


    /**
     * Converts an ISO date to current locale format
     *
     */
    static function locale_date_time($iso_date_time = null) {
        $timestamp = Ak::getTimestamp($iso_date_time);
        $format = Ak::locale('date_time_format');
        return Ak::getDate($timestamp, $format);
    }

    /**
     * Converts an ISO date to current locale format
     *
     */
    static function locale_date($iso_date = null) {
        $timestamp = Ak::getTimestamp($iso_date);
        $format = Ak::locale('date_format');
        return Ak::getDate($timestamp, $format);
    }
}

