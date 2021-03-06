<?php

class Template_Controller extends Controller {
	
	public static $default_template = 'simple';
	
	protected $template = null;
	
	public function before() {
		parent::before();
		$this->template = Twig::factory($this->template ?: self::$default_template);
	}
	
	public function after() {
		// pre-render the view content, so if it breaks we get a good stack trace
		$content = (string)$this->view;
		$this->view = $this->template;
		$this->view->content = $content;
		
		parent::after(); // call parent to render the view
	}

}
