<?php
/**
 * These two classes have been <i>heavily borrowed</i> from Ruby on Rails' ActiveRecord so much that
 * this piece can be considered a straight port. The reason for this is that the vaildation process is
 * tricky due to order of operations/events. The former combined with PHP's odd typecasting means
 * that it was easier to formulate this piece base on the rails code.
 *
 * @package ActiveRecord
 */

namespace ActiveRecord;
use ActiveRecord\Model;
use IteratorAggregate;
use ArrayIterator;

/**
 * Manages validations for a {@link Model}.
 *
 * This class isn't meant to be directly used. Instead you define
 * validators thru static variables in your {@link Model}. Example:
 *
 * <code>
 * class Person extends ActiveRecord\Model {
 *   static $validatesLengthOf = array(
 *     array('name', 'within' => array(30,100),
 *     array('state', 'is' => 2)
 *   );
 * }
 *
 * $person = new Person();
 * $person->name = 'Tito';
 * $person->state = 'this is not two characters';
 *
 * if (!$person->isValid())
 *   print_r($person->errors);
 * </code>
 *
 * @package ActiveRecord
 * @see Errors
 * @link http://www.phpactiverecord.org/guides/validations
 */
class Validations
{
	private $model;
	private $options = array();
	private $validators = array();
	private $record;

	private static $VALIDATION_FUNCTIONS = array(
		'validates_presence_of',
		'validates_size_of',
		'validates_length_of',
		'validates_inclusion_of',
		'validates_exclusion_of',
		'validates_format_of',
		'validates_numericality_of',
		'validates_uniqueness_of'
	);

	private static $DEFAULT_VALIDATION_OPTIONS = array(
		'on' => 'save',
		'allow_null' => false,
		'allow_blank' => false,
		'message' => null,
	);

	private static  $ALL_RANGE_OPTIONS = array(
		'is' => null,
		'within' => null,
		'in' => null,
		'minimum' => null,
		'maximum' => null,
	);

	private static $ALL_NUMERICALITY_CHECKS = array(
		'greater_than' => null,
		'greater_than_or_equal_to'  => null,
		'equal_to' => null,
		'less_than' => null,
		'less_than_or_equal_to' => null,
		'odd' => null,
		'even' => null
	);

	/**
	 * Constructs a {@link Validations} object.
	 *
	 * @param Model $model The model to validate
	 * @return Validations
	 */
	public function __construct(Model $model)
	{
		$this->model = $model;
		$this->record = new Errors($this->model);
		$this->klass = Reflections::instance()->get(get_class($this->model));
		$this->validators = array_intersect(array_keys($this->klass->getStaticProperties()), self::$VALIDATION_FUNCTIONS);
	}

	public function getRecord()
	{
		return $this->record;
	}

	/**
	 * Returns validator data.
	 *
	 * @return array
	 */
	public function rules()
	{
		$data = array();
		foreach ($this->validators as $validate)
		{
			$attrs = $this->klass->getStaticPropertyValue($validate);

			foreach (wrap_strings_in_arrays($attrs) as $attr)
			{
				$field = $attr[0];

				if (!isset($data[$field]) || !is_array($data[$field]))
					$data[$field] = array();

				$attr['validator'] = $validate;
				unset($attr[0]);
				array_push($data[$field],$attr);
			}
		}
		return $data;
	}

	/**
	 * Runs the validators.
	 *
	 * @return Errors the validation errors if any
	 */
	public function validate()
	{
		foreach ($this->validators as $validate)
		{
			$definition = $this->klass->getStaticPropertyValue($validate);
			$this->$validate(wrap_strings_in_arrays($definition));
		}

		$modelReflection = Reflections::instance()->get($this->model);

		if ($modelReflection->hasMethod('validate') && $modelReflection->getMethod('validate')->isPublic())
			$this->model->validate();

		$this->record->clearModel();
		return $this->record;
	}

