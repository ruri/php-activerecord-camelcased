<?php
include 'helpers/config.php';

class ModelCallbackTest extends DatabaseTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp($connectionName);

		$this->venue = new Venue();
		$this->callback = Venue::table()->callback;
	}

	public function registerAndInvokeCallbacks($callbacks, $return, $closure)
	{
		if (!is_array($callbacks))
			$callbacks = array($callbacks);

		$fired = array();

		foreach ($callbacks as $name)
			$this->callback->register($name,function($model) use (&$fired, $name, $return) { $fired[] = $name; return $return; });

		$closure($this->venue);
		return array_intersect($callbacks,$fired);
	}

	public function assertFires($callbacks, $closure)
	{
		$executed = $this->registerAndInvokeCallbacks($callbacks,true,$closure);
		$this->assertEquals(count($callbacks),count($executed));
	}

	public function assertDoesNotFire($callbacks, $closure)
	{
		$executed = $this->registerAndInvokeCallbacks($callbacks,true,$closure);
		$this->assertEquals(0,count($executed));
	}

	public function assertFiresReturnsFalse($callbacks, $onlyFire, $closure)
	{
		if (!is_array($onlyFire))
			$onlyFire = array($onlyFire);

		$executed = $this->registerAndInvokeCallbacks($callbacks,false,$closure);
		sort($onlyFire);
		$intersect = array_intersect($onlyFire,$executed);
		sort($intersect);
		$this->assertEquals($onlyFire,$intersect);
	}

	public function testAfterConstructFiresByDefault()
	{
		$this->assertFires('after_construct',function($model) { new Venue(); });
	}

	public function testFireValidationCallbacksOnInsert()
	{
		$this->assertFires(array('before_validation','after_validation','before_validation_on_create','after_validation_on_create'),
			function($model) { $model = new Venue(); $model->save(); });
	}

	public function testFireValidationCallbacksOnUpdate()
	{
		$this->assertFires(array('before_validation','after_validation','before_validation_on_update','after_validation_on_update'),
			function($model) { $model = Venue::first(); $model->save(); });
	}

	public function testValidationCallBacksNotFiredDueToBypassingValidations()
	{
		$this->assertDoesNotFire('before_validation',function($model) { $model->save(false); });
	}

	public function testBeforeValidationReturningFalseCancelsCallbacks()
	{
		$this->assertFiresReturnsFalse(array('before_validation','after_validation'),'before_validation',
			function($model) { $model->save(); });
	}

	public function testFiresBeforeSaveAndBeforeUpdateWhenUpdating()
	{
		$this->assertFires(array('before_save','before_update'),
			function($model) { $model = Venue::first(); $model->name = "something new"; $model->save(); });
	}

	public function testBeforeSaveReturningFalseCancelsCallbacks()
	{
		$this->assertFiresReturnsFalse(array('before_save','before_create'),'before_save',
			function($model) { $model = new Venue(); $model->save(); });
	}

	public function testDestroy()
	{
		$this->assertFires(array('before_destroy','after_destroy'),
			function($model) { $model->delete(); });
	}
}
?>
