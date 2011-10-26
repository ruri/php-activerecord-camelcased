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
		$this->assertHasCallback('afterConstruct');
	}

	public function testRegister()
	{
		$this->callback->register('afterConstruct');
		$this->assertHasCallback('afterConstruct');
	}

	public function testRegisterNonGeneric()
	{
		$this->callback->register('afterConstruct','nonGenericAfterConstruct');
		$this->assertHasCallback('afterConstruct','nonGenericAfterConstruct');
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testRegisterInvalidCallback()
	{
		$this->callback->register('invalidCallback');
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testRegisterCallbackWithUndefinedMethod()
	{
		$this->callback->register('afterConstruct','doNotDefineMe');
	}

	public function testRegisterWithStringDefinition()
	{
		$this->callback->register('afterConstruct','afterConstruct');
		$this->assertHasCallback('afterConstruct');
	}

	public function testRegisterWithClosure()
	{
		$this->callback->register('afterConstruct',function($mode) { });
	}

	public function testRegisterWithNullDefinition()
	{
		$this->callback->register('afterConstruct',null);
		$this->assertHasCallback('afterConstruct');
	}

	public function testRegisterWithNoDefinition()
	{
		$this->callback->register('afterConstruct');
		$this->assertHasCallback('afterConstruct');
	}

	public function testRegisterAppendsToRegistry()
	{
		$this->callback->register('afterConstruct');
		$this->callback->register('afterConstruct','nonGenericAfterConstruct');
		$this->assertEquals(array('afterConstruct','afterConstruct','nonGenericAfterConstruct'),$this->callback->getCallbacks('afterConstruct'));
	}

	public function testRegisterPrependsToRegistry()
	{
		$this->callback->register('afterConstruct');
		$this->callback->register('afterConstruct','nonGenericAfterConstruct',array('prepend' => true));
		$this->assertEquals(array('nonGenericAfterConstruct','afterConstruct','afterConstruct'),$this->callback->getCallbacks('afterConstruct'));
	}

	public function testRegistersViaStaticArrayDefinition()
	{
		$this->assertHasCallback('afterDestroy','afterDestroyOne');
		$this->assertHasCallback('afterDestroy','afterDestroyTwo');
	}

	public function testRegistersViaStaticStringDefinition()
	{
		$this->assertHasCallback('beforeDestroy','beforeDestroyUsingString');
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
		$this->callback->register('afterConstruct');
		$this->callback->register('afterConstruct');
		$this->assertEquals(array('afterConstruct','afterConstruct','afterConstruct'),$this->callback->getCallbacks('afterConstruct'));
	}

	public function testRegisterClosureCallback()
	{
		$closure = function($model) {};
		$this->callback->register('afterSave',$closure);
		$this->assertEquals(array($closure),$this->callback->getCallbacks('afterSave'));
	}

	public function testGetCallbacksReturnsArray()
	{
		$this->callback->register('afterConstruct');
		$this->assertTrue(is_array($this->callback->getCallbacks('afterConstruct')));
	}

	public function testGetCallbacksReturnsNull()
	{
		$this->assertNull($this->callback->getCallbacks('this_callback_name_should_never_exist'));
	}

	public function testInvokeRunsAllCallbacks()
	{
		$mock = $this->getMock('VenueCB',array('afterDestroyOne','afterDestroyTwo'));
		$mock->expects($this->once())->method('afterDestroyOne');
		$mock->expects($this->once())->method('afterDestroyTwo');
		$this->callback->invoke($mock,'afterDestroy');
	}

	public function testInvokeClosure()
	{
		$iRan = false;
		$this->callback->register('afterValidation',function($model) use (&$iRan) { $iRan = true; });
		$this->callback->invoke(null,'afterValidation');
		$this->assertTrue($iRan);
	}

	public function testInvokeImplicitlyCallsSaveFirst()
	{
		$this->assertImplicitSave('beforeSave','beforeCreate');
		$this->assertImplicitSave('beforeSave','beforeUpdate');
		$this->assertImplicitSave('afterSave','afterCreate');
		$this->assertImplicitSave('afterSave','afterUpdate');
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testInvokeUnregisteredCallback()
	{
		$mock = $this->getMock('VenueCB', array('columns'));
		$this->callback->invoke($mock,'beforeValidationOnCreate');
	}

	public function testBeforeCallbacksPassOnFalseReturnCallbackReturnedFalse()
	{
		$this->callback->register('beforeValidation',function($model) { return false; });
		$this->assertFalse($this->callback->invoke(null,'beforeValidation'));
	}

	public function testBeforeCallbacksDoesNotPassOnFalseForAfterCallbacks()
	{
		$this->callback->register('afterValidation',function($model) { return false; });
		$this->assertTrue($this->callback->invoke(null,'afterValidation'));
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
		VenueCB::$beforeCreate = array('beforeCreateHaltExecution');
		ActiveRecord\Table::clearCache('VenueCB');
		$table = ActiveRecord\Table::load('VenueCB');

		$iRan = false;
		$iShouldHaveRan = false;
		$table->callback->register('beforeSave', function($model) use (&$iShouldHaveRan) { $iShouldHaveRan = true; });
		$table->callback->register('beforeCreate',function($model) use (&$iRan) { $iRan = true; });
		$table->callback->register('afterCreate',function($model) use (&$iRan) { $iRan = true; });

		$v = VenueCB::find(1);
		$v->id = null;
		VenueCB::create($v->attributes());

		$this->assertTrue($iShouldHaveRan);
		$this->assertFalse($iRan);
		$this->assertTrue(strpos(ActiveRecord\Table::load('VenueCB')->lastSql, 'INSERT') === false);
	}

	public function testBeforeSaveReturnedFalseHaltsExecution()
	{
		VenueCB::$beforeUpdate = array('beforeUpdateHaltExecution');
		ActiveRecord\Table::clearCache('VenueCB');
		$table = ActiveRecord\Table::load('VenueCB');

		$iRan = false;
		$iShouldHaveRan = false;
		$table->callback->register('beforeSave', function($model) use (&$iShouldHaveRan) { $iShouldHaveRan = true; });
		$table->callback->register('beforeUpdate',function($model) use (&$iRan) { $iRan = true; });
		$table->callback->register('afterSave',function($model) use (&$iRan) { $iRan = true; });

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
		VenueCB::$beforeDestroy = array('beforeDestroyHaltExecution');
		ActiveRecord\Table::clearCache('VenueCB');
		$table = ActiveRecord\Table::load('VenueCB');

		$iRan = false;
		$table->callback->register('beforeDestroy',function($model) use (&$iRan) { $iRan = true; });
		$table->callback->register('afterDestroy',function($model) use (&$iRan) { $iRan = true; });

		$v = VenueCB::find(1);
		$ret = $v->delete();

		$this->assertFalse($iRan);
		$this->assertFalse($ret);
		$this->assertTrue(strpos(ActiveRecord\Table::load('VenueCB')->lastSql, 'DELETE') === false);
	}

	public function testBeforeValidationReturnedFalseHaltsExecution()
	{
		VenueCB::$beforeValidation = array('beforeValidationHaltExecution');
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