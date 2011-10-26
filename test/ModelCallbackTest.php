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
		$this->assertFires('afterConstruct',function($model) { new Venue(); });
	}

	public function testFireValidationCallbacksOnInsert()
	{
		$this->assertFires(array('beforeValidation','afterValidation','beforeValidationOnCreate','afterValidationOnCreate'),
			function($model) { $model = new Venue(); $model->save(); });
	}

	public function testFireValidationCallbacksOnUpdate()
	{
		$this->assertFires(array('beforeValidation','afterValidation','beforeValidationOnUpdate','afterValidationOnUpdate'),
			function($model) { $model = Venue::first(); $model->save(); });
	}

	public function testValidationCallBacksNotFiredDueToBypassingValidations()
	{
		$this->assertDoesNotFire('beforeValidation',function($model) { $model->save(false); });
	}

	public function testBeforeValidationReturningFalseCancelsCallbacks()
	{
		$this->assertFiresReturnsFalse(array('beforeValidation','afterValidation'),'beforeValidation',
			function($model) { $model->save(); });
	}

	public function testFiresBeforeSaveAndBeforeUpdateWhenUpdating()
	{
		$this->assertFires(array('beforeSave','beforeUpdate'),
			function($model) { $model = Venue::first(); $model->name = "something new"; $model->save(); });
	}

	public function testBeforeSaveReturningFalseCancelsCallbacks()
	{
		$this->assertFiresReturnsFalse(array('beforeSave','beforeCreate'),'beforeSave',
			function($model) { $model = new Venue(); $model->save(); });
	}

	public function testDestroy()
	{
		$this->assertFires(array('beforeDestroy','afterDestroy'),
			function($model) { $model->delete(); });
	}
}
?>
