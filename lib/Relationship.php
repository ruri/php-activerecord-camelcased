<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

/**
 * Interface for a table relationship.
 *
 * @package ActiveRecord
 */
interface InterfaceRelationship
{
	public function __construct($options=array());
	public function buildAssociation(Model $model, $attributes=array());
	public function createAssociation(Model $model, $attributes=array());
}

/**
 * Abstract class that all relationships must extend from.
 *
 * @package ActiveRecord
 * @see http://www.phpactiverecord.org/guides/associations
 */
abstract class AbstractRelationship implements InterfaceRelationship
{
	/**
	 * Name to be used that will trigger call to the relationship.
	 *
	 * @var string
	 */
	public $attributeName;

	/**
	 * Class name of the associated model.
	 *
	 * @var string
	 */
	public $className;

	/**
	 * Name of the foreign key.
	 *
	 * @var string
	 */
	public $foreignKey = array();

	/**
	 * Options of the relationship.
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * Is the relationship single or multi.
	 *
	 * @var boolean
	 */
	protected $polyRelationship = false;

	/**
	 * List of valid options for relationships.
	 *
	 * @var array
	 */
	static protected $validAssociationOptions = array('class_name', 'class', 'foreign_key', 'conditions', 'select', 'readonly');

	/**
	 * Constructs a relationship.
	 *
	 * @param array $options Options for the relationship (see {@link valid_association_options})
	 * @return mixed
	 */
	public function __construct($options=array())
	{
		$this->attributeName = $options[0];
		$this->options = $this->mergeAssociationOptions($options);

		$relationship = strtolower(denamespace(get_called_class()));

		if ($relationship === 'hasmany' || $relationship === 'hasandbelongstomany')
			$this->polyRelationship = true;

		if (isset($this->options['conditions']) && !is_array($this->options['conditions']))
			$this->options['conditions'] = array($this->options['conditions']);

		if (isset($this->options['class']))
			$this->setClassName($this->options['class']);
		elseif (isset($this->options['class_name']))
			$this->setClassName($this->options['class_name']);

		$this->attributeName = strtolower(Inflector::instance()->variablize($this->attributeName));

		if (!$this->foreignKey && isset($this->options['foreign_key']))
			$this->foreignKey = is_array($this->options['foreign_key']) ? $this->options['foreign_key'] : array($this->options['foreign_key']);
	}

	protected function getTable()
	{
		return Table::load($this->className);
	}

	/**
	 * What is this relationship's cardinality?
	 *
	 * @return bool
	 */
	public function isPoly()
	{
		return $this->polyRelationship;
	}

	/**
	 * Eagerly loads relationships for $models.
	 *
	 * This method takes an array of models, collects PK or FK (whichever is needed for relationship), then queries
	 * the related table by PK/FK and attaches the array of returned relationships to the appropriately named relationship on
	 * $models.
	 *
	 * @param Table $table
	 * @param $models array of model objects
	 * @param $attributes array of attributes from $models
	 * @param $includes array of eager load directives
	 * @param $queryKeys -> key(s) to be queried for on included/related table
	 * @param $modelValuesKeys -> key(s)/value(s) to be used in query from model which is including
	 * @return void
	 */
	protected function queryAndAttachRelatedModelsEagerly(Table $table, $models, $attributes, $includes=array(), $queryKeys=array(), $modelValuesKeys=array())
	{
		$values = array();
		$options = $this->options;
		$inflector = Inflector::instance();
		$queryKey = $queryKeys[0];
		$modelValuesKey = $modelValuesKeys[0];

		foreach ($attributes as $column => $value)
			$values[] = $value[$inflector->variablize($modelValuesKey)];

		$values = array($values);
		$conditions = SQLBuilder::createConditionsFromUnderscoredString($table->conn,$queryKey,$values);

		if (isset($options['conditions']) && strlen($options['conditions'][0]) > 1)
			Utils::addCondition($options['conditions'], $conditions);
		else
			$options['conditions'] = $conditions;

		if (!empty($includes))
			$options['include'] = $includes;

		if (!empty($options['through'])) {
			// save old keys as we will be reseting them below for inner join convenience
			$pk = $this->primaryKey;
			$fk = $this->foreignKey;

			$this->setKeys($this->getTable()->class->getName(), true);

			if (!isset($options['class_name'])) {
				$class = classify($options['through'], true);
				$throughTable = $class::table();
			} else {
				$class = $options['class_name'];
				$relation = $class::table()->getRelationship($options['through']);
				$throughTable = $relation->getTable();
			}
			$options['joins'] = $this->constructInnerJoinSql($throughTable, true);

			$queryKey = $this->primaryKey[0];

			// reset keys
			$this->primaryKey = $pk;
			$this->foreignKey = $fk;
		}

		$options = $this->unsetNonFinderOptions($options);

		$class = $this->className;

		$relatedModels = $class::find('all', $options);
		$usedModels = array();
		$modelValuesKey = $inflector->variablize($modelValuesKey);
		$queryKey = $inflector->variablize($queryKey);

		foreach ($models as $model)
		{
			$matches = 0;
			$keyToMatch = $model->$modelValuesKey;

			foreach ($relatedModels as $related)
			{
				if ($related->$queryKey == $keyToMatch)
				{
					$hash = spl_object_hash($related);

					if (in_array($hash, $usedModels))
						$model->setRelationshipFromEagerLoad(clone($related), $this->attributeName);
					else
						$model->setRelationshipFromEagerLoad($related, $this->attributeName);

					$usedModels[] = $hash;
					$matches++;
				}
			}

			if (0 === $matches)
				$model->setRelationshipFromEagerLoad(null, $this->attributeName);
		}
	}