	/**
	 * Validates a field is not null and not blank.
	 *
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $validatesPresenceOf = array(
	 *     array('first_name'),
	 *     array('last_name')
	 *   );
	 * }
	 * </code>
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>message:</b> custom error message</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings</li>
	 * </ul>
	 *
	 * @param array $attrs Validation definition
	 */
	public function validatesPresenceOf($attrs)
	{
		$configuration = array_merge(self::$DEFAULT_VALIDATION_OPTIONS, array('message' => Errors::$DEFAULT_ERROR_MESSAGES['blank'], 'on' => 'save'));

		foreach ($attrs as $attr)
		{
			$options = array_merge($configuration, $attr);
			$this->record->addOnBlank($options[0], $options['message']);
		}
	}

	/**
	 * Validates that a value is included the specified array.
	 *
	 * <code>
	 * class Car extends ActiveRecord\Model {
	 *   static $validatesInclusionOf = array(
	 *     array('fuel_type', 'in' => array('hyrdogen', 'petroleum', 'electric')),
	 *   );
	 * }
	 * </code>
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>in/within:</b> attribute should/shouldn't be a value within an array</li>
	 * <li><b>message:</b> custome error message</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings</li>
	 * </ul>
	 *
	 * @param array $attrs Validation definition
	 */
	public function validatesInclusionOf($attrs)
	{
		$this->validatesInclusionOrExclusionOf('inclusion', $attrs);
	}

	/**
	 * This is the opposite of {@link validates_include_of}.
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>in/within:</b> attribute should/shouldn't be a value within an array</li>
	 * <li><b>message:</b> custome error message</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings</li>
	 * </ul>
	 *
	 * @param array $attrs Validation definition
	 * @see validates_inclusion_of
	 */
	public function validatesExclusionOf($attrs)
	{
		$this->validatesInclusionOrExclusionOf('exclusion', $attrs);
	}

	/**
	 * Validates that a value is in or out of a specified list of values.
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>in/within:</b> attribute should/shouldn't be a value within an array</li>
	 * <li><b>message:</b> custome error message</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings</li>
	 * </ul>
	 *
	 * @see validates_inclusion_of
	 * @see validates_exclusion_of
	 * @param string $type Either inclusion or exclusion
	 * @param $attrs Validation definition
	 */
	public function validatesInclusionOrExclusionOf($type, $attrs)
	{
		$configuration = array_merge(self::$DEFAULT_VALIDATION_OPTIONS, array('message' => Errors::$DEFAULT_ERROR_MESSAGES[$type], 'on' => 'save'));

		foreach ($attrs as $attr)
		{
			$options = array_merge($configuration, $attr);
			$attribute = $options[0];
			$var = $this->model->$attribute;

			if (isset($options['in']))
				$enum = $options['in'];
			elseif (isset($options['within']))
				$enum = $options['within'];

			if (!is_array($enum))
				array($enum);

			$message = str_replace('%s', $var, $options['message']);

			if ($this->isNullWithOption($var, $options) || $this->isBlankWithOption($var, $options))
				continue;

			if (('inclusion' == $type && !in_array($var, $enum)) || ('exclusion' == $type && in_array($var, $enum)))
				$this->record->add($attribute, $message);
		}
	}

