<?php

class ServerResponseComponent extends Object {

	public $components = array('RequestHandler');

	/**
	 * An array of status code indexes that relate to the correctly formatted
	 * status code string.
	 * 
	 * @var mixed
	 * @access public
	 */
	public $statusCodes = array(
		200 => '200 Ok',
		201 => '201 Created',
		303 => '303 See Other',
		401 => '401 Unauthorized',
		403 => '403 Forbidden',
		404 => '404 Not Found',
		405 => '405 Method Not Allowed'
	);

	/**
	 * Array of valid options that can be set in the settings array passed into
	 * the initialize method.
	 * 
	 * @var array
	 * @access protected
	 */
	protected $validOptions = array();
	
	/**
	 * All data that is set to the header is stored in this array. This is the 
	 * single place where options are set and then converted to header calls
	 * in beforeRender.
	 *
	 * @var array
	 * @access protected
	 */
	protected $responseData = array();
	
	/**
	 * Explicitly set this using the setter method and this code will be used
	 * regardless of property values.
	 * 
	 * @var int
	 * @access public
	 */
	protected $responseCode = false;
	
	/**
	 * A message that will be set in the response body if set to a string value.
	 * 
	 * @var string
	 * @access protected
	 */
	protected $responseMessage = false;
	
	/**
	 * Set this property to the method type used in the controller. Valid 
	 * method types are Add, Edit, Delete, View. Use View for both Index and 
	 * View methods.
	 * 
	 * @var string
	 * @access public
	 */
	protected $methodType = false;
	
	/**
	 * Set this value in the controller based on the success or failure of 
	 * the controller method. Used in combination with the $methodType to 
	 * determine responseCode unless $responseCode is set explicitly.
	 * 
	 * @var bool
	 * @access public
	 */
	protected $methodSuccess = false;
	
	/**
	 * Data array that is built to be set to the view. The controller will not
	 * have to set data to the view at all. Just add data view the controller 
	 * setter method, and it will be set in $this->beforeRender();
	 * 
	 * @var array
	 * @access protected
	 */
	protected $returnData = array();
	
	/**
	 * httpHeaderType
	 * 
	 * @var string
	 * @access protected
	 */
	protected $httpHeaderType = 'HTTP/1.1';

	/**
	 * Apply settings set in the controllers $components array and build the default
	 * layout and values for the responseData property.
	 * 
	 * @access public
	 * @param object &$controller
	 * @param array $options. (default: array())
	 * @return void
	 */
	public function initialize(&$controller, $settings = array()) {
		$this->setOptions($settings);
		$this->responseData = array(
			'controller' => $controller->params['controller'],
			'action' => $controller->params['action'],
			'plugin' => $controller->params['plugin'],
			'url' => $controller->params['url']['url'],
			'status' => null,
			'code' => null,
			'message' => null,
			'success' => null,
			'response' => null
		);
	}

	/**
	 * After the controller has had a chance in the beforeFilter callback to 
	 * manually set properties in this component, this method will automatically
	 * set response code to 405 in the http protocal type does not match with 
	 * correct method types. Also responsible for saving to the access log db
	 * table;
	 * 
	 * @access public
	 * @param object &$controller
	 * @return void
	 */
	public function startup(&$controller) {
		$postMethodTypes = array('add', 'edit', 'delete');
		$getMethodTypes = array('view', 'index');
		$validMethodTypes = array_merge($postMethodTypes, $getMethodTypes);
		if (!$this->methodType && in_array($this->responseData['action'], $validMethodTypes)) {
			$this->setMethodType($this->responseData['action']);
		}
		if (in_array(strtolower($this->methodType), $postMethodTypes) && !$this->RequestHandler->isPost()) {
			$this->setResponseCode(405);
		}
		if (in_array(strtolower($this->methodType), $getMethodTypes) && !$this->RequestHandler->isGet()) {
			$this->setResponseCode(405);
		}
	}
	
	/**
	 * beforeRender function.
	 * 
	 * @access public
	 * @param mixed &$controller
	 * @return void
	 */
	public function beforeRender(&$controller) {
		$this->generateStatusCode();
		$params = $controller->params;
		if (isset($params['paging'])) {
			$paging = $params['paging'];
			$model = Inflector::classify($this->responseData['controller']);
			if (isset($paging[$model])) {
				$paging = $paging[$model];
				header('X-Paging-Page: '.(int)$paging['page']);
				header('X-Paging-Current: '.(int)$paging['current']);
				header('X-Paging-Count: '.(int)$paging['count']);
				header('X-Paging-Next: '.(int)$paging['nextPage']);
				header('X-Paging-Prev: '.(int)$paging['prevPage']);
				header('X-Paging-PageCount: '.(int)$paging['pageCount']);
			}
		}
		if (!empty($this->responseData['status']) && in_array($this->responseData['status'], $this->statusCodes)) {
			header($this->httpHeaderType.' '.$this->statusCodes[$this->responseData['status']]);
			$this->responseData['code'] = $this->statusCodes[$this->responseData['status']];
		} else {
			header($this->httpHeaderType.' 500 Internal Server Error');
			$this->responseData['code'] = '500 Internal Server Error';
		}
		$this->responseData['response'] = $this->returnData;
		$controller->set('response', $this->responseData);
		$controller->autoRender = false;
	}
	