	/**
	 * Creates a new instance of specified {@link Model} with the attributes pre-loaded.
	 *
	 * @param Model $model The model which holds this association
	 * @param array $attributes Hash containing attributes to initialize the model with
	 * @return Model
	 */
	public function buildAssociation(Model $model, $attributes=array())
	{
		$className = $this->className;
		return new $className($attributes);
	}

	/**
	 * Creates a new instance of {@link Model} and invokes save.
	 *
	 * @param Model $model The model which holds this association
	 * @param array $attributes Hash containing attributes to initialize the model with
	 * @return Model
	 */
	public function createAssociation(Model $model, $attributes=array())
	{
		$className = $this->className;
		$newRecord = $className::create($attributes);
		return $this->appendRecordToAssociate($model, $newRecord);
	}

	protected function appendRecordToAssociate(Model $associate, Model $record)
	{
		$association =& $associate->{$this->attributeName};

		if ($this->polyRelationship)
			$association[] = $record;
		else
			$association = $record;

		return $record;
	}

	protected function mergeAssociationOptions($options)
	{
		$availableOptions = array_merge(self::$validAssociationOptions,static::$validAssociationOptions);
		$validOptions = array_intersect_key(array_flip($availableOptions),$options);

		foreach ($validOptions as $option => $v)
			$validOptions[$option] = $options[$option];

		return $validOptions;
	}

	protected function unsetNonFinderOptions($options)
	{
		foreach (array_keys($options) as $option)
		{
			if (!in_array($option, Model::$VALID_OPTIONS))
				unset($options[$option]);
		}
		return $options;
	}

	/**
	 * Infers the $this->className based on $this->attributeName.
	 *
	 * Will try to guess the appropriate class by singularizing and uppercasing $this->attributeName.
	 *
	 * @return void
	 * @see attribute_name
	 */
	protected function setInferredClassName()
	{
		$singularize = ($this instanceOf HasMany ? true : false);
		$this->setClassName(classify($this->attributeName, $singularize));
	}

	protected function setClassName($className)
	{
		$reflection = Reflections::instance()->add($className)->get($className);

		if (!$reflection->isSubClassOf('ActiveRecord\\Model'))
			throw new RelationshipException("'$className' must extend from ActiveRecord\\Model");

		$this->className = $className;
	}

	protected function createConditionsFromKeys(Model $model, $conditionKeys=array(), $valueKeys=array())
	{
		$conditionString = implode('_and_', $conditionKeys);
		$conditionValues = array_values($model->getValuesFor($valueKeys));

		// return null if all the foreign key values are null so that we don't try to do a query like "id is null"
		if (all(null,$conditionValues))
			return null;

		$conditions = SQLBuilder::createConditionsFromUnderscoredString(Table::load(get_class($model))->conn,$conditionString,$conditionValues);

		# DO NOT CHANGE THE NEXT TWO LINES. add_condition operates on a reference and will screw options array up
		if (isset($this->options['conditions']))
			$optionsConditions = $this->options['conditions'];
		else
			$optionsConditions = array();

		return Utils::addCondition($optionsConditions, $conditions);
	}

