<?php

class Input {
	
	/**
	 * @var  Request  Request that created the controller
	 */
	private $_request = null;
	
	/**
	 * Whether the request is using JSON REST
	 * @var boolean
	 */
	private $_rest = null;
	
	/**
	 * Input data
	 * @var array
	 */
	private $_data = null;
	const ISSET_DETECTION_MAGIC_VALUE = 0xDE7EC7;
	
	public function __construct($request) {
		$this->_rest = $request->headers('Content-Type') == 'application/json';
		$this->_request = $request;
		$this->_data = ($this->isREST() and !empty($this->_request->body())) ?
				$this->decode() :
				array_merge($this->parse_query_string(), $this->_request->post());
	}
	
	private function decode() {
		Logger::debug("Decoding JSON request: ".$this->_request->body());
		$i = json_decode($this->_request->body(), true);
		if ($i === false or $i === null) throw new Api_Exception_InvalidInput("Invalid JSON input '".$this->_request->body()."'");
		return $i;
	}
	
	public function isREST() {
		return $this->_rest;
	}
	
	public function __get($field) {
		return $this->fetch($field) ?: $this->fetch(str_replace('_', '-', $field));
	}
	
	public function __isset($field) {
		return $this->fetch($field, static::ISSET_DETECTION_MAGIC_VALUE) != static::ISSET_DETECTION_MAGIC_VALUE;
	}
	
	public function fetch($field, $default = null) {
		return Arr::path($this->_data, $field, $default);
	}
	
	public function isset($field) {
		$normalized_field = str_replace('-','_', $field);
		foreach ($this->_data as $key => $value) {
			$normalized_key = str_replace('-','_', $key);
			if ($normalized_field == $normalized_key)
				return true;
		}
		return false;
	}
	
	/**
	 * Retrieve a list of data fields according to the specified list
	 * Only data fields found in the incoming data are retrieved.
	 * Field lookup in insensitive to dash vs. underscore, but return
	 * keys as specified in the input list
	 * @param array $list
	 * @return array data fields found
	 */
	public function getFields($list = []) {
		if (empty($list))
			return $this->_data;
		$out = [];
		foreach ($list as $field)
			if ($this->isset($field))
				$out[$field] = $this->{$field};
		return $out;
	}
	
	public function filterFields($prefix = "") {
		$out = [];
		foreach ($this->_data as $key => $value) {
			if (strpos($key, $prefix) === 0)
				$out[$key] = $value;
		}
		return $out;
	}
	
	private function parse_query_string() {
		return array_reduce(array_map(function($part){
			return array_map(function($kv){ return urldecode($kv); },explode('=', $part,2));
		}, explode('&', $_SERVER['QUERY_STRING'])), function($query, $pair) {
			$query[$pair[0]] = @$pair[1];
			return $query;
		}, []);
	}
}