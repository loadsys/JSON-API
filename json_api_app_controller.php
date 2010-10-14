<?php

class JsonApiAppController extends AppController {

	public $components = array(
		'json_api.ServerResponse',
		'json_api.RequestData' => array(
			'form' => true
		)
	);

}

?>