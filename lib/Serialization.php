<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;
use XmlWriter;

/**
 * Base class for Model serializers.
 *
 * All serializers support the following options:
 *
 * <ul>
 * <li><b>only:</b> a string or array of attributes to be included.</li>
 * <li><b>except:</b> a string or array of attributes to be excluded.</li>
 * <li><b>methods:</b> a string or array of methods to invoke. The method's name will be used as a key for the final attributes array
 * along with the method's returned value</li>
 * <li><b>include:</b> a string or array of associated models to include in the final serialized product.</li>
 * <li><b>onlyMethod:</b> a method that's called and only the resulting array is serialized
 * <li><b>skipInstruct:</b> set to true to skip the <?xml ...?> declaration.</li>
 * </ul>
 *
 * Example usage:
 *
 * <code>
 * # include the attributes id and name
 * # run $model->encodedDescription() and include its return value
 * # include the comments association
 * # include posts association with its own options (nested)
 * $model->toJson(array(
 *   'only' => array('id','name', 'encodedDescription'),
 *   'methods' => array('encodedDescription'),
 *   'include' => array('comments', 'posts' => array('only' => 'id'))
 * ));
 *
 * # except the password field from being included
 * $model->toXml(array('except' => 'password')));
 * </code>
 *
 * @package ActiveRecord
 * @link http://www.phpactiverecord.org/guides/utilities#topic-serialization
 */
abstract class Serialization
{
	protected $model;
	protected $options;
	protected $attributes;

	/**
	 * The default format to serialize DateTime objects to.
	 *
	 * @see DateTime
	 */
	public static $DATETIME_FORMAT = 'iso8601';

	/**
	 * Set this to true if the serializer needs to create a nested array keyed
	 * on the name of the included classes such as for xml serialization.
	 *
	 * Setting this to true will produce the following attributes array when
	 * the include option was used:
	 *
	 * <code>
	 * $user = array('id' => 1, 'name' => 'Tito',
	 *   'permissions' => array(
	 *     'permission' => array(
	 *       array('id' => 100, 'name' => 'admin'),
	 *       array('id' => 101, 'name' => 'normal')
	 *     )
	 *   )
	 * );
	 * </code>
	 *
	 * Setting to false will produce this:
	 *
	 * <code>
	 * $user = array('id' => 1, 'name' => 'Tito',
	 *   'permissions' => array(
	 *     array('id' => 100, 'name' => 'admin'),
	 *     array('id' => 101, 'name' => 'normal')
	 *   )
	 * );
	 * </code>
	 *
	 * @var boolean
	 */
	protected $includesWithClassNameElement = false;

	/**
	 * Constructs a {@link Serialization} object.
	 *
	 * @param Model $model The model to serialize
	 * @param array &$options Options for serialization
	 * @return Serialization
	 */
	public function __construct(Model $model, &$options)
	{
		$this->model = $model;
		$this->options = $options;
		$this->attributes = $model->attributes();
		$this->parseOptions();
	}

	private function parseOptions()
	{
		$this->checkOnly();
		$this->checkExcept();
		$this->checkMethods();
		$this->checkInclude();
		$this->checkOnlyMethod();        
	}

	private function checkOnly()
	{
		if (isset($this->options['only']))
		{
			$this->optionsToA('only');

			$exclude = array_diff(array_keys($this->attributes),$this->options['only']);
			$this->attributes = array_diff_key($this->attributes,array_flip($exclude));
		}
	}

	private function checkExcept()
	{
		if (isset($this->options['except']) && !isset($this->options['only']))
		{
			$this->optionsToA('except');
			$this->attributes = array_diff_key($this->attributes,array_flip($this->options['except']));
		}
	}

	private function checkMethods()
	{
		if (isset($this->options['methods']))
		{
			$this->optionsToA('methods');

			foreach ($this->options['methods'] as $method)
			{
				if (method_exists($this->model, $method))
					$this->attributes[$method] = $this->model->$method();
			}
		}
	}
	
	private function checkOnlyMethod()
	{
		if (isset($this->options['onlyMethod']))
		{
			$method = $this->options['onlyMethod'];
			if (method_exists($this->model, $method))
				$this->attributes = $this->model->$method();
		}
	}

