<?php
include 'helpers/config.php';

class CallBackTest extends DatabaseTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp($connectionName);

		// ensure VenueCB model has been loaded
		VenueCB::find(1);

		$this->callback = new ActiveRecord\CallBack('VenueCB');
	}

	public function assertHasCallback($callbackName, $methodName=null)
	{
		if (!$methodName)
			$methodName = $callbackName;

		$this->assertTrue(in_array($methodName,$this->callback->getCallbacks($callbackName)));
	}

	public function assertImplicitSave($firstMethod, $secondMethod)
	{
		$iRan = array();
		$this->callback->register($firstMethod,function($model) use (&$iRan, $firstMethod) { $iRan[] = $firstMethod; });
		$this->callback->register($secondMethod,function($model) use (&$iRan, $secondMethod) { $iRan[] = $secondMethod; });
		$this->callback->invoke(null,$secondMethod);
		$this->assertEquals(array($firstMethod,$secondMethod),$iRan);
	}

	public function testGenericCallbackWasAutoRegistered()
	{
		$this->assertHasCallback('after_construct');
	}

	public function testRegister()
	{
		$this->callback->register('after_construct');
		$this->assertHasCallback('after_construct');
	}

	public function testRegisterNonGeneric()
	{
		$this->callback->register('after_construct','non_generic_after_construct');
		$this->assertHasCallback('after_construct','non_generic_after_construct');
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testRegisterInvalidCallback()
	{
		$this->callback->register('invalid_callback');
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testRegisterCallbackWithUndefinedMethod()
	{
		$this->callback->register('after_construct','do_not_define_me');
	}

	public function testRegisterWithStringDefinition()
	{
		$this->callback->register('after_construct','after_construct');
		$this->assertHasCallback('after_construct');
	}

	public function testRegisterWithClosure()
	{
		$this->callback->register('after_construct',function($mode) { });
	}

	public function testRegisterWithNullDefinition()
	{
		$this->callback->register('after_construct',null);
		$this->assertHasCallback('after_construct');
	}

	public function testRegisterWithNoDefinition()
	{
		$this->callback->register('after_construct');
		$this->assertHasCallback('after_construct');
	}

	public function testRegisterAppendsToRegistry()
	{
		$this->callback->register('after_construct');
		$this->callback->register('after_construct','non_generic_after_construct');
		$this->assertEquals(array('after_construct','after_construct','non_generic_after_construct'),$this->callback->getCallbacks('after_construct'));
	}

	public function testRegisterPrependsToRegistry()
	{
		$this->callback->register('after_construct');
		$this->callback->register('after_construct','non_generic_after_construct',array('prepend' => true));
		$this->assertEquals(array('non_generic_after_construct','after_construct','after_construct'),$this->callback->getCallbacks('after_construct'));
	}

	public function testRegistersViaStaticArrayDefinition()
	{
		$this->assertHasCallback('after_destroy','after_destroy_one');
		$this->assertHasCallback('after_destroy','after_destroy_two');
	}

	public function testRegistersViaStaticStringDefinition()
	{
		$this->assertHasCallback('before_destroy','before_destroy_using_string');
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testRegisterViaStaticWithInvalidDefinition()
	{
		$className = "Venues_" . md5(uniqid());
		eval("class $className extends ActiveRecord\\Model { static \$tableName = 'venues'; static \$afterSave = 'method_that_does_not_exist'; };");
		new $className();
		new ActiveRecord\CallBack($className);
	}

	public function testCanRegisterSameMultipleTimes()
	{
		$this->callback->register('after_construct');
		$this->callback->register('after_construct');
		$this->assertEquals(array('after_construct','after_construct','after_construct'),$this->callback->getCallbacks('after_construct'));
	}

	public function testRegisterClosureCallback()
	{
		$closure = function($model) {};
		$this->callback->register('after_save',$closure);
		$this->assertEquals(array($closure),$this->callback->getCallbacks('after_save'));
	}

	public function testGetCallbacksReturnsArray()
	{
		$this->callback->register('after_construct');
		$this->assertTrue(is_array($this->callback->getCallbacks('after_construct')));
	}

	public function testGetCallbacksReturnsNull()
	{
		$this->assertNull($this->callback->getCallbacks('this_callback_name_should_never_exist'));
	}

	public function testInvokeRunsAllCallbacks()
	{
		$mock = $this->getMock('VenueCB',array('after_destroy_one','after_destroy_two'));
		$mock->expects($this->once())->method('after_destroy_one');
		$mock->expects($this->once())->method('after_destroy_two');
		$this->callback->invoke($mock,'after_destroy');
	}

	public function testInvokeClosure()
	{
		$iRan = false;
		$this->callback->register('after_validation',function($model) use (&$iRan) { $iRan = true; });
		$this->callback->invoke(null,'after_validation');
		$this->assertTrue($iRan);
	}

	public function testInvokeImplicitlyCallsSaveFirst()
	{
		$this->assertImplicitSave('before_save','before_create');
		$this->assertImplicitSave('before_save','before_update');
		$this->assertImplicitSave('after_save','after_create');
		$this->assertImplicitSave('after_save','after_update');
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testInvokeUnregisteredCallback()
	{
		$mock = $this->getMock('VenueCB', array('columns'));
		$this->callback->invoke($mock,'before_validation_on_create');
	}

	public function testBeforeCallbacksPassOnFalseReturnCallbackReturnedFalse()
	{
		$this->callback->register('before_validation',function($model) { return false; });
		$this->assertFalse($this->callback->invoke(null,'before_validation'));
	}

	public function testBeforeCallbacksDoesNotPassOnFalseForAfterCallbacks()
	{
		$this->callback->register('after_validation',function($model) { return false; });
		$this->assertTrue($this->callback->invoke(null,'after_validation'));
	}

	public function testGh28AfterCreateShouldBeInvokedAfterAutoIncrementingPkIsSet()
	{
		$that = $this;
		VenueCB::$afterCreate = function($model) use ($that) { $that->assertNotNull($model->id); };
		ActiveRecord\Table::clearCache('VenueCB');
		$venue = VenueCB::find(1);
		$venue = new VenueCB($venue->attributes());
		$venue->id = null;
		$venue->name = 'alksdjfs';
		$venue->save();
	}

	public function testBeforeCreateReturnedFalseHaltsExecution()
	{
		VenueCB::$beforeCreate = array('before_create_halt_execution');
		ActiveRecord\Table::clearCache('VenueCB');
		$table = ActiveRecord\Table::load('VenueCB');

		$iRan = false;
		$iShouldHaveRan = false;
		$table->callback->register('before_save', function($model) use (&$iShouldHaveRan) { $iShouldHaveRan = true; });
		$table->callback->register('before_create',function($model) use (&$iRan) { $iRan = true; });
		$table->callback->register('after_create',function($model) use (&$iRan) { $iRan = true; });

		$v = VenueCB::find(1);
		$v->id = null;
		VenueCB::create($v->attributes());

		$this->assertTrue($iShouldHaveRan);
		$this->assertFalse($iRan);
		$this->assertTrue(strpos(ActiveRecord\Table::load('VenueCB')->lastSql, 'INSERT') === false);
	}

	public function testBeforeSaveReturnedFalseHaltsExecution()
	{
		VenueCB::$beforeUpdate = array('before_update_halt_execution');
		ActiveRecord\Table::clearCache('VenueCB');
		$table = ActiveRecord\Table::load('VenueCB');

		$iRan = false;
		$iShouldHaveRan = false;
		$table->callback->register('before_save', function($model) use (&$iShouldHaveRan) { $iShouldHaveRan = true; });
		$table->callback->register('before_update',function($model) use (&$iRan) { $iRan = true; });
		$table->callback->register('after_save',function($model) use (&$iRan) { $iRan = true; });

		$v = VenueCB::find(1);
		$v->name .= 'test';
		$ret = $v->save();

		$this->assertTrue($iShouldHaveRan);
		$this->assertFalse($iRan);
		$this->assertFalse($ret);
		$this->assertTrue(strpos(ActiveRecord\Table::load('VenueCB')->lastSql, 'UPDATE') === false);
	}

	public function testBeforeDestroyReturnedFalseHaltsExecution()
	{
		VenueCB::$beforeDestroy = array('before_destroy_halt_execution');
		ActiveRecord\Table::clearCache('VenueCB');
		$table = ActiveRecord\Table::load('VenueCB');

		$iRan = false;
		$table->callback->register('before_destroy',function($model) use (&$iRan) { $iRan = true; });
		$table->callback->register('after_destroy',function($model) use (&$iRan) { $iRan = true; });

		$v = VenueCB::find(1);
		$ret = $v->delete();

		$this->assertFalse($iRan);
		$this->assertFalse($ret);
		$this->assertTrue(strpos(ActiveRecord\Table::load('VenueCB')->lastSql, 'DELETE') === false);
	}

	public function testBeforeValidationReturnedFalseHaltsExecution()
	{
		VenueCB::$beforeValidation = array('before_validation_halt_execution');
		ActiveRecord\Table::clearCache('VenueCB');
		$table = ActiveRecord\Table::load('VenueCB');

		$v = VenueCB::find(1);
		$v->name .= 'test';
		$ret = $v->save();

		$this->assertFalse($ret);
		$this->assertTrue(strpos(ActiveRecord\Table::load('VenueCB')->lastSql, 'UPDATE') === false);
	}
};
?>