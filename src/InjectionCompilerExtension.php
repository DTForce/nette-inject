<?php
/**
 * This file is part of the DTForce Nette-Injection extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */


namespace DTForce\NetteInject;

use DTForce\NetteInject\Service\IInjectableService;
use DTForce\NetteInject\Service\InjectableTrait;
use DTForce\NetteInject\Service\IServiceMarker;
use Nette\Configurator;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\ServiceDefinition;
use Nette\DI\Statement;
use Nette\Loaders\RobotLoader;
use Nette\Reflection\ClassType;
use Nette\Utils\Strings;
use ReflectionClass;


/**
 * Class InjectionCompilerExtension is extension for Nette compiler, giving ability
 * to inject service automatically to other services, by name.
 *
 * For migration from old style of definition you can something based on these REGEXP (unquote by removing {})
 *
 * @package DTForce\NetteInjection
 */
class InjectionCompilerExtension extends CompilerExtension
{

	const TAG_INJECT = 'inject';

	/**
	 * Constant must match with interface describing injectable service.
	 */
	const IIS_INJECT_SERVICE_METHOD = 'injectService';
	const IIS_INJECT_PARAMETERS_METHOD = 'injectParameters';
	const IIS_GET_INJECTION_PROPS_METHOD = 'getInjectionByNameProperties';
	const IIS_GET_INJECTION_PROPS_TYPE_METHOD = 'getInjectionByTypeProperties';
	const IIS_INJECTION_COMPLETED_METHOD = 'injectionCompleted';

	const METHOD_MAPPING_INTERFACE = [
		self::IIS_INJECT_SERVICE_METHOD => self::IIS_INJECT_SERVICE_METHOD,
		self::IIS_INJECT_PARAMETERS_METHOD => self::IIS_INJECT_PARAMETERS_METHOD,
		self::IIS_GET_INJECTION_PROPS_METHOD => self::IIS_GET_INJECTION_PROPS_METHOD,
		self::IIS_GET_INJECTION_PROPS_TYPE_METHOD => self::IIS_GET_INJECTION_PROPS_TYPE_METHOD,
		self::IIS_INJECTION_COMPLETED_METHOD => self::IIS_INJECTION_COMPLETED_METHOD
	];

	const METHOD_MAPPING_TRAIT = [
		self::IIS_INJECT_SERVICE_METHOD => 'InjectableTrait_' . self::IIS_INJECT_SERVICE_METHOD,
		self::IIS_INJECT_PARAMETERS_METHOD => 'InjectableTrait_' . self::IIS_INJECT_PARAMETERS_METHOD,
		self::IIS_GET_INJECTION_PROPS_METHOD => 'InjectableTrait_' . self::IIS_GET_INJECTION_PROPS_METHOD,
		self::IIS_GET_INJECTION_PROPS_TYPE_METHOD => 'InjectableTrait_' . self::IIS_GET_INJECTION_PROPS_TYPE_METHOD,
		self::IIS_INJECTION_COMPLETED_METHOD => 'InjectableTrait_' . self::IIS_INJECTION_COMPLETED_METHOD
	];

	/**
	 * @var RobotLoader
	 */
	private static $staticRobotLoader;

	/**
	 * @var RobotLoader
	 */
	private $robotLoader;


	/**
	 * Creates RobotLoader and unregisters Nette's original inject extension.
	 *
	 * @param Configurator $configurator
	 * @param string $robotLoaderDir
	 */
	public static function bootstrapTweak(Configurator $configurator, $robotLoaderDir)
	{
		self::$staticRobotLoader = $configurator->createRobotLoader()
				->addDirectory($robotLoaderDir)
				->register();

		unset($configurator->defaultExtensions['inject']);
	}


	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$definitions = $builder->getDefinitions();

		foreach ($definitions as $definition) {
			if ($definition->getClass() === null) {
				$className = $definition->getFactory()->getEntity();
			} else {
				$className = $definition->getClass();
			}
			if ( ! class_exists($className)) {
				continue;
			}
			$this->injectForServiceDefinition($className, $definition);
		}


