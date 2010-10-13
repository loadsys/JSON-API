<?php 

/**
 * JsonSource class.
 *
 * The model object for each request type (create, read, update, delete)
 * must have a member property that defines which api section to refer to.
 * Example: plugin tasks model Task in this app would have a property
 * $apiModel that would be set to Task.
 *
 * Put this in config/database.php
 * var $json_api = array(
 *		'datasource' => 'jsonSource',
 *		'base_url' => 'cake json api url'
 *	);
 * Then in any model that uses the api (or in app model if they all do) add this:
 * public $useDbConfig = 'json_api';
 * 
 * @extends Datasource
 */
App::import('Core', 'HttpSocket');
 
class JsonSource extends Datasource {

	/**
	 * cacheSources
	 * 
	 * (default value: true)
	 * 
	 * @var bool
	 * @access public
	 */
	public $cacheSources = true;
	
	/**
	 * _baseConfig
	 * 
	 * @var mixed
	 * @access public
	 */
	public $_baseConfig = array(
		'url' => ''
	);
	
	/**
	 * _http
	 * 
	 * (default value: null)
	 * 
	 * @var mixed
	 * @access protected
	 */
	protected $_http = null;
	
	/**
	 * _statusInterpreter
	 * 
	 * (default value: null)
	 * 
	 * @var mixed
	 * @access protected
	 */
	protected $_statusInterpreter = null;
	
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @param mixed $config
	 * @return void
	 */
	public function __construct($config) {
		parent::__construct($config);
		$this->_http =& new HttpSocket();
	}
	
	/**
	 * listSources function.
	 * 
	 * @access public
	 * @param mixed $data. (default: null)
	 * @return void
	 */
	public function listSources($data = null) {
		return true;
	}
	
	/**
	 * describe function.
	 * 
	 * @access public
	 * @param mixed &$Model
	 * @return void
	 */
	public function describe(&$Model) {
		$url = $this->_setApiUrl($Model);
		$url .= '/describe.json';
		$response = $this->_makeApiCall($url, 'get');
		$Model->_schema = $response;
		return $response;
	}
	
	/**
	 * read function.
	 * 
	 * @access public
	 * @param mixed &$Model
	 * @param array $query. (default: array())
	 * @return void
	 */
	public function read(&$Model, $query = array()) {
		$url = $this->_setApiUrl($Model);
		$url .= '/index.json';
		pr($query);
		return $this->_makeApiCall($url, 'get');
	}
	
	/**
	 * create function.
	 * 
	 * @access public
	 * @param mixed &$Model
	 * @param mixed $fields. (default: null)
	 * @param mixed $values. (default: null)
	 * @return void
	 */
	public function create(&$Model, $fields = null, $values = null) {
		$url = $this->_setApiUrl($Model);
		$url .= '/add.json';
		$data = $this->_createDataArray($fields, $values);
		return $this->_makeApiCall($url, 'post', $data);
	}
	
	/**
	 * update function.
	 * 
	 * @access public
	 * @param mixed &$Model
	 * @param mixed $fields. (default: null)
	 * @param mixed $values. (default: null)
	 * @return void
	 */
	public function update(&$Model, $fields = null, $values = null) {
		$url = $this->_setApiUrl($Model);
		$url .= '/edit/'.$Model->id.'.json';
		$data = $this->_createDataArray($fields, $values);
		return $this->_makeApiCall($url, 'put', $data);
	}
	
	/**
	 * delete function.
	 * 
	 * @access public
	 * @param mixed &$Model
	 * @param mixed $id. (default: null)
	 * @return void
	 */
	public function delete(&$Model, $id = null) {
		$url = $this->_setApiUrl($Model);
		$url .= '/delete/'.$id.'.json';
		return $this->_makeApiCall($url, 'delete');
	}
	
	/**
	 * calculate function.
	 * 
	 * @access public
	 * @param mixed &$Model
	 * @return void
	 */
	public function calculate(&$Model) {
		return array('count' => true);
	}
	
	/**
	 * Generic method for taking a url, http protocol type, and an
	 * array of data and making the api call.
	 * 
	 * @access protected
	 * @param mixed $url. (default: null)
	 * @param mixed $type. (default: null)
	 * @param mixed &$Model
	 * @param mixed $data. (default: null)
	 * @return void
	 */
	protected function _makeApiCall($url = null, $type = null, $data = null) {
		if (!$url || !$type) {
			return false;
		}
		$response = json_decode($this->_http->{$type}($url, $data), true);
		pr($response);
		return $response;
	}
	
	/**
	 * Based on the member property $apiModel in the passed in
	 * Model class, returns the url with the appropriate subdirectory
	 * 
	 * @access protected
	 * @param mixed &$Model
	 * @return void
	 */
	protected function _setApiUrl(&$Model) {
		$url = $this->config['base_url'];
		if (substr($url, strlen($url) - 1) != '/') {
			$url .= '/';
		}
		if (property_exists($Model, 'apiModel')) {
			$url .= Inflector::pluralize(strtolower($Model->apiModel));
		}
		return $url;
	}
	
	/**
	 * Takes array of fiels and values and creates an associative array,
	 * suitable for being sent to the api.
	 * 
	 * @access protected
	 * @param mixed $fields
	 * @param mixed $values
	 * @return void
	 */
	protected function _createDataArray($fields, $values) {
		$data = array();
		$count = count($fields);
		for($i = 0; $i < $count; $i++) {
			$data[$fields[$i]] = $values[$i];
		}
		return $data;
	}


}

?>