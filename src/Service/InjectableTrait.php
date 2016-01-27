<?php
/**
 * This file is part of the DTForce Nette-Injection extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */

namespace DTForce\NetteInject\Service;

use InvalidArgumentException;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use ReflectionClass;


/**
 * Trait offering same functionality as {@see Service} to allow easy way of implementing {@see IInjectableService}.
 * @package DTForce\NetteInjection\Service
 */
trait InjectableTrait
{

	/**
	 * @var array
	 */
	private $InjectableTrait_alreadyInjected = [];

	/**
	 * @var bool
	 */
	private $InjectableTrait_injectionCompleted = false;

	/**
	 * @var array
	 */
	private $InjectableTrait_parameters = [];

	/**
	 * @var array
	 */
	protected static $InjectableTrait_reflections = [];


	/**
	 * @return \Nette\Reflection\Property[]
	 */
	public static function InjectableTrait_getPropertiesCache()
	{
		return static::InjectableTrait_getReflection()->getProperties(\ReflectionProperty::IS_PROTECTED);
	}


	/**
	 * @return ClassType
	 */
	public static function InjectableTrait_getReflection()
	{
		$class = get_called_class();
		if ( ! array_key_exists($class, static::$InjectableTrait_reflections)) {
			static::$InjectableTrait_reflections[$class] = new ClassType(get_called_class());
		}
		return static::$InjectableTrait_reflections[$class];
	}


	/**
	 * Injects service to a property.
	 *
	 * @param string $propertyName Name of property
	 * @param object $service Instance of desired service
	 * @throws \InvalidArgumentException When tried to inject same property twice or
	 * trying inject non injectable property or trying to inject after injection process was completed.
	 */
	public final function InjectableTrait_injectService($propertyName, $service)
	{
		if ( ! array_key_exists($propertyName, $this->InjectableTrait_alreadyInjected)
			&& ! $this->InjectableTrait_injectionCompleted
		) {
			$reflectionClass = self::InjectableTrait_getReflection();
			$property = $reflectionClass->getProperty($propertyName);
			$property->setAccessible(true);
			$property->setValue($this, $service);
			$this->InjectableTrait_alreadyInjected[$propertyName] = true;
		} else {
			if (array_key_exists($propertyName, $this->InjectableTrait_alreadyInjected)) {
				throw new InvalidArgumentException(
					"Error when injecting propertyName:$propertyName. Injection was done already."
				);
			}
			if ($this->InjectableTrait_injectionCompleted) {
				throw new InvalidArgumentException(
					"Error when injecting propertyName:$propertyName. Cannot inject when injection process was completed before."
				);
			}
		}
	}


	/**
	 * Returns array of properties needed to be injected.
	 * Keys of array are property names, values are service names.
	 *
	 * @return array
	 */
	public static function InjectableTrait_getInjectionByNameProperties()
	{
		$injectionProperties = [];
		$properties = static::InjectableTrait_getReflection()->getProperties();
		foreach ($properties as $property) {
			if ($property->hasAnnotation(AService::INJECT_SERVICE_ANNOTATION)) {
				$serviceName = $property->getAnnotation(AService::INJECT_SERVICE_ANNOTATION);
				if (is_string($serviceName) && strlen($serviceName) > 0) {
					$injectionProperties[$property->name] = $serviceName;
				}
			}
		}
		return $injectionProperties;
	}


	/**
	 * Returns array of properties needed to be injected.
	 * Keys of array are property names, values are service types.
	 *
	 * Name must match with InjectionCompilerExtension::IIS_GET_INJECTION_PROPS_METHOD
	 *
	 * @return array
	 */
	public static function InjectableTrait_getInjectionByTypeProperties()
	{
		$injectionProperties = [];
		$properties = static::InjectableTrait_getReflection()->getProperties();
		foreach ($properties as $property) {
			if ($property->hasAnnotation(AService::INJECT_SERVICE_ANNOTATION)) {
				$serviceName = $property->getAnnotation(AService::INJECT_SERVICE_ANNOTATION);
				$type = $property->getAnnotation(AService::TYPE_ANNOTATION);
				if (($serviceName === true || strlen($serviceName) === 0) && $type !== null) {
					$type = AnnotationsParser::expandClassName($type, $property->getDeclaringClass());
					$injectionProperties[$property->name] = $type;
				}
			}
		}
		return $injectionProperties;
	}


	/**
	 * Notifies service about completion of service injection.
	 */
	public final function InjectableTrait_injectionCompleted()
	{
		$this->InjectableTrait_injectionCompleted = true;
		if (method_exists($this, 'onInjectionCompleted')) {
			$method = $this->InjectableTrait_getReflection()->getMethod('onInjectionCompleted');
			$method->setAccessible(true);
			$method->invoke($this);
		}
	}


	public final function InjectableTrait_injectParameters(\Nette\DI\Container $container)
	{
		$this->InjectableTrait_parameters = $container->getParameters();
	}


	/**
	 * Returns value of parameter in DI container. It starts at
	 * parameters: key in config and you can
	 * use dot to navigate to deeper levels.
	 *
	 * @param $key
	 * @param mixed $default Default value returned, if value was not found.
	 * @return mixed
	 */
	protected function InjectableTrait_getParameter($key, $default = null)
	{
		return \Nette\Utils\Arrays::get($this->InjectableTrait_parameters, explode('.', $key), $default);
	}

}