	/**
	 * Used for setters and getters on member properties. 
	 * 
	 * @access public
	 * @param string $methodName
	 * @param array $params
	 * @return void
	 */
	public function __call($methodName, $params = array()) {
		$exists = true;
		if (strstr($methodName, 'set') && count($params) >= 1) {
			$name = substr($methodName, 3);
			$name = strtolower(Inflector::underscore($name));
			$name = Inflector::variable($name);
			if (isset($this->{$name})) {
				$this->{$name} = $params[0];
				return true;
			} else {
				$exists = false;
			}
		} else {
			$name = $methodName;
			if (isset($this->{$name})) {
				return $this->{$name};
			} else {
				$exists = false;
			}
		}
		if (!$exists) {
			throw new Exception("Stop it, property doesn't exist: ".$name);
		}
	}
	
	/**
	 * Set values from the controller. These values will be added to the 
	 * returnData property and then finally set to the view in a consistent
	 * way at the end of the beforeRender callback.
	 * 
	 * @access public
	 * @param mixed $key
	 * @param mixed $data
	 * @return void
	 */
	public function set($key = null, $data = null) {
		if (!$key && !$data) {
			return false;
		}
		$mergeData = array();
		if (is_array($key)) {
			$mergeData = $key;
		} else {
			if ($data) {
				$mergeData = array($key => $data);
			}
		}
		$this->returnData = array_merge($this->returnData, $mergeData);
		return true;
	}
	
	/**
	 * Method for explicitly setting the methodSuccess property and optionally
	 * setting responseMessage property as well.
	 * 
	 * @access public
	 * @param int $success
	 * @param string $message
	 * @return bool
	 */
	public function setMethodSuccess($success = null, $message = null) {
		if (!$success) {
			return false;
		}
		$this->methodSuccess = $success;
		if ($message) {
			$this->responseMessage = $message;
		}
		return true;
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
	 * Sets both the status code and message in the responseData array
	 * 
	 * @access protected
	 * @param int $status
	 * @param string $message
	 * @return bool
	 */
	protected function setStatusMessageAndSuccess($status = null, $message = null, $success = null) {
		if (!$status) {
			return false;
		}
		$this->responseData['status'] = $status;
		if (!empty($message)) {
			$this->responseData['message'] = $message;
		}
		$this->responseData['success'] = (int)$success;
		return true;
	}

	/**
	 * This method is called at the beginning of the beforeRender method. 
	 * If the responseCode property was explicitly set, then this method
	 * will use it rather than generating a response code. It will also use
	 * the responseMessage if it was explicitly set. Otherwise it will 
	 * generate a response code and message based on the methodType and 
	 * methodSuccess properties.
	 * 
	 * @access protected
	 * @return void
	 */
	protected function generateStatusCode() {
		$status = null;
		$message = null;
		$success = $this->methodSuccess;
		if ($this->responseCode && array_key_exists($this->responseCode, $this->statusCodes)) {
			$status = $this->responseCode;
			if ($this->responseMessage) {
				$message = $this->responseMessage;
			}
		} else {
			switch (strtolower($this->methodType)) {
				case 'add':
					if ($success) {
						$status = 201;
					} else {
						$status = 200;
						$message = "Failed to add new object";
					}
				break;
				case 'edit':
					$status = 200;
					if (!$success) {
						$message = "Failed to edit object";
					}
				break;
				case 'delete':
					$status = 200;
					if (!$success) {
						$message = "Failed to delete object";
					}
				break;
				case 'view':
					$status = 200;
					if (!$success) {
						$message = "Failed to retrieve object";
					}
				break;
				case 'index':
					$status = 200;
					if (!$success) {
						$message = "Failed to retrieve objects";
					}
				break;
				default:
					$status = 501;
					$message = "Method type not implemented";
				break;
			}
		}
		if (!empty($this->responseMessage)) {
			$message = $this->responseMessage;
		}
		return $this->setStatusMessageAndSuccess($status, $message, $success);
	}

}

?>