	/**
	 * Creates INNER JOIN SQL for associations.
	 *
	 * @param Table $fromTable the table used for the FROM SQL statement
	 * @param bool $usingThrough is this a THROUGH relationship?
	 * @param string $alias a table alias for when a table is being joined twice
	 * @return string SQL INNER JOIN fragment
	 */
	public function constructInnerJoinSql(Table $fromTable, $usingThrough=false, $alias=null)
	{
		if ($usingThrough)
		{
			$joinTable = $fromTable;
			$joinTableName = $fromTable->getFullyQualifiedTableName();
			$fromTableName = Table::load($this->className)->getFullyQualifiedTableName();
 		}
		else
		{
			$joinTable = Table::load($this->className);
			$joinTableName = $joinTable->getFullyQualifiedTableName();
			$fromTableName = $fromTable->getFullyQualifiedTableName();
		}

		// need to flip the logic when the key is on the other table
		if ($this instanceof HasMany || $this instanceof HasOne)
		{
			$this->setKeys($fromTable->class->getName());

			if ($usingThrough)
			{
				$foreignKey = $this->primaryKey[0];
				$joinPrimaryKey = $this->foreignKey[0];
			}
			else
			{
				$joinPrimaryKey = $this->foreignKey[0];
				$foreignKey = $this->primaryKey[0];
			}
		}
		else
		{
			$foreignKey = $this->foreignKey[0];
			$joinPrimaryKey = $this->primaryKey[0];
		}

		if (!is_null($alias))
		{
			$aliasedJoinTableName = $alias = $this->getTable()->conn->quoteName($alias);
			$alias .= ' ';
		}
		else
			$aliasedJoinTableName = $joinTableName;

		return "INNER JOIN $joinTableName {$alias}ON($fromTableName.$foreignKey = $aliasedJoinTableName.$joinPrimaryKey)";
	}

	/**
	 * This will load the related model data.
	 *
	 * @param Model $model The model this relationship belongs to
	 */
	abstract function load(Model $model);
};

/**
 * One-to-many relationship.
 *
 * <code>
 * # Table: people
 * # Primary key: id
 * # Foreign key: school_id
 * class Person extends ActiveRecord\Model {}
 *
 * # Table: schools
 * # Primary key: id
 * class School extends ActiveRecord\Model {
 *   static $hasMany = array(
 *     array('people')
 *   );
 * });
 * </code>
 *
 * Example using options:
 *
 * <code>
 * class Payment extends ActiveRecord\Model {
 *   static $belongsTo = array(
 *     array('person'),
 *     array('order')
 *   );
 * }
 *
 * class Order extends ActiveRecord\Model {
 *   static $hasMany = array(
 *     array('people',
 *           'through'    => 'payments',
 *           'select'     => 'people.*, payments.amount',
 *           'conditions' => 'payments.amount < 200')
 *     );
 * }
 * </code>
 *
 * @package ActiveRecord
 * @see http://www.phpactiverecord.org/guides/associations
 * @see valid_association_options
 */
class HasMany extends AbstractRelationship
{
	/**
	 * Valid options to use for a {@link HasMany} relationship.
	 *
	 * <ul>
	 * <li><b>limit/offset:</b> limit the number of records</li>
	 * <li><b>primary_key:</b> name of the primary_key of the association (defaults to "id")</li>
	 * <li><b>group:</b> GROUP BY clause</li>
	 * <li><b>order:</b> ORDER BY clause</li>
	 * <li><b>through:</b> name of a model</li>
	 * </ul>
	 *
	 * @var array
	 */
	static protected $validAssociationOptions = array('primary_key', 'order', 'group', 'having', 'limit', 'offset', 'through', 'source');

	protected $primaryKey;

	private $hasOne = false;
	private $through;

	/**
	 * Constructs a {@link HasMany} relationship.
	 *
	 * @param array $options Options for the association
	 * @return HasMany
	 */
	public function __construct($options=array())
	{
		parent::__construct($options);

		if (isset($this->options['through']))
		{
			$this->through = $this->options['through'];

			if (isset($this->options['source']))
				$this->setClassName($this->options['source']);
		}

		if (!$this->primaryKey && isset($this->options['primary_key']))
			$this->primaryKey = is_array($this->options['primary_key']) ? $this->options['primary_key'] : array($this->options['primary_key']);

		if (!$this->className)
			$this->setInferredClassName();
	}

	protected function setKeys($modelClassName, $override=false)
	{
		//infer from class_name
		if (!$this->foreignKey || $override)
			$this->foreignKey = array(Inflector::instance()->keyify($modelClassName));

		if (!$this->primaryKey || $override)
			$this->primaryKey = Table::load($modelClassName)->pk;
	}