	/**
	 * Validates that a value is numeric.
	 *
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $validatesNumericalityOf = array(
	 *     array('salary', 'greater_than' => 19.99, 'less_than' => 99.99)
	 *   );
	 * }
	 * </code>
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>only_integer:</b> value must be an integer (e.g. not a float)</li>
	 * <li><b>even:</b> must be even</li>
	 * <li><b>odd:</b> must be odd"</li>
	 * <li><b>greater_than:</b> must be greater than specified number</li>
	 * <li><b>greater_than_or_equal_to:</b> must be greater than or equal to specified number</li>
	 * <li><b>equal_to:</b> ...</li>
	 * <li><b>less_than:</b> ...</li>
	 * <li><b>less_than_or_equal_to:</b> ...</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings</li>
	 * </ul>
	 *
	 * @param array $attrs Validation definition
	 */
	public function validatesNumericalityOf($attrs)
	{
		$configuration = array_merge(self::$DEFAULT_VALIDATION_OPTIONS, array('only_integer' => false));

		// Notice that for fixnum and float columns empty strings are converted to nil.
		// Validates whether the value of the specified attribute is numeric by trying to convert it to a float with Kernel.Float
		// (if only_integer is false) or applying it to the regular expression /\A[+\-]?\d+\Z/ (if only_integer is set to true).
		foreach ($attrs as $attr)
		{
			$options = array_merge($configuration, $attr);
			$attribute = $options[0];
			$var = $this->model->$attribute;

			$numericalityOptions = array_intersect_key(self::$ALL_NUMERICALITY_CHECKS, $options);

			if ($this->isNullWithOption($var, $options))
				continue;

			$notANumberMessage = (isset($options['message']) ? $options['message'] : Errors::$DEFAULT_ERROR_MESSAGES['not_a_number']);

			if (true === $options['only_integer'] && !is_integer($var))
			{
				if (!preg_match('/\A[+-]?\d+\Z/', (string)($var)))
				{
					$this->record->add($attribute, $notANumberMessage);
					continue;
				}
			}
			else
			{
				if (!is_numeric($var))
				{
					$this->record->add($attribute, $notANumberMessage);
					continue;
				}

				$var = (float)$var;
			}

			foreach ($numericalityOptions as $option => $check)
			{
				$optionValue = $options[$option];
				$message = (isset($options['message']) ? $options['message'] : Errors::$DEFAULT_ERROR_MESSAGES[$option]);

				if ('odd' != $option && 'even' != $option)
				{
					$optionValue = (float)$options[$option];

					if (!is_numeric($optionValue))
						throw new ValidationsArgumentError("$option must be a number");

					$message = str_replace('%d', $optionValue, $message);

					if ('greater_than' == $option && !($var > $optionValue))
						$this->record->add($attribute, $message);

					elseif ('greater_than_or_equal_to' == $option && !($var >= $optionValue))
						$this->record->add($attribute, $message);

					elseif ('equal_to' == $option && !($var == $optionValue))
						$this->record->add($attribute, $message);

					elseif ('less_than' == $option && !($var < $optionValue))
						$this->record->add($attribute, $message);

					elseif ('less_than_or_equal_to' == $option && !($var <= $optionValue))
						$this->record->add($attribute, $message);
				}
				else
				{
					if (('odd' == $option && !Utils::isOdd($var)) || ('even' == $option && Utils::isOdd($var)))
						$this->record->add($attribute, $message);
				}
			}
		}
	}

	/**
	 * Alias of {@link validates_length_of}
	 *
	 * @param array $attrs Validation definition
	 */
	public function validatesSizeOf($attrs)
	{
		$this->validatesLengthOf($attrs);
	}

	/**
	 * Validates that a value is matches a regex.
	 *
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $validatesFormatOf = array(
	 *     array('email', 'with' => '/^.*?@.*$/')
	 *   );
	 * }
	 * </code>
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>with:</b> a regular expression</li>
	 * <li><b>message:</b> custom error message</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings</li>
	 * </ul>
	 *
	 * @param array $attrs Validation definition
	 */
	public function validatesFormatOf($attrs)
	{
		$configuration = array_merge(self::$DEFAULT_VALIDATION_OPTIONS, array('message' => Errors::$DEFAULT_ERROR_MESSAGES['invalid'], 'on' => 'save', 'with' => null));

		foreach ($attrs as $attr)
		{
			$options = array_merge($configuration, $attr);
			$attribute = $options[0];
			$var = $this->model->$attribute;

			if (is_null($options['with']) || !is_string($options['with']) || !is_string($options['with']))
				throw new ValidationsArgumentError('A regular expression must be supplied as the [with] option of the configuration array.');
			else
				$expression = $options['with'];

			if ($this->isNullWithOption($var, $options) || $this->isBlankWithOption($var, $options))
				continue;

			if (!@preg_match($expression, $var))
			$this->record->add($attribute, $options['message']);
		}
	}

