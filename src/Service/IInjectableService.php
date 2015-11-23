<?php
/**
 * This file is part of the DTForce Nette-Injection extension (http://www.dtforce.com/).
 *
 * This source file is subject to the GNU Lesser General Public License.
 */

namespace DTForce\NetteInject\Service;


/**
 * Interface of service able to handle automatic container injection.
 *
 * Name must match with {@see InjectionCompilerExtension::IIS_FULL_NAME}
 *
 * @package DTForce\NetteInjection\Service
 */
interface IInjectableService extends IServiceMarker
{

	/**
	 * Injects service to a property.
	 *
	 * Name must match with {@see InjectionCompilerExtension::IIS_FULL_NAME}
	 *
	 * @param string $propertyName
	 * @param object $service
	 */
	function injectService($propertyName, $service);


	/**
	 * Returns array of properties needed to be injected.
	 * Keys of array are property names, values are service names.
	 *
	 * Name must match with {@see InjectionCompilerExtension::IIS_FULL_NAME}
	 *
	 * @return array
	 */
	static function getInjectionByNameProperties();


	/**
	 * Returns array of properties needed to be injected.
	 * Keys of array are property names, values are service types.
	 *
	 * Name must match with {@see InjectionCompilerExtension::IIS_FULL_NAME}
	 *
	 * @return array
	 */
	static function getInjectionByTypeProperties();


	/**
	 * Notifies service about completion of service injection.
	 *
	 * Name must match with {@see InjectionCompilerExtension::IIS_FULL_NAME}
	 */
	function injectionCompleted();


	/**
	 * Inject container parameters.
	 *
	 * @param $container
	 */
	function injectParameters(\Nette\DI\Container $container);

}
