<?php

class RequestDataComponent extends Object {

	/**
	 * The default key that the data is placed in. Can be found in
	 * $controller->params[$key].
	 * 
	 * @var string
	 * @access public
	 */
	public $key = 'requestData';
	
	/**
	 * Whether or not to add the named parameters to the request
	 * data arrray
	 * 
	 * @var bool
	 * @access public
	 */
	public $named = true;
	
	/**
	 * Whether or not to add the query string data to the request 
	 * data array
	 * 
	 * @var bool
	 * @access public
	 */
	public $query = true;
	
	/**
	 * Whether or not to add form data to the request data array
	 * 
	 * @var bool
	 * @access public
	 */
	public $form = false;

	/**
	 * Array of valid options that can be modified from the settings
	 * array passed into the component's initialize method. Should
	 * refect member properties that can be configurable.
	 * 
	 * @var array
	 * @access public
	 */
	public $validOptions = array(
		'key',
		'named',
		'query',
		'form'
	);

	/**
	 * initialize function.
	 * 
	 * @access public
	 * @param mixed &$controller
	 * @param array $settings. (default: array())
	 * @return void
	 */
	public function initialize(&$controller, $settings = array()) {
		$this->setOptions($settings);
		$data = $this->parseData($controller->params);
		$controller->params[$this->key] = $data;
		if (empty($params['named'])) {
			$controller->params['named'] = $data;
		}
	}
	
	/**
	 * Takes the settings array passed into the initialize method and
	 * sets member properties to appropriate values.
	 * 
	 * @access protected
	 * @param array $options
	 * @return bool
	 */
	protected function setOptions($options) {
		if (!is_array($options) || empty($options)) {
			return false;
		}
		foreach ($this->validOptions as $type) {
			if (array_key_exists($type, $options)) {
				$this->{$type} = $options[$type];
			}
		}
		return true;
	}
	
	/**
	 * Takes data and returns the new array of merged data. Priority is
	 * Query String, then Named Params, then Form Data
	 * 
	 * @access protected
	 * @param array $data
	 * @return array
	 */
	protected function parseData($params) {
		if (!is_array($params) || empty($params)) {
			return array();
		}
		$formData = array();
		$queryData = array();
		$namedData = array();
		if ($this->query) {
			if (!empty($params['url'])) {
				unset($params['url']['url']);
				unset($params['url']['ext']);
				if (!empty($params['url'])) {
					$queryData = $params['url'];
				}
			}
		}
		if ($this->named) {
			if (!empty($params['named'])) {
				$namedData = $params['named'];
			}
		}
		if ($this->form) {
			if (!empty($params['form'])) {
				$form = $params['form'];
				foreach ($form as $key => $value) {
					if (is_array($value)) {
						$formData[$key] = $value;
					} else {
						$formData = array_merge($formData, $value);
					}
				}
			}
		}
		return array_merge($formData, $namedData, $queryData);
	}

}

?>