	/**
	 * Validates the length of a value.
	 *
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $validatesLengthOf = array(
	 *     array('name', 'within' => array(1,50))
	 *   );
	 * }
	 * </code>
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>is:</b> attribute should be exactly n characters long</li>
	 * <li><b>in/within:</b> attribute should be within an range array(min,max)</li>
	 * <li><b>maximum/minimum:</b> attribute should not be above/below respectively</li>
	 * <li><b>message:</b> custome error message</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings. (Even if this is set to false, a null string is always shorter than a maximum value.)</li>
	 * </ul>
	 *
	 * @param array $attrs Validation definition
	 */
	public function validatesLengthOf($attrs)
	{
		$configuration = array_merge(self::$DEFAULT_VALIDATION_OPTIONS, array(
			'too_long'     => Errors::$DEFAULT_ERROR_MESSAGES['too_long'],
			'too_short'    => Errors::$DEFAULT_ERROR_MESSAGES['too_short'],
			'wrong_length' => Errors::$DEFAULT_ERROR_MESSAGES['wrong_length']
		));

		foreach ($attrs as $attr)
		{
			$options = array_merge($configuration, $attr);
			$rangeOptions = array_intersect(array_keys(self::$ALL_RANGE_OPTIONS), array_keys($attr));
			sort($rangeOptions);

			switch (sizeof($rangeOptions))
			{
				case 0:
					throw new  ValidationsArgumentError('Range unspecified.  Specify the [within], [maximum], or [is] option.');

				case 1:
					break;

				default:
					throw new  ValidationsArgumentError('Too many range options specified.  Choose only one.');
			}

			$attribute = $options[0];
			$var = $this->model->$attribute;
			if ($this->isNullWithOption($var, $options) || $this->isBlankWithOption($var, $options))
				continue;
			if ($rangeOptions[0] == 'within' || $rangeOptions[0] == 'in')
			{
				$range = $options[$rangeOptions[0]];

				if (!(Utils::isA('range', $range)))
					throw new  ValidationsArgumentError("$rangeOption must be an array composing a range of numbers with key [0] being less than key [1]");
				$rangeOptions = array('minimum', 'maximum');
				$attr['minimum'] = $range[0];
				$attr['maximum'] = $range[1];
			}
			foreach ($rangeOptions as $rangeOption)
			{
				$option = $attr[$rangeOption];

				if ((int)$option <= 0)
					throw new  ValidationsArgumentError("$rangeOption value cannot use a signed integer.");

				if (is_float($option))
					throw new  ValidationsArgumentError("$rangeOption value cannot use a float for length.");

				if (!($rangeOption == 'maximum' && is_null($this->model->$attribute)))
				{
					$messageOptions = array('is' => 'wrong_length', 'minimum' => 'too_short', 'maximum' => 'too_long');

					if (isset($options['message']))
						$message = $options['message'];
					else
						$message = $options[$messageOptions[$rangeOption]];
					

					$message = str_replace('%d', $option, $message);
					$attributeValue = $this->model->$attribute;
					$len = strlen($attributeValue);
					$value = (int)$attr[$rangeOption];

					if ('maximum' == $rangeOption && $len > $value)
						$this->record->add($attribute, $message);

					if ('minimum' == $rangeOption && $len < $value)
						$this->record->add($attribute, $message);

					if ('is' == $rangeOption && $len !== $value)
						$this->record->add($attribute, $message);
				}
			}
		}
	}

	/**
	 * Validates the uniqueness of a value.
	 *
	 * <code>
	 * class Person extends ActiveRecord\Model {
	 *   static $validatesUniquenessOf = array(
	 *     array('name'),
	 *     array(array('blah','bleh'), 'message' => 'blech')
	 *   );
	 * }
	 * </code>
	 *
	 * Available options:
	 *
	 * <ul>
	 * <li><b>with:</b> a regular expression</li>
	 * <li><b>message:</b> custom error message</li>
	 * <li><b>allow_blank:</b> allow blank strings</li>
	 * <li><b>allow_null:</b> allow null strings</li>
	 * </ul>
	 *
	 * @param array $attrs Validation definition
	 */
	public function validatesUniquenessOf($attrs)
	{
		$configuration = array_merge(self::$DEFAULT_VALIDATION_OPTIONS, array(
			'message' => Errors::$DEFAULT_ERROR_MESSAGES['unique']
		));

		foreach ($attrs as $attr)
		{
			$options = array_merge($configuration, $attr);
			$pk = $this->model->getPrimaryKey();
			$pkValue = $this->model->$pk[0];

			if (is_array($options[0]))
			{
				$addRecord = join("_and_", $options[0]);
				$fields = $options[0];
			}
			else
			{
				$addRecord = $options[0];
				$fields = array($options[0]);
			}

			$sql = "";
			$conditions = array("");

			if ($pkValue === null)
				$sql = "{$pk[0]} is not null";
			else
			{
				$sql = "{$pk[0]}!=?";
				array_push($conditions,$pkValue);
			}

			foreach ($fields as $field)
			{
				$field = $this->model->getRealAttributeName($field);
				$sql .= " and {$field}=?";
				array_push($conditions,$this->model->$field);
			}

			$conditions[0] = $sql;

			if ($this->model->exists(array('conditions' => $conditions)))
				$this->record->add($addRecord, $options['message']);
		}
	}