		foreach ($this->getContainerBuilder()->getDefinitions() as $def) {
			if ($def->getTag(self::TAG_INJECT) && $def->getClass()) {
				$this->updateDefinition($def);
			}
		}
	}


	private function updateDefinition(ServiceDefinition $def)
	{
		/** @var Statement[] $injects */
		$injects = [];

		foreach (self::getInjectMethods($def->getClass()) as $method) {
			$injects[] = new Statement($method);
		}

		$setups = $def->getSetup();
		foreach ($injects as $inject) {
			foreach ($setups as $key => $setup) {
				if ($setup->getEntity() === $inject->getEntity()) {
					$inject = $setup;
					unset($setups[$key]);
				}
			}
			array_unshift($setups, $inject);
		}
		$def->setSetup($setups);
	}


	/**
	 * Generates list of inject methods.
	 * @return array
	 * @internal
	 */
	public static function getInjectMethods($class)
	{
		return array_values(array_filter(get_class_methods($class), function ($name) {
			return substr($name, 0, 6) === 'inject';
		}));
	}


	public function loadConfiguration()
	{
		$this->robotLoader = self::$staticRobotLoader;
		$builder = $this->getContainerBuilder();
		$this->loadDefinitions($builder);
	}


	private function loadDefinitions(ContainerBuilder $builder)
	{
		$classes = $this->robotLoader->getIndexedClasses();
		$markerInterface = new ClassType(IServiceMarker::class);
		foreach ($classes as $key => $val) {
			if (Strings::endsWith($key, 'Service')) {
				$reflection = new ClassType($key);
				$serviceName = '_auto.' . str_replace("\\", "_", $key);
				if ( ! $reflection->isAbstract() && $reflection->isSubclassOf($markerInterface)) {
					$builder->addDefinition($serviceName)->setClass($key);
				}
			}
		}
	}


	/**
	 * @param string $className
	 * @return bool
	 */
	private static function usesInjectableTrait($className)
	{
		return in_array(InjectableTrait::class, class_uses($className));
	}


	/**
	 * @param ClassType $class
	 * @return bool
	 */
	private function isInjectableService(ClassType $class)
	{
		return $class->implementsInterface(IInjectableService::class);
	}


	/**
	 * @param ClassType $class
	 * @return mixed|null
	 */
	private function getInjectionByNameProperties(ClassType $class, array $methodMapping)
	{
		$properties = $this->callStaticReflectionNoParams($class, $methodMapping[self::IIS_GET_INJECTION_PROPS_METHOD]);
		return $properties;
	}


	/**
	 * @param ClassType $class
	 * @return mixed|null
	 */
	private function getInjectionByTypeProperties(ClassType $class, array $methodMapping)
	{
		$properties = $this->callStaticReflectionNoParams($class, $methodMapping[self::IIS_GET_INJECTION_PROPS_TYPE_METHOD]);
		return $properties;
	}


	/**
	 * @param ClassType $class
	 * @param string $method
	 * @return mixed|null
	 */
	private function callStaticReflectionNoParams(ClassType $class, $method)
	{
		return $class->getMethod($method)->invokeArgs(null, []);
	}


	/**
	 * @param $className
	 * @param $definition
	 * @return ClassType
	 */
	public function injectForServiceDefinition($className, ServiceDefinition $definition)
	{
		$class = new ClassType($className);
		if ($this->isInjectableService($class)) {
			$this->injectToDefinition($definition, $class, self::METHOD_MAPPING_INTERFACE);
		} else if ($this->usesInjectableTrait($className)) {
			$this->injectToDefinition($definition, $class, self::METHOD_MAPPING_TRAIT);
		}
	}


	/**
	 * @param ServiceDefinition $definition
	 * @param ClassType $class
	 * @param array $methodMapping
	 */
	public function injectToDefinition(ServiceDefinition $definition, ClassType $class, array $methodMapping)
	{
		$injectionProperties = $this->getInjectionByNameProperties($class, $methodMapping);
		// Copy original setup method to be able to place them after injection
		$setupSave = $definition->setup;
		$definition->setup = [];
		foreach ($injectionProperties as $injectionProperty => $injectedServiceName) {
			$definition->addSetup(
				$methodMapping[self::IIS_INJECT_SERVICE_METHOD],
				[$injectionProperty, '@' . $injectedServiceName]
			);
		}
		$injectionProperties = $this->getInjectionByTypeProperties($class, $methodMapping);
		foreach ($injectionProperties as $injectionProperty => $injectedServiceType) {
			$definition->addSetup(
				$methodMapping[self::IIS_INJECT_SERVICE_METHOD],
				[$injectionProperty, '@' . $injectedServiceType]
			);
		}

		$definition->addSetup($methodMapping[self::IIS_INJECT_PARAMETERS_METHOD], ['@container']);
		$definition->addSetup($methodMapping[self::IIS_INJECTION_COMPLETED_METHOD], []);
		// Add original setup statements after injection completed method call
		foreach ($setupSave as $originalSetup) {
			$definition->addSetup($originalSetup);
		}
	}

}
