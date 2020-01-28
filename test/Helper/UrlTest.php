<?php

/**
 * @see       https://github.com/laminas/laminas-view for the canonical source repository
 * @copyright https://github.com/laminas/laminas-view/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-view/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\View\Helper;

use Laminas\View\Helper\Url as UrlHelper;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\ModuleRouteListener;
use Laminas\Mvc\Router\RouteMatch;
use Laminas\Mvc\Router\SimpleRouteStack as Router;

/**
 * Laminas\View\Helper\Url Test
 *
 * Tests formText helper, including some common functionality of all form helpers
 *
 * @group      Laminas_View
 * @group      Laminas_View_Helper
 */
class UrlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var UrlHelper
     */
    private $url;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $router = new Router();
        $router->addRoute('home', array(
            'type' => 'Laminas\Mvc\Router\Http\Literal',
            'options' => array(
                'route' => '/',
            )
        ));
        $router->addRoute('default', array(
                'type' => 'Laminas\Mvc\Router\Http\Segment',
                'options' => array(
                    'route' => '/:controller[/:action]',
                )
        ));
        $this->router = $router;

        $this->url = new UrlHelper;
        $this->url->setRouter($router);
    }

    public function testHelperHasHardDependencyWithRouter()
    {
        $this->setExpectedException('Laminas\View\Exception\RuntimeException', 'No RouteStackInterface instance provided');
        $url = new UrlHelper;
        $url('home');
    }

    public function testHomeRoute()
    {
        $url = $this->url->__invoke('home');
        $this->assertEquals('/', $url);
    }

    public function testModuleRoute()
    {
        $url = $this->url->__invoke('default', array('controller' => 'ctrl', 'action' => 'act'));
        $this->assertEquals('/ctrl/act', $url);
    }

    public function testModel()
    {
        $it = new \ArrayIterator(array('controller' => 'ctrl', 'action' => 'act'));

        $url = $this->url->__invoke('default', $it);
        $this->assertEquals('/ctrl/act', $url);
    }

    /**
     * @expectedException \Laminas\View\Exception\InvalidArgumentException
     */
    public function testThrowsExceptionOnInvalidParams()
    {
        $this->url->__invoke('default', 'invalid params');
    }

    public function testPluginWithoutRouteMatchesInEventRaisesExceptionWhenNoRouteProvided()
    {
        $this->setExpectedException('Laminas\View\Exception\RuntimeException', 'RouteMatch');
        $this->url->__invoke();
    }

    public function testPluginWithRouteMatchesReturningNoMatchedRouteNameRaisesExceptionWhenNoRouteProvided()
    {
        $this->url->setRouteMatch(new RouteMatch(array()));
        $this->setExpectedException('Laminas\View\Exception\RuntimeException', 'matched');
        $this->url->__invoke();
    }

    public function testPassingNoArgumentsWithValidRouteMatchGeneratesUrl()
    {
        $routeMatch = new RouteMatch(array());
        $routeMatch->setMatchedRouteName('home');
        $this->url->setRouteMatch($routeMatch);
        $url = $this->url->__invoke();
        $this->assertEquals('/', $url);
    }

    public function testCanReuseMatchedParameters()
    {
        $this->router->addRoute('replace', array(
            'type'    => 'Laminas\Mvc\Router\Http\Segment',
            'options' => array(
                'route'    => '/:controller/:action',
                'defaults' => array(
                    'controller' => 'LaminasTest\Mvc\Controller\TestAsset\SampleController',
                ),
            ),
        ));
        $routeMatch = new RouteMatch(array(
            'controller' => 'foo',
        ));
        $routeMatch->setMatchedRouteName('replace');
        $this->url->setRouteMatch($routeMatch);
        $url = $this->url->__invoke('replace', array('action' => 'bar'), array(), true);
        $this->assertEquals('/foo/bar', $url);
    }

    public function testCanPassBooleanValueForThirdArgumentToAllowReusingRouteMatches()
    {
        $this->router->addRoute('replace', array(
            'type' => 'Laminas\Mvc\Router\Http\Segment',
            'options' => array(
                'route'    => '/:controller/:action',
                'defaults' => array(
                    'controller' => 'LaminasTest\Mvc\Controller\TestAsset\SampleController',
                ),
            ),
        ));
        $routeMatch = new RouteMatch(array(
            'controller' => 'foo',
        ));
        $routeMatch->setMatchedRouteName('replace');
        $this->url->setRouteMatch($routeMatch);
        $url = $this->url->__invoke('replace', array('action' => 'bar'), true);
        $this->assertEquals('/foo/bar', $url);
    }

    public function testRemovesModuleRouteListenerParamsWhenReusingMatchedParameters()
    {
        $router = new \Laminas\Mvc\Router\Http\TreeRouteStack;
        $router->addRoute('default', array(
            'type' => 'Laminas\Mvc\Router\Http\Segment',
            'options' => array(
                'route'    => '/:controller/:action',
                'defaults' => array(
                    ModuleRouteListener::MODULE_NAMESPACE => 'LaminasTest\Mvc\Controller\TestAsset',
                    'controller' => 'SampleController',
                    'action'     => 'Dash'
                )
            ),
            'child_routes' => array(
                'wildcard' => array(
                    'type'    => 'Laminas\Mvc\Router\Http\Wildcard',
                    'options' => array(
                        'param_delimiter'     => '=',
                        'key_value_delimiter' => '%'
                    )
                )
            )
        ));

        $routeMatch = new RouteMatch(array(
            ModuleRouteListener::MODULE_NAMESPACE => 'LaminasTest\Mvc\Controller\TestAsset',
            'controller' => 'Rainbow'
        ));
        $routeMatch->setMatchedRouteName('default/wildcard');

        $event = new MvcEvent();
        $event->setRouter($router)
              ->setRouteMatch($routeMatch);

        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->onRoute($event);

        $helper = new UrlHelper();
        $helper->setRouter($router);
        $helper->setRouteMatch($routeMatch);

        $url = $helper->__invoke('default/wildcard', array('Twenty' => 'Cooler'), true);
        $this->assertEquals('/Rainbow/Dash=Twenty%Cooler', $url);
    }
}
