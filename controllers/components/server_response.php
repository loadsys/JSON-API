<?php
/**
 * Server Response Component
 *
 * Automatically sets server response codes and the response data in JSON format
 * for .json requests
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright 2010, Loadsys Consulting, Inc. (http://www.loadsys.com)
 * @version       $1.0$
 * @modifiedby    $LastChangedBy: Joey Trapp (Loadsys) $
 * @lastmodified  $Date: 2010-10-14$
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
class ServerResponseComponent extends Object {

	public $components = array('RequestHandler');
	
	/**
	 * Boolean property that is true when a request is of type JSON.
	 *
	 * @var bool
	 * @access public
	 */
	public $isJson;

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
	protected $methodSuccess = true;
	
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
	 * Reference to the controller using the component. Gets replaced
	 * in each call back so that the appropriate controller is always
	 * being used.
	 * 
	 * @var mixed
	 * @access protected
	 */
	protected $controller;

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
		$this->controller = $controller;
		$this->setOptions($settings);
		$this->isJson = ($controller->params['url']['ext'] == 'json');
		$this->responseData = array(
			'controller' => $controller->params['controller'],
			'action' => $controller->params['action'],
			'plugin' => $controller->params['plugin'],
			'url' => $controller->params['url']['url'],
			'status' => null,
			'code' => null,
			'message' => null,
			'success' => null,
			'response' => null,
			'paging' => null
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
		$this->controller = $controller;
		if ($this->isJson) {
			$postMethodTypes = array('add', 'edit', 'delete');
			$getMethodTypes = array('view', 'index');
			$validMethodTypes = array_merge($postMethodTypes, $getMethodTypes);
			if (!$this->methodType && in_array($this->responseData['action'], $validMethodTypes)) {
				$this->setMethodType($this->responseData['action']);
			}
			if (method_exists($this->controller, 'beforeCompareMethodType')) {
				$methodType = $this->controller->beforeCompareMethodType($this->methodType);
				if (is_string($methodType)) {
					$this->methodType = $methodType;
				}
			}
			if (in_array(strtolower($this->methodType), $postMethodTypes) && !$this->RequestHandler->isPost()) {
				$this->setResponseCode(405);
			}
			if (in_array(strtolower($this->methodType), $getMethodTypes) && !$this->RequestHandler->isGet()) {
				$this->setResponseCode(405);
			}
			
			// Set the responseData appropriately and call this render
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
		$this->controller = $controller;
		if ($this->isJson) {
			$this->generateStatusCode();
			$params = $controller->params;
			if (isset($params['paging'])) {
				$paging = $params['paging'];
				$_paging = array();
				$model = $controller->modelClass;
				if (isset($paging[$model])) {
					$_paging = $paging[$model];
				} else {
					$models = $controller->modelNames;
					foreach ($models as $m) {
						if (isset($paging[$m])) {
							$_paging = $paging[$m];
							break;
						}
					}
				}
				if (!empty($_paging)) {
					header('X-Paging-Page: '.(int)$_paging['page']);
					header('X-Paging-Current: '.(int)$_paging['current']);
					header('X-Paging-Count: '.(int)$_paging['count']);
					header('X-Paging-Next: '.(int)$_paging['nextPage']);
					header('X-Paging-Prev: '.(int)$_paging['prevPage']);
					header('X-Paging-PageCount: '.(int)$_paging['pageCount']);
					$this->responseData['paging'] = array(
						'page' => (int)$_paging['page'],
						'current' => (int)$_paging['current'],
						'count' => (int)$_paging['count'],
						'nextPage' => (int)$_paging['nextPage'],
						'prevPage' => (int)$_paging['prevPage'],
						'pageCount' => (int)$_paging['pageCount']
					);
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
			$this->render();
		}
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
	 * Method called at the end of the beforeRender method that will echo the
	 * json encoded data and exit execution of the script.
	 * 
	 * @access public
	 * @return void
	 */
	public function render() {
		if (method_exists($this->controller, 'beforeJsonRender')) {
			$returnData = $this->controller->beforeJsonRender($this->responseData['response']);
			if (is_array($returnData)) {
				$this->responseData['response'] = $returnData;
			}
		}
		echo json_encode($this->responseData);
		exit();		
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
	 * The following methods are here just so that the properties can not be explicitly
	 * set from the controller without using the appropriate methods ($this->set() and
	 * beforeJsonRender() callback)
	 */
	public function setResponseData() { return; }
	public function setReturnData() { return; }
	
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
	 * @param mixed $status
	 * @param string $message
	 * @return bool
	 */
	protected function setStatusMessageAndSuccess($status = null, $message = null, $success = null) {
		if (is_array($status)) {
			extract($status, EXTR_OVERWRITE);
		}
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
		$params = compact('status', 'message', 'success');
		if (method_exists($this->controller, 'beforeSetResponseInfo')) {
			$tmpParams = $this->controller->beforeSetResponseInfo($params);
			if (is_array($tmpParams)) {
				$params = $tmpParams;
			}
		}
		return $this->setStatusMessageAndSuccess($params);
	}

}

?>