	private function isNullWithOption($var, &$options)
	{
		return (is_null($var) && (isset($options['allow_null']) && $options['allow_null']));
	}

	private function isBlankWithOption($var, &$options)
	{
		return (Utils::isBlank($var) && (isset($options['allow_blank']) && $options['allow_blank']));
	}
}

/**
 * Class that holds {@link Validations} errors.
 *
 * @package ActiveRecord
 */
class Errors implements IteratorAggregate
{
	private $model;
	private $errors;

	public static $DEFAULT_ERROR_MESSAGES = array(
		'inclusion'    => "is not included in the list",
		'exclusion'    => "is reserved",
		'invalid'      => "is invalid",
		'confirmation' => "doesn't match confirmation",
		'accepted'     => "must be accepted",
		'empty'        => "can't be empty",
		'blank'        => "can't be blank",
		'too_long'     => "is too long (maximum is %d characters)",
		'too_short'    => "is too short (minimum is %d characters)",
		'wrong_length' => "is the wrong length (should be %d characters)",
		'taken'        => "has already been taken",
		'not_a_number' => "is not a number",
		'greater_than' => "must be greater than %d",
		'equal_to'     => "must be equal to %d",
		'less_than'    => "must be less than %d",
		'odd'          => "must be odd",
		'even'         => "must be even",
		'unique'       => "must be unique",
		'less_than_or_equal_to' => "must be less than or equal to %d",
		'greater_than_or_equal_to' => "must be greater than or equal to %d"
	);

	/**
	 * Constructs an {@link Errors} object.
	 *
	 * @param Model $model The model the error is for
	 * @return Errors
	 */
	public function __construct(Model $model)
	{
		$this->model = $model;
	}

	/**
	 * Nulls $model so we don't get pesky circular references. $model is only needed during the
	 * validation process and so can be safely cleared once that is done.
	 */
	public function clearModel()
	{
		$this->model = null;
	}

	/**
	 * Add an error message.
	 *
	 * @param string $attribute Name of an attribute on the model
	 * @param string $msg The error message
	 */
	public function add($attribute, $msg)
	{
		if (is_null($msg))
			$msg = self :: $DEFAULT_ERROR_MESSAGES['invalid'];

		if (!isset($this->errors[$attribute]))
			$this->errors[$attribute] = array($msg);
		else
			$this->errors[$attribute][] = $msg;
	}

	/**
	 * Adds an error message only if the attribute value is {@link http://www.php.net/empty empty}.
	 *
	 * @param string $attribute Name of an attribute on the model
	 * @param string $msg The error message
	 */
	public function addOnEmpty($attribute, $msg)
	{
		if (empty($msg))
			$msg = self::$DEFAULT_ERROR_MESSAGES['empty'];

		if (empty($this->model->$attribute))
			$this->add($attribute, $msg);
	}

	/**
	 * Retrieve error messages for an attribute.
	 *
	 * @param string $attribute Name of an attribute on the model
	 * @return array or null if there is no error.
	 */
	public function __get($attribute)
	{
		if (!isset($this->errors[$attribute]))
			return null;

		return $this->errors[$attribute];
	}

