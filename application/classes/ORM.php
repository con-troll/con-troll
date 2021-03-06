<?php

class ORM extends Kohana_ORM {
	
	protected $_columns = [];
	
	/**
	 * (non-PHPdoc)
	 * @see Kohana_ORM::set()
	 */
	public function set($column, $value) {
		// handle type conversions, if the model specifies it
		$field_def = @$this->_columns[$column];
		if (is_array($field_def)) {
			switch (@$field_def['type']) {
				case 'DateTime':
				case 'DateInterval':
					$value = self::sqlize($value);
					break;
				case 'boolean':
					$value = $value ? '1' : '0';
			}
		}
		
		return parent::set($column, $value);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Kohana_ORM::get()
	 */
	public function get($column) {
		$value = parent::get($column);
		// handle type conversions, if the model specifies it
		$field_def = @$this->_columns[$column];
		if (is_array($field_def)) {
			switch (@$field_def['type']) {
				case 'DateTime':
				case 'DateInterval':
					$value = $this->unsqlize($value);
					break;
				case 'boolean':
					$value = is_numeric($value) ? ($value != 0) : $value;
					break;
			}
		}
		return $value;
	}
	
	/**
	 * convert date/time values to sql date
	 * @param mixed $value
	 */
	public static function sqlize($value) {
		if (is_numeric($value))
			return date("Y-m-d H:i:s", $value);
		if ($value instanceof DateTime)
			return self::sqlize($value->getTimestamp());
		if ($value instanceof DateInterval)
			return self::date_interval_iso_format($value);
		return $value;
	}
	
	/**
	 * Convert SQL date to DateTime value
	 * @param string $value
	 */
	public static function unsqlize($value) {
		if (@$value[0] == 'P') // date interval
			return new DateInterval($value);
		return DateTime::createFromFormat("Y-m-d H:i:s", $value);
	}
	
	public static function date_interval_iso_format(DateInterval $interval) {
		list($date,$time) = explode("T",$interval->format("P%yY%mM%dDT%hH%iM%sS"));
		// now, we need to remove anything that is a zero, but make sure to not remove
		// something like 10D or 20D
		$res =
			str_replace([ 'M0D', 'Y0M', 'P0Y' ], [ 'M', 'Y', 'P' ], $date) .
			rtrim(str_replace([ 'M0S', 'H0M', 'T0H'], [ 'M', 'H', 'T' ], "T$time"),"T");
		if ($res == 'P') // edge case - if we remove everything, DateInterval will hate us later
			return 'PT0S';
		return $res;
	}
	
	public function compile() {
		$this->_build(Database::SELECT);
		return $this->_db_builder->compile();
	}
	
	public function save(Validation $validation = NULL) {
		try {
			return parent::save($validation);
		} catch (Database_Exception $e) {
			if (strstr($e->getMessage(), 'Duplicate entry'))
					throw new Api_Exception_Duplicate(null,"Duplicate " . $this->_table_name);
			throw $e;
		}
	}
	
	public static function gen_slug($title) {
		return strtolower(preg_replace('/[^a-zA-Zא-ת0-9]+/', '-', $title));
	}
	
	/**
	 * Return a JSON friendly array presentation of the data
	 * based on Kohana_ORM#as_array()
	 */
	public function for_json() {
		$ar = $this->as_array();
		$out = [];
		foreach ($ar as $key => $value) {
			if ($value instanceof DateTime) { // format DateTime for consumption
				$value = $value->format(DateTime::ATOM);
			}
			if ($value instanceof DateInterval) { // format DateInterval for consumption
				$value = $this->date_interval_iso_format($value);
			}
			$out[str_replace('_', '-', $key)] = $value;
		}
		return $out;
	}
	
	public function __toString() {
		return '[' . $this->_object_name . ':' . $this->pk() . ']';
	}
	
	/**
	 * Helper call to convert an array or Database_Result to an array of "for_json" objects
	 * @param array|Database_Result $result
	 */
	public static function result_for_json($result, $for_json_method = 'for_json') {
		if ($result instanceof Database_Result)
			$result = $result->as_array();
		if ($result instanceof Generator) {
			$result = iterator_to_array($result);
		}
		return array_map(function(ORM $ent) use($for_json_method) {
			return $ent->{$for_json_method}();
		}, $result);
	}
	
	function cached($lifetime = NULL) {
		$cache_control = @$_SERVER['HTTP_CACHE_CONTROL'];
		if (is_null($cache_control))
			return parent::cached($lifetime);
		if ($cache_control == 'no-cache')
			return $this; // allow client to disable caches
		if (strpos($cache_control, 'max-age') === 0) {
			list($nop, $sec) = explode('=',$cache_control,2);
			$lifetime = min($lifetime, (int)$sec);
		}
		return parent::cached($lifetime);
	}
}
