<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Enhancer;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */


use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use TYPO3\CMS\Extbase\Reflection\ClassSchema;

/**
 * Resolves a static list (like page.typeNum) against a file pattern. Usually added on the very last part
 * of
 * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'][$controllerName] = ['actions' => \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $actionsList)];
 *
 * a typical configuration looks like this:
 * type: ExtbasePluginEnhancer
 * vendor: 'GeorgRinger'
 * extension: 'News'
 * plugin: 'Pi1'
 * routes:
 *   - { routePath: '/blog/{page}', '_controller': 'News::list' }
 *   - { routePath: '/blog/{slug}', '_controller': 'News::detail' }
 * requirements:
 *   page: { regexp: '[0-9]+'}
 *   slug: { regexp: '.*', resolver: 'SlugResolver', tableName: 'tx_news_domain_model_news', fieldName: 'path_segment' }
 *
 */
class ExtbasePluginEnhancer
{
    protected $configuration;

    protected $routesOfPlugin;

    protected $namespace;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
        $extensionName = $this->configuration['extension'];
        $pluginName = $this->configuration['plugin'];
        $this->namespace = 'tx_' . strtolower($extensionName) . '_' . strtolower($pluginName);
        $this->routesOfPlugin = $this->configuration['routes'] ?? [];
        return;
        // we should do this somewhere else.
        $controllersActions = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'];
        $routesOfPlugin = [];
        foreach ($controllersActions as $controllerName => $actions) {
            $controllerName = ucfirst($controllerName);
            $className = $this->configuration['vendor'] . '\\' . $extensionName . '\\Controller\\' . ucfirst($controllerName) . 'Controller';
            foreach ($actions['actions'] as $action) {
                $methodName = $action . 'Action';
                $cls = new ClassSchema($className);
                $identifier = ucfirst($controllerName) . '::' . $action;
                $routesOfPlugin[$identifier] = [
                    '_controller' => $controllerName . '::' . $action,
                    'routePath' => '/' . strtolower($controllerName) . '-' . strtolower($action)
                ];
                foreach ($cls->getMethod($methodName)['params'] as $argumentName => $argument) {

                    $routesOfPlugin[$identifier]['routePath'][$argument['position']] = [
                        'name' => $argumentName,
                        'type' => $argument['type'],
                        'optional' => $argument['optional'],
                        'nullable' => $argument['nullable'],
                        'defaultValue' => $argument['defaultValue']
                    ];
                }
            }
        }
        $this->map = $map;
    }

    /**
     * Used when a URL is matched.
     * @param RouteCollection $collection
     */
    public function addVariants(RouteCollection $collection)
    {
        $i = 0;
        $defaultPageRoute = $collection->get('default');
        foreach ($this->routesOfPlugin as $routeDefinition) {
            $routePath = $routeDefinition['routePath'];
            unset($routeDefinition['routePath']);
            $defaults = array_merge_recursive($defaultPageRoute->getDefaults(), $routeDefinition);
            $route = new Route(rtrim($defaultPageRoute->getPath(), '/') . '/' . ltrim($routePath, '/'), $defaults, [], ['utf8' => true]);
            $route->addRequirements($this->configuration['requirements']);
            $collection->add($this->namespace . '_' . $i++, $route);
        }
    }

    /**
     * @param Route $route
     * @return Route
     */
    public function enhanceDefaultRoute(Route $route)
    {
        return $route;
    }

    public function flattenParameters($parameters) {
        return $parameters;
    }

    public function unflattenParameters($parameters) {
        return $parameters;
    }
}
