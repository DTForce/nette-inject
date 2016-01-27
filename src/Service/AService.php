<?php
/**
 * This file is part of the DTForce Nette-Injection extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */

namespace DTForce\NetteInject\Service;
use Nette\Utils\Arrays;


/**
 * Common service ancestor allowing to inject service thanks to {@see InjectionCompilerExtension}.
 *
 * @package DTForce\NetteInjection\Service
 */
abstract class AService implements IInjectableService
{

	use InjectableTrait;

	const INJECT_SERVICE_ANNOTATION = 'inject';
	const TYPE_ANNOTATION = 'var';

	protected function onInjectionCompleted()
	{
		$this->startup();
	}


	/**
	 * Called when all properties has been injected.
	 */
	protected function startup()
	{
	}


	/**
	 * {@inheritdoc}
	 */
	public function injectService($propertyName, $service)
	{
		$this->InjectableTrait_injectService($propertyName, $service);
	}


	/**
	 * {@inheritdoc}
	 */
	public static function getInjectionByNameProperties()
	{
		return self::InjectableTrait_getInjectionByNameProperties();
	}


	/**
	 * {@inheritdoc}
	 */
	public static function getInjectionByTypeProperties()
	{
		return self::InjectableTrait_getInjectionByTypeProperties();
	}


	/**
	 * {@inheritdoc}
	 */
	public function injectionCompleted()
	{
		$this->InjectableTrait_injectionCompleted();
	}


	/**
	 * {@inheritdoc}
	 */
	public function injectParameters(\Nette\DI\Container $container)
	{
		$this->InjectableTrait_injectParameters($container);
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
		return Arrays::get($this->InjectableTrait_parameters, explode('.', $key), $default);
	}

}