	private function checkInclude()
	{
		if (isset($this->options['include']))
		{
			$this->optionsToA('include');

			$serializerClass = get_class($this);

			foreach ($this->options['include'] as $association => $options)
			{
				if (!is_array($options))
				{
					$association = $options;
					$options = array();
				}

				try {
					$assoc = $this->model->$association;

					if (!is_array($assoc))
					{
						$serialized = new $serializerClass($assoc, $options);
						$this->attributes[$association] = $serialized->toA();;
					}
					else
					{
						$includes = array();

						foreach ($assoc as $a)
						{
							$serialized = new $serializerClass($a, $options);

							if ($this->includesWithClassNameElement)
								$includes[strtolower(get_class($a))][] = $serialized->toA();
							else
								$includes[] = $serialized->toA();
						}

						$this->attributes[$association] = $includes;
					}

				} catch (UndefinedPropertyException $e) {
					;//move along
				}
			}
		}
	}

	final protected function optionsToA($key)
	{
		if (!is_array($this->options[$key]))
			$this->options[$key] = array($this->options[$key]);
	}

	/**
	 * Returns the attributes array.
	 * @return array
	 */
	final public function toA()
	{
		foreach ($this->attributes as &$value)
		{
			if ($value instanceof \DateTime)
				$value = $value->format(self::$DATETIME_FORMAT);
		}
		return $this->attributes;
	}

	/**
	 * Returns the serialized object as a string.
	 * @see to_s
	 * @return string
	 */
	final public function __toString()
	{
		return $this->toS();
	}

	/**
	 * Performs the serialization.
	 * @return string
	 */
	abstract public function toS();
};

/**
 * Array serializer.
 *
 * @package ActiveRecord
 */
class ArraySerializer extends Serialization
{
	public static $includeRoot = false;

	public function toS()
	{
		return self::$includeRoot ? array(strtolower(get_class($this->model)) => $this->toA()) : $this->toA();
	}
}

/**
 * JSON serializer.
 *
 * @package ActiveRecord
 */
class JsonSerializer extends ArraySerializer
{
	public static $includeRoot = false;

	public function toS()
	{
		parent::$includeRoot = self::$includeRoot;
		return json_encode(parent::toS());
	}
}

/**
 * XML serializer.
 *
 * @package ActiveRecord
 */
class XmlSerializer extends Serialization
{
	private $writer;

	public function __construct(Model $model, &$options)
	{
		$this->includesWithClassNameElement = true;
		parent::__construct($model,$options);
	}

	public function toS()
	{
		return $this->xmlEncode();
	}

	private function xmlEncode()
	{
		$this->writer = new XmlWriter();
		$this->writer->openMemory();
		$this->writer->startDocument('1.0', 'UTF-8');
		$this->writer->startElement(strtolower(denamespace(($this->model))));
		$this->write($this->toA());
		$this->writer->endElement();
		$this->writer->endDocument();
		$xml = $this->writer->outputMemory(true);

		if (@$this->options['skipInstruct'] == true)
			$xml = preg_replace('/<\?xml version.*?\?>/','',$xml);

		return $xml;
	}

	private function write($data, $tag=null)
	{
		foreach ($data as $attr => $value)
		{
			if ($tag != null)
				$attr = $tag;

			if (is_array($value) || is_object($value))
			{
				if (!is_int(key($value)))
				{
					$this->writer->startElement($attr);
					$this->write($value);
					$this->writer->endElement();
				}
				else
					$this->write($value, $attr);

				continue;
			}

			$this->writer->writeElement($attr, $value);
		}
	}
}

/**
 * CSV serializer.
 *
 * @package ActiveRecord
 */
class CsvSerializer extends Serialization
{
  public static $delimiter = ',';
  public static $enclosure = '"';

  public function toS()
  {
    if (@$this->options['onlyHeader'] == true) return $this->header();
    return $this->row();
  }

  private function header()
  {
    return $this->toCsv(array_keys($this->toA()));
  }

  private function row()
  {
    return $this->toCsv($this->toA());
  }

  private function toCsv($arr)
  {
    $outstream = fopen('php://temp', 'w');
    fputcsv($outstream, $arr, self::$delimiter, self::$enclosure);
    rewind($outstream);
    $buffer = trim(stream_get_contents($outstream));
    fclose($outstream);
    return $buffer;
  }
}