	public function load(Model $model)
	{
		$className = $this->className;
		$this->setKeys(get_class($model));

		// since through relationships depend on other relationships we can't do
		// this initiailization in the constructor since the other relationship
		// may not have been created yet and we only want this to run once
		if (!isset($this->initialized))
		{
			if ($this->through)
			{
				// verify through is a belongs_to or has_many for access of keys
				if (!($throughRelationship = $this->getTable()->getRelationship($this->through)))
					throw new HasManyThroughAssociationException("Could not find the association $this->through in model " . get_class($model));

				if (!($throughRelationship instanceof HasMany) && !($throughRelationship instanceof BelongsTo))
					throw new HasManyThroughAssociationException('has_many through can only use a belongs_to or has_many association');

				// save old keys as we will be reseting them below for inner join convenience
				$pk = $this->primaryKey;
				$fk = $this->foreignKey;

				$this->setKeys($this->getTable()->class->getName(), true);
				
				$class = $this->className;
				$relation = $class::table()->getRelationship($this->through);
				$throughTable = $relation->getTable();
				$this->options['joins'] = $this->constructInnerJoinSql($throughTable, true);

				// reset keys
				$this->primaryKey = $pk;
				$this->foreignKey = $fk;
			}

			$this->initialized = true;
		}

		if (!($conditions = $this->createConditionsFromKeys($model, $this->foreignKey, $this->primaryKey)))
			return null;

		$options = $this->unsetNonFinderOptions($this->options);
		$options['conditions'] = $conditions;
		return $className::find($this->polyRelationship ? 'all' : 'first',$options);
	}

	private function injectForeignKeyForNewAssociation(Model $model, &$attributes)
	{
		$this->setKeys($model);
		$primaryKey = Inflector::instance()->variablize($this->foreignKey[0]);

		if (!isset($attributes[$primaryKey]))
			$attributes[$primaryKey] = $model->id;

		return $attributes;
	}

	public function buildAssociation(Model $model, $attributes=array())
	{
		$attributes = $this->injectForeignKeyForNewAssociation($model, $attributes);
		return parent::buildAssociation($model, $attributes);
	}

	public function createAssociation(Model $model, $attributes=array())
	{
		$attributes = $this->injectForeignKeyForNewAssociation($model, $attributes);
		return parent::createAssociation($model, $attributes);
	}

	public function loadEagerly($models=array(), $attributes=array(), $includes, Table $table)
	{
		$this->setKeys($table->class->name);
		$this->queryAndAttachRelatedModelsEagerly($table,$models,$attributes,$includes,$this->foreignKey, $table->pk);
	}
};

/**
 * One-to-one relationship.
 *
 * <code>
 * # Table name: states
 * # Primary key: id
 * class State extends ActiveRecord\Model {}
 *
 * # Table name: people
 * # Foreign key: state_id
 * class Person extends ActiveRecord\Model {
 *   static $hasOne = array(array('state'));
 * }
 * </code>
 *
 * @package ActiveRecord
 * @see http://www.phpactiverecord.org/guides/associations
 */
class HasOne extends HasMany
{
};

/**
 * @todo implement me
 * @package ActiveRecord
 * @see http://www.phpactiverecord.org/guides/associations
 */
class HasAndBelongsToMany extends AbstractRelationship
{
	public function __construct($options=array())
	{
		/* options =>
		 *   join_table - name of the join table if not in lexical order
		 *   foreign_key -
		 *   association_foreign_key - default is {assoc_class}_id
		 *   uniq - if true duplicate assoc objects will be ignored
		 *   validate
		 */
	}

	public function load(Model $model)
	{

	}
};

/**
 * Belongs to relationship.
 *
 * <code>
 * class School extends ActiveRecord\Model {}
 *
 * class Person extends ActiveRecord\Model {
 *   static $belongsTo = array(
 *     array('school')
 *   );
 * }
 * </code>
 *
 * Example using options:
 *
 * <code>
 * class School extends ActiveRecord\Model {}
 *
 * class Person extends ActiveRecord\Model {
 *   static $belongsTo = array(
 *     array('school', 'primary_key' => 'school_id')
 *   );
 * }
 * </code>
 *
 * @package ActiveRecord
 * @see valid_association_options
 * @see http://www.phpactiverecord.org/guides/associations
 */
class BelongsTo extends AbstractRelationship
{
	public function __construct($options=array())
	{
		parent::__construct($options);

		if (!$this->className)
			$this->setInferredClassName();

		//infer from class_name
		if (!$this->foreignKey)
			$this->foreignKey = array(Inflector::instance()->keyify($this->className));

		$this->primaryKey = array(Table::load($this->className)->pk[0]);
	}

	public function load(Model $model)
	{
		$keys = array();
		$inflector = Inflector::instance();

		foreach ($this->foreignKey as $key)
			$keys[] = $inflector->variablize($key);

		if (!($conditions = $this->createConditionsFromKeys($model, $this->primaryKey, $keys)))
			return null;

		$options = $this->unsetNonFinderOptions($this->options);
		$options['conditions'] = $conditions;
		$class = $this->className;
		return $class::first($options);
	}

	public function loadEagerly($models=array(), $attributes, $includes, Table $table)
	{
		$this->queryAndAttachRelatedModelsEagerly($table,$models,$attributes,$includes, $this->primaryKey,$this->foreignKey);
	}
};
?>
