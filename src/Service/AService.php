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


/**
 * Trait offering same functionality as {@see Service} to allow easy way of implementing {@see IInjectableService}.
 * @package DTForce\NetteInjection\Service
 */
trait ServiceTrait
{

	/**
	 * @var array
	 */
	private $alreadyInjected = [];

	/**
	 * @var bool
	 */
	private $injectionCompleted = false;

	/**
	 * @var array
	 */
	private $parameters = [];

	/**
	 * @var array
	 */
	protected static $ServiceTrait__reflections = [];


	/**
	 * @return \Nette\Reflection\Property[]
	 */
	public static function getPropertiesCache()
	{
		return static::getReflection()->getProperties(\ReflectionProperty::IS_PROTECTED);
	}


	/**
	 * @return ClassType
	 */
	public static function getReflection()
	{
		$class = get_called_class();
		if ( ! array_key_exists($class, static::$ServiceTrait__reflections)) {
			static::$ServiceTrait__reflections[$class] = new ClassType(get_called_class());
		}
		return static::$ServiceTrait__reflections[$class];
	}


	/**
	 * Injects service to a property.
	 *
	 * @param string $propertyName Name of property
	 * @param object $service Instance of desired service
	 * @throws \InvalidArgumentException When tried to inject same property twice or
	 * trying inject non injectable property or trying to inject after injection process was completed.
	 */
	public final function injectService($propertyName, $service)
	{
		if ( ! array_key_exists($propertyName, $this->alreadyInjected)
			&& ! $this->injectionCompleted
		) {
			$this->{$propertyName} = $service;
			$this->alreadyInjected[$propertyName] = true;
		} else {
			if (array_key_exists($propertyName, $this->alreadyInjected)) {
				throw new InvalidArgumentException(
					"Error when injecting propertyName:$propertyName. Injection was done already."
				);
			}
			if ($this->injectionCompleted) {
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
	public static function getInjectionByNameProperties()
	{
		$injectionProperties = [];
		$properties = static::getReflection()->getProperties();
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
	public static function getInjectionByTypeProperties()
	{
		$injectionProperties = [];
		$properties = static::getReflection()->getProperties();
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
	public final function injectionCompleted()
	{
		$this->injectionCompleted = true;
		$this->startup();
	}


	/**
	 * Called when all properties has been injected.
	 */
	protected function startup()
	{
	}


	public final function injectParameters(\Nette\DI\Container $container)
	{
		$this->parameters = $container->getParameters();
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
	protected function getParameter($key, $default = null)
	{
		return \Nette\Utils\Arrays::get($this->parameters, explode('.', $key), $default);
	}

}

/**
 * Common service ancestor allowing to inject service thanks to {@see InjectionCompilerExtension}.
 *
 * @package DTForce\NetteInjection\Service
 */
abstract class AService implements IInjectableService
{

	use ServiceTrait;

	const INJECT_SERVICE_ANNOTATION = 'inject';
	const TYPE_ANNOTATION = 'var';

}