	/**
	 * Adds the error message only if the attribute value was null or an empty string.
	 *
	 * @param string $attribute Name of an attribute on the model
	 * @param string $msg The error message
	 */
	public function addOnBlank($attribute, $msg)
	{
		if (!$msg)
			$msg = self::$DEFAULT_ERROR_MESSAGES['blank'];

		if (($value = $this->model->$attribute) === '' || $value === null)
			$this->add($attribute, $msg);
	}

	/**
	 * Returns true if the specified attribute had any error messages.
	 *
	 * @param string $attribute Name of an attribute on the model
	 * @return boolean
	 */
	public function isInvalid($attribute)
	{
		return isset($this->errors[$attribute]);
	}

	/**
	 * Returns the error message(s) for the specified attribute or null if none.
	 *
	 * @param string $attribute Name of an attribute on the model
	 * @return string/array	Array of strings if several error occured on this attribute.
	 */
	public function on($attribute)
	{
		$errors = $this->$attribute;

		return $errors && count($errors) == 1 ? $errors[0] : $errors;
	}

	/**
	 * Returns the internal errors object.
	 *
	 * <code>
	 * $model->errors->getRawErrors();
	 *
	 * # array(
	 * #  "name" => array("can't be blank"),
	 * #  "state" => array("is the wrong length (should be 2 chars)",
	 * # )
	 * </code>
	 */
	public function getRawErrors()
	{
		return $this->errors;
	}

	/**
	 * Returns all the error messages as an array.
	 *
	 * <code>
	 * $model->errors->fullMessages();
	 *
	 * # array(
	 * #  "Name can't be blank",
	 * #  "State is the wrong length (should be 2 chars)"
	 * # )
	 * </code>
	 *
	 * @return array
	 */
	public function fullMessages()
	{
		$fullMessages = array();

		$this->toArray(function($attribute, $message) use (&$fullMessages) {
			$fullMessages[] = $message;
		});

		return $fullMessages;
	}

	/**
	 * Returns all the error messages as an array, including error key.
	 *
	 * <code>
	 * $model->errors->errors();
	 *
	 * # array(
	 * #  "name" => array("Name can't be blank"),
	 * #  "state" => array("State is the wrong length (should be 2 chars)")
	 * # )
	 * </code>
	 *
	 * @param array $closure Closure to fetch the errors in some other format (optional)
	 *                       This closure has the signature function($attribute, $message)
	 *                       and is called for each available error message.
	 * @return array
	 */
	public function toArray($closure=null)
	{
		$errors = array();

		if ($this->errors)
		{
			foreach ($this->errors as $attribute => $messages)
			{
				foreach ($messages as $msg)
				{
					if (is_null($msg))
						continue;

					$errors[$attribute][] = ($message = Utils::humanAttribute($attribute) . ' ' . $msg);

					if ($closure)
						$closure($attribute,$message);
				}
			}
		}
		return $errors;
	}

	/**
	 * Convert all error messages to a String.
	 * This function is called implicitely if the object is casted to a string:
	 *
	 * <code>
	 * echo $error;
	 *
	 * # "Name can't be blank\nState is the wrong length (should be 2 chars)"
	 * </code>
	 * @return string
	 */
	public function __toString()
	{
		return implode("\n", $this->fullMessages());
	}

	/**
	 * Returns true if there are no error messages.
	 * @return boolean
	 */
	public function isEmpty()
	{
		return empty($this->errors);
	}

	/**
	 * Clears out all error messages.
	 */
	public function clear()
	{
		$this->errors = array();
	}

	/**
	 * Returns the number of error messages there are.
	 * @return int
	 */
	public function size()
	{
		if ($this->isEmpty())
			return 0;

		$count = 0;

		foreach ($this->errors as $attribute => $error)
			$count += count($error);

		return $count;
	}

	/**
	 * Returns an iterator to the error messages.
	 *
	 * This will allow you to iterate over the {@link Errors} object using foreach.
	 *
	 * <code>
	 * foreach ($model->errors as $msg)
	 *   echo "$msg\n";
	 * </code>
	 *
	 * @return ArrayIterator
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->fullMessages());
	}
};
?>
