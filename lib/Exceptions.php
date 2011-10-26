<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

/**
 * Generic base exception for all ActiveRecord specific errors.
 *
 * @package ActiveRecord
 */
class ActiveRecordException extends \Exception {};

/**
 * Thrown when a record cannot be found.
 *
 * @package ActiveRecord
 */
class RecordNotFound extends ActiveRecordException {};

/**
 * Thrown when there was an error performing a database operation.
 *
 * The error will be specific to whatever database you are running.
 *
 * @package ActiveRecord
 */
class DatabaseException extends ActiveRecordException
{
	public function __construct($adapterOrStringOrMystery)
	{
		if ($adapterOrStringOrMystery instanceof Connection)
		{
			parent::__construct(
				join(", ",$adapterOrStringOrMystery->connection->errorInfo()),
				intval($adapterOrStringOrMystery->connection->errorCode()));
		}
		elseif ($adapterOrStringOrMystery instanceof \PDOStatement)
		{
			parent::__construct(
				join(", ",$adapterOrStringOrMystery->errorInfo()),
				intval($adapterOrStringOrMystery->errorCode()));
		}
		else
			parent::__construct($adapterOrStringOrMystery);
	}
};

/**
 * Thrown by {@link Model}.
 *
 * @package ActiveRecord
 */
class ModelException extends ActiveRecordException {};

/**
 * Thrown by {@link Expressions}.
 *
 * @package ActiveRecord
 */
class ExpressionsException extends ActiveRecordException {};

/**
 * Thrown for configuration problems.
 *
 * @package ActiveRecord
 */
class ConfigException extends ActiveRecordException {};

/**
 * Thrown when attempting to access an invalid property on a {@link Model}.
 *
 * @package ActiveRecord
 */
class UndefinedPropertyException extends ModelException
{
	/**
	 * Sets the exception message to show the undefined property's name.
	 *
	 * @param str $propertyName name of undefined property
	 * @return void
	 */
	public function __construct($className, $propertyName)
	{
		if (is_array($propertyName))
		{
			$this->message = implode("\r\n", $propertyName);
			return;
		}

		$this->message = "Undefined property: {$className}->{$propertyName} in {$this->file} on line {$this->line}";
		parent::__construct();
	}
};

/**
 * Thrown when attempting to perform a write operation on a {@link Model} that is in read-only mode.
 *
 * @package ActiveRecord
 */
class ReadOnlyException extends ModelException
{
	/**
	 * Sets the exception message to show the undefined property's name.
	 *
	 * @param str $className name of the model that is read only
	 * @param str $methodName name of method which attempted to modify the model
	 * @return void
	 */
	public function __construct($className, $methodName)
	{
		$this->message = "{$className}::{$methodName}() cannot be invoked because this model is set to read only";
		parent::__construct();
	}
};

/**
 * Thrown for validations exceptions.
 *
 * @package ActiveRecord
 */
class ValidationsArgumentError extends ActiveRecordException {};

/**
 * Thrown for relationship exceptions.
 *
 * @package ActiveRecord
 */
class RelationshipException extends ActiveRecordException {};

/**
 * Thrown for has many thru exceptions.
 *
 * @package ActiveRecord
 */
class HasManyThroughAssociationException extends RelationshipException {};